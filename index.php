<?php

/**
* The main index file to which all requests are routed through .htaccess, as shown below:
*   RewriteEngine on
*   RewriteCond %{REQUEST_FILENAME} !-f
*   RewriteRule ^.*$ /waze-dashboard/index.php [NC,L,QSA]
*
* The $_SERVER variables HTTP_HOST and REQUEST_URI are used to reconstruct the requested page.
* The script then looks at the first folder and tries to find a corresponding module with that name. 
* These modules are then responsible for setting all the necessary parameters and calling the corresponding template to display them.
*/

// constant to be checked in all other files to make sure any requests originate from this file
define('IN_DASHBOARD', true);

ini_set('display_errors', true);
error_reporting(E_ALL);

$code_errors = array();

error_reporting(E_ALL);
function handle_error($errno, $errstr, $errfile, $errline) {
	global $code_errors;
	$code_errors[] = $errstr . ' in ' . substr($errfile, strrpos($errfile, '/')+1, -4) . ' at line ' . $errline;
	return true;
}
set_error_handler('handle_error');

require('config.php');

// Obtain the current path. Due to the .htaccess RewriteEngine rule all requests land on this page (invisible to the client)
$path = str_replace(
	array(ROOT_FOLDER, '-'),
	array('', '_'),
	parse_url('http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($path == '') {
	$path = 'index';
}
$folders = explode('/', $path);
$folders = array_merge($folders, array_fill(count($folders), 3 - count($folders), ''));

require('common.php');

$meta_tags = array(
	'description' => 'This dashboard is a community-led initiative to monitor various data sources for open tasks and issues',
	'author' => 'Waze Benelux (Glodenox)'
);
$page_title = 'Waze Community Dashboard';

// Retrieve general template data
if (isset($_SESSION['user_id'])) {
	// In theory $_SESSION should be safe, but extra protection doesn't hurt here
	$user_id = (int) $_SESSION['user_id'];
	if ($user_id > 0) {
		$stmt = $db->prepare('SELECT id, name, avatar, slack_user_id, slack_team_id, slack_access_token, follow_bits, notify_bits, area_north, area_east, area_south, area_west FROM dashboard_users WHERE id = ?');
		if ($stmt->execute(array($user_id))) {
			$user = $stmt->fetchObject();
		}
	}
}
$todo_count = array();
$reports_area = (isset($user) ? ' AND lon BETWEEN ' . $user->area_west . ' AND ' . $user->area_east . ' AND lat BETWEEN ' . $user->area_south . ' AND ' . $user->area_north : '');
$stmt = $db->prepare('SELECT count(id) FROM dashboard_reports WHERE status IN (' . STATUS_REPORTED . ',' . STATUS_UPDATED . ',' . STATUS_TO_REMOVE . ') AND end_time >= :current_time' . $reports_area);
$result = $stmt->execute(array('current_time' => time()));
if ($result) {
	$todo_count['reports'] = $stmt->fetchColumn();
}
$stmt = $db->prepare('SELECT count(id) FROM dashboard_support_topics WHERE status = 1');
$result = $stmt->execute();
if ($result) {
	$todo_count['support-topics'] = $stmt->fetchColumn();
}
if (is_file('logs/closure-requests-count.cache')) {
	$todo_count['closure-requests'] = (int)file_get_contents('logs/closure-requests-count.cache');
}

if (!is_file("modules/module_{$folders[0]}.php")) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
	require('templates/template_404.php');
	exit;
}

$active_module = $folders[0];
require("modules/module_{$active_module}.php");

?>