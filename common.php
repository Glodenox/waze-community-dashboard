<?php

// Constants
define('PRIORITY_LOW', 0);
define('PRIORITY_NORMAL', 1);
define('PRIORITY_HIGH', 2);
define('PRIORITY_URGENT', 3);
const PRIORITIES = array(
	PRIORITY_LOW => 'Low',
	PRIORITY_NORMAL => 'Normal',
	PRIORITY_HIGH => 'High',
	PRIORITY_URGENT => 'Urgent'
);

define('ACTION_INSERT', 1);
define('ACTION_UPDATE', 2);
define('ACTION_SET_STATUS', 3);
define('ACTION_SET_LEVEL', 4);
define('ACTION_SET_PRIORITY', 5);
define('ACTION_MESSAGE', 6);
define('ACTION_CANCEL', 7);
const ACTIONS = array(
	ACTION_INSERT => 'Report creation',
	ACTION_UPDATE => 'Report updated',
	ACTION_SET_STATUS => 'Status changed',
	ACTION_SET_LEVEL => 'Editor level changed',
	ACTION_SET_PRIORITY => 'Priority changed',
	ACTION_CANCEL => 'Marked as cancelled',
	ACTION_MESSAGE => 'Modified the report notes'
);

define('STATUS_REPORTED', 1);
define('STATUS_UPDATED', 2);
define('STATUS_PROCESSED', 3);
define('STATUS_TO_REMOVE', 4);
define('STATUS_REMOVED', 5);
define('STATUS_TO_IGNORE', 6);
define('STATUS_TO_INVESTIGATE', 7);
const STATUSES = array(
	STATUS_REPORTED => 'New',
	STATUS_UPDATED => 'Updated',
	STATUS_PROCESSED => 'Processed',
	STATUS_TO_REMOVE => 'Cancelled',
	STATUS_REMOVED => 'Archived',
	STATUS_TO_IGNORE => 'To Ignore',
	STATUS_TO_INVESTIGATE => 'To Investigate'
);

try {
	$db = new PDO(sprintf('mysql:dbname=%s;host=%s;port=%s;charset=utf8', DB_DATABASE, DB_HOST, DB_PORT), DB_USERNAME, DB_PASSWORD);
} catch (PDOException $e) {
	die('Connection failed: ' . $e->getMessage());
}

// make our own PHP session handler
class DBSessionHandler implements SessionHandlerInterface {
	public function __construct() {
		session_set_save_handler(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
		register_shutdown_function('session_write_close');
		session_name('Dashboard_Session_ID');
		session_start([
			'cookie_path' => rtrim(ROOT_FOLDER, '/')
		]);
		$current_settings = session_get_cookie_params();
		// Need to set the cookie manual to update the expires timestamp
		setcookie(session_name(), session_id(), [
			'expires' => time() + COOKIE_LIFETIME,
			'secure' => true,
			'httponly' => true,
			'samesite' => 'Lax',
			'path' => $current_settings['path'],
			'domain' => $current_settings['domain']
		]);
	}

	public function open($save_path, $session_id) {
		return true;
	}

	public function close() {
		return true;
	}

	public function read($session_id) {
		global $db;
		$stmt = $db->prepare('SELECT session_data FROM dashboard_user_sessions WHERE session_id = ? AND session_expires > ?');
		if ($stmt->execute(array($session_id, time()))) {
			if ($obj = $stmt->fetchObject()) {
				return $obj->session_data;
			}
		}
		return '';
	}

	public function write($session_id, $session_data) {
		global $db;
		$expire = time() + COOKIE_LIFETIME;
		$stmt = $db->prepare('REPLACE INTO dashboard_user_sessions SET session_id = ?, session_expires = ?, session_data = ?, user_agent = ?, ip_address = ?');
		return $stmt->execute(array($session_id, $expire, $session_data, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']));
	}

	public function destroy($session_id) {
		global $db;
		$stmt = $db->prepare('DELETE FROM dashboard_user_sessions WHERE session_id = ? LIMIT 1');
		return $stmt->execute(array($session_id));
	}

	// Note: ignoring the maxlifetime argument on purpose, we've decided ourselves when to remove
	public function gc($maxlifetime) {
		global $db;
		$stmt = $db->prepare('DELETE FROM dashboard_user_sessions WHERE session_expires < ?');
		return $stmt->execute(array(time()));
	}
}

ini_set('session.gc_maxlifetime', 30*24*60*60);
// Handler registers itself in __construct
new DBSessionHandler();

function json_encode_safe($value, $options = 0, $depth = 512) {
	// Deal with PHP bug surrounding precision of floats in json_encode: https://stackoverflow.com/questions/42981409/php7-1-json-encode-float-issue
	// Also limit the precision to 6 while we're at it
	return preg_replace('/(\.[0-9]{6})[0-9]+/', '\1', json_encode($value, $options, $depth));
}

function json_fail($error_message) {
	header($_SERVER["SERVER_PROTOCOL"] . ' 400 Invalid Request', true, 400); 
	echo json_encode_safe(array(
		'ok' => false,
		'error' => $error_message
	), JSON_NUMERIC_CHECK);
	exit;
}

function json_send($obj = null) {
	global $code_errors;
	if (count($code_errors) > 0) {
		json_fail(count($code_errors) == 1 ? $code_errors[0] : $code_errors);
	} else if ($obj === null) {
		echo json_encode_safe(array(
			'ok' => true
		), JSON_NUMERIC_CHECK);
	} else {
		echo json_encode_safe(array_merge(array('ok' => true), $obj), JSON_NUMERIC_CHECK);
	}
	exit;
}

function execute($stmt, $values) {
	$result = $stmt->execute($values);
	if (!$result || strlen($stmt->errorInfo()[2]) > 0) {
		json_fail("Database statement failed due to: " . (strlen($stmt->errorInfo()[2]) > 0 ? $stmt->errorInfo()[2] : $stmt->errorInfo()[1]));
	}
}

function multi_insert($sql, $rows) {
	global $db;
	$cols = count($rows[0]);
	$one_row = ',(?' . str_repeat(',?', $cols-1) . ')';
	// minus 1 row as the sql statement already contains one row
	$sql .= str_repeat($one_row, count($rows)-1);
	$values = call_user_func_array('array_merge', $rows);
	$stmt = $db->prepare($sql);
	execute($stmt, $values);
}

function redirect($folder = '') {
	header('Location: ' . BASE_URL . $folder);
	echo '<a href="' . BASE_URL . $folder . '">Click here if you are not being redirected</a>';
	exit();
}

function send_notification($payload, $server = 'Netherlands') {
	// Don't send notifications from the test server to other people
	if (DEBUG && array_key_exists('channel', $payload) && $payload['channel'] != '@glodenox' && $payload['channel'] != 'U0959H276') {
		return;
	}
	// Call Slack webhook for notification
	$h = curl_init(SLACK_INCOMING_WEBHOOKS[$server]);
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_POSTFIELDS => array("payload" => json_encode($payload)),
		CURLOPT_USERAGENT => "Waze Community Dashboard"
	));
	$result = curl_exec($h);
	curl_close($h);
}

function camelToRegular($text) {
	$words = preg_split('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $text);
	return implode(' ', array_map('ucfirst', $words));
}

function spaceOutCommas($text) {
	if (!is_string($text)) {
		return $text;
	}
	return str_replace(array(',', '  '), array(', ', ' '), $text);
}

// Credit to Glavic at http://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
function time_elapsed_string($datetime) {
	$now = new DateTime;
	$ago = new DateTime($datetime);
	$diff = $now->diff($ago);

	$diff->w = floor($diff->d / 7);
	$diff->d -= $diff->w * 7;

	$units = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
	);
	$string = [];
	foreach ($units as $unit => $name) {
		if ($diff->$unit) {
			$string[] = $diff->$unit . ' ' . $name . ($diff->$unit > 1 ? 's' : '');
		}
	}

	$string = array_slice($string, 0, 2);
	return $string ? implode(', ', $string) . ' ago' : 'just now';
}

?>