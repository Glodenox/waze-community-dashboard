<?php

define('DEBUG', false);
define('COOKIE_LIFETIME', 30*24*60*60);

// Database connection
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_DATABASE', '');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');

// Slack configuration
define('SLACK_CLIENT_ID', ''); // App ID generated by Slack
define('SLACK_CLIENT_SECRET', ''); // App secret generated by Slack
define('SLACK_INCOMING_WEBHOOK_URL', null); // Incoming webhook URL
const SLACK_INCOMING_WEBHOOKS = array(
	'SlackServerName' => null
);

define('ROOT_FOLDER', '/'); // adjust if the dashboard isn't located at the root of the website or subdomain
define('BASE_URL', ($_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . ROOT_FOLDER);

?>