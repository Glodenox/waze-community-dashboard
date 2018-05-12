<?php

if (!defined('IN_DASHBOARD')) {
	exit;
}

define('SCRAPER_URL', 'https://www.waze.com/forum/viewforum.php?f=%u&mobile=mobile&sk=t&sd=d');
define('SCRAPER_ID', 3);

class Scraper {
	function update_source() {
		global $db, $code_errors;

		$stmt = $db->prepare('SELECT id, name, slack_team, slack_channels FROM dashboard_support_forums');
		execute($stmt, array());
		$forums = $stmt->fetchAll(PDO::FETCH_OBJ);

		$results = array(
			'received' => 0,
			'new' => 0,
			'archived' => 0
		);
		foreach ($forums as $forum) {
			$url = sprintf(SCRAPER_URL, $forum->id);
			$h = curl_init($url);
			curl_setopt_array($h, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT => "Waze Benelux Dashboard crawler"
			));
			$result = curl_exec($h);
			curl_close($h);

			$stmt = $db->prepare('SELECT id FROM dashboard_support_topics WHERE forum_id = ?');
			execute($stmt, array($forum->id));
			$existing_topics = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
			$new_topics = array();

			preg_match_all('/<li class="row topic-0 link"[^>]+>\W*<p><a href="\.\/viewtopic\.php\?f=[0-9]+&amp;t=([0-9]+)[^>]+>([^<]+)<\/a>\W*<\/p>[^"]+"[^>]+>0<\/span>([^<]+)/', $result, $matches, PREG_SET_ORDER);
			$results['received'] += count($matches);
			foreach ($matches as $topic) {
				$idx = array_search($topic[1], $existing_topics);
				if ($idx !== FALSE) {
					unset($existing_topics[$idx]);
				} else {
					$new_topics[] = $topic;
				}
			}

			if (count($new_topics)) {
				array_walk($new_topics, function(&$topic, $idx, $forum_id) {
					array_shift($topic);
					$topic[2] = strtotime($topic[2]);
					$topic[3] = $forum_id;
					$topic[4] = STATUS_REPORTED;
				}, $forum->id);

				if ($forum->slack_team != '') {
					$text = array();
					foreach ($new_topics as $new_topic) {
						$text[] = 'New topic posted in ' . $forum->name . ': <https://www.waze.com/forum/viewtopic.php?f=' . $forum->id . '&t=' . $new_topic[0] . '|' . $new_topic[1] . '>.';
					}
					foreach (explode(',', $forum->slack_channels) as $slack_channel) {
						send_notification(array(
							"icon_emoji" => ":waze:",
							"channel" => $slack_channel,
							"text" => implode("\n", $text)
						));
					}
				}

				multi_insert('INSERT INTO dashboard_support_topics (id, title, timestamp, forum_id, status) VALUES (?,?,?,?,?)', $new_topics);
				$results['new'] += count($new_topics);
			}

			// Mark topics that have been replied to as processed
			if (count($existing_topics)) {
				$stmt = $db->prepare('UPDATE dashboard_support_topics SET status = ' . STATUS_REMOVED . ' WHERE id IN (' . implode(',', $existing_topics) . ')');
				$stmt->execute();
				$results['archived'] += $stmt->rowCount();
			}
			sleep(2); // Prevent triggering any firewalls
		}

		// Update last execution time and add the result
		$stmt = $db->prepare('UPDATE dashboard_sources SET last_update = CURRENT_TIMESTAMP(), last_execution_result = ? WHERE id = ' . SCRAPER_ID);
		execute($stmt, array(json_encode(array_merge($results, array('errors' => $code_errors)))));

		return $results;
	}
}