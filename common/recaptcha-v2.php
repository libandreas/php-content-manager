<?php

require_once __DIR__ . '/functions.php';
load_enviroment_variables(dirname(__DIR__) . '/.env');

if (!defined('GOOGLE_RECAPTCHA_V2_SITE_KEY')) {
	define('GOOGLE_RECAPTCHA_V2_SITE_KEY', '6LdXcCkUAAAAAKOfs6iE8aK9XOQxGW5eOGDUI_dp');
}

$recaptchaSecret = getenv('GOOGLE_RECAPTCHA_V2_SECRET_KEY');
if ($recaptchaSecret === false || trim($recaptchaSecret) === '') {
	throw new RuntimeException('Missing GOOGLE_RECAPTCHA_V2_SECRET_KEY in .env.');
}
define('GOOGLE_RECAPTCHA_V2_SECRET_KEY', $recaptchaSecret);

//<script src="https://www.google.com/recaptcha/api.js" async defer></script>
//<div class="g-recaptcha" data-sitekey="YOUR_SITE_KEY"></div>

function check_recaptcha_v2($g_recaptcha_response = false)
{
	$g_recaptcha_response = $g_recaptcha_response !== false ? $g_recaptcha_response : ($_POST['g-recaptcha-response'] ?? null);

	if ($g_recaptcha_response == null || GOOGLE_RECAPTCHA_V2_SECRET_KEY == '') {
		return false;
	}

	$data = array(
		'secret' => GOOGLE_RECAPTCHA_V2_SECRET_KEY,
		'response' => $g_recaptcha_response,
		'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
	);

	$options = array(
		'http' => array(
			'header' => "Content-type: application/x-www-form-urlencoded\r\n",
			'method' => 'POST',
			'content' => http_build_query($data)
		),
		'ssl' => array(
			'cafile' => __DIR__ . '/cacert.pem',
			'verify_peer' => true,
			'verify_peer_name' => true
		)
	);

	$result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create($options));
	if ($result == false) {
		return false;
	}

	$result = json_decode($result, true);

	return isset($result['success']) && $result['success'] == true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !check_recaptcha_v2()) {
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Verification failed</title></head><body>';
	echo '<p>Sorry, you did not pass the human verification check.</p>';
	echo '<p><a class="cms-link" href="' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/', ENT_QUOTES, 'UTF-8') . '">Return</a></p>';
	echo '</body></html>';
	exit;
}
