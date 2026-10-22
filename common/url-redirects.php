<?php

function call_redirect_url_from_csv($url)
{

	// Fallback if file not exists
	$target_file = $_SERVER['DOCUMENT_ROOT'] . "/urls-redirects.csv";
	if (!file_exists($target_file)) {

		return false;
	}

	// Normalize input url
	$url = normalize_url_for_rewrite($url);

	// Patch browser url with with ; in case of , for escaping comma inside csv
	$url = str_replace(',', ';', $url);

	// Call lines
	$lines = file($target_file);

	// Read from botoom so I can use latest url first
	$lines = array_reverse($lines);
	foreach ($lines as $csvline) {

		// Skip lines starting with #
		if (strpos(trim($csvline), '#') === 0) {
			continue;
		}

		// Get first cell of csv
		$csvArray = str_getcsv($csvline, ', ');
		if (count($csvArray) < 2) {
			continue;
		}

		// Get urls items
		$first_url = trim($csvArray[0]);
		$second_url = trim($csvArray[1]);

		// If url is encoded then decode
		$first_url = normalize_url_for_rewrite($first_url);
		$second_url = normalize_url_for_rewrite($second_url);

		// Match url with a pattern
		if (doesUrlMatchPattern_2($first_url, $url)) {

			// If the URL matches the first URL, replace it with the second URL
			$second_url = replaceUrlWithPattern_2($first_url, $second_url, $url);
			return $second_url;
		}

		// Absolute Match
		elseif ($first_url == $url) {

			return $second_url;
		}
	}

	// If nothing is found, return false
	return false;
}

// Sync the file only if it's the main file being executed directly in CLI
require_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	require_once __DIR__ . '/polyfills.php';
}

// get the current url
// this file need also url-rewrites.php for calling functions
$current_url = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
$current_url .= '://' . ($_SERVER['HTTP_HOST'] ?? '');
$current_url .= $_SERVER['REQUEST_URI'] ?? '';
$current_url = normalize_url_2($current_url); //new

// check if target page not exists before starting to call the second url
if (!file_exists($_SERVER['SCRIPT_FILENAME'])) {

	// call the second url
	$real_url = call_redirect_url_from_csv($current_url);
	$new_url_loaded_successfully = ($real_url != false);

	// rewrite the url
	if ($real_url != false) {

		// setup server variables listening for the new url
		header('Location: ' . $real_url);

		// for better safety put also meta redirect here
		echo '<meta http-equiv="refresh" content="0; url=' . $real_url . '">';
		exit();
	}
}