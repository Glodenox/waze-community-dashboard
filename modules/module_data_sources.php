<?php

/**
* This module provides access to the sources used to retrieve reports and their latest status
*/

if (is_numeric($folders[1]) && !$folders[2]) { // Display the individual source page
	$sourceId = (int)$folders[1];
	if ($sourceId == 0) {
		$error = 'Could not find requested source';
	} else {
		$stmt = $db->prepare("SELECT name, description, url, data_type, last_execution_result, state, last_update FROM dashboard_sources WHERE id = ?");
		$stmt->execute(array($sourceId));
		if ($stmt === false) {
			$error = 'Could not find requested source';
		} else {
			$source = $stmt->fetch(PDO::FETCH_OBJ);
			if ($source === false || count($source) == 0) {
				$error = 'Could not find requested source';
			} else {
				$stmt = $db->query('SELECT round(lon, 1) as lon, round(lat, 1) as lat, count(id) as reports FROM dashboard_reports WHERE source = ' . $sourceId . ' GROUP BY 1, 2');
				$heatmap = $stmt->fetchAll(PDO::FETCH_OBJ);
			}
		}
	}
	$page_title = $source->name . ' - Data Sources - ' . $page_title;
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
