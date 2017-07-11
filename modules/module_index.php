<?php

$stmt = $db->prepare('SELECT max(priority) as priority FROM dashboard_reports WHERE status IN (' . STATUS_REPORTED . ',' . STATUS_UPDATED . ',' . STATUS_TO_REMOVE . ') AND end_time >= :current_time');
$result = $stmt->execute(array('current_time' => time()));
if ($result === false) {
	$reports_severity = 'green';
} else {
	$obj = $stmt->fetch(PDO::FETCH_OBJ);
	if ($obj === false) {
		$reports_severity = 'green';
	} else {
		$reports_severity = priorityToColor($obj->priority);
	}
}

$alerts = array(
	array(
		'name' => 'reports',
		'url' => ROOT_FOLDER.'reports',
		'fa-class' => 'fa-map-marker',
		'counter' => $todo_count['reports'],
		'color' => $reports_severity
	),
	array(
		'name' => 'news items',
		'url' => ROOT_FOLDER.'news-items',
		'fa-class' => 'fa-rss-square',
		'counter' => '0',
		'color' => 'green'
	),
	array(
		'name' => 'support topics',
		'url' => ROOT_FOLDER.'support-topics',
		'fa-class' => 'fa-life-ring',
		'counter' => $todo_count['support-topics'],
		'color' => $todo_count['support-topics'] <= 2 ? 'orange' : 'red'
	),
	array(
		'name' => 'closure requests',
		'url' => 'https://docs.google.com/spreadsheets/d/1qP1aFqkAQTtOqXmydRSOejtYMdFwtTRWaC2Y3475YMM/edit#gid=703487280',
		'fa-class' => 'fa-road',
		'counter' => $todo_count['closure-requests'],
		'color' => $todo_count['closure-requests'] <= 2 ? 'orange' : 'red'
	)
);

require('templates/template_index.php');

function priorityToColor($priority) {
	if ($priority >= 2) {
		return 'red';
	} else if ($priority >= 1) {
		return 'orange';
	} else {
		return 'green';
	}
}

?>
