<?php

function format_edit_link_file_for_html_editor()
{

	// Get the current URL
	$current_url = get_current_url();
	$location = path2url(__FILE__);
	$url_with_live_html = update_url_params(array('t' => $current_url), $location);

	if (is_user_admin_back_end()) {

		//edit php file
		$location = path2url(__FILE__);
		$url_with_live_html = update_url_params(array('t' => $current_url), $location);
	} else {

		$url_with_live_html = '#';
	}

	return $url_with_live_html;
}

function normalize_live_edit_target_path($target_url)
{
	$target_url = urldecode((string) $target_url);
	$target_path = parse_url($target_url, PHP_URL_PATH);
	$target_path = rtrim((string) $target_path, '/');

	if ($target_path === '') {
		$target_path = '/';
	}

	return $target_path;
}

function setup_cms4_buffer($html)
{

	// βάζει το link για να επιστρέψει στην προηγούμενη σελίδα
	if (str_contains(get_current_url(), "/" . basename(__FILE__))) {

		//reset original
		$url = update_url_params(array('reset-original' => true));
		$put = "<a class=\"cms-link\" onclick=\"return confirm('Are you sure you want to reset to the original version? All changes will be lost!')\" href=\"$url\" style=\"background:rgba(255,255,255,0.5);text-decoration:none;color:red;text-shadow: 1px 1px white;\"><small style=\"user-select:none;cursor:pointer;font-size:12pt\">Reset original 🔄</small></a><br>";

		//save changes
		$put .= "<a class=\"cms-link\" id=\"save-live-changes\" style=\"background:rgba(255,255,255,0.5);text-decoration:none;color:black;text-shadow: 1px 1px white;\"><small style=\"user-select:none;cursor:pointer;font-size:12pt\">Save changes 💾</small></a><br>";

		//pull back
		$url = urldecode($_GET['t']);
		$put .= "<a class=\"cms-link\" href=\"$url\" style=\"background:rgba(255,255,255,0.5);text-decoration:none;color:blue;text-shadow: 1px 1px white;\"><small style=\"user-select:none;cursor:pointer;font-size:12pt\">Return ↩️</small></a><br>";

		//put links on html
		$put = '<h2>🌌 Galaxy Content Manager</h2><p><small>Edit page content with a live preview.</small></p>' . $put;
		$put = "<div contenteditable=\"false\" style=\"background:transparent;display: inline-block;position: fixed;bottom: 0;right: 0;z-index: 9999999999999999999999;text-align: right;padding:6px;\" id=\"floating-tools-div\">$put</div>";
		$ids = "<span style=\"display:none;\">" . basename(__FILE__) . "</span>" . PHP_EOL;
		$html = str_replace("</body>", $put . PHP_EOL . "</body>", $html);
		$html = str_replace("</body>", $ids . PHP_EOL . "</body>", $html);

		//use a regular expression to remove all <script> tags and their content
		$pattern = '/<script\b[^>]*>(.*?)<\/script>/is';
		$html = preg_replace($pattern, '', $html);

		//use url relatives for editting to correct stylesheets and images
		if (function_exists('rel2abs_in_html')) {
			$html = rel2abs_in_html($html, urldecode($_GET['t']));
		}

		// Push the js version in the html cache
		if (defined('ABSPATH') && !str_contains($html, 'content-4.js')) {
			$html = str_ireplace('</head>', '<script defer src="/wp-content/plugins/intelligent-front-editor/common/content-4.js"></script></head>', $html);
		} else {
			$html = str_ireplace('</head>', '<script defer src="/common/content-4.js"></script></head>', $html);
		}
	}

	// να μην κάνει τίποτα αμα ειναι μέσα στο admin
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// attach links
	$put = null;
	if (is_user_admin_back_end()) {

		//edit html - keep the full current URL so the return button preserves nested editor context
		$current_url = get_current_url();
		$location = path2url(__FILE__);
		$url_with_html = update_url_params(array('t' => urlencode($current_url)), $location);

		//edit html all
		$url_with_html_all = update_url_params(array('t' => urlencode($current_url), 'mode' => 'all'), $location);

		//if file is null
		$put .= "<a class=\"cms-link\" style=\"text-decoration:none;color:dodgerblue;text-shadow: 1px 1px white\" href=\"" . $url_with_html . "\"><small style=\"font-size:12pt\">Edit live 🥃</small></a><br>";
		$put .= "<a class=\"cms-link\" style=\"text-decoration:none;color:dodgerblue;text-shadow: 1px 1px white\" href=\"" . $url_with_html_all . "\"><small style=\"font-size:12pt\">Edit live all 🥃</small></a><br>";
	}

	// apply changes in the flow of buffer - file json
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-edits.json';
	if (file_exists($db_file) && file_get_contents($db_file) != "" && file_get_contents($db_file) != 'null') {

		$db_content = file_get_contents($db_file);
		$db_array = json_decode($db_content, true);

		// Extract only the path from the current URL (ignore query string)
		$current_url = get_current_url();
		$normalized_path = parse_url($current_url, PHP_URL_PATH);
		$normalized_path = rtrim($normalized_path, '/');
		if (empty($normalized_path)) {
			$normalized_path = '/';
		}

		//use normalized path inside db array (convert stored URLs to paths)
		if (isset($db_array['live-edits-per-url'])) {
			$edits_by_path = [];
			foreach ($db_array['live-edits-per-url'] as $key => $value) {
				$key_path = parse_url($key, PHP_URL_PATH);
				$key_path = rtrim($key_path, '/');
				if (empty($key_path)) {
					$key_path = '/';
				}
				$edits_by_path[$key_path] = $value;
			}
			$db_array['live-edits-per-url'] = $edits_by_path;
		}

		//first apply global
		if (isset($db_array['live-edits-global'])) {
			$html = applyChangesToHtml($html, $db_array['live-edits-global']);
		}

		//then apply per url (matched by path only)
		if (isset($db_array['live-edits-per-url'][$normalized_path])) {
			$html = applyChangesToHtml($html, $db_array['live-edits-per-url'][$normalized_path]);
		}
	}

	//push
	$html = push_admin_link_on_html($html, $put, str_replace(".php", "", basename(__FILE__)));
	return $html;
}

function merge_json_arrays_by_key($array1, $array2)
{
	$merged = $array1;

	foreach ($array2 as $key => $value) {
		if (!isset($merged[$key])) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = merge_json_arrays_by_key($merged[$key], $value);
			} else {
				$merged[$key] = $value;
			}
		}
	}

	return $merged;
}

/**
 * Merges two arrays of HTML changes.
 *
 * @param array $array1 The first array of changes.
 * @param array $array2 The second array of changes.
 * @return array The merged array of changes.
 */
function mergeHtmlChanges($array1, $array2)
{
	// Create an associative array using the 'path' as the key for quick lookup
	$mergedChanges = [];

	// Add changes from the first array
	foreach ($array1 as $change) {
		$path = $change['path'];
		$mergedChanges[$path] = $change;
	}

	// Add or merge changes from the second array
	foreach ($array2 as $change) {
		$path = $change['path'];
		if (isset($mergedChanges[$path])) {
			// If the same path exists, decide how to merge
			// For this example, we'll merge the 'node' content
			$mergedChanges[$path] = mergeChangeNodes($mergedChanges[$path], $change);
		} else {
			$mergedChanges[$path] = $change;
		}
	}

	// Return the merged changes as an indexed array
	return array_values($mergedChanges);
}

/**
 * Merges two change nodes.
 *
 * @param array $node1 The first change node.
 * @param array $node2 The second change node.
 * @return array The merged change node.
 */
function mergeChangeNodes(array $node1, array $node2)
{
	// For simplicity, we'll prioritize the second node
	// Adjust this logic based on your requirements
	return $node2;
}

// load functions
include_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

if (PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && !is_user_admin_back_end()) {
	header('Cache-Control: no-store');
	if ($_SERVER['REQUEST_METHOD'] !== 'GET' || isset($_GET['render-page-with-php']) || isset($_GET['reset-original'])) {
		http_response_code(403);
		exit('Administrator access required.');
	}
	$returnParams = $_GET;
	unset($returnParams['username'], $returnParams['passwd'], $returnParams['login'], $returnParams['logout'], $returnParams['delete-option'], $returnParams['reset-original']);
	$returnParams['login_return'] = '1';
	$returnUrl = path2url(__FILE__) . '?' . http_build_query($returnParams);
	$loginUrl = update_url_params(['t' => urlencode($returnUrl)], path2url(__DIR__ . '/content-0.php'));
	if (!isset($_GET['login_return'])) {
		header('Location: ' . $loginUrl, true, 302);
		exit;
	}
	http_response_code(403);
	header('Content-Type: text/html; charset=utf-8');
	echo '<p>Sign-in failed or administrator access is unavailable.</p>';
	echo '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Sign in again</a></p>';
	exit;
}


// παραγωγη σελιδας
ob_start("setup_cms4_buffer");

// render page and pull back for javascript
if (isset($_GET["render-page-with-php"]) && $_GET["render-page-with-php"] == "yes") {

	if (!isset($_GET['t'])) {
		exit("Error: Missing 't' parameter");
	}

	$url = urldecode($_GET['t']);
	$file = url2path($url);

	if (!file_exists($file)) {
		exit("Error: File not found: " . $file);
	}

	// Now run the file instead of the static one
	setup_environment_for_an_other_url($url, $url);
	$data = file_get_contents($file);
	exit(eval ('?>' . $data));
}

if (isset($_POST['save-changes']) && isset($_GET['mode']) && $_GET['mode'] == 'all' && is_user_admin_back_end()) {
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-edits.json';
	$json_data = $_POST['changes'];
	$json_data = push_json_patches($json_data);

	// Read existing live-edits.json
	if (file_exists($db_file)) {
		$db_content = file_get_contents($db_file);
		$db_array = json_decode($db_content, true);
	} else {
		$db_array = [];
	}

	// Decode the new JSON data
	$new_array = json_decode($json_data, true, 512, JSON_UNESCAPED_UNICODE);

	// Update the live-edits-global key
	if (!isset($db_array['live-edits-global'])) {
		$db_array['live-edits-global'] = [];
	}
	$db_array['live-edits-global'] = mergeHtmlChanges($db_array['live-edits-global'], $new_array);

	// Save back to live-edits.json
	file_put_contents($db_file, json_encode($db_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	exit;
} elseif (isset($_POST['save-changes']) && is_user_admin_back_end()) {

	// παραγωγη σελιδας
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-edits.json';
	$normalized_path = normalize_live_edit_target_path($_GET['t']);
	$json_data = $_POST['changes'];
	$json_data = push_json_patches($json_data);

	// Read existing live-edits.json
	if (file_exists($db_file)) {
		$db_content = file_get_contents($db_file);
		$db_array = json_decode($db_content, true);
	} else {
		$db_array = [];
	}

	// Initialize live-edits-per-url if not exists
	if (!isset($db_array['live-edits-per-url'])) {
		$db_array['live-edits-per-url'] = [];
	}
	if (!isset($db_array['live-edits-per-url'][$normalized_path])) {
		$db_array['live-edits-per-url'][$normalized_path] = [];
	}

	// Decode new changes
	$new_array = json_decode($json_data, true, 512, JSON_UNESCAPED_UNICODE);
	if ($new_array === null) {
		trigger_error("JSON Error: " . json_last_error());
		trigger_error("JSON Error Message: " . json_last_error_msg());
		for ($i = 0; $i < strlen($json_data); $i++) {
			$char = $json_data[$i];
			if (ord($char) < 32 || ord($char) > 127) {
				trigger_error("Invalid character at position $i: " . ord($char));
			}
		}
	}

	// Merge changes for this URL (by path)
	$db_array['live-edits-per-url'][$normalized_path] = mergeHtmlChanges(
		$db_array['live-edits-per-url'][$normalized_path],
		$new_array
	);

	// Save back to live-edits.json
	file_put_contents($db_file, json_encode($db_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	exit;
}

// Reset global content
if (isset($_GET['reset-original']) && isset($_GET['mode']) && $_GET['mode'] == 'all' && is_user_admin_back_end()) {
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-edits.json';
	if (file_exists($db_file)) {
		$db_content = file_get_contents($db_file);
		$db_array = json_decode($db_content, true);
		if (isset($db_array['live-edits-global'])) {
			unset($db_array['live-edits-global']);
			file_put_contents($db_file, json_encode($db_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	// redirect to the same page
	$return_url = urldecode($_GET['t']);
	echo '<meta http-equiv="refresh" content="0;url=' . $return_url . '">';
}

// Reset URL-specific content
else if (isset($_GET['reset-original']) && is_user_admin_back_end()) {
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-edits.json';
	$normalized_path = normalize_live_edit_target_path($_GET['t']);

	if (file_exists($db_file)) {
		$db_content = file_get_contents($db_file);
		$db_array = json_decode($db_content, true);
		if (isset($db_array['live-edits-per-url'][$normalized_path])) {
			unset($db_array['live-edits-per-url'][$normalized_path]);
			file_put_contents($db_file, json_encode($db_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	// redirect to the same page
	$return_url = urldecode($_GET['t']);
	echo '<meta http-equiv="refresh" content="0;url=' . $return_url . '">';
}

//if is that page
if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {

	// example html for ajax request
	?><!DOCTYPE html>
	<html lang="en">

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Galaxy Content Manager - Live Editor</title>
		<meta name="description" content="Edit live page content in Galaxy Content Manager.">
	</head>

	<body>

	</body>

	</html>
	<?php
}
