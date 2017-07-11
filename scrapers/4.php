<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_ID', 4);
define('REPORT_INSERT', 'INSERT INTO dashboard_reports (start_time, end_time, lon, lat, description, status, priority, required_editor_level, source, external_identifier, external_data, geojson, last_modified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
define('REPORT_HISTORY_INSERT', 'INSERT INTO dashboard_report_history (report_id, timestamp, action_id, value) VALUES (?,?,?,?)');
define('REPORT_STATUS_UPDATE', 'UPDATE dashboard_reports SET status = ? WHERE id = ?');
define('REPORT_FOLLOWERS', 'SELECT u.slack_user_id FROM dashboard_report_follow f, dashboard_users u WHERE f.report_id = ? AND f.user_id = u.id AND u.notify_bits & (1 << ?) <> 0');
define('DATE_FORMAT', 'Y-m-d H:i:s');
const DATA_FILTER = array('startDateTime', 'endDateTime', 'coordinates', 'title', 'level');

class Scraper {
	function update_source() {
		global $db, $code_errors;

		set_time_limit(0);

		$h = curl_init('https://www.wegstatus.nl/feeds/wwjson.php');
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
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

		$roadworks = json_decode($result);
		if (count($roadworks) == 0) {
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => array_merge(array('Could not retrieve any results from the service'), $code_errors)))));;
			return array(
				'received' => 0
			);
		}

		$current_events = array();
		$cancelled_events = array();
		if ($result = $db->query('SELECT id, external_identifier, start_time, end_time, status, last_modified FROM dashboard_reports WHERE source = ' . SCRAPER_ID . ' AND status <> ' . STATUS_REMOVED)) {
			while ($event = $result->fetchObject()) {
				$current_events[$event->external_identifier] = array(
					'id' => $event->id,
					'startTime' => $event->start_time,
					'endTime' => $event->end_time,
					'status' => $event->status,
					'last_modified' => $event->last_modified
				);
				$cancelled_events[$event->external_identifier] = array(
					'id' => $event->id,
					'status' => $event->status
				);
			}
		}
		if ($result = $db->query('SELECT r.external_identifier, r.description FROM dashboard_reports r, dashboard_report_follow f WHERE f.report_id = r.id AND r.source = ' . SCRAPER_ID . ' AND r.status <> ' . STATUS_TO_REMOVE . ' AND r.status <> ' . STATUS_REMOVED)) {
			while ($event = $result->fetchObject()) {
				$cancelled_events[$event->external_identifier]['description'] = $event->description;
			}
		}

		$stats = array(
			STATUS_REPORTED => 0,
			STATUS_UPDATED => 0,
			STATUS_TO_IGNORE => 0,
			STATUS_TO_REMOVE => 0,
			STATUS_REMOVED => 0
		);
		$notifications_by_user = array();
		// Buffer some statements before actually inserting data
		$stmt_buffer = array();
		foreach ($roadworks as $roadwork) {
			unset($cancelled_events[$roadwork->wsId]);
			$startDateTime = new DateTime($roadwork->startDateTime, new DateTimeZone('Europe/Brussels'));
			$endDateTime = new DateTime($roadwork->endDateTime, new DateTimeZone('Europe/Brussels'));
			$updateDateTime = new DateTime($roadwork->updateDateTime, new DateTimeZone('Europe/Brussels'));
			$coordinates = explode(', ', $roadwork->coordinates);
			$line_string = array();
			$middle_lon = $middle_lat = 0;
			foreach ($coordinates as $coordinate) {
				$coordinate = explode(' ', $coordinate);
				$middle_lat += $coordinate[0];
				$middle_lon += $coordinate[1];
				$line_string[] = array_reverse($coordinate);
			}
			$middle_lat /= count($coordinates);
			$middle_lon /= count($coordinates);
			$geojson = array(
				'type' => (count($coordinates) > 1 ? 'LineString' : 'Point'),
				'coordinates' => (count($coordinates) > 1 ? $line_string : $line_string[0])
			);
			$external_data = array();
			$external_data['Source URL'] = $roadwork->sourceurl;
			unset($roadwork->sourceurl);
			$external_data['Last update'] = $roadwork->updateDateTime;
			unset($roadwork->updateDateTime);
			foreach($roadwork as $key => $value) {
				// Perform filtering
				if (in_array($key, DATA_FILTER) || $value == null || count($value) == 0 || (is_string($value) && count(trim($value)) == 0)) {
					continue;
				}
				$external_data[camelToRegular($key)] = spaceOutCommas($value);
			}
			// Is this a report to potentially update?
			if (array_key_exists($roadwork->wsId, $current_events)) {
				$match = $current_events[$roadwork->wsId];
				// Do we need to update the existing report?
				if ($updateDateTime->format(DATE_FORMAT) != $match['last_modified']) {
					if ($match['status'] != STATUS_TO_IGNORE) {
						$match['status'] = STATUS_UPDATED;
					}
					$db->beginTransaction();
					$stmt = $db->prepare('UPDATE dashboard_reports SET start_time = ?, end_time = ?, lon = ?, lat = ?, description = ?, status = ?, geojson = ?, external_data = ?, last_modified = ? WHERE id = ?');
					execute($stmt, array($startDateTime->format('U'), $endDateTime->format('U'), $middle_lon, $middle_lat, trim(html_entity_decode($roadwork->title)),
							$match['status'], json_encode($geojson), json_encode($external_data), $updateDateTime->format('c'), $match['id']));
					$stmt = $db->prepare(REPORT_HISTORY_INSERT);
					execute($stmt, array($match['id'], time(), ACTION_UPDATE, null));
					$db->commit();
					$this->add_notifications($notifications_by_user, $match['id'], trim(html_entity_decode($roadwork->title)));
					$stats[$match['status']]++;
				}
			} else if ($endDateTime->format('U') > time()) {
				// Not an existing event to update, insert instead if it isn't over yet
				$priority = PRIORITY_NORMAL;
				foreach (PRIORITIES as $priority_idx => $priority_value) {
					if ($roadwork->priority == $priority_value) {
						$priority = $priority_idx;
					}
				}
				// put up to 20 statements in an array so we can do a multi-insert
				$stmt_buffer[] = array($startDateTime->format('U'), $endDateTime->format('U'), $middle_lon, $middle_lat, trim(html_entity_decode($roadwork->title)),
						STATUS_REPORTED, $priority, (int)$roadwork->level, SCRAPER_ID, $roadwork->wsId, json_encode($external_data), json_encode($geojson), $updateDateTime->format('c'));
				$stats[STATUS_REPORTED]++;
				if (count($stmt_buffer) >= 20) {
					$db->beginTransaction();
					multi_insert(REPORT_INSERT, $stmt_buffer);
					$history_values = array_map(function($id) {
						return array($id, time(), ACTION_INSERT, null);
					}, range($db->lastInsertId(), $db->lastInsertId() + count($stmt_buffer) - 1));
					multi_insert(REPORT_HISTORY_INSERT, $history_values);
					$stmt_buffer = array();
					$db->commit();
				}
			}
		}
		if (count($stmt_buffer) > 0) {
			$db->beginTransaction();
			multi_insert(REPORT_INSERT, $stmt_buffer);
			$history_values = array_map(function($id) {
				return array($id, time(), ACTION_INSERT, null);
			}, range($db->lastInsertId(), $db->lastInsertId() + count($stmt_buffer) - 1));
			multi_insert(REPORT_HISTORY_INSERT, $history_values);
			$stmt_buffer = array();
			$db->commit();
		}

		// Cancel any missing reports
		$history_stmt = $db->prepare(REPORT_HISTORY_INSERT);
		$cancel_stmt = $db->prepare(REPORT_STATUS_UPDATE);
		foreach ($cancelled_events as $wsId => $cancelled_event) {
			if ($cancelled_event['status'] == STATUS_TO_REMOVE) {
				unset($cancelled_events[$wsId]);
				continue;
			}
			$new_status = ($cancelled_event['status'] == STATUS_PROCESSED ? STATUS_TO_REMOVE : STATUS_REMOVED);
			execute($history_stmt, array($cancelled_event['id'], time(), ACTION_SET_STATUS, $new_status));
			execute($cancel_stmt, array($new_status, $cancelled_event['id']));
			// If the description isn't set, nobody is following this event
			if (array_key_exists('description', $cancelled_event)) {
				$this->add_notifications($notifications_by_user, $cancelled_event['id'], $cancelled_event['description']);
			}
			$stats[$new_status]++;
		}

		// Archive outdated entries
		$stmt = $db->prepare('INSERT INTO dashboard_report_history (report_id, timestamp, user_id, action_id, value) SELECT id, ' . time() . ', NULL, ' . ACTION_SET_STATUS . ', ' . STATUS_REMOVED . ' FROM dashboard_reports WHERE end_time < :current_time AND source = :source_id AND status <> ' . STATUS_REMOVED);
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$stmt = $db->prepare('UPDATE dashboard_reports SET status = ' . STATUS_REMOVED . ', last_modified = ' . time() . ' WHERE end_time < :current_time AND status <> ' . STATUS_REMOVED . ' AND source = :source_id');
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$archived_reports = $stmt->rowCount();

		$results = array(
			'received' => count($roadworks),
			'previous-count' => count($current_events),
			'new' => $stats[STATUS_REPORTED],
			'updated' => $stats[STATUS_UPDATED],
			'cancelled' => $stats[STATUS_TO_REMOVE],
			'archived' => $archived_reports + $stats[STATUS_REMOVED]
		);

		// Send out notifications to users
		foreach ($notifications_by_user as $user => $reports) {
			$text = "Modified events:\n";
			$additional_reports = count($reports) - 10;
			array_splice($reports, 10);
			foreach ($reports as $report) {
				$text .= '> <' . BASE_URL . 'reports#' . $report['id'] . '|' . str_replace(array('<', '>', '&', "\n"), array('&lt;', '&gt;', '&amp;', ' '), $report['description']) . ">\n";
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

		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => $code_errors)))));

		return $results;
	}

	function update_external_data(&$report, $update = true) {}

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
					'description' => str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $report_description)
				);
			}
		}
	}
}