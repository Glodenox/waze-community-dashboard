<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_URL', 'https://www.waze.com/events/unready');
define('SCRAPER_ID', 5);
define('REPORT_INSERT', 'INSERT INTO dashboard_reports (start_time, end_time, lon, lat, description, status, priority, source, external_identifier, external_data, geojson, last_modified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
define('REPORT_HISTORY_INSERT', 'INSERT INTO dashboard_report_history (report_id, timestamp, action_id, value) VALUES (?,?,?,?)');
define('REPORT_STATUS_UPDATE', 'UPDATE dashboard_reports SET status = ? WHERE id = ?');
define('REPORT_FOLLOWERS', 'SELECT u.slack_user_id FROM dashboard_report_follow f, dashboard_users u WHERE f.report_id = ? AND f.user_id = u.id AND u.notify_bits & (1 << ?) <> 0');
define('DATE_FORMAT', 'Y-m-d H:i:s');
const COUNTRY_TO_CHANNEL = array(
	'Belgium' => '#big-events-nl-be',
	'Netherlands' => '#big-events-nl-be',
	'Luxembourg' => '#big-events-nl-be'
);

class Scraper {
	function update_source() {
		global $db, $code_errors;

		set_time_limit(0);

		$h = curl_init(SCRAPER_URL);
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING => 'gzip',
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

		$lastUpdate = new DateTime();
		preg_match_all("/<a class='mte-unready' href='https:\/\/www.waze.com\/editor\?lat=(?P<lat>-?\d{1,3}\.\d{1,20})&amp;lon=(?P<lon>-?\d{1,3}\.\d{1,20})&amp;majorTrafficEvent=(?P<mte>[\d\.-]+)&amp;mode=1' target='_blank'>\n<div class='mte-unready__name'>(?P<name>[^<]+)<\/div>(\n<div class='mte-unready__creator'>\(Created by (?P<creator>[^\)]+)\)<\/div>)?\n<div class='mte-unready__date'>(?P<date>[^>]+)<\/div>(\n<div class='mte-unready__address'>(?P<address>[^>]+)<\/div>)?/", $result, $unresolved_mtes, PREG_SET_ORDER);
		if (preg_match("/data-last-updated='([^']+)'/", $result, $match) === 1) {
			$lastUpdate = new DateTime($match[1]);
		}

		if (count($unresolved_mtes) == 0) {
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => array_merge(array('Could not retrieve any results from the service'), $code_errors)))));;
			return array(
				'received' => 0
			);
		}

		$benelux_mtes = array_filter($unresolved_mtes, function($mte) {
			// An address is required, otherwise discard
			if (!array_key_exists('address', $mte)) {
				return false;
			}
			// Retrieve country from address
			$country = explode(', ', $mte['address']);
			$country = end($country);
			return array_key_exists($country, COUNTRY_TO_CHANNEL);
		});

		$current_events = array();
		$cancelled_events = array();
		if ($result = $db->query('SELECT id, external_identifier, start_time, status, last_modified FROM dashboard_reports WHERE source = ' . SCRAPER_ID . ' AND status <> ' . STATUS_REMOVED)) {
			while ($event = $result->fetchObject()) {
				$current_events[$event->external_identifier] = array(
					'id' => $event->id,
					'startTime' => $event->start_time,
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
			STATUS_REMOVED => 0
		);
		$notifications_by_user = array();

		// Group MTEs by country for notifications later on
		$grouped_mtes = array();

		// Buffer some statements before actually inserting data
		$stmt_buffer = array();
		foreach ($benelux_mtes as $mte) {
			unset($cancelled_events[$mte['mte']]);
			$dates = explode(' - ', strtolower($mte['date']));
			$startDateTime = new DateTime($dates[0], new DateTimeZone('UTC'));
			$endDateTime = (count($dates) > 1) ? new DateTime($dates[1] . 'T23:59:00', new DateTimeZone('UTC')) : null;
			// As the first date is missing its year, the guessed year might be wrong
			while ($endDateTime != null && $startDateTime > $endDateTime) {
				$startDateTime->sub(new DateInterval('P1Y'));
			}
			$coordinate = array($mte['lon'], $mte['lat']);
			$geojson = array(
				'type' => 'Point',
				'coordinates' => $coordinate
			);

			if (!array_key_exists($mte['mte'], $current_events)) {
				$external_data = array();
				$external_data['WME URL'] = 'https://www.waze.com/editor?lat=' . $mte['lat'] . '&lon=' . $mte['lon'] . '&majorTrafficEvent=' . $mte['mte'] . '&mode=1&zoom=2';
				if (strlen(trim($mte['creator'])) > 0) {
					$external_data['Creator'] = $mte['creator'];
				}
				// put up to 20 statements in an array so we can do a multi-insert
				$stmt_buffer[] = array(
					$startDateTime->format('U'),
					($endDateTime != null ? $endDateTime->format('U') : $startDateTime->format('U') + 86340), // Add 23:59:00 to start time so it spans practically the whole day
					$mte['lon'],
					$mte['lat'],
					trim(html_entity_decode($mte['name'])),
					STATUS_REPORTED,
					PRIORITY_HIGH,
					SCRAPER_ID,
					$mte['mte'],
					json_encode($external_data),
					json_encode($geojson),
					$lastUpdate->format('c')
				);
				$stats[STATUS_REPORTED]++;
				$country = explode(', ', $mte['address']);
				$country = end($country);
				if (!array_key_exists($country, $grouped_mtes)) {
					$grouped_mtes[$country] = array();
				}
				$grouped_mtes[$country][] = array(
					'url' => $external_data['WME URL'],
					'creator' => (array_key_exists('Creator', $external_data) ? $external_data['Creator'] : 'Events page user'),
					'address' => $mte['address'],
					'name' => $mte['name'],
					'date' => $mte['date']
				);
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

		// Remove any missing reports
		$history_stmt = $db->prepare(REPORT_HISTORY_INSERT);
		$cancel_stmt = $db->prepare(REPORT_STATUS_UPDATE);
		foreach ($cancelled_events as $wsId => $cancelled_event) {
			execute($history_stmt, array($cancelled_event['id'], time(), ACTION_SET_STATUS, STATUS_REMOVED));
			execute($cancel_stmt, array(STATUS_REMOVED, $cancelled_event['id']));
			$stats[STATUS_REMOVED]++;
		}

		// Archive outdated entries
		$stmt = $db->prepare('INSERT INTO dashboard_report_history (report_id, timestamp, user_id, action_id, value) SELECT id, ' . time() . ', NULL, ' . ACTION_SET_STATUS . ', ' . STATUS_REMOVED . ' FROM dashboard_reports WHERE end_time < :current_time AND source = :source_id AND status <> ' . STATUS_REMOVED);
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$stmt = $db->prepare('UPDATE dashboard_reports SET status = ' . STATUS_REMOVED . ', last_modified = ' . time() . ' WHERE end_time < :current_time AND status <> ' . STATUS_REMOVED . ' AND source = :source_id');
		$stmt->execute(array(':current_time' => time(), ':source_id' => SCRAPER_ID));
		$archived_reports = $stmt->rowCount();

		$results = array(
			'received' => count($benelux_mtes),
			'previous-count' => count($current_events),
			'new' => $stats[STATUS_REPORTED],
			'updated' => $stats[STATUS_UPDATED],
			'archived' => $archived_reports + $stats[STATUS_REMOVED]
		);
		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => $code_errors)))));

		foreach ($grouped_mtes as $country => $mtes) {
			$channels = explode(',', COUNTRY_TO_CHANNEL[$country]);
			$text = 'New unresolved major traffic event' . (count($mtes) > 1 ? 's' : '') . ' found:';
			foreach ($mtes as $mte) {
				$text .= "\n> <" . $mte['url'] . '|' . $mte['name'] . '> in _' . $mte['address'] . '_ (' . $mte['date'] . ') by ' . $mte['creator'];
			}
			foreach ($channels as $channel) {
				send_notification(array(
					'channel' => $channel,
					"icon_emoji" => ":waze:",
					"text" => $text
				));
			}
		}

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
				if (!$notifications[$follower]) {
					$notifications[$follower] = array();
				}
				$notifications[$follower][] = array(
					'id' => $report_id,
					'description' => str_replace(array('<', '>', '&', "\n"), array('&lt;', '&gt;', '&amp;', ' '), $report_description)
				);
			}
		}
	}
}
?>