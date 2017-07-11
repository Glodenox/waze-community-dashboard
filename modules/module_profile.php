<?php

if (!$folders[1]) {
	$folders[1] = '';
}
switch ($folders[1]) {
	// Display the profile page
	case '':
	case 'index':
		if (!array_key_exists('user_id', $_SESSION)) {
			$error = "User isn't logged in";
			require('templates/template_error.php');
			die();
		}
		// Retrieve user statistics
		$stmt = $db->prepare('SELECT action_id, count(report_id) as total FROM dashboard_report_history WHERE user_id = ? GROUP BY action_id');
		$stmt->execute(array($user->id));
		$action_stats = $stmt->fetchAll(PDO::FETCH_OBJ);

		$stmt = $db->prepare('SELECT area_north as north, area_east as east, area_south as south, area_west as west FROM dashboard_users WHERE id = ?');
		$stmt->execute(array($user->id));
		$management_area = $stmt->fetchObject();

		$page_title = 'Profile - ' . $page_title;
		require('templates/template_profile.php');
		break;

	case 'logout':
		if (!array_key_exists('user_id', $_SESSION)) {
			$error = "User isn't logged in";
			require('templates/template_error.php');
			die();
		}
		unset($_SESSION['user_id']);
		redirect();

	default:
		// We're dealing with an API call
		require('modules/module_profile_api.php');
}

?>
