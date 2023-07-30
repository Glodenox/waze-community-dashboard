<?php

function retrieve_access($code) {
	$post_data = array(
		'client_id' => SLACK_CLIENT_ID,
		'client_secret' => SLACK_CLIENT_SECRET,
		'code' => $code,
		'redirect_uri' => BASE_URL . 'auth'
	);
	$h = curl_init('https://slack.com/api/openid.connect.token');
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query($post_data)
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
	$h = curl_init("https://slack.com/api/openid.connect.userInfo?token=$access_token");
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false
	));
	$identify_response = curl_exec($h);
	curl_close($h);
	$user_data = json_decode($identify_response);

	if (!$user_data->ok) {
		json_fail('Retrieving user data failed with error code ' . htmlspecialchars($user_data->error) . '.');
	}
	return $user_data;
}

?>