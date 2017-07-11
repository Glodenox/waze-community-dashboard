<?php

define('DEBUG', false);

// Database connection
define('DB_HOST', 'localhost');
define('DB_DATABASE', '');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');

// Slack configuration
define('SLACK_CLIENT_ID', ''); // App ID generated by Slack
define('SLACK_CLIENT_SECRET', ''); // App secret generated by Slack
define('SLACK_INCOMING_WEBHOOK_URL', null); // Incoming webhook URL

define('ROOT_FOLDER', '/'); // adjust if the dashboard isn't located at the root of the website or subdomain
define('BASE_URL', ($_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . ROOT_FOLDER);

?>