<?php

switch($folders[1]) {
	case '':
	case 'index':
		$stmt = $db->prepare('SELECT t.id, t.forum_id, t.title, t.timestamp, f.name as forum_name FROM dashboard_support_topics t, dashboard_support_forums f WHERE t.forum_id = f.id AND status = ' . STATUS_REPORTED . ' ORDER BY timestamp ASC');
		execute($stmt, array());
		$support_topics = $stmt->fetchAll(PDO::FETCH_OBJ);
		$page_title = 'Support Topics - ' . $page_title;
		require('templates/template_support_topics.php');
		break;

	case 'process':
		if (!isset($user)) {
			json_fail('User not logged in');
		}
		$topic = (int)$_GET['id'];
		if ($topic == 0) {
			json_fail('No report ID or invalid report ID provided');
		}
		$stmt = $db->prepare('UPDATE dashboard_support_topics SET status = ' . STATUS_REMOVED . ' WHERE id = :topic_id');
		execute($stmt, array(
			':topic_id' => $topic
		));
		json_send();
		break;

	default:
		json_fail('Unknown operation');
}

?>