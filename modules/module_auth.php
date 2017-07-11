<?php

if (isset($_GET['error'])) {
	$error = 'Error: ' . htmlspecialchars($_GET['error']);
	require('templates/template_error.php');
	die();
}
if (!isset($_GET['code'])) {
	$error = 'code is missing. Did you navigate to this page directly?';
	require('templates/template_error.php');
	die();
}

if (!preg_match('/^[0-9a-z\.]+$/', $_GET['code'])) {
	$error = 'code ' . htmlspecialchars($_GET['code']) . ' is not in an expected format!';
	require('templates/template_error.php');
	die();
}

require('modules/auth_common.php');

$access = retrieve_access($_GET['code']);
$user_data = retrieve_user($access->access_token);

// Try to retrieve user from DB
$stmt = $db->prepare('SELECT id, slack_access_token, name, avatar FROM dashboard_users WHERE slack_user_id = ? AND slack_team_id = ?');
if ($stmt->execute(array($user_data->user->id, $user_data->team->id))) {
	$user = $stmt->fetchObject();
	if (isset($user->id)) {
		$_SESSION['user_id'] = $user->id;
		// check whether data is outdated
		if ($user->slack_access_token != $access->access_token || $user->avatar != $user_data->user->image_48 || $user->name != $user_data->user->name) {
			$stmt = $db->prepare('UPDATE dashboard_users SET slack_access_token = ?, name = ?, avatar = ? WHERE id = ?');
			$stmt->execute(array($access->access_token, $user_data->user->name, $user_data->user->image_48, $user->id));
		}
		redirect();
	}
}

// Not found
$stmt = $db->query("SELECT min(lon) as west, max(lon) as east, min(lat) as south, max(lat) as north FROM dashboard_reports");
$data_bounds = $stmt->fetchObject();

$stmt = $db->prepare('INSERT INTO dashboard_users (slack_user_id, slack_access_token, slack_team_id, name, avatar, area_north, area_east, area_south, area_west) VALUES (?,?,?,?,?,?,?,?,?)');
$insertOk = $stmt->execute(array($user_data->user->id, $access->access_token, $user_data->team->id, $user_data->user->name, $user_data->user->image_48, $data_bounds->north, $data_bounds->east, $data_bounds->south, $data_bounds->west));

if (!$insertOk) {
	$error = "Database statement failed due to: " . (count($stmt->errorInfo()[2]) > 0 ? $stmt->errorInfo()[2] : $stmt->errorInfo()[1]);
	require('templates/template_error.php');
	die();
}

$_SESSION['user_id'] = $db->lastInsertId();

redirect();

?>