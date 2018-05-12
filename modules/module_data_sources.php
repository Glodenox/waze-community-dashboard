<?php

/**
* This module provides access to the sources used to retrieve reports and their latest status
*/

if (is_numeric($folders[1]) && !$folders[2]) { // Display the individual source page
	$sourceId = (int)$folders[1];
	if ($sourceId == 0) {
		$error = 'Could not find requested source';
	} else {
		$stmt = $db->query('SELECT name, description, url, data_type, last_execution_result, state, last_update FROM dashboard_sources WHERE id = ' . $sourceId);
		if ($stmt === false) {
			$error = 'Could not find requested source';
		} else {
			$source = $stmt->fetch(PDO::FETCH_OBJ);
			if ($source === false || count($source) == 0) {
				$error = 'Could not find requested source';
			} else {
				$stmt = $db->query('SELECT min(lon) as min_lon, max(lon) as max_lon, min(lat) as min_lat, max(lat) as max_lat FROM dashboard_reports WHERE source = ' . $sourceId);
				$map_bounds = $stmt->fetch(PDO::FETCH_OBJ);
				// The bounds default to 0 if no reports are available for this source
				if ($map_bounds->min_lon == 0 && $map_bounds->min_lat == 0 && $map_bounds->max_lon == 0 && $map_bounds->max_lat == 0) {
					$map_bounds = FALSE;
				}
				if ($source->data_type == 'Reports') {
					$stmt = $db->query('SELECT status, count(id) as count FROM dashboard_reports WHERE source = ' . $sourceId . ' AND status <> 5 GROUP BY status');
					$source_stats = $stmt->fetchAll(PDO::FETCH_OBJ);
					if (isset($user)) {
						$stmt = $db->query('SELECT value as status, count(DISTINCT id) as count FROM dashboard_report_history h, dashboard_reports r WHERE h.report_id = r.id AND user_id = ' . $user->id . ' AND action_id = 3 AND r.source = ' . $sourceId . ' AND status <> 5 GROUP BY value');
						$source_personal_stats = $stmt->fetchAll(PDO::FETCH_OBJ);
					}
				}
			}
		}
	}
	$page_title = (isset($error) ? $error : $source->name) . ' - Data Sources - ' . $page_title;
	require('templates/template_data_source.php');
} elseif ($folders[1] == '' || $folders[1] == 'index') { // Retrieve the overview of all sources
	$stmt = $db->prepare("SELECT s.id, s.name, s.data_type, s.last_update, s.state, s.update_cooldown, IF(s.data_type = 'Support Topics', (SELECT count(t.id) FROM dashboard_support_topics t), (SELECT count(r.id) FROM dashboard_reports r WHERE r.source = s.id)) as items FROM dashboard_sources s");
	$stmt->execute();
	$sources = $stmt->fetchAll(PDO::FETCH_OBJ);
	$page_title = 'Data Sources - ' . $page_title;
	require('templates/template_data_sources.php');
} else { // We're dealing with an API call
	require('modules/module_data_sources_api.php');
}

?>
