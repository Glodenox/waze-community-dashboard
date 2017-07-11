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

/*
Description: The point-in-polygon algorithm allows you to check if a point is
inside a polygon or outside of it.
Author: Michaël Niessen (2009)
Website: http://AssemblySys.com

If you find this script useful, you can show your
appreciation by getting Michaël a cup of coffee ;)
PayPal: michael.niessen@assemblysys.com

As long as this notice (including author name and details) is included and
UNALTERED, this code is licensed under the GNU General Public License version 3:
http://www.gnu.org/licenses/gpl.html
*/
class PointLocation {
	var $pointOnVertex = true; // Check if the point sits exactly on one of the vertices?

	function pointLocation() {}

	function pointInPolygon($point, $polygon, $pointOnVertex = true) {
		$this->pointOnVertex = $pointOnVertex;

		// Transform string coordinates into arrays with x and y values
		$point = $this->pointStringToCoordinates($point);
		$vertices = array(); 
		foreach ($polygon as $vertex) {
			$vertices[] = $this->pointStringToCoordinates($vertex); 
		}

		// Check if the point sits exactly on a vertex
		if ($this->pointOnVertex == true and $this->pointOnVertex($point, $vertices) == true) {
			return "vertex";
		}
		// Check if the point is inside the polygon or on the boundary
		$intersections = 0; 
		$vertices_count = count($vertices);
		for ($i=1; $i < $vertices_count; $i++) {
			$vertex1 = $vertices[$i-1]; 
			$vertex2 = $vertices[$i];
			if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x'])) { // Check if point is on an horizontal polygon boundary
				return "boundary";
			}
			if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y']) { 
				$xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x']; 
				if ($xinters == $point['x']) { // Check if point is on the polygon boundary (other than horizontal)
					return "boundary";
				}
				if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
					$intersections++; 
				}
			} 
		} 
		// If the number of edges we passed through is odd, then it's in the polygon. 
		if ($intersections % 2 != 0) {
			return "inside";
		} else {
			return "outside";
		}
	}
 
	function pointOnVertex($point, $vertices) {
		foreach($vertices as $vertex) {
			if ($point == $vertex) {
				return true;
			}
		}
	}
 
	function pointStringToCoordinates($pointString) {
		$coordinates = explode(" ", $pointString);
		return array("x" => $coordinates[0], "y" => $coordinates[1]);
	}
}

class Scraper {
	function update_source() {
		global $db, $code_errors;

		set_time_limit(0);

		$h = curl_init(SCRAPER_URL);
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

		preg_match_all("/<a class='mte-unready' href='https:\/\/www.waze.com\/editor\?lat=(?P<lat>-?\d{1,3}\.\d{1,20})&amp;lon=(?P<lon>-?\d{1,3}\.\d{1,20})&amp;majorTrafficEvent=(?P<mte>[\d\.-]+)&amp;mode=1' target='_blank'>\n<div class='mte-unready__name'>(?P<name>[^<]+)<\/div>(\n<div class='mte-unready__creator'>\(Created by (?P<creator>[^\)]+)\)<\/div>)?\n<div class='mte-unready__date'>(?P<date>[^>]+)<\/div>/", $result, $unresolved_mtes, PREG_SET_ORDER);
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
			$pointLocation = new PointLocation();
			$benelux = array("6.36805 49.45344","6.39234 49.58327","6.51551 49.71985","6.53113 49.81142","6.32539 49.86156","6.14712 50.04236","6.19158 50.23357","6.41097 50.32963","6.38041 50.45523","5.99587 50.79163","6.11120 50.91312","5.99580 51.02403","6.19347 51.15556","6.23724 51.47913","5.97323 51.80804","6.17860 51.85975","6.73828 51.88937","6.87437 51.95871","6.77849 52.07343","7.07564 52.18091","7.07989 52.40590","6.75242 52.49791","6.76996 52.63561","7.06766 52.62975","7.21898 53.00798","7.21400 53.26952","6.95994 53.33177","6.83222 53.65680","4.86889 53.48391","2.54301 51.07501","2.61945 50.81593","2.92684 50.68585","3.03109 50.76682","3.21506 50.70300","3.28507 50.51413","3.72107 50.31430","4.02697 50.35446","4.19639 50.25878","4.12730 49.97475","4.65305 49.97716","4.78724 50.14351","4.86649 49.80851","5.24472 49.67482","5.45254 49.50135","6.36805 49.45344");
			return $pointLocation->pointInPolygon($mte['lon'].' '.$mte['lat'], $benelux) == 'inside';
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

		// Buffer some statements before actually inserting data
		$stmt_buffer = array();
		foreach ($benelux_mtes as $mte) {
			unset($cancelled_events[$mte['mte']]);
			$dates = explode(' - ', strtolower($mte['date']));
			$startDateTime = new DateTime($dates[0], new DateTimeZone('UTC'));
			$endDateTime = (count($dates) > 1) ? new DateTime($dates[1], new DateTimeZone('UTC')) : null;
			// As the first date is missing its year, the guessed year might be wrong
			while ($endDateTime != null && $startDateTime > $endDateTime) {
				$startDateTime->sub(new DateInterval('P1Y'));
			}
			$coordinate = array($mte['lon'], $mte['lat']);
			$geojson = array(
				'type' => 'Point',
				'coordinates' => $coordinate
			);
			$external_data = array();
			$external_data['WME URL'] = 'https://www.waze.com/editor?lat=' . $mte['lat'] . '&lon=' . $mte['lon'] . '&majorTrafficEvent=' . $mte['mte'] . '&mode=1&zoom=2';
			if (strlen(trim($mte['creator'])) > 0) {
				$external_data['Creator'] = $mte['creator'];
			}

			if (!array_key_exists($mte['mte'], $current_events)) {
				// put up to 20 statements in an array so we can do a multi-insert
				$stmt_buffer[] = array($startDateTime->format('U'), ($endDateTime != null ? $endDateTime->format('U') : null), $mte['lon'], $mte['lat'], trim(html_entity_decode($mte['name'])),
						STATUS_REPORTED, PRIORITY_HIGH, SCRAPER_ID, $mte['mte'], json_encode($external_data), json_encode($geojson), $lastUpdate->format('c'));
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