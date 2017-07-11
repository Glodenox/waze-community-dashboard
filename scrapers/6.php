<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_URL', 'https://docs.google.com/spreadsheets/d/1qP1aFqkAQTtOqXmydRSOejtYMdFwtTRWaC2Y3475YMM/pub?gid=703487280&single=true&output=tsv');
define('SCRAPER_ID', 6);

class Scraper {
	function update_source() {
		global $db, $code_errors;

		$h = curl_init(SCRAPER_URL);
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_USERAGENT => "Waze Benelux crawler"
		));
		$result = curl_exec($h);
		if ($result === false) {
			$code_errors[] = 'Could not retrieve Sheet: ' . curl_error($h);
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => $code_errors))));
			exit();
		}
		curl_close($h);

		$lines = preg_split("/((\r?\n)|(\r\n?))/", $result);
		$column = array_search('transfer?', explode("\t", $lines[0]));
		if ($column === FALSE) {
			$code_errors[] = 'Could not find the transfer status column in the sheet';
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => $code_errors))));
			exit();
		}
		$todo_values = array('ready', 'correction !');
		$results = array(
			'received' => 0,
			'new' => 0
		);
		foreach(array_slice($lines, 1) as $line) {
			$values = explode("\t", $line);
			if (array_search($values[$column], $todo_values) !== FALSE) {
				$results['new']++;
			}
			$results['received']++;
		}
		file_put_contents('logs/closure-requests-count.cache', $results['new']);

		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => $code_errors)))));

		return $results;
	}
}