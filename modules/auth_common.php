<?php

function retrieve_access($code) {
	$h = curl_init(sprintf("https://slack.com/api/oauth.access?client_id=%s&client_secret=%s&code=%s&redirect_uri=%s", SLACK_CLIENT_ID, SLACK_CLIENT_SECRET, $code, BASE_URL . 'auth'));
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false
	));
	$access_response = curl_exec($h);
	curl_close($h);
	$access = json_decode($access_response);
	if (!$access->ok) {
		$error = 'Access request denied for Client ID ' . SLACK_CLIENT_ID . ' with error code ' . htmlspecialchars($access->error) . '.';
		require('templates/template_error.php');
		die();
	}
	return $access;
}

function retrieve_user($access_token) {
	$h = curl_init("https://slack.com/api/users.identity?token={$access_token}");
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false
	));
	$identify_response = curl_exec($h);
	curl_close($h);
	$user_data = json_decode($identify_response);

	if (!$user_data->ok) {
		$error = 'Retrieving user data failed with error code ' . htmlspecialchars($user_data->error) . '.';
		require('templates/template_error.php');
		die();
	}
	return $user_data;
}

?>