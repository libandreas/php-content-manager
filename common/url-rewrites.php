<?php

function write_new_url_on_csv($first_url, $second_url)
{
	static $csv_previous_data = null;

	// Decode URL parameters
	$first_url = urldecode($first_url);
	$second_url = urldecode($second_url);

	// Load the CSV data only once
	$csv_file_path = dirname(dirname(__FILE__)) . '/urls-rewrites.csv';
	if ($csv_previous_data === null) {

		// Check if the CSV file exists
		if (file_exists($csv_file_path)) {
			$csv_previous_data = file_get_contents($csv_file_path);
		} else {
			$csv_previous_data = '';
		}
	}

	$line = $first_url . ", " . $second_url;

	// Check if the line already exists in the CSV
	if (!str_contains($csv_previous_data, $line)) {
		// Check if the last character is not a newline
		if (substr($csv_previous_data, -1) !== PHP_EOL) {
			file_put_contents($csv_file_path, PHP_EOL, FILE_APPEND);
			$csv_previous_data .= PHP_EOL;
		}

		file_put_contents($csv_file_path, $line, FILE_APPEND);
		// Update the cached data
		$csv_previous_data .= $line;
	}
}

function delete_url_from_csv_matching_id($second_url)
{
	// Decode URL parameter
	$second_url = urldecode($second_url);

	// Define the CSV file path
	$csv_file_path = dirname(dirname(__FILE__)) . '/urls-rewrites.csv';

	// Check if the CSV file exists
	if (!file_exists($csv_file_path)) {
		return; // File does not exist, nothing to comment out
	}

	// Read the CSV file contents
	$csv_data = file($csv_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	// Extract the ID from the URL if present
	$parsed_url = parse_url($second_url);
	parse_str($parsed_url['query'] ?? '', $query_params);
	$post_id = $query_params['page_id'] ?? $query_params['p'] ?? null;

	// Comment out the line that contains the real_url or the ID
	$updated_csv_data = array_map(function ($line) use ($second_url, $post_id) {
		if ((str_contains($line, ", " . $second_url) || ($post_id && (str_contains($line, "page_id=$post_id") || str_contains($line, "p=$post_id")))) && !str_starts_with($line, '#')) {
			return '# ' . $line; // Comment this line
		}
		return $line;
	}, $csv_data);

	// Write the updated contents back to the CSV file
	file_put_contents($csv_file_path, implode(PHP_EOL, $updated_csv_data) . PHP_EOL);
}

function recover_url_from_csv_matching_id($second_url)
{
	// Decode URL parameter
	$second_url = urldecode($second_url);

	// Define the CSV file path
	$csv_file_path = dirname(dirname(__FILE__)) . '/urls-rewrites.csv';

	// Check if the CSV file exists
	if (!file_exists($csv_file_path)) {
		return; // File does not exist, nothing to recover
	}

	// Read the CSV file contents
	$csv_data = file($csv_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	// Extract the ID from the URL if present
	$parsed_url = parse_url($second_url);
	parse_str($parsed_url['query'] ?? '', $query_params);
	$post_id = $query_params['page_id'] ?? $query_params['p'] ?? null;

	// Uncomment the line that contains the real_url or the ID
	$updated_csv_data = array_map(function ($line) use ($second_url, $post_id) {
		if (preg_match('/^# ?/', $line) && (str_contains($line, ", " . $second_url) || ($post_id && (str_contains($line, "page_id=$post_id") || str_contains($line, "p=$post_id"))))) {
			return ltrim($line, '# ');
		}
		return $line;
	}, $csv_data);

	// Write the updated contents back to the CSV file
	file_put_contents($csv_file_path, implode(PHP_EOL, $updated_csv_data) . PHP_EOL);
}

function doesUrlMatchPattern_2($patternUrl, $testUrl)
{
	if (strpos($patternUrl, '*') === false) {
		return false;
	}

	$escapedPattern = preg_quote($patternUrl, '/');

	$regexPattern = str_replace('\*', '.*', $escapedPattern);

	if (preg_match('/^' . $regexPattern . '$/', $testUrl)) {
		return true;
	} else {
		return false;
	}
}

function replaceUrlWithPattern_2($patternUrl, $replacementUrl, $testUrl)
{
	$regexPattern = str_replace(['\*', '\.'], ['([^/]*)', '\.'], preg_quote($patternUrl, '#'));

	if (substr($patternUrl, -1) === '/') {
		$regexPattern = rtrim($regexPattern, '\\/') . '\\/?';
	}

	if (preg_match('#^' . $regexPattern . '$#', $testUrl, $matches)) {
		array_shift($matches);

		foreach ($matches as $index => $match) {
			$replacementUrl = preg_replace('#\*#', $match, $replacementUrl, 1);
		}

		return $replacementUrl;
	} else {
		return $testUrl;
	}
}

function normalize_url_for_rewrite($url)
{
	if (!isset($_SERVER['HTTP_HOST'])) {
		$_SERVER['HTTP_HOST'] = null;
	}
	$url = preg_replace('(^https?://(www\.)?)', '', $url, 1);
	$url = str_replace("localhost:8000/", $_SERVER['HTTP_HOST'] . "/", $url);
	$url = preg_replace('/\/index\.(html|php)(?=\?|$)/', '/', $url);
	$url = urldecode($url);
	$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . "://" . $url;
	return $url;
}

function normalize_url_for_rewrite_spe($url)
{
	if (!isset($_SERVER['HTTP_HOST'])) {
		$_SERVER['HTTP_HOST'] = null;
	}
	$url = preg_replace('(^https?://(www\.)?)', '', $url, 1);
	$url = preg_replace('/\/index\.(html|php)(?=\?|$)/', '/', $url);
	$url = urldecode($url);
	$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . "://" . $url;
	return $url;
}

function get_rewrites_maps_static($target_file)
{
	static $maps_cache = null;

	$empty_maps = [
		'second_to_first' => [],
		'first_to_second' => []
	];

	if ($maps_cache !== null) {
		return $maps_cache;
	}

	if (!file_exists($target_file)) {
		$maps_cache = $empty_maps;
		return $maps_cache;
	}

	$csv_content = file_get_contents($target_file);
	if ($csv_content === false || trim($csv_content) === '') {
		$maps_cache = $empty_maps;
		return $maps_cache;
	}

	$scheme = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http');
	$host = $_SERVER['HTTP_HOST'] ?? '';

	// One-shot normalization on the complete CSV text
	$csv_content = urldecode($csv_content);
	$csv_content = preg_replace("/\r\n?|\n/", "\n", $csv_content);
	$csv_content = preg_replace('/^[ \t]*#.*$/m', '', $csv_content); // remove commented rows
	$csv_content = preg_replace('~https?://(?:www\.)?~i', '', $csv_content);
	if ($host !== '') {
		$csv_content = str_replace('localhost:8000/', $host . '/', $csv_content);
	}
	$csv_content = preg_replace('/\/index\.(html|php)(?=(\?|\s*,|\s*$))/i', '/', $csv_content);

	$rows = [];
	preg_match_all('/^\s*([^,\n]+?)\s*,\s*([^\n]+?)\s*$/m', $csv_content, $rows, PREG_SET_ORDER);
	if (empty($rows)) {
		$maps_cache = $empty_maps;
		return $maps_cache;
	}

	$rows = array_reverse($rows); // keep latest entries first

	$maps_cache = array_reduce($rows, function ($carry, $row) use ($scheme) {
		$first_url = trim($row[1] ?? '');
		$second_url = trim($row[2] ?? '');

		if ($first_url === '' || $second_url === '') {
			return $carry;
		}

		$first_url = $scheme . '://' . $first_url;
		$second_url = $scheme . '://' . $second_url;

		if (!isset($carry['second_to_first'][$second_url])) {
			$carry['second_to_first'][$second_url] = $first_url;
		}

		if (!isset($carry['first_to_second'][$first_url])) {
			$carry['first_to_second'][$first_url] = $second_url;
		}

		return $carry;
	}, $empty_maps);

	return $maps_cache;
}

function call_first_url_from_csv($url)
{

	// Fallback if file not exists
	$target_file = $_SERVER['DOCUMENT_ROOT'] . "/urls-rewrites.csv";
	if (!file_exists($target_file)) {

		return false;
	}

	// Inline normalize input url (avoid function call in hot path)
	if (!isset($_SERVER['HTTP_HOST'])) {
		$_SERVER['HTTP_HOST'] = null;
	}
	$url = preg_replace('(^https?://(www\.)?)', '', $url, 1);
	$url = str_replace("localhost:8000/", $_SERVER['HTTP_HOST'] . "/", $url);
	$url = preg_replace('/\/index\.(html|php)(?=\?|$)/', '/', $url);
	$url = urldecode($url);
	$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . "://" . $url;

	$maps = get_rewrites_maps_static($target_file);
	return $maps['second_to_first'][$url] ?? false;
}

function call_second_url_from_csv($url)
{

	// Fallback if file not exists
	$target_file = $_SERVER['DOCUMENT_ROOT'] . "/urls-rewrites.csv";
	if (!file_exists($target_file)) {

		return false;
	}

	// Inline normalize input url (avoid function call in hot path)
	if (!isset($_SERVER['HTTP_HOST'])) {
		$_SERVER['HTTP_HOST'] = null;
	}
	$url = preg_replace('(^https?://(www\.)?)', '', $url, 1);
	$url = str_replace("localhost:8000/", $_SERVER['HTTP_HOST'] . "/", $url);
	$url = preg_replace('/\/index\.(html|php)(?=\?|$)/', '/', $url);
	$url = urldecode($url);
	$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . "://" . $url;

	// Patch browser url with with ; in case of , for escaping comma inside csv
	$url = str_replace(',', ';', $url);

	$maps = get_rewrites_maps_static($target_file);
	return $maps['first_to_second'][$url] ?? false;
}

function replace_rewrites_urls_in_html($html)
{
	// Fallback if file not exists
	$target_file = $_SERVER['DOCUMENT_ROOT'] . "/urls-rewrites.csv";
	if (!file_exists($target_file)) {
		return $html;
	}

	$maps = get_rewrites_maps_static($target_file);
	$url_map = $maps['second_to_first'];
	if (empty($url_map)) {
		return $html;
	}

	// First crawl all links and make them absolute
	if (function_exists('rel2abs_in_html')) {
		$base = get_current_url_no_query();
		//$html = rel2abs_in_html($html, $base);
	}

	// Split HTML to exclude floating-tools-div from replacement
	$parts = preg_split('/(<div[^>]*id=["\']floating-tools-div["\'][^>]*>.*?<\/div>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

	// Process each part
	$normalized_url_cache = [];
	foreach ($parts as $index => $part) {
		// Skip the floating-tools-div using pattern matching
		if (preg_match('/id=["\']floating-tools-div["\']/i', $part)) {
			continue;
		}

		// Replace URLs using a single callback
		$parts[$index] = preg_replace_callback(
			'/href=["\']([^"\']+)["\']/i',
			function ($matches) use ($url_map, &$normalized_url_cache) {
				$original_url = $matches[1];
				if (isset($normalized_url_cache[$original_url])) {
					$url = $normalized_url_cache[$original_url];
				} else {
					if (!isset($_SERVER['HTTP_HOST'])) {
						$_SERVER['HTTP_HOST'] = null;
					}
					$url = preg_replace('(^https?://(www\.)?)', '', $original_url, 1);
					$url = str_replace("localhost:8000/", $_SERVER['HTTP_HOST'] . "/", $url);
					$url = preg_replace('/\/index\.(html|php)(?=\?|$)/', '/', $url);
					$url = urldecode($url);
					$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . "://" . $url;
					$normalized_url_cache[$original_url] = $url;
				}

				// First try exact match (fastest - O(1) lookup)
				if (isset($url_map[$url])) {
					return 'href="' . $url_map[$url] . '"';
				}

				// No match found, return original
				return $matches[0];
			},
			$part
		);
	}

	// Reassemble the HTML
	$html = implode('', $parts);

	return $html;
}

function setup_rewrites_buffer($html)
{

	if ($html === null || $html === '') {
		return $html; // Return early if $html is null or empty
	}

	// Replace all original urls in html with rewritten ones
	$html = replace_rewrites_urls_in_html($html);
	return $html;
}

// Sync the file only if it's the main file being executed directly in CLI
require_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	require_once __DIR__ . '/polyfills.php';
}

// get the current url
$current_url = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
$current_url .= '://' . ($_SERVER['HTTP_HOST'] ?? '');
$current_url .= $_SERVER['REQUEST_URI'] ?? '';
$current_url = normalize_url_for_rewrite($current_url); //new

// setup the rewrites inside the html file
ob_start('setup_rewrites_buffer');

// Convert current URL to file path by replacing host with server root
$parsed_url = parse_url($current_url);
$url_path = $parsed_url['path'] ?? '/';
$target_file_path = $_SERVER['DOCUMENT_ROOT'] . $url_path;

// If it's a directory, check for index.php or index.html
if (is_dir($target_file_path)) {
	if (file_exists($target_file_path . '/index.php')) {
		$target_file_path .= '/index.php';
	} elseif (file_exists($target_file_path . '/index.html')) {
		$target_file_path .= '/index.html';
	} else {
		$target_file_path .= '/index.php'; // Default to index.php if neither exists
	}
}

// check if target file not exists before starting to call the second url
// Exclude favicon.ico from rewrite logic
if ($url_path !== '/favicon.ico' && !file_exists($target_file_path)) {

	// call the second url
	$real_url = call_second_url_from_csv($current_url);
	$new_url_loaded_successfully = ($real_url != false);

	// rewrite the url
	if ($real_url != false) {

		// setup server variables listening for the new url
		setup_environment_for_an_other_url($real_url, $real_url);

		// include the file and then exit
		include_once($_SERVER['SCRIPT_FILENAME']);
		exit();
	}
}