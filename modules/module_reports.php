<?php

/**
* This module provides access to the reported road closures, such as road works, manifestations, planned closures, ...
* By default the module returns the web page on which the road reports are listed
*/

if (!$folders[1]) { // Display the reports page
	// Retrieve the available sources
	$stmt = $db->query("SELECT id, name FROM dashboard_sources WHERE data_type = 'Reports'");
	$report_sources = $stmt->fetchAll(PDO::FETCH_OBJ);
	// Retrieve current data information
	$stmt = $db->query("SELECT min(lon) as 'min_lon', max(lon) as 'max_lon', min(lat) as 'min_lat', max(lat) as 'max_lat' FROM dashboard_reports");
	$map_data = $stmt->fetch(PDO::FETCH_OBJ);

	$page_title = 'Reports - ' . $page_title;
	$meta_tags['description'] = 'Retrieve and process reports that may affect traffic';

	require('templates/template_reports.php');
} else { // We're dealing with an API call
	require('modules/module_reports_api.php');
}

?>
