<?php

/**
* This module provides the API to access the reported events, such as road works, manifestations, planned closures, ...
* Supported actions:
*  /query: retrieve reports based on arguments:
*   - bounds: comma-separated bbox to geographically filter reports on (required)
*   - status: filter reports by status (default: reported & updated)
*   - source: source id to filter on (default: all sources)
*   - priority: the priority level to filter on in reports (default: all priorities)
*   - level: the required editor level to look for (default: all levels)
*   - followed: a flag that to decides to only return reports that the user follows (requires login, default: false)
*  /get: retrieve details from a specific report by its id retrieved in /query
*   - id: id of the report to retrieve
*  /heatmap: retrieve a collection of points roughly indicating where all reports that still require an action are located
*  /set-status: change the status of a report
*   - id: id of the report
*   - status: status to be set for this report (if missing, message must be present)
*  /set-notes: modify the notes for this event
*   - id: id of the report
*   - notes: new content to set for this report
*  /set-level: assign a report to an editor level
*   - id: id of the report to assign
*   - level: editor level to assign to the report (1 to 6, -1 for undefined)
*  /set-priority
*   - id: id of the report to change
*   - priority to set (0 to 3 for low, normal, high and urgent)
*  /follow: follow a certain report (get update notifications)
*   - id: id of the report to follow
*  /unfollow: stop following a certain report
*   - id: id of the report to unfollow
**/

if (!defined('IN_DASHBOARD')) {
	exit;
}

require('libs/Parsedown.php');

header('Content-Type: application/json; charset=utf-8');
switch($folders[1]) {
	case 'query':
		if (!array_key_exists('bounds', $_GET)) {
			json_fail('No bounds specified');
		}
		if (!preg_match('/\d+\.\d+,\d+\.\d+,\d+\.\d+,\d+\.\d+/', $_GET['bounds'])) {
			json_fail('Invalid bounds specified');
		}
		list($left, $bottom, $right, $top) = explode(',', $_GET['bounds']);
		$where_args = array('r.source = s.id AND r.lon BETWEEN :left AND :right AND r.lat BETWEEN :bottom AND :top');
		$where_params = array(
			':left' => $left,
			':top' => $top,
			':right' => $right,
			':bottom' => $bottom
		);
		if (array_key_exists('status', $_GET) && $_GET['status'] != -1) {
			if (!array_key_exists($_GET['status'], STATUSES) && $_GET['status'] != 'actionable') {
				json_fail('Unknown status report specified');
			}
			$where_args[] = ($_GET['status'] == 'actionable' ? '(r.status = ' . STATUS_REPORTED . ' OR r.status = ' . STATUS_UPDATED . ' OR r.status = ' . STATUS_TO_REMOVE . ')' : 'r.status = ' . (int)$_GET['status']);
		}
		$source = (array_key_exists('source', $_GET) ? (int)$_GET['source'] : -1);
		if ($source >= 0) {
			$where_args[] = 'r.source = :source_id';
			$where_params[':source_id'] = $source;
		}
		$priority = (array_key_exists('priority', $_GET) && array_key_exists($_GET['priority'], PRIORITIES) ? (int)$_GET['priority'] : -1);
		if ($priority >= 0) {
			$where_args[] = 'r.priority = :priority';
			$where_params[':priority'] = $priority;
		}
		$level = (array_key_exists('level', $_GET) ? (int)$_GET['level'] : -1);
		if ($level >= 0 && $level < 7) {
			$where_args[] = 'r.required_editor_level = :level';
			$where_params[':level'] = $level;
		}
		$periods = array(
			'past' => 'r.end_time < :current',
			'active' => 'r.start_time < :current AND r.end_time > :current',
			'soon' => 'r.start_time > :current AND r.start_time < :current + 1209600', // 2 weeks ahead
			'future' => 'r.start_time > :current'
		);
		$period = (array_key_exists('period', $_GET) && array_key_exists($_GET['period'], $periods) ? $_GET['period'] : '');
		if ($period != '') {
			$where_args[] = '((' . $periods[$period] . ') OR (r.end_time IS NULL AND r.start_time IS NULL))';
			$where_params[':current'] = time();
		}
		$followed = isset($user) && array_key_exists('followed', $_GET) && $_GET['followed'] == '1';
		if ($followed) {
			$where_args[] = 'f.user_id = ' . $user->id . ' AND r.id = f.report_id';
		}

		$stmt = $db->prepare('SELECT r.id, r.start_time, r.end_time, r.lon, r.lat, r.description, r.priority, r.source, s.name AS source_name FROM dashboard_reports r, dashboard_sources s' . ($followed ? ', dashboard_report_follow f' : '') . ' WHERE ' . implode(' AND ', $where_args) . ' ORDER BY r.start_time LIMIT 500');
		execute($stmt, $where_params);
		$reports = $stmt->fetchAll(PDO::FETCH_OBJ);
		json_send(array(
			'reports' => $reports
		));

	case 'get':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		$stmt = $db->prepare('SELECT r.id, start_time, end_time, lon, lat, r.description, status, priority, required_editor_level, notes, source, s.name AS source_name, geojson, external_identifier, external_data, last_modified FROM dashboard_reports r, dashboard_sources s WHERE r.id = :report_id AND s.id = r.source');
		execute($stmt, array(':report_id' => $report_id));
		$report = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$report) {
			json_fail('No report found with that ID');
		}
		if (substr($report['external_data'], 0, 1) == '{') {
			$report['external_data'] = json_decode($report['external_data'], true);
		} elseif ($report['external_data'] != '') {
			$report['external_data'] = unserialize($report['external_data']);
		}
		$report['geojson'] = json_decode($report['geojson']);
		if (file_exists('scrapers/' . $report['source'] . '.php') && (!$report['external_data'] || count($report['external_data']) == 0 || (isset($_GET['force']) && isset($user)))) {
			require('scrapers/' . $report['source'] . '.php');
			$scraper = new Scraper();
			try {
				$scraper->update_external_data($report);
			} catch (Exception $e) {
				// Tough luck, but not worth failing over.
				// TODO: log these instances
			}
		}
		$report['notes_source'] = ($report['notes'] === FALSE ? '' : $report['notes']);
		$report['notes'] = ($report['notes'] === FALSE ? '' : Parsedown::instance()->text($report['notes']));
		$stmt = $db->prepare('SELECT FROM_UNIXTIME(h.timestamp) as timestamp, h.action_id, h.value, h.user_id, u.name as username, h.details 
							  FROM dashboard_report_history h 
							  LEFT JOIN dashboard_users u ON h.user_id = u.id 
							  WHERE h.report_id = :report_id');
		execute($stmt, array(':report_id' => $report_id));
		$history = $stmt->fetchAll(PDO::FETCH_OBJ);
		$report['history'] = $history;
		if (isset($user)) {
			$stmt = $db->prepare('SELECT report_id FROM dashboard_report_follow WHERE report_id = :report_id AND user_id = :user_id');
			execute($stmt, array(':report_id' => $report_id, ':user_id' => $user->id));
			$report['following'] = $stmt->rowCount() > 0;
			$stmt = $db->prepare('SELECT id, name, claim_time FROM dashboard_users WHERE claim_report = :report_id AND claim_time > UNIX_TIMESTAMP() - 60*60');
			execute($stmt, array(':report_id' => $report_id));
			$claim = $stmt->fetch(PDO::FETCH_OBJ);
			$report['claimUserId'] = ($claim === FALSE ? null : $claim->id);
			$report['claimUsername'] = ($claim === FALSE ? null : $claim->name);
			$report['claimTime'] = ($claim === FALSE ? null : $claim->claim_time);
		}
		json_send(array(
			'report' => $report
		));

	case 'heatmap':
		$stmt = $db->query('SELECT round(lon, 2) as lon, round(lat, 2) as lat, count(id) as reports FROM dashboard_reports WHERE status IN (' . STATUS_REPORTED . ',' . STATUS_UPDATED . ',' . STATUS_TO_REMOVE . ') GROUP BY 1, 2');
		$heatmap = $stmt->fetchAll(PDO::FETCH_OBJ);
		json_send(array(
			'heatmap' => $heatmap
		));

	case 'set_status':
		$report_id = (int)$_GET['id'];
		$status = (int)$_GET['status'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		if ($report_id == 0) {
			json_fail('No status or invalid status provided');
		}
		if (!in_array($status, array_keys(STATUSES)) || $status == STATUS_UPDATED || $status == STATUS_TO_REMOVE) {
			json_fail('Provided status is not accepted');
		}
		$stmt = $db->prepare('SELECT status FROM dashboard_reports WHERE id = :report_id');
		execute($stmt, array(':report_id' => $report_id));
		$current_status = $stmt->fetch(PDO::FETCH_OBJ)->status;
		if ($current_status == $status) {
			json_fail('This is already the status of this report');
		}
		$stmt = $db->prepare('UPDATE dashboard_reports SET status = :status WHERE id = :report_id');
		execute($stmt, array(
			':status' => $status,
			':report_id' => $report_id
		));
		$stmt = $db->prepare('SELECT process_auto_jump FROM dashboard_users WHERE id = :user_id');
		execute($stmt, array(':user_id' => $user->id));
		$process_auto_jump = $stmt->fetch(PDO::FETCH_OBJ)->process_auto_jump;
		$user_following = handle_action($report_id, ACTION_SET_STATUS, $status, STATUSES[$status]);
		json_send(array(
			'following' => $user_following,
			'autojump' => $process_auto_jump ? true : false
		));

	case 'set_notes':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		if (!isset($_GET['notes']) || trim($_GET['notes']) == '') {
			json_fail('Notes is missing or empty');
		}
		if (count($_GET['notes']) > 1000) {
			json_fail('Notes too long (maximum 1000 characters)');
		}
		$stmt = $db->prepare('UPDATE dashboard_reports SET notes = ? WHERE id = ?');
		execute($stmt, array($_GET['notes'], $report_id));
		$user_following = handle_action($report_id, ACTION_MESSAGE, null, $_GET['notes']);
		json_send(array(
			'notes' => Parsedown::instance()->text($_GET['notes']),
			'following' => $user_following
		));

	case 'set_level':
		if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
			json_fail('No report ID or invalid report ID provided');
		}
		$report_id = (int)$_GET['id'];
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		if (!isset($_GET['level']) || !is_numeric($_GET['level'])) {
			json_fail('No valid editor level provided');
		}
		$stmt = $db->prepare('UPDATE dashboard_reports SET required_editor_level = :editor_level WHERE id = :report_id');
		execute($stmt, array(
			':report_id' => $report_id,
			':editor_level' => (int)$_GET['level']
		));
		$stmt = $db->prepare('SELECT process_auto_jump FROM dashboard_users WHERE id = :user_id');
		execute($stmt, array(':user_id' => $user->id));
		$process_auto_jump = $stmt->fetch(PDO::FETCH_OBJ)->process_auto_jump;
		$user_following = handle_action($report_id, ACTION_SET_LEVEL, (int)$_GET['level'], (int)$_GET['level']);
		json_send(array(
			'following' => $user_following,
			'autojump' => $process_auto_jump ? true : false
		));

	case 'set_priority':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$priority = (int)$_GET['priority'];
		if (!isset($_GET['priority']) || !array_key_exists($priority, PRIORITIES)) {
			json_fail('No valid priority provided');
		}
		$stmt = $db->prepare('UPDATE dashboard_reports SET priority = :priority WHERE id = :report_id');
		execute($stmt, array(
			':report_id' => $report_id,
			':priority' => $priority
		));
		$user_following = handle_action($report_id, ACTION_SET_PRIORITY, $priority, PRIORITIES[$priority]);
		json_send(array(
			'following' => $user_following
		));

	case 'follow':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$stmt = $db->prepare('INSERT INTO dashboard_report_follow (report_id, user_id) VALUES (:report_id, :user_id)');
		execute($stmt, array(
			':report_id' => $report_id,
			':user_id' => $user->id
		));
		json_send();

	case 'unfollow':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$stmt = $db->prepare('DELETE FROM dashboard_report_follow WHERE report_id = :report_id AND user_id = :user_id');
		execute($stmt, array(
			':report_id' => $report_id,
			':user_id' => $user->id
		));
		json_send();

	case 'claim':
		$report_id = (int)$_GET['id'];
		if ($report_id == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$stmt = $db->prepare('SELECT id, name, claim_time FROM dashboard_users WHERE claim_report = :report_id');
		execute($stmt, array(':report_id' => $report_id));
		$current_claim = $stmt->fetch(PDO::FETCH_OBJ);
		if ($current_claim !== FALSE) {
			if ($current_claim->claim_time > time() - 60*15) {
				json_fail('This report has been recently claimed by ' . addslashes($current_claim->name) . ' already.');
			} else {
				$stmt = $db->prepare('UPDATE dashboard_users SET claim_report = NULL, claim_time = NULL WHERE id = :user_id');
				execute($stmt, array(
					':user_id' => $current_claim->id
				));
			}
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET claim_report = :report_id, claim_time = UNIX_TIMESTAMP() WHERE id = :user_id');
		execute($stmt, array(
			':report_id' => $report_id,
			':user_id' => $user->id
		));
		json_send();

	case 'release_claim':
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET claim_report = NULL, claim_time = NULL WHERE id = :user_id');
		execute($stmt, array(
			':user_id' => $user->id
		));
		json_send();

	default:
		json_fail('Unknown action requested');
}

/**
* Add the action event to the history of a report (if necessary), automatically follow the report (if configured) and send notifications to any followers
* @return Whether or not this action made the user follow this report
*/
function handle_action($report_id, $action, $value, $value_text) {
	global $user, $db;
	$user_following = false;

	$notifications = array(
		ACTION_UPDATE => "A report you are following has been updated by <@%s>: %s. <http://home.tomputtemans.com/waze-dashboard/reports#%u|See report>",
		ACTION_SET_STATUS => "<@%s> has changed the status of a report you are following to '%s'. <http://home.tomputtemans.com/waze-dashboard/reports#%u|See report>",
		ACTION_SET_LEVEL => "<@%s> has changed the required editor level of a report you are following to %s. <http://home.tomputtemans.com/waze-dashboard/reports#%u|See report>",
		ACTION_SET_PRIORITY => "<@%s> has changed the priority for a report you are following to '%s'. <http://home.tomputtemans.com/waze-dashboard/reports#%u|See report>",
		ACTION_MESSAGE => '<@%1$s> has posted a message to a report you are following. <http://home.tomputtemans.com/waze-dashboard/reports#%3$u|See report>' . "\n" . '>>>%2$s'
	);

	// If it is an action to put in the history logs, store it
	if (array_key_exists($action, ACTIONS)) {
		$stmt = $db->prepare('INSERT INTO dashboard_report_history (timestamp, report_id, user_id, action_id, value) VALUES (?, ?, ?, ?, ?)');
		execute($stmt, array(time(), $report_id, $user->id, $action, $value));
	}

	// Check whether this action makes the user follow the report
	if ($user->follow_bits & (1 << $action)) {
		$stmt = $db->prepare('INSERT IGNORE INTO dashboard_report_follow (report_id, user_id) VALUES (:report_id, :user_id)');
		execute($stmt, array(
			':report_id' => $report_id,
			':user_id' => $user->id
		));
		$user_following = true;
	}

	// We only have strings for these notifications, so there's no point continuing otherwise
	if (!array_key_exists($action, $notifications)) {
		return;
	}
	// Retrieve all followers to be notified of this action
	$stmt = $db->prepare('SELECT slack_user_id, notify_bits FROM dashboard_report_follow, dashboard_users WHERE user_id = id AND report_id = :report_id');
	execute($stmt, array(':report_id' => $report_id));
	$followers = $stmt->fetchAll();
	// Remove the author of the action from this list as the users don't need to be notified of their own action
	$followers = array_filter($followers, function($follower) {
		global $user;
		return $follower['slack_user_id'] != $user->slack_user_id;
	});

	$payload = array(
		"icon_emoji" => ":waze:",
		"text" => sprintf($notifications[$action], $user->slack_user_id, $value_text, $report_id)
	);

	foreach ($followers as $follower) {
		// is the bit for this action set?
		if ($follower['notify_bits'] & (1 << $action)) {
			$payload['channel'] = $follower['slack_user_id'];
			send_notification($payload);
		}
	}
	return $user_following;
}

?>
