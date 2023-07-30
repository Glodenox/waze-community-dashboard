<?php

header('Content-Type: application/json; charset=utf-8');
switch($folders[1]) {
	case 'resync':
		if (!array_key_exists('user_id', $_SESSION)) {
			json_fail("User isn't logged in");
		}
		require('modules/auth_common.php');
		$user_data = retrieve_user($user->slack_access_token);
		$user_id = $user_data->{'https://slack.com/user_id'};
		$team_id = $user_data->{'https://slack.com/team_id'};
		$stmt = $db->prepare('UPDATE dashboard_users SET name = ?, avatar = ?, slack_user_id = ?, slack_team_id = ? WHERE id = ?');
		execute($stmt, array($user_data->name, $user_data->picture, $user_id, $team_id, $user->id));
		json_send();

	case 'change_area':
		if (!array_key_exists('user_id', $_SESSION)) {
			json_fail("User isn't logged in");
		}
		if (!array_key_exists('north', $_GET) || !is_numeric($_GET['north']) ||
				!array_key_exists('east', $_GET) || !is_numeric($_GET['east']) ||
				!array_key_exists('south', $_GET) || !is_numeric($_GET['south']) ||
				!array_key_exists('west', $_GET) || !is_numeric($_GET['west'])) {
			json_fail('One or more coordinate locations missing or not a number');
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET area_north = ?, area_east = ?, area_south = ?, area_west = ? WHERE id = ?');
		execute($stmt, array($_GET['north'], $_GET['east'], $_GET['south'], $_GET['west'], $user->id));
		json_send();

	case 'change_follow':
		json_fail('Not implemented');

	case 'set_editor_level':
		json_fail('Not implemented');

	case 'change_notification':
		if (!array_key_exists('user_id', $_SESSION)) {
			json_fail("User isn't logged in");
		}
		if (!isset($_GET['config']) || $_GET['config'] > 255 || $_GET['config'] < 0) {
			json_fail("Either no or invalid config provided");
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET notify_bits = :config WHERE id = :user_id');
		execute($stmt, array(
			'config' => (int) $_GET['config'],
			'user_id' => $user->id
		));
		json_send();

	case 'change_autojump':
		if (!array_key_exists('user_id', $_SESSION)) {
			json_fail("User isn't logged in");
		}
		if (!isset($_GET['autojump'])) {
			json_fail('Preference is missing in request');
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET process_auto_jump = :autojump WHERE id = :user_id');
		execute($stmt, array(
			'autojump' => $_GET['autojump'] != 'false' ? 1 : 0,
			'user_id' => $user->id
		));
		json_send();
		break;

	case 'change_follow_defaults':
		if (!array_key_exists('user_id', $_SESSION)) {
			json_fail("User isn't logged in");
		}
		if (!isset($_GET['config']) || $_GET['config'] > 255 || $_GET['config'] < 0) {
			json_fail("Either no or invalid config provided");
		}
		$stmt = $db->prepare('UPDATE dashboard_users SET follow_bits = :config WHERE id = :user_id');
		execute($stmt, array(
			'config' => (int) $_GET['config'],
			'user_id' => $user->id
		));
		json_send();

	default:
		json_fail("Unknown action requested");
}

?>