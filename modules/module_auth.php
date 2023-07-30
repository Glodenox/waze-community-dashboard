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
$user_data = jwt_decode($access->id_token);
$user_id = $user_data->{'https://slack.com/user_id'};
$team_id = $user_data->{'https://slack.com/team_id'};

if ($team_id != SLACK_TEAM_ID) {
	$error = 'Incorrect Slack team used to authenticate. If you are unable to change the team name, make sure to go to slack.com and choose to "try using a different email" there first.';
	require('templates/template_error.php');
	die();
}

// Try to retrieve user from DB
$stmt = $db->prepare('SELECT id, slack_access_token, name, avatar FROM dashboard_users WHERE slack_user_id = ? AND slack_team_id = ?');
if ($stmt->execute(array($user_id, $team_id))) {
	$user = $stmt->fetchObject();
	if (isset($user->id)) {
		$_SESSION['user_id'] = $user->id;
		// check whether data is outdated
		if ($user->slack_access_token != $access->access_token || $user->avatar != $user_data->picture || $user->name != $user_data->name) {
			$stmt = $db->prepare('UPDATE dashboard_users SET slack_access_token = ?, name = ?, avatar = ? WHERE id = ?');
			$stmt->execute(array($access->access_token, $user_data->name, $user_data->picture, $user->id));
		}
		redirect();
	}
}

// Not found
$stmt = $db->query("SELECT min(lon) as west, max(lon) as east, min(lat) as south, max(lat) as north FROM dashboard_reports");
$data_bounds = $stmt->fetchObject();

$stmt = $db->prepare('INSERT INTO dashboard_users (slack_user_id, slack_access_token, slack_team_id, name, avatar, area_north, area_east, area_south, area_west) VALUES (?,?,?,?,?,?,?,?,?)');
$insertOk = $stmt->execute(array($user_id, $access->access_token, $team_id, $user_data->name, $user_data->picture, $data_bounds->north, $data_bounds->east, $data_bounds->south, $data_bounds->west));

if (!$insertOk) {
	$error = "Database statement failed due to: " . (count($stmt->errorInfo()[2]) > 0 ? $stmt->errorInfo()[2] : $stmt->errorInfo()[1]);
	require('templates/template_error.php');
	die();
}

$_SESSION['user_id'] = $db->lastInsertId();

redirect();

// Credit: https://www.converticacommerce.com/support-maintenance/security/php-one-liner-decode-jwt-json-web-tokens/
function jwt_decode($jwt_string) {
	return json_decode(base64_decode(str_replace(array('_', '-'), array('/', '+'), explode('.', $jwt_string)[1])));
}

?>