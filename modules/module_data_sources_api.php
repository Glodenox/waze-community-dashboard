<?php

header('Content-Type: application/json; charset=utf-8');
switch($folders[2]) {
	case 'update':
		$source_id = (int)$folders[1];
		if ($source_id <= 0) {
			json_fail('No source ID or invalid source ID provided');
		}
		$stmt = $db->prepare('SELECT name, update_cooldown, notify, UNIX_TIMESTAMP(last_update) as last_update FROM dashboard_sources WHERE id = :source_id');
		execute($stmt, array(':source_id' => $source_id));
		$source = $stmt->fetch(PDO::FETCH_OBJ);
		if (!$source) {
			json_fail('Unknown source specified');
		}
		if (!file_exists('scrapers/' . $source_id . '.php')) {
			json_fail('Could not retrieve scraper logic');
		}
		if ($source->last_update > time() - $source->update_cooldown) {
			json_fail('Source was updated too recently already');
		}
		$semaphore = sem_get(ftok(__FILE__, $source_id));
		if (!sem_acquire($semaphore, TRUE)) {
			file_put_contents('logs/scraper-general.err', 'Source is already being updated (at ' . time() . ' by ' . $_SERVER['REMOTE_ADDR'] . ")\n", FILE_APPEND);
			json_fail('Source is already being updated');
		}
		require('scrapers/' . $source_id . '.php');
		$db->beginTransaction();
		$stmt = $db->prepare("SELECT state FROM dashboard_sources WHERE id = $source_id FOR UPDATE");
		execute($stmt, array());
		$state = $stmt->fetchColumn();
		// Force unlock if still running after an hour
		if ($state == 'running' && $source->last_update < time() - $source->update_cooldown - 3600) {
			$state = 'inactive';
		}
		// Otherwise fail if not set as inactive
		if ($state != 'inactive') {
			$db->commit();
			json_fail('Process already running or in error');
		}
		$db->query("UPDATE dashboard_sources SET state = 'running' WHERE id = $source_id");
		$db->commit();

		// change error reporting
		set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
		$scraper = new Scraper();
		try {
			$results = $scraper->update_source();
		} catch(Exception $e) {
			file_put_contents('logs/scraper-' . $source_id . '.err', $e . "\n", FILE_APPEND);
			$results = array(
				'errors' => $e->__toString()
			);
		} finally {
			$db->query("UPDATE dashboard_sources SET state = 'inactive' WHERE id = $source_id");
		}
		restore_error_handler();
		sem_release($semaphore);

		// TODO: whitelist instead of blacklist here
		unset($results['received']);
		unset($results['previous-count']);

		$results = array_filter($results); // without callback specified, array_filter removes all entries equalling to FALSE (0 == FALSE)

		if (count($results) != 0 && $source->notify && array_keys($results)[0] != 'archived') {
			$result_convert = function(&$result, $category) {
				$result = $result . ' ' . $category;
			};
			array_walk($results, $result_convert);
			send_notification(array(
				"icon_emoji" => ":waze:",
				"text" => '<http' . ($_SERVER['SERVER_PORT']==443 ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . ROOT_FOLDER . "reports|Source _'{$source->name}'_ updated>: " . implode(', ', $results) . '.'
			), 'Belux');
		}

		json_send(array(
			'result' => $results
		));

	case 'heatmap':
		$source_id = (int)$folders[1];
		if ($source_id <= 0) {
			json_fail('No source ID or invalid source ID provided');
		}
		$filter = (array_key_exists('filter', $_GET) ? $_GET['filter'] : -1);
		if ($filter === -1) {
			json_fail('No heatmap filter specified');
		}
		$heatmap_filters = array(
			'all' => '',
			'open' => " AND status IN (1, 2, 3, 4, 7)",
			//'user' => '', TODO
		);
		foreach (STATUSES as $key => $name) {
			$heatmap_filters[strtolower(str_replace('_', '-', $name))] = ' AND status = ' . $key;
		}
		if (!array_key_exists($filter, $heatmap_filters)) {
			json_fail('No valid heatmap filter specified');
		}
		$stmt = $db->query('SELECT round(lon, 1) as lon, round(lat, 1) as lat, count(id) as reports FROM dashboard_reports WHERE source = ' . $source_id . $heatmap_filters[$filter] . ' GROUP BY 1, 2');
		$data = $stmt->fetchAll(PDO::FETCH_OBJ);
		json_send(array(
			'result' => $data
		));

	default:
		json_fail('Unknown action requested');
}

?>