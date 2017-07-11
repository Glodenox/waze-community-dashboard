<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_URL', 'http://api.gipod.vlaanderen.be/ws/v1/workassignment?offset=%u&limit=%u');
define('SCRAPER_ID', 1);
define('REPORT_INSERT', 'INSERT INTO dashboard_reports (start_time, end_time, lon, lat, description, status, priority, source, external_identifier, external_data, geojson) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
define('REPORT_HISTORY_INSERT', 'INSERT INTO dashboard_report_history (report_id, timestamp, action_id, value, details) VALUES (?,?,?,?,?)');
define('REPORT_STATUS_UPDATE', 'UPDATE dashboard_reports SET status = ? WHERE id = ?');
define('REPORT_FOLLOWERS', 'SELECT u.slack_user_id FROM dashboard_report_follow f, dashboard_users u WHERE f.report_id = ? AND f.user_id = u.id AND u.notify_bits & (1 << ?) <> 0');
define('DATE_FORMAT', 'Y-m-d H:i:s');
const DATA_FILTER = array('startDateTime', 'endDateTime', 'description', 'gipodId', 'location', 'diversions');

class Scraper {
	function update_source() {
		global $db, $code_errors;

		set_time_limit(0);

		$url = sprintf(SCRAPER_URL, 0, 10000); // Do not lower, all other reports will be marked as cancelled
		$h = curl_init($url);
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_USERAGENT => "Waze Benelux crawler"
		));
		$result = curl_exec($h);
		if ($result === false) {
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => array_merge(array('Could not access service: ' . curl_error($h)), $code_errors)))));
			curl_close($h);
			return array(
				'received' => 0
			);
		}
		curl_close($h);
		$zip = new ZipArchive;
		$res = $zip->open('logs/1-' . time() . '.zip', ZipArchive::CREATE);
		if ($res === TRUE) {
			$zip->addFromString('work_assignments_' . time() . '.json', $result);
			$zip->close();
		}

		$work_assignments = json_decode($result);
		if (count($work_assignments) == 0) {
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => array_merge(array('Could not retrieve any results from the service'), $code_errors)))));;
			return array(
				'received' => 0
			);
		}
		if (count($work_assignments) == 10000) {
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => array_merge(array('Reached maximum amount of events when polling service, increase limit!'), $code_errors)))));;
			return array(
				'received' => 10000
			);
		}

		$current_events = array();
		if ($result = $db->query('SELECT id, external_identifier, start_time, end_time, status FROM dashboard_reports WHERE source = ' . SCRAPER_ID . ' AND end_time > ' . time() . ' AND start_time < ' . (time() + 31*24*60*60))) {
			while ($event = $result->fetchObject()) {
				$current_events[$event->external_identifier] = array(
					'id' => $event->id,
					'startTime' => $event->start_time,
					'endTime' => $event->end_time,
					'status' => $event->status
				);
			}
		}
		if ($result = $db->query('SELECT r.external_identifier, r.description FROM dashboard_reports r, dashboard_report_follow f WHERE f.report_id = r.id AND r.source = ' . SCRAPER_ID . ' AND end_time > ' . time() . ' AND start_time < ' . (time() + 31*24*60*60))) {
			while ($event = $result->fetchObject()) {
				$current_events[$event->external_identifier]['description'] = $event->description;
			}
		}

		if ($result = $db->query('SELECT description, min_lon, max_lon, min_lat, max_lat FROM dashboard_report_filters WHERE source_id = ' . SCRAPER_ID)) {
			$event_filters = $result->fetchAll();
		} else {
			$event_filters = array();
		}

		$stats = array(
			STATUS_REPORTED => 0,
			STATUS_UPDATED => 0,
			STATUS_TO_IGNORE => 0,
			STATUS_TO_REMOVE => 0,
			STATUS_REMOVED => 0
		);
		$notifications_by_user = array();

		// Buffer some statements before actually doing an insert
		$stmt_buffer = array();
		foreach ($work_assignments as $work_assignment) {
			$startDateTime = new DateTime($work_assignment->startDateTime, new DateTimeZone('Europe/Brussels'));
			$endDateTime = new DateTime($work_assignment->endDateTime, new DateTimeZone('Europe/Brussels'));
			// Is this a report to potentially update?
			if (array_key_exists($work_assignment->gipodId, $current_events)) {
				$match = $current_events[$work_assignment->gipodId];
				$archived = $match['status'] == STATUS_TO_REMOVE || $match['status'] == STATUS_REMOVED;
				// Do we need to update the existing report?
				if ($startDateTime->format('U') != $match['startTime'] || $endDateTime->format('U') != $match['endTime'] || $archived) {
					if ($match['status'] != STATUS_TO_IGNORE) {
						$match['status'] = STATUS_UPDATED;
					}
					$db->beginTransaction();
					$stmt = $db->prepare('UPDATE dashboard_reports SET start_time = ?, end_time = ?, lon = ?, lat = ?, description = ?, status = ?, priority = ?, last_modified = ? WHERE id = ?');
					execute($stmt, array($startDateTime->format('U'), $endDateTime->format('U'), $work_assignment->coordinate->coordinates[0], $work_assignment->coordinate->coordinates[1],
							trim($work_assignment->description), $match['status'], ($work_assignment->importantHindrance ? PRIORITY_HIGH : PRIORITY_LOW), time(), $match['id']));
					$details = array();
					if ($startDateTime->format('U') != $match['startTime']) {
						$matchedStartTime = new DateTime('@' . $match['startTime']);
						$details[] = 'Start time changed from ' . $matchedStartTime->format(DATE_FORMAT) . ' to ' . $startDateTime->format(DATE_FORMAT);
					}
					if ($endDateTime->format('U') != $match['endTime']) {
						$matchedEndTime = new DateTime('@' . $match['endTime']);
						$details[] = 'End time changed from ' . $matchedEndTime->format(DATE_FORMAT) . ' to ' . $endDateTime->format(DATE_FORMAT);
					}
					if ($archived) {
						$details[] = 'Report appeared again at data source';
					}
					$stmt = $db->prepare(REPORT_HISTORY_INSERT);
					execute($stmt, array($match['id'], time(), ACTION_UPDATE, null, implode("\n", $details)));
					$db->commit();
					$this->add_notifications($notifications_by_user, $match['id'], trim($work_assignment->description));
					$stats[$match['status']]++;
				}
			} else if ($endDateTime->format('U') > time()) {
				$filtered_by_rule = $this->is_filtered($work_assignment, $event_filters);
				$current_report = array(
					'external_identifier' => $work_assignment->gipodId,
					'status' => ($filtered_by_rule ? STATUS_TO_IGNORE : STATUS_REPORTED)
				);
				sleep(1);
				// Try to update the external data, we don't insert the record if it fails
				if ($this->update_external_data($current_report, false)) {
					// put up to 20 statements in an array so we can do a multi-insert
					$stmt_buffer[] = array($startDateTime->format('U'), $endDateTime->format('U'), $work_assignment->coordinate->coordinates[0], $work_assignment->coordinate->coordinates[1],
							trim($work_assignment->description), $current_report['status'], ($work_assignment->importantHindrance ? PRIORITY_HIGH : PRIORITY_LOW), SCRAPER_ID, $work_assignment->gipodId,
							json_encode($current_report['external_data']), json_encode($current_report['geojson']));
					$stats[$current_report['status']]++;
					if (count($stmt_buffer) >= 20) {
						$db->beginTransaction();
						multi_insert(REPORT_INSERT, $stmt_buffer);
						$insert_statuses = array_map(function($values) {
							return $values[5];
						}, $stmt_buffer);
						$history_values = array_map(function($id, $status) {
							return array($id, time(), ACTION_INSERT, null, ($status == STATUS_TO_IGNORE ? 'Marked as to ignore as it matches one or more filters' : null));
						}, range($db->lastInsertId(), $db->lastInsertId() + count($stmt_buffer) - 1), $insert_statuses);
						multi_insert(REPORT_HISTORY_INSERT, $history_values);
						$stmt_buffer = array();
						$db->commit();
					}
				}
			}
			// mark event as treated by removing it from the list, so it will not be cancelled/archived
			unset($current_events[$work_assignment->gipodId]);
		}
		if (count($stmt_buffer) > 0) {
			$db->beginTransaction();
			multi_insert(REPORT_INSERT, $stmt_buffer);
			$insert_statuses = array_map(function($values) {
				return $values[5];
			}, $stmt_buffer);
			$history_values = array_map(function($id, $status) {
				return array($id, time(), ACTION_INSERT, null, ($status == STATUS_TO_IGNORE ? 'Marked as to ignore as it matches one or more filters' : null));
			}, range($db->lastInsertId(), $db->lastInsertId() + count($stmt_buffer) - 1), $insert_statuses);
			multi_insert(REPORT_HISTORY_INSERT, $history_values);
			$stmt_buffer = array();
			$db->commit();
		}

		// Cancel any events we didn't find at the source
		$history_stmt = $db->prepare(REPORT_HISTORY_INSERT);
		$cancel_stmt = $db->prepare(REPORT_STATUS_UPDATE);
		foreach ($current_events as $gipodId => $cancelled_event) {
			if ($cancelled_event['status'] == STATUS_TO_REMOVE || $cancelled_event['status'] == STATUS_REMOVED) {
				continue;
			}
			// Don't cancel the event if the server still has details for it
			$cancelled_event['external_identifier'] = $gipodId;
			if ($this->update_external_data($cancelled_event, false)) {
				if (!array_key_exists('external_data', $cancelled_event) || !array_key_exists('startDateTime', $cancelled_event['external_data']) || !array_key_exists('endDateTime', $cancelled_event['external_data'])) {
					file_put_contents('logs/debug.log', time() . ' (1): Event ' . $gipodId . ' was no longer present in the overview and no external details found. Must be a bug.' . PHP_EOL , FILE_APPEND | LOCK_EX);
					continue;
				}
				$startDateTime = new DateTime($cancelled_event['external_data']['startDateTime'], new DateTimeZone('Europe/Brussels'));
				$endDateTime = new DateTime($cancelled_event['external_data']['endDateTime'], new DateTimeZone('Europe/Brussels'));
				if ($startDateTime->format('U') == $cancelled_event['startTime'] && $endDateTime->format('U') == $cancelled_event['endTime']) {
					file_put_contents('logs/debug.log', time() . ' (1): Event ' . $gipodId . ' was no longer present in the overview, but found in the details, though no time difference. To investigate.' . PHP_EOL , FILE_APPEND | LOCK_EX);
					continue;
				}
				$db->beginTransaction();
				$stmt = $db->prepare('UPDATE dashboard_reports SET start_time = ?, end_time = ?, last_modified = ? WHERE id = ?');
				execute($stmt, array($startDateTime->format('U'), $endDateTime->format('U'), time(), $cancelled_event['id']));
				$details = array();
				if ($startDateTime->format('U') != $cancelled_event['startTime']) {
					$matchedStartTime = new DateTime('@' . $cancelled_event['startTime']);
					$details[] = 'Start time changed from ' . $matchedStartTime->format(DATE_FORMAT) . ' to ' . $startDateTime->format(DATE_FORMAT);
				}
				if ($endDateTime->format('U') != $cancelled_event['endTime']) {
					$matchedEndTime = new DateTime('@' . $cancelled_event['endTime']);
					$details[] = 'End time changed from ' . $matchedEndTime->format(DATE_FORMAT) . ' to ' . $endDateTime->format(DATE_FORMAT);
				}
				$stmt = $db->prepare(REPORT_HISTORY_INSERT);
				execute($stmt, array($cancelled_event['id'], time(), ACTION_UPDATE, null, implode("\n", $details)));
				$db->commit();
				continue;
			}
			$new_status = ($cancelled_event['status'] == STATUS_PROCESSED ? STATUS_TO_REMOVE : STATUS_REMOVED);
			execute($history_stmt, array($cancelled_event['id'], time(), ACTION_SET_STATUS, $new_status, 'Report no longer found at data source'));
			execute($cancel_stmt, array($new_status, $cancelled_event['id']));
			// If the description isn't set, nobody is following this event
			if (array_key_exists('description', $cancelled_event)) {
				$this->add_notifications($notifications_by_user, $cancelled_event['id'], $cancelled_event['description']);
			}
			$stats[$new_status]++;
		}

		// Archive outdated entries
		$stmt = $db->prepare('INSERT INTO dashboard_report_history (report_id, timestamp, user_id, action_id, value, details) SELECT id, ' . time() . ', NULL, ' . ACTION_SET_STATUS . ', ' . STATUS_REMOVED . ', "End time is in the past" FROM dashboard_reports WHERE end_time < :current_time AND source = :source_id AND status <> ' . STATUS_REMOVED);
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$stmt = $db->prepare('UPDATE dashboard_reports SET status = ' . STATUS_REMOVED . ', last_modified = ' . time() . ' WHERE end_time < :current_time AND status <> ' . STATUS_REMOVED . ' AND source = :source_id');
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$archived_reports = $stmt->rowCount();

		// Delete old entries (60 days)
		$stmt = $db->prepare('DELETE FROM dashboard_reports WHERE end_time < :two_months_ago AND source = :source_id');
		$stmt->execute(array(':two_months_ago' => (time() - 60*24*60*60), ':source_id' => SCRAPER_ID));

		// Send out notifications to users
		foreach ($notifications_by_user as $user => $reports) {
			$text = "Modified events:\n";
			$additional_reports = count($reports) - 10;
			array_splice($reports, 10);
			foreach ($reports as $report) {
				$text .= '> <' . BASE_URL . 'reports#' . $report['id'] . '|' . str_replace(array('>', "\n", "\r"), array('&gt;', ' ', ' '), $report['description']) . ">\n";
			}
			if ($additional_reports > 0) {
				$text .= $additional_reports . ' more reports were omitted.';
			}
			send_notification(array(
				"icon_emoji" => ":waze:",
				"channel" => $user,
				"text" => $text
			));
		}

		$results = array(
			'received' => count($work_assignments),
			'new' => $stats[STATUS_REPORTED],
			'updated' => $stats[STATUS_UPDATED],
			'cancelled' => $stats[STATUS_TO_REMOVE],
			'archived' => $archived_reports + $stats[STATUS_REMOVED]
		);

		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => array_splice($code_errors, 15))))));

		return $results;
	}

	function update_external_data(&$report, $update = true) {
		global $db, $code_errors;

		if (array_key_exists('external_data', $report) && is_array($report['external_data']) && array_key_exists('Last GIPOD Update', $report['external_data'])) {
			$day_ago = new DateTime();
			$day_ago->sub(new DateInterval('P1D'));
			$last_update = new DateTime($report['external_data']['Last GIPOD Update']);
			$interval = $day_ago->diff($last_update);
			if ($interval->d < 1) {
				return;
			}
		}

		$h = curl_init('http://api.gipod.vlaanderen.be/ws/v1/workassignment/' . $report['external_identifier']);
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT => "Waze Benelux crawler"
		));
		$result = curl_exec($h);
		if (curl_errno($h)) {
			throw new Exception('Could not retrieve GIPOD data: ' . curl_error($h));
		}
		$return_code = curl_getinfo($h, CURLINFO_HTTP_CODE);
		curl_close($h);
		$original_status = $report['status'];
		if ($return_code == 404) {
			// Nothing to do if it was already marked as cancelled or archived
			if ($report['status'] == STATUS_TO_REMOVE || $report['status'] == STATUS_REMOVED) {
				return false;
			}
			$report['status'] = ($report['status'] == STATUS_PROCESSED ? STATUS_TO_REMOVE : STATUS_REMOVED);
			if (!$update) {
				return false;
			}
			// Event has been cancelled (automatic archive if it was not processed)
			$result = $db->query('UPDATE dashboard_reports SET status = ' . $report['status'] . ', last_modified = ' . time() . ' WHERE id = ' . $report['id']);
			if ($result && $result->rowCount() > 0) {
				$stmt = $db->prepare(REPORT_HISTORY_INSERT);
				execute($stmt, array($report['id'], time(), ACTION_SET_STATUS, $report['status'], 'Report no longer found at data source when updating external data'));
			}
			return false;
		}
		$response = json_decode($result, TRUE);
		if (!$result || $response === NULL) {
			throw new Exception('Could not parse response from GIPOD (' . count($result) . ' characters)');
		}
		if (array_key_exists('location', $response) && array_key_exists('geometry', $response['location'])) {
			$report['geojson'] = (object)$response['location']['geometry'];
		}
		if (!array_key_exists('geojson', $report) || $report['geojson'] == false) {
			$report['geojson'] = '';
		}
		$external_data = array();
		$external_data['Last GIPOD Update'] = date(DATE_FORMAT);
		$external_data['GIPOD ID'] = $report['external_identifier'];
		if (array_key_exists('location', $response) && array_key_exists('cities', $response['location']) && is_string($response['location']['cities'])) {
			$external_data['Cities'] = implode(', ', $response['location']['cities']);
		}
		if (array_key_exists('startTime', $report)) {
			$external_data['startDateTime'] = $response['startDateTime'];
			$external_data['endDateTime'] = $response['endDateTime'];
		}
		foreach($response as $key => $value) {
			// Perform filtering
			if (in_array($key, DATA_FILTER) || $value == null || count($value) == 0 || (is_string($value) && count(trim($value)) == 0)) {
				continue;
			}
			if (is_array($value)) {
				foreach ($value as $subkey => $subvalue) {
					unset($value[$subkey]);
					if (is_array($subvalue)) {
						foreach($subvalue as $testvalue) {
							if (!is_string($testvalue)) {
								file_put_contents('logs/debug.log', time() . ' (1): Event ' . $report['external_identifier'] . ' contains a non-string value: ' . var_export($testvalue, true) . '.' . PHP_EOL , FILE_APPEND | LOCK_EX);
							}
						}
						$value[camelToRegular($subkey)] = implode(', ', $subvalue);
					} else if ($subvalue != null && count(trim($subvalue)) != 0) {
						$value[camelToRegular($subkey)] = spaceOutCommas($subvalue);
					}
				}
			}
			$external_data[camelToRegular($key)] = spaceOutCommas($value);
		}
		// Hindrance outside of road
		if ($report['status'] == STATUS_REPORTED && $response['hindrance'] && $response['hindrance']['locations'] && count(array_diff($response['hindrance']['locations'], array('Voetpad', 'Fietspad', 'Halte openbaar vervoer', 'Zijberm', 'Busbaan', 'Trambaan', 'Parkeerstrook', 'Zijberm', 'Middenberm', 'Tussenberm', 'Pechstrook'))) == 0) {
			$report['status'] = STATUS_TO_IGNORE;
		}
		if ($update) {
			if ($original_status != $report['status']) {
				$history_stmt = $db->prepare(REPORT_HISTORY_INSERT);
				execute($history_stmt, array($report['id'], time(), ACTION_SET_STATUS, $report['status'], 'External data indicates that the location of this event will not affect road traffic'));
			}
			$stmt = $db->prepare('UPDATE dashboard_reports SET geojson = ?, external_data = ?, status = ? WHERE id = ?');
			execute($stmt, array(json_encode($report['geojson']), json_encode($external_data), $report['status'], $report['id']));
		}
		$report['external_data'] = $external_data;
		return true;
	}

	private function add_notifications(&$notifications, $report_id, $report_description) {
		global $db;
		$stmt = $db->prepare(REPORT_FOLLOWERS);
		$result = $stmt->execute(array($report_id, ACTION_SET_STATUS));
		if ($result) {
			$followers = $stmt->fetchAll(PDO::FETCH_COLUMN);
			foreach ($followers as $follower) {
				if (!array_key_exists($follower, $notifications)) {
					$notifications[$follower] = array();
				}
				$notifications[$follower][] = array(
					'id' => $report_id,
					'description' => $report_description
				);
			}
		}
	}

	private function is_filtered($report, $filters) {
		foreach ($filters as $filter) {
			if (preg_replace('/\s+/', ' ', $filter['description']) == preg_replace('/\s+/', ' ', trim($report->description)) &&
					$filter['min_lon'] <= $report->coordinate->coordinates[0] && $report->coordinate->coordinates[0] <= $filter['max_lon'] &&
					$filter['min_lat'] <= $report->coordinate->coordinates[1] && $report->coordinate->coordinates[1] <= $filter['max_lat']) {
				return true;
			}
		}
		return false;
	}
}

?>