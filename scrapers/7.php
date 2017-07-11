<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_URL', 'https://www.waze.com/row-Descartes/app/Features?language=en&bbox=4.20,50.75,4.65,51.15&problemFilter=1,1&mapUpdateRequestFilter=1,0&venueLevel=4&venueFilter=2,0,2&mapComments=true&sandbox=true');
define('SCRAPER_ID', 7);

class Scraper {
	function update_source() {
		global $db, $code_errors;

		$h = curl_init(SCRAPER_URL);
		curl_setopt_array($h, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_USERAGENT => "Waze Benelux Community Dashboard (Glodenox)"
		));
		$result = curl_exec($h);
		if ($result === false) {
			$code_errors[] = 'Could not retrieve map features ' . curl_error($h);
			$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
			execute($stmt, array(json_encode(array('errors' => $code_errors))));
			$db->query("UPDATE dashboard_sources SET state = 'inactive' WHERE id = " . SCRAPER_ID);
			exit();
		}
		curl_close($h);

		$features = json_decode($result);
		file_put_contents('logs/FeaturesResponse.json', $result);
		$results = array(
			'received' => count($features->mapUpdateRequests->objects) + count($features->mapComments->objects),
			'new' => 0,
			'updated' => 0
		);
		// Retrieve current data
		/*$stmt = $db->query('SELECT id, state FROM dashboard_requests WHERE lon BETWEEN 4.20 AND 4.65 AND lat BETWEEN 50.75 AND 51.15');
		$current_features = $stmt->fetchAll(PDO::FETCH_OBJ);*/

		// Process the results
		foreach($features->mapUpdateRequests->objects as $update_request) {
			$stmt = $db->prepare('INSERT INTO dashboard_requests (id, state, type, lon, lat, description, creation_time, last_message_time) VALUES (?,?,?,?,?,?,?,?)');
			execute($stmt, array(
				$update_request->id,
				$update_request->open ? 'open' : 'resolved',
				'update_request',
				$update_request->geometry->coordinates[0],
				$update_request->geometry->coordinates[1],
				$update_request->description,
				$update_request->driveDate / 1000,
				$update_request->updatedOn == null ? null : $update_request->updatedOn / 1000
			));
			$results['new']++;
		}
		foreach($features->problems->objects as $problem) {
			$stmt = $db->prepare('INSERT INTO dashboard_requests (id, state, type, lon, lat, description, creation_time, last_message_time) VALUES (?,?,?,?,?,?,?,?)');
			execute($stmt, array(
				$update_request->id,
				$update_request->open ? 'open' : 'resolved',
				'map_problem',
				$update_request->geometry->coordinates[0],
				$update_request->geometry->coordinates[1],
				$update_request->problemType,
				time(),
				$update_request->resolvedOn == null ? null : $update_request->resolvedOn / 1000
			));
			$results['new']++;
		}
		foreach($features->venues->objects as $venue) {
			if (count($venue->venueUpdateRequests) > 0 && $venue->geometry->type == 'Point') { // temporary fix to prevent area places from screwing things up
				foreach($venue->venueUpdateRequests as $update_request) {
					$stmt = $db->prepare('INSERT INTO dashboard_requests (id, state, type, lon, lat, description, creation_time) VALUES (?,?,?,?,?,?,?)');
					execute($stmt, array(
						$update_request->id,
						'open',
						'venue_update',
						$venue->geometry->coordinates[0],
						$venue->geometry->coordinates[1],
						$venue->name,
						$update_request->dateAdded / 1000
					));
					$results['new']++;
				}
			}
		}
		foreach($features->mapComments->objects as $map_comment) {
			if ($map_comment->geometry->type == 'Point') { // temporary fix to prevent area comments from screwing things up
				$stmt = $db->prepare('INSERT INTO dashboard_requests (id, state, type, lon, lat, description, creation_time, last_message_time) VALUES (?,?,?,?,?,?,?,?)');
				execute($stmt, array(
					$map_comment->id,
					!isset($map_comment->conversation) || count($map_comment->conversation) == 0 || $this->are_all_comments_by_author($map_comment->conversation, $map_comment->createdBy) ? 'open' : 'resolved',
					'map_comment',
					$map_comment->geometry->coordinates[0],
					$map_comment->geometry->coordinates[1],
					$map_comment->subject,
					$map_comment->createdOn / 1000,
					$map_comment->updatedOn == null ? null : $map_comment->updatedOn / 1000
				));
				$results['new']++;
			}
		}

		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => $code_errors)))));

		return $results;
	}

	function are_all_comments_by_author($conversation, $author_id) {
		foreach ($conversation as $comment) {
			if ($comment->userID != $author_id) {
				return false;
			}
		}
		return true;
	}
}