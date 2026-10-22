<?php

///////////////////////////// CALL USER DATA ////////////////////////////

function call_user_data($username = false, $field = false, $today = false, $variant = false)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	if ($username == false) {
		foreach ($db['users'] as $data) {
			if (isset($data[$field][$today][$variant])) {
				return is_array($data[$field][$today][$variant]) ? implode(',', $data[$field][$today][$variant]) : (string) $data[$field][$today][$variant];
			}
		}
	} else if (isset($db['users'][$username])) {
		if ($field == false) {
			foreach ($db['users'][$username] as $data) {
				if (isset($data[$today][$variant])) {
					return is_array($data[$today][$variant]) ? implode(',', $data[$today][$variant]) : (string) $data[$today][$variant];
				}
			}
		} else if ($today == false) {
			if (!isset($db['users'][$username][$field]) || !is_array($db['users'][$username][$field])) {
				return '';
			}

			foreach ($db['users'][$username][$field] as $data) {
				if (isset($data[$variant])) {
					return is_array($data[$variant]) ? implode(',', $data[$variant]) : (string) $data[$variant];
				}
			}
		} else if ($variant == false) {
			$value = $db['users'][$username][$field][$today] ?? '';
			return is_array($value) ? implode(',', $value) : (string) $value;
		} else {
			$value = $db['users'][$username][$field][$today][$variant] ?? '';
			return is_array($value) ? implode(',', $value) : (string) $value;
		}
	}

	return '';
}

function call_user_data_in_series($username = false, $field = false, $today = false, $variant = 'category')
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$datas = [];

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	if ($username == false) {
		foreach ($db['users'] as $userData) {
			if (isset($userData[$field][$today][$variant])) {
				$datas = $userData[$field][$today][$variant];
			}
		}
	} else if (isset($db['users'][$username])) {
		if ($field == false) {
			foreach ($db['users'][$username] as $fieldData) {
				if (isset($fieldData[$today][$variant])) {
					$datas = $fieldData[$today][$variant];
				}
			}
		} else if ($today == false) {
			if (!isset($db['users'][$username][$field]) || !is_array($db['users'][$username][$field])) {
				return [];
			}

			foreach ($db['users'][$username][$field] as $todayData) {
				if (isset($todayData[$variant])) {
					$datas = $todayData[$variant];
				}
			}
		} else if (isset($db['users'][$username][$field][$today][$variant])) {
			$datas = $db['users'][$username][$field][$today][$variant];
		}
	}

	if (is_array($datas)) {
		ksort($datas);
	} else {
		$datas = (array) $datas; // convert to array if needed
		ksort($datas);
	}
	return array_filter($datas);
}

function call_custom_variants($username = false, $field = false, $today = false, $exclude_defaults = true)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$variants = [];

	if ($username == false) {
		foreach ($db['users'] as $userData) {
			if ($field && isset($userData[$field])) {
				foreach ($userData[$field] as $todayData) {
					$variants = array_merge($variants, array_keys($todayData));
				}
			} else {
				foreach ($userData as $fieldData) {
					foreach ($fieldData as $todayData) {
						$variants = array_merge($variants, array_keys($todayData));
					}
				}
			}
		}
	} else if (isset($db['users'][$username])) {
		foreach ($db['users'][$username] as $fieldData) {
			$variants = array_merge($variants, array_keys($fieldData[$today] ?? []));
		}
	}

	$variants = array_filter($variants, function ($variant) use ($exclude_defaults) {
		if ($exclude_defaults && preg_match('/^(category|location|image)-/', $variant)) {
			return false;
		}
		$excluded = ['title', 'description', 'image', 'passwd', 'level', 'sort', 'sortby', 'hidden', 'invalid'];
		return !in_array($variant, $excluded);
	});

	$variants = array_map(function ($variant) {
		return str_replace(['-', '_'], ' ', mb_ucfirst($variant));
	}, array_values($variants));

	$variants = array_unique_cross_greek_characters($variants);

	usort($variants, function ($a, $b) {
		return is_numeric($a) && is_numeric($b) ? $a - $b : strcasecmp($a, $b);
	});

	return $variants;
}

function call_user_field_from_today($today)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	if (!isset($db['users'])) {
		return false;
	}

	foreach ($db['users'] as $username => $userData) {
		foreach ($userData as $field => $fieldData) {
			if (is_array($fieldData) && isset($fieldData[$today])) {
				return $field;
			}
		}
	}

	return false;
}

function call_user_data_flat($username, $field, $today, $variant, $maxwidth = false, $more_link = false)
{
	$data = call_user_data($username, $field, $today, $variant);
	$check = strip_tags_with_whitespace($data);
	$check = str_replace('&nbsp;', '', $check);
	if ($check == null) {

		return 'User have not add any description';
	} else {

		//if no width found
		if ($maxwidth == null or $maxwidth == 0) {

			return strip_tags_with_whitespace($data);
		} else {

			$output = strip_tags_with_whitespace($data);
			$output = wordwrap($output, $maxwidth, PHP_EOL, false);
			$output = explode(PHP_EOL, $output);
			$c = count($output);

			//set dots if triable is found
			if ($c > 1) {

				if ($more_link == true) {

					return $output[0] . "... <a class=\"more\" href=\"$more_link\">" . 'View All' . "</a>";
				} else {

					return $output[0] . "...";
				}
			} else {
				return $output[0];
			}
		}
	}
}

function call_user_data_nested_x($username = false, $field = false, $today = false, $variant = false, $x = false)
{
	$a = call_user_data_in_series($username, $field, $today, $variant);
	if ($x == false) {
		$r = reset($a);
		return $r;
	} elseif (isset($a[$x])) {
		$r = $a[$x];
		return $r;
	} elseif (count($a) > 0) {
		$r = reset($a);
		return $r;
	}
	return null;
}

function call_user_data_with_space($username = false, $field = false, $today = false, $variant = false)
{
	$data = call_user_data($username, $field, $today, $variant);

	// Split the telephone number into an array of characters
	$characters = str_split($data);

	// Join the characters with a space between each one
	$data = implode(' ', $characters);

	return $data;
}

function call_user_data_html($username = false, $field = false, $today = false, $variant = false)
{
	$content = call_user_data($username, $field, $today, $variant);
	$content = nl2br($content);

	// Check if content contains HTML tags
	if (!preg_match('/<\/?[a-z][\s\S]*>/i', $content)) {
		// If no HTML tags found, format the text with paragraphs
		$content = htmlspecialchars($content); // Convert special characters to HTML entities
		$paragraphs = preg_split("/(\r\n|\n|\r){2,}/", $content); // Split content into paragraphs based on empty lines
		$content = '<p>' . implode('</p><p>', array_filter(array_map('trim', $paragraphs))) . '</p>'; // Wrap paragraphs in <p> tags
	} elseif ($content == null or trim($content) == null) {
		$content = '<p>User have not add any description</p>';
	}
	return trim($content);
}

function call_user_file($username = false, $field = false, $today = false, $variant = false)
{

	//first check for any urls before start making that fucking file
	$test = call_user_data($username, $field, $today, $variant);
	if ($test != null) {
		return $test;
	}

	if ($username == false) {
		$username = "*";
	}
	if ($field == false) {
		$field = "*";
	}
	if ($today == false) {
		$today = "*";
	}
	if ($variant == false) {
		$variant = "*";
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	$files = glob($_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/$variant.*", GLOB_BRACE);
	$files = array_merge_cross_extensions($files);

	if (array_key_exists(0, $files)) {
		$file = end($files);
		$src = str_replace($_SERVER["DOCUMENT_ROOT"], site_url_des(), $file);
		return $src;
	} else {
		return false;
	}
}

function call_user_file_in_series($username = false, $field = false, $today = false, $variant = false)
{
	//test results from call user data if there is any urls
	$test = call_user_data_in_series($username, $field, $today, $variant);
	if ($test != array()) {
		return $test;
	}

	if ($username == false) {
		$username = "*";
	}
	if ($today == false) {
		$today = "*";
	}
	if ($field == false) {
		$field = "*";
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	// Get all files in variant folder
	$files = glob($_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/$variant/*.*", GLOB_BRACE);
	$files = array_merge_cross_extensions($files);

	// Convert file paths to URLs
	$urls = array_map(function ($file) {
		$file = normalize_path($file);
		return str_replace(normalize_path($_SERVER["DOCUMENT_ROOT"]), site_url_des(), $file);
	}, $files);

	// Clean up results and reindex based on basename numbers
	$urls = array_remove_empty_values($urls);
	$urls = array_remove_duplicate_values($urls);

	$reindexed = [];
	foreach ($urls as $url) {
		$basename = basename($url);
		preg_match('/(\d+)/', $basename, $matches);
		if (isset($matches[1])) {
			$reindexed[(int) $matches[1]] = $url;
		}
	}
	ksort($reindexed);

	return $reindexed;
}

function call_user_file_nested_x($username = false, $field = false, $today = false, $variant = false, $x = false)
{
	//first checking for any urls before start making that fucking file
	$test = call_user_data_nested_x($username, $field, $today, $variant, $x);
	if ($test != null) {
		return $test;
	}

	$a = call_user_file_in_series($username, $field, $today, $variant);
	if ($x == false) {
		$r = reset($a);
	} elseif (isset($a[$x])) {
		$r = $a[$x];
	} else {
		$r = reset($a);
	}
	return $r;
}

function call_user_display_name($username)
{

	$read = call_user_data($username, 'info', false, "name");
	if ($read != null) {

		$read = trim($read);
		return mb_ucwords($read);
	} else {

		return "Anonymous user " . str_replace("ip-", "", $username);
	}
}

function call_user_display_image($username)
{

	$read = call_user_file($username, 'info', false, 'image');
	if ($read != null) {
		return $read;
	} else {
		return "/images/default-user.jpg";
	}
}

function call_user_email($username)
{
	$read = call_user_data($username, 'info', false, 'email');
	if ($read != null) {

		return $read;
	}
}

function call_user_email_for_review($username)
{
	$read = call_user_data($username, 'info', false, 'email-for-review');
	if ($read != null) {

		return $read;
	}
}

function call_username_by_email($email, $show_errors = false)
{

	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$users = [];
	foreach ($db['users'] as $username => $userData) {
		$users[] = $username;
	}
	foreach ($users as $user) {

		//check if this user has a password to avoid confusion with other system users who have the same email.
		if ($email == call_user_email($user) && str_contains($email, "@")) {

			return $user;
		}
	}

	if ($show_errors == true) {
		trigger_error('email not found');
	}
	return false;
}

function call_username_by_email_for_review($email)
{

	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$users = [];
	foreach ($db['users'] as $username => $userData) {
		$users[] = $username;
	}
	foreach ($users as $user) {

		//check if this user has a password to avoid confusion with other system users who have the same email.
		if ($email == call_user_email_for_review($user)) {

			return $user;
		}
	}
}

function call_user_locations($username)
{

	return call_user_data_in_series($username, 'info', false, 'location');
}

//old that becaome deprecated acting as fallback
function call_user_categories($username)
{

	return call_user_data_in_series($username, 'info', false, 'category');
}

function call_listing_categories($today)
{

	return call_user_data_in_series(false, 'listings', $today, 'category');
}

function call_listing_category($today)
{
	$all = call_listing_categories($today);
	return reset($all) !== false ? reset($all) : null;
}

function call_listing_locations($today)
{
	return call_user_data_in_series(false, 'listings', $today, 'location');
}

function call_listing_location($today)
{
	$all = call_listing_locations($today);
	return reset($all) !== false ? reset($all) : null;
}

function call_user_rank($username)
{

	//todo: also make an other function called call product or listing rank
	$todays = call_user_todays(false, "rankings");
	if (count($todays) == 0) {

		return 'N/A';
	} else {

		$r = 0;
		$n = 0;
		foreach ($todays as $today) {

			$to_user = call_user_data(false, 'rankings', $today, 'rank-user');
			if ($username == $to_user) {

				$rank = call_user_data(false, 'rankings', $today, 'rank-value');
				$n++;
				$r = $r + $rank;
			}
		}

		if ($n != 0) {

			return round($r / $n, 0);
		} else {
			return 'N/A';
		}
	}
}

function call_username_by_today($today)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Search through users for the given today
	foreach ($db['users'] as $username => $userData) {
		foreach ($userData as $field => $fieldData) {
			if (isset($fieldData[$today])) {
				return $username;
			}
		}
	}

	return null; // Return null if no matching today found
}

function call_username_by_today_this_user($today)
{

	//call username by today of this user - des methoed
	$usersDir = $_SERVER['DOCUMENT_ROOT'] . '/users/';
	$pattern = $usersDir . '*' . DIRECTORY_SEPARATOR . $today . DIRECTORY_SEPARATOR . '*.json';

	$matchingFiles = glob($pattern);

	if ($matchingFiles) {
		$matchingUsername = basename(dirname(dirname($matchingFiles[0])));
		return $matchingUsername;
	}

	return null;
}

function call_usernames_with_pagination_and_todays($category = false, $location = false, $all_pages = false)
{
	global $min;
	global $max;

	// Setup min and maximum
	setup_pagination_variables();

	$output = array();
	$i = 0;

	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Get all todays across all users and sort them
	$todays = [];
	foreach ($db['users'] as $username => $userData) {
		foreach ($userData as $field => $fieldData) {
			$todays = array_merge($todays, array_keys($fieldData));
		}
	}
	sort($todays); // Sort todays ascending

	// Process users by today order
	foreach ($todays as $today) {
		foreach ($db['users'] as $username => $userData) {

			// Check if user category and location match
			if ($category && $location && is_listing_category($category, $username) && is_listing_location($location, $username)) {
				$i++;
				if ($i > $min || $all_pages) {
					$output[] = $username;
				}
			} elseif (!$category && $location && is_listing_location($location, $username)) {
				$i++;
				if ($i > $min || $all_pages) {
					$output[] = $username;
				}
			} elseif ($category && !$location && is_listing_category($category, $username)) {
				$i++;
				if ($i > $min || $all_pages) {
					$output[] = $username;
				}
			} elseif (!$category && !$location) {
				$i++;
				if ($i > $min || $all_pages) {
					$output[] = $username;
				}
			}

			// Stop at max
			if ($i == $max && !$all_pages) {
				break 2;
			}
		}
	}

	return array_values(array_unique($output));
}

function call_user_todays($username = false, $field = false, $reverse = true)
{
	static $cache = [];

	$cacheKey = "$username|$field|$reverse";

	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$t = array();

	// Get todays based on wildcards
	if ($username == false) {
		foreach ($db['users'] as $userData) {
			if ($field == false) {
				foreach ($userData as $fieldData) {
					$t = array_merge($t, array_keys($fieldData));
				}
			} else if (isset($userData[$field])) {
				$t = array_merge($t, array_keys($userData[$field]));
			}
		}
	} else if (isset($db['users'][$username])) {
		if ($field == false) {
			foreach ($db['users'][$username] as $fieldData) {
				$t = array_merge($t, array_keys($fieldData));
			}
		} else if (isset($db['users'][$username][$field])) {
			$t = array_merge($t, array_keys($db['users'][$username][$field]));
		}
	}

	$t = array_unique($t);
	if ($reverse) {
		rsort($t);
	} else {
		sort($t); // Default sort in ascending order
	}

	$cache[$cacheKey] = $t;
	return $t;
}

function call_user_todays_with_pagination_and_filters($username = false, $field = false, $reverse = true, $all_pages = false)
{
	// Static cache array
	static $cache = [];

	// Generate a unique cache key based on function arguments and relevant GET parameters
	$cacheKey = md5(serialize(func_get_args()) . serialize($_GET));

	// Check if the result is already cached
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	global $min;
	global $max;

	// Setup min and maximum
	setup_pagination_variables();

	// Setup categories
	$category = CATEGORY;
	$location = LOCATION;

	// Retrieve sorting parameters from URL
	$sort = isset($_GET['sort']) ? $_GET['sort'] : 'asc';
	$sortBy = isset($_GET['sortby']) ? $_GET['sortby'] : null;

	// Remove spaces and replaces with hyphens and all lowercase
	$sort = mb_strtolower(str_replace(' ', '-', $sort));
	$sortBy = $sortBy !== null ? mb_strtolower(str_replace(' ', '-', $sortBy)) : null;

	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Fetch today's files using cache-supportive call
	$files = call_user_todays($username, $field, $reverse);

	// Setup any filter via get variables
	$variants = call_custom_variants();

	// Assume $files is an array of today's directories
	$filteredFiles = [];

	// et the internal character encoding
	mb_internal_encoding('UTF-8');

	// Create an array of GET parameters for comparison
	foreach ($_GET as $key => $value) {

		//prevent null
		if ($value == null) {
			continue;
		}

		$value = urldecode($value);
		$key = urldecode($key);

		$value = mb_strtolower(str_replace(['_', ' '], '-', $value), 'UTF-8');
		$key = mb_strtolower(str_replace(['_', ' '], '-', $key), 'UTF-8');
		$_GET_for_compare[$key] = $value;
	}

	// Filter files based on criteria
	foreach ($files as $today) {

		$meetsCriteria = true;

		foreach ($variants as $variant) {

			$variant = mb_strtolower(str_replace(' ', '-', $variant));
			if (isset($_GET_for_compare[$variant]) && $variant != 'category' && $variant != 'location') {
				$requiredValue = $_GET_for_compare[$variant];
				$actualValues = call_user_data_in_series(false, $field, $today, $variant);

				if (!is_array($actualValues)) {
					$actualValues = [$actualValues];
				}

				$meetsCriteria = false;
				foreach ($actualValues as $actualValue) {
					$actualValue = mb_strtolower(str_replace(' ', '-', $actualValue));

					if (strpos($requiredValue, ':') !== false) {
						list($minValue, $maxValue) = explode(':', $requiredValue);
						$minValue = (is_numeric($minValue) && !empty(trim($minValue))) ? $minValue : 0;
						$maxValue = (is_numeric($maxValue) && !empty(trim($maxValue))) ? $maxValue : 99999999999999999;
						if ($actualValue >= $minValue && $actualValue <= $maxValue) {
							$meetsCriteria = true;
							break;
						}
					} elseif ($requiredValue != null) {
						if ($actualValue == $requiredValue) {
							$meetsCriteria = true;
							break;
						}
					}
				}

				if (!$meetsCriteria) {
					break;
				}
			}
		}

		if ($meetsCriteria) {
			$filteredFiles[] = $today;
		}
	}

	// Sort the filtered directories if sortBy is specified
	if ($sortBy) {
		usort($filteredFiles, function ($todayA, $todayB) use ($sortBy, $sort) {
			$valueA = call_user_data(false, false, $todayA, $sortBy);
			$valueB = call_user_data(false, false, $todayB, $sortBy);

			if ($sort == 'asc') {
				return $valueA <=> $valueB;
			} else {
				return $valueB <=> $valueA;
			}
		});
	}

	// Prepare the output with pagination
	$output = [];
	$i = 0;

	foreach ($filteredFiles as $today) {
		if (call_user_data(false, $field, $today, 'hidden') == true) {
			continue;
		}

		if (
			($category == true && $location == true && is_listing_category($category, $today) && is_listing_location($location, $today)) ||
			($category == false && $location == true && is_listing_location($location, $today)) ||
			($category == true && $location == false && is_listing_category($category, $today)) ||
			($category == false && $location == false)
		) {

			if ($i >= $min) {
				$output[] = $today;
			}

			$i++;

			if ($i >= $max && !$all_pages) {
				break;
			}
		}
	}

	// Cache the result before returning
	$cache[$cacheKey] = $output;

	return $output;
}

function call_usernames_all($include_invalids = false)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$output = [];

	foreach ($db['users'] as $username => $userData) {
		$output[] = $username;
	}

	// Sort by register date high to low
	sort($output); // Default sort in ascending order

	// Remove duplicates
	$output = array_values(array_unique($output));

	return $output;
}

function call_user_tags($username)
{

	$categories = call_user_categories($username);
	$locations = call_user_locations($username);
	$page_title = call_user_display_name($username) . " - ";
	$page_tags = call_user_display_name($username) . ", ";
	$i = 0;
	$j = 0;
	$k = 0;
	foreach ($categories as $category) {
		$i++;
		if ($i <= 3) {
			$page_title .= $category . ", ";
		}
	}
	foreach ($categories as $category) {
		$j++;
		if ($i <= 9) {
			$page_tags .= $category . ", ";
		}
	}
	foreach ($locations as $location) {
		$k++;
		if ($i <= 7) {
			$page_tags .= $location . ", ";
		}
	}
	$page_title = substr($page_title, 0, -2);
	$page_tags = substr($page_tags, 0, -2);
	return $page_tags;
}

/////////////////////// WRITE USER DATA ///////////////////////

function write_user_data($username = 'global', $field = 'info', $today = '1', $variant = false, $data_to_push = 'example')
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));
	if ($variant == null or $variant == '') {

		trigger_error('Variant must be provided');
		return false;
	}

	// Initialize structure if needed
	if (!isset($db['users'][$username])) {
		$db['users'][$username] = [];
	}
	if (!isset($db['users'][$username][$field])) {
		$db['users'][$username][$field] = [];
	}

	// If today today is false then set to 1
	if ($today == false) {
		$today = '1';
	}

	// Add/update the data without conversion
	if (!isset($db['users'][$username][$field][$today])) {
		$db['users'][$username][$field][$today] = [];
	}

	$db['users'][$username][$field][$today][$variant] = $data_to_push;

	// Save back to file
	saveJsonDb($db);

	return true;
}

function write_user_data_append($username = 'global', $field = 'info', $today = '1', $variant = false, $data_to_push = 'example')
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));
	if ($variant == null or $variant == '') {

		trigger_error('Variant must be provided');
		return false;
	}

	// Initialize structure if needed
	if (!isset($db['users'][$username])) {
		$db['users'][$username] = [];
	}
	if (!isset($db['users'][$username][$field])) {
		$db['users'][$username][$field] = [];
	}

	// If today today is false then set to 1
	if ($today == false) {
		$today = '1';
	}

	// Initialize today array if needed
	if (!isset($db['users'][$username][$field][$today])) {
		$db['users'][$username][$field][$today] = [];
	}

	// Initialize variant array if needed
	if (!isset($db['users'][$username][$field][$today][$variant])) {
		$db['users'][$username][$field][$today][$variant] = [];
	}

	// Check if value already exists before adding
	if (!in_array($data_to_push, $db['users'][$username][$field][$today][$variant])) {
		$db['users'][$username][$field][$today][$variant][] = $data_to_push;
	}

	// Save back to file
	saveJsonDb($db);

	return true;
}

function write_user_file($username, $field, $today, $variant, $tmp_location = false)
{
	if (empty($username) || empty($field) || empty($today) || empty($variant)) {
		trigger_error('Field, today, and variant must be provided');
		return false;
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	if ($tmp_location == false && isset($_FILES['file']['tmp_name'])) {
		$tmp_location = $_FILES['file']['tmp_name'];
	} elseif ($tmp_location == false) {
		return false;
	}

	// Extension
	$mime_type = mime_content_type($tmp_location);
	$ext = get_extension_from_mime_type($mime_type);

	// Determine destination path
	$dest_dir = $_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/";
	create_path($dest_dir);

	// Check for existing files with different extensions
	$existing_files = glob($dest_dir . "$variant.*");
	if (!empty($existing_files)) {
		foreach ($existing_files as $existing_file) {
			unlink($existing_file);
		}
	}

	$dest_file = $dest_dir . "$variant.$ext";

	// Start writing the file
	if (move_uploaded_file($tmp_location, $dest_file) || copy($tmp_location, $dest_file) || copy_from_url($tmp_location, $dest_file)) {
		chmod($dest_file, 0777);

		if (is_image($dest_file)) {
			thumbnail_2($dest_file, 1024, $dest_file);

			if (get_extension($dest_file) == 'png' && !isPngCompressed($dest_file)) {
				png2jpg($dest_file, $dest_file, 80);
			} elseif ((get_extension($dest_file) == 'jpg' || get_extension($dest_file) == 'jpeg') && !isJpegCompressed($dest_file)) {
				jpg2jpg($dest_file, $dest_file, 80);
			}
		}

		return $today;
	}

	trigger_error('File upload failed');
	return false;
}

function write_user_file_append($username, $field, $today, $variant, $tmp_location = false, $basename = false, $no_thumbnail = false)
{
	if (empty($username) || empty($field) || empty($today) || empty($variant)) {
		trigger_error('Field, today, and variant must be provided');
		return false;
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	if ($tmp_location == false && isset($_FILES['file']['tmp_name'])) {
		$tmp_location = $_FILES['file']['tmp_name'];
	} elseif ($tmp_location == false) {
		return false;
	}

	$files = glob($_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/$variant/*.*");
	$files = array_merge_cross_extensions($files);

	$max_num = 0;
	foreach ($files as $file) {
		if (preg_match('/(\d+)(?=\.[^.]+$)/', basename($file), $matches)) {
			$file_num = (int) $matches[1];
			if ($file_num > $max_num) {
				$max_num = $file_num;
			}
		}
	}

	$next_num = $max_num + 1;
	$mime_type = mime_content_type($tmp_location);
	$ext = get_extension_from_mime_type($mime_type);

	//fix bug with null extension
	if ($ext == null && $basename != false) {
		$ext = get_extension($basename);
	} elseif ($ext == null) {
		$ext = get_extension($tmp_location);
	}

	$new_path = $_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/$variant/$next_num.$ext";

	create_path(dirname($new_path));

	if (move_uploaded_file($tmp_location, $new_path) || copy($tmp_location, $new_path) || copy_from_url($tmp_location, $new_path)) {
		chmod($new_path, 0777);

		if (is_image($new_path)) {

			// make thumbnail for images only
			if ($no_thumbnail == false) {
				thumbnail_2($new_path, 1024, $new_path);
			}

			// optimize images
			if (get_extension($new_path) == 'png' && !isPngCompressed($new_path)) {
				png2jpg($new_path, $new_path, 80);
			} elseif ((get_extension($new_path) == 'jpg' || get_extension($new_path) == 'jpeg') && !isJpegCompressed($new_path)) {
				jpg2jpg($new_path, $new_path, 80);
			}
		}

		return true;
	}

	trigger_error('File append failed');
	return false;
}

function move_user_data_up($username, $field, $today)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$candidates = call_user_todays($username, $field);
	$next_smaller_today = oneStepSmaller($today, $candidates) - rand(10, 99);

	if ($next_smaller_today == null || count($candidates) == 0) {
		return false;
	}

	// Move data to new today key
	$db['users'][$username][$field][$next_smaller_today] = $db['users'][$username][$field][$today];
	unset($db['users'][$username][$field][$today]);

	// Save changes
	saveJsonDb($db);

	return $next_smaller_today;
}

function move_user_data_down($username, $field, $today)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Format field
	$field = mb_strtolower(str_replace(" ", "-", $field));

	$candidates = call_user_todays($username, $field);
	$next_bigger_today = oneStepBigger($today, $candidates) + rand(10, 99);

	if ($next_bigger_today == null || count($candidates) == 0) {
		return false;
	}

	// Move data to new today key
	$db['users'][$username][$field][$next_bigger_today] = $db['users'][$username][$field][$today];
	unset($db['users'][$username][$field][$today]);

	// Save changes
	saveJsonDb($db);

	return $next_bigger_today;
}

function write_user_category($username, $category, $show_errors = false)
{

	return write_user_data_append($username, 'info', false, 'category', $category);
}

function write_user_location($username, $location, $show_errors = false)
{

	return write_user_data_append($username, 'info', false, 'location', $location);
}

function write_display_name($username, $new_display_name)
{

	//setup the vars
	$name = str_replace("-", " ", $new_display_name);
	$name = str_replace("_", " ", $name);
	$name = mb_ucwords($name);

	//check for minimum characters is 3 at now
	if (strlen(slugify($name)) >= 3) {

		//write user display name
		write_user_data($username, 'info', false, 'name', $name);
		return true;
	} else {

		trigger_error('user display name is to small');
		return false;
	}
}

///////////////////////// DELETE USER DATA /////////////////////////

function delete_user_data($username = false, $field = false, $today = false, $variant = false)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(' ', '-', $field));
	$variant = mb_strtolower(str_replace(' ', '-', $variant));

	foreach ($db['users'] as $u => $userData) {
		if ($username == false || $u == $username) {
			foreach ($userData as $f => $fieldData) {
				if ($field == false || $f == $field) {
					foreach ($fieldData as $t => $todayData) {
						if ($today == false || $t == $today) {
							if ($variant == false) {
								unset($db['users'][$u][$f][$t]);
							} else {
								unset($db['users'][$u][$f][$t][$variant]);
							}
						}
					}
				}
			}
		}
	}

	saveJsonDb($db);
	return true;
}

function delete_user_data_all($username = false, $field = false, $today = false, $variant = false, $data_to_pull = false)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$found = false;

	// Format field and variant
	$field = mb_strtolower(str_replace(' ', '-', $field));
	$variant = mb_strtolower(str_replace(' ', '-', $variant));

	foreach ($db['users'] as $u => $userData) {
		if ($username == false || $u == $username) {
			foreach ($userData as $f => $fieldData) {
				if ($field == false || $f == $field) {
					foreach ($fieldData as $t => $todayData) {
						if ($today == false || $t == $today) {
							foreach ($todayData as $v => $variantData) {
								if (is_array($variantData)) {
									foreach ($variantData as $k => $nestedValue) {
										if (trim($nestedValue) == trim($data_to_pull) || $data_to_pull === false) {
											unset($db['users'][$u][$f][$t][$v][$k]);
											$found = true;
										}
									}
									if (empty($db['users'][$u][$f][$t][$v])) {
										unset($db['users'][$u][$f][$t][$v]);
									}
								}
							}
						}
					}
				}
			}
		}
	}

	if ($found) {
		saveJsonDb($db);
		return true;
	}
	return false;
}

function delete_user_data_nested_x($username = false, $field = false, $today = false, $variant = false, $x = false)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	$found = false;

	// Format field and variant
	$field = mb_strtolower(str_replace(' ', '-', $field));
	$variant = mb_strtolower(str_replace(' ', '-', $variant));

	foreach ($db['users'] as $u => $userData) {
		if ($username == false || $u == $username) {
			foreach ($userData as $f => $fieldData) {
				if ($field == false || $f == $field) {
					foreach ($fieldData as $t => $todayData) {
						if ($today == false || $t == $today) {
							foreach ($todayData as $v => $variantData) {
								if (is_array($variantData)) {
									if (isset($variantData[$x])) {
										unset($db['users'][$u][$f][$t][$v][$x]);
										$found = true;
									}
									if (empty($db['users'][$u][$f][$t][$v])) {
										unset($db['users'][$u][$f][$t][$v]);
									}
								}
							}
						}
					}
				}
			}
		}
	}

	if ($found) {
		saveJsonDb($db);
		return true;
	}
	return false;
}

function delete_user_file($username = false, $field = false, $today = false, $variant = false)
{

	//first delete the url from the json if url exists
	delete_user_data($username, $field, $today, $variant);

	// Format field and variant
	$field = mb_strtolower(str_replace(' ', '-', $field));
	$variant = mb_strtolower(str_replace(' ', '-', $variant));

	//now continue deleting real file if exists
	if ($username == false) {
		$username = "*";
	}
	if ($today == false) {
		$today = "*";
	}
	if ($field == false) {
		$field = "*";
	}
	$files = array_filter(glob($_SERVER['DOCUMENT_ROOT'] . "/users/$username/$field/$today/$variant.*"), function ($file) {
		return !in_array(basename($file), ['.', '..']);
	});
	$data_found = false;
	foreach ($files as $file) {
		//delete
		if (file_exists($file)) {
			unlink($file);
			$data_found = true;
		}
	}

	if (!$data_found) {
		return false;
	}

	return true;
}

function delete_user_file_nested_x($username = false, $field = false, $today = false, $variant = false, $x = false)
{

	//first delete the url from the json if url exists
	$found = false;
	if (delete_user_data_nested_x($username, $field, $today, $variant, $x)) {

		$found = true;
	}

	// Format field and variant
	$field = mb_strtolower(str_replace(' ', '-', $field));
	$variant = mb_strtolower(str_replace(' ', '-', $variant));

	//scan categories
	if ($username == false) {
		$username = "*";
	}
	if ($today == false) {
		$today = "*";
	}
	if ($field == false) {
		$field = "*";
	}
	if ($x == false) {
		$x = "*";
	}
	$files = glob($_SERVER['DOCUMENT_ROOT'] . "/users/$username/$field/$today/$variant/$x.*");
	foreach ($files as $file) {

		//delete
		if (file_exists($file)) {
			unlink($file);
			$found = true;
		}
	}

	return $found;
}

function delete_user_file_all($username = false, $field = false, $today = false, $variant = false, $data_to_pull = false)
{
	//first delete the urls from the json if urls exists
	delete_user_data_all($username, $field, $today, $variant);

	// Format field and variant
	$field = mb_strtolower(str_replace(" ", "-", $field));
	$variant = mb_strtolower(str_replace(" ", "-", $variant));

	//scan categories
	if ($username == false) {
		$username = "*";
	}
	if ($today == false) {
		$today = "*";
	}
	if ($field == false) {
		$field = "*";
	}
	$files = glob($_SERVER['DOCUMENT_ROOT'] . "/users/$username/$field/$today/$variant/*.*");
	foreach ($files as $file) {

		$data = file_get_contents($file);
		$data = trim($data);
		$data_to_pull = trim($data_to_pull);
		if ($data == $data_to_pull or $data_to_pull == false) {

			//delete
			if (file_exists($file)) {
				unlink($file);
			}
			return true;
		}
	}

	return false;
}

function delete_user_category($username, $category)
{

	return delete_user_data_all($username, 'info', false, 'category', $category);
}

function delete_user_location($username, $location)
{

	return delete_user_data_all($username, 'info', false, 'location', $location);
}


function add_to_favorites($user_to_favorite, $current_user)
{
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/users.json';
	$db = [];
	if (file_exists($db_file)) {
		$db = json_decode(file_get_contents($db_file), true) ?: [];
	}

	if (!isset($db['users'][$current_user]['info']['1']['favorites'])) {
		$db['users'][$current_user]['info']['1']['favorites'] = [];
	}

	if (!in_array($user_to_favorite, $db['users'][$current_user]['info']['1']['favorites'])) {
		$db['users'][$current_user]['info']['1']['favorites'][] = $user_to_favorite;
	}

	saveJsonDb($db);
}

////////////////////////////////////// DB JSON CORE /////////////////////////////

$global_json_db_cache = null;

function loadJsonDb()
{

	// Check if cache is still valid
	global $global_json_db_cache;
	if ($global_json_db_cache !== null) {
		return $global_json_db_cache;
	}

	$main_db_path = $_SERVER['DOCUMENT_ROOT'] . '/users.json';

	// Load main DB with file locking to prevent race conditions
	$json_db = [];
	if (file_exists($main_db_path)) {
		$lock_file = fopen($main_db_path, 'r');
		if ($lock_file) {
			// Shared lock - multiple reads allowed, blocks writes
			flock($lock_file, LOCK_SH);
			$json_content = file_get_contents($main_db_path);
			flock($lock_file, LOCK_UN);
			fclose($lock_file);

			$json_db = json_decode($json_content, true) ?: [];
		} else {
			// Fallback if fopen fails
			$json_db = json_decode(file_get_contents($main_db_path), true) ?: [];
		}
	}

	// Update cache
	$global_json_db_cache = $json_db;

	return $json_db ?: [];
}

function saveJsonDb($db)
{

	global $global_json_db_cache;

	// Save main DB with exclusive file locking to prevent race conditions
	$main_db_path = $_SERVER['DOCUMENT_ROOT'] . '/users.json';

	// Open file for writing (create if doesn't exist)
	$lock_file = fopen($main_db_path, 'c');

	if ($lock_file) {
		// Exclusive lock - blocks all other reads and writes
		flock($lock_file, LOCK_EX);

		// Truncate file and write new content
		ftruncate($lock_file, 0);
		rewind($lock_file);
		fwrite($lock_file, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		// Release lock and close
		flock($lock_file, LOCK_UN);
		fclose($lock_file);
	} else {
		// Fallback if fopen fails (shouldn't happen, but safety first)
		file_put_contents($main_db_path, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	$global_json_db_cache = $db;
}

//////////////////////////////// STATUS FUNCTIONS /////////////////////

function check_rate_limit(callable $verify)
{
	$ip = $_SERVER['REMOTE_ADDR'];
	$file = fopen($_SERVER['DOCUMENT_ROOT'] . '/login-attempts.txt', 'c+');
	if ($file === false) {
		throw new RuntimeException('Cannot open login-attempts.txt');
	}

	try {
		// Keep the limit check, verification and failed-attempt update under one lock.
		if (!flock($file, LOCK_EX)) {
			throw new RuntimeException('Cannot lock login-attempts.txt');
		}
		$content = stream_get_contents($file);
		if ($content === false) {
			throw new RuntimeException('Cannot read login-attempts.txt');
		}
		$data = [];
		$now = time();
		foreach (explode("\n", trim($content)) as $line) {
			if ($line === '') {
				continue;
			}
			$parts = explode(' | ', trim($line));
			if (count($parts) !== 2 || !preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $parts[1])) {
				throw new RuntimeException('Invalid format in login-attempts.txt');
			}
			$address = $parts[0];
			foreach (explode(',', $parts[1]) as $timestamp) {
				if ((int) $timestamp > $now - 300) {
					$data[$address][] = (int) $timestamp;
				}
			}
		}
		
		$attempts = $data[$ip] ?? [];
		$retry_after = count($attempts) >= 5 ? min($attempts) + 300 - $now : 0;
		$valid = false;
		if ($retry_after === 0) {
			$valid = $verify();
			if (!$valid) {
				$data[$ip][] = time();
			}
		}

		$lines = [];
		foreach ($data as $address => $times) {
			$lines[] = $address . ' | ' . implode(',', $times);
		}
		$attempt_text = implode("\n", $lines);
		if ($attempt_text !== '') {
			$attempt_text .= "\n";
		}
		if (!rewind($file) || !ftruncate($file, 0) || fwrite($file, $attempt_text) !== strlen($attempt_text) || !fflush($file)) {
			throw new RuntimeException('Cannot save login-attempts.txt');
		}
	} finally {
		fclose($file);
	}

	if ($retry_after > 0) {
		http_response_code(429);
		header('Retry-After: ' . $retry_after);
		trigger_error('Πολλά αιτήματα. Προσπαθήστε ξανά σε ' . $retry_after . ' δευτερόλεπτα.', E_USER_WARNING);
		exit;
	}
	return $valid;
}

function is_user_login($username, $passwd, $show_errors = false)
{
	static $cache = [];

	$cacheKey = $username . '|' . $passwd;

	if (isset($cache[$cacheKey])) {
		if (!$cache[$cacheKey] && $show_errors) {
			trigger_error(user_exists(strtolower($username)) ? 'Password does not match' : 'Username not found');
		}
		return $cache[$cacheKey];
	}

	// Try multiple decoding levels to support various client inputs
	$passwd_candidates = [
		$passwd,
		rawurldecode($passwd),
		urldecode($passwd),
		rawurldecode(rawurldecode($passwd))
	];

	//do nothing for null no login
	if ($username == null or $passwd == null or is_user_anonymous($username)) {
		$cache[$cacheKey] = false;
		return false;
	}

	$username = strtolower($username);
	$match = check_rate_limit(function () use ($username, $passwd_candidates) {
		$getpasswd = call_user_data($username, 'info', false, 'passwd');
		if (user_exists($username) && $getpasswd != null) {
			foreach ($passwd_candidates as $candidate) {
				if ($candidate == $getpasswd && $candidate != null) {
					return true;
				}
			}
		}
		return false;
	});

	$cache[$cacheKey] = $match;
	if (!$match && $show_errors) {
		trigger_error(user_exists($username) ? 'Password does not match' : 'Username not found');
	}
	return $match;
}

function is_user_super_power($username, $passwd, $show_errors = false)
{
	static $cache = [];

	$cacheKey = $username . '|' . $passwd . '|' . ($show_errors ? '1' : '0');

	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	//όχι το platform login εδώ
	if (is_user_login($username, $passwd)) {

		$read = call_user_data($username, 'info', false, 'level');
		if ($read == 4 or $read == 'admin') {
			$cache[$cacheKey] = true;
			return true;
		} else {
			if ($show_errors == true) {
				trigger_error('user have not super power');
			}
			$cache[$cacheKey] = false;
			return false;
		}
	} else {
		if ($show_errors == true) {
			trigger_error('user is not login');
		}
		$cache[$cacheKey] = false;
		return false;
	}
}

function can_profile_write($username, $passwd, $profile_username, $show_errors = false)
{
	static $cache = [];

	$cacheKey = $username . '|' . $passwd . '|' . $profile_username . '|' . ($show_errors ? '1' : '0');
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	//check if the user is anonymous
	if (is_user_anonymous($username) && $username == $profile_username) {
		$cache[$cacheKey] = true;
		return true;
	} elseif (is_user_login($username, $passwd) && $username == $profile_username) {
		$cache[$cacheKey] = true;
		return true;
	} elseif (is_user_super_power($username, $passwd)) {
		$cache[$cacheKey] = true;
		return true;
	} else {
		if ($show_errors == true) {
			trigger_error('user can not write to this user');
		}
		$cache[$cacheKey] = false;
		return false;
	}
}

function user_exists($username, $show_errors = false)
{
	// Load db
	$db = loadJsonDb();

	if (!isset($db['users']) || $username === null) {
		if ($show_errors) {
			trigger_error('username not found');
		}
		return false;
	}

	// normalize the searched username
	$normalizedSearch = slugify(mb_strtolower($username));

	// iterate users and compare using strtolower + slugify
	foreach ($db['users'] as $storedUsername => $fields) {
		$normalizedStored = slugify(mb_strtolower($storedUsername));
		if ($normalizedSearch === $normalizedStored) {
			// ensure user has at least one non-empty field/today
			foreach ($fields as $field) {
				if (!empty($field)) {
					foreach ($field as $today) {
						if (!empty($today)) {
							return true;
						}
					}
				}
			}
			break;
		}
	}

	if ($show_errors) {
		trigger_error('username not found');
	}

	return false;
}

function is_user_category($category, $username)
{

	$user_categories = call_user_categories($username);

	//search on the user categories
	foreach ($user_categories as $user_category) {

		$user_category_parent = call_category_parent($user_category);

		//αν η κατηγορία είναι ίδια
		if ($user_category == $category && $user_category != false && $category == false) {

			return true;
		}

		//αν ψάχνω μητρική κατηγορία, και η κατηγορία του χρήστη είναι παιδί της μητρικής
		elseif ($user_category_parent == $category && $user_category != false && $user_category_parent != null) {

			return true;
		}
	}
}

function is_listing_category($category, $today)
{

	$listing_categories = call_listing_categories($today);
	foreach ($listing_categories as $listing_category) {

		$listing_category_parent = call_category_parent($listing_category);

		//αν η κατηγορία είναι ίδια
		if ($listing_category == $category && $listing_category != false && $category == true) {

			return true;
		}

		//αν ψάχνω μητρική κατηγορία, και η κατηγορία του χρήστη είναι παιδί της μητρικής
		elseif ($listing_category_parent == $category && $listing_category != false && $listing_category_parent != null) {

			return true;
		}
	}
}

function is_listing_location($location, $today)
{

	$listing_locations = call_listing_locations($today);
	foreach ($listing_locations as $listing_location) {

		$listing_location_parent = call_location_parent($listing_location);

		// If the location is the same
		if ($listing_location == $location && $listing_location != null && $location == true) {

			return true;
		}

		// If looking for parent location, and the user's location is a child of the parent location
		elseif ($listing_location_parent == $location && $listing_location != null && $listing_location_parent != null) {

			return true;
		}
	}
}

function is_user_location($location, $username)
{

	$user_locations = call_user_locations($username);

	//search on the user categories
	foreach ($user_locations as $user_location) {

		$user_location_parent = call_location_parent($user_location);

		//αν η κατηγορία είναι ίδια
		if ($user_location == $location && $user_location != null && $location == false) {

			return true;
		}

		//αν ψάχνω μητρική κατηγορία, και η κατηγορία του χρήστη είναι παιδί της μητρικής
		elseif ($user_location_parent == $location && $user_location != null && $user_location_parent != null) {

			return true;
		}
	}
}

function is_sponsor($username)
{

	$start = call_user_data($username, 'info', false, 'sponsor-start');
	$end = call_user_data($username, 'info', false, 'sponsor-end');
	if ($start != null) {

		if ($end != null) {

			$time_start = strtotime($start);
			$time_end = strtotime($end);
			$time_now = strtotime(date('Y-m-d'));
			if ($time_start >= date('Y-m-d') && $time_now <= $time_end) {

				return true;
			} else {

				return false;
			}
		} else {

			return true;
		}
	} else {

		return false;
	}
}

function is_certified($username)
{

	$start = call_user_data($username, 'info', false, 'cert-start');
	$end = call_user_data($username, 'info', false, 'cert-end');
	if ($start != null) {

		if ($end != null) {

			$time_start = strtotime($start);
			$time_end = strtotime($end);
			$time_now = strtotime(date('Y-m-d'));
			if ($time_start >= date('Y-m-d') && $time_now <= $time_end) {

				return true;
			} else {

				return false;
			}
		} else {

			return true;
		}
	} else {

		return false;
	}
}

function is_user_anonymous($username)
{
	return strpos($username, "ip-") === 0; //dont remove === please!!!!
}

////////////////////////////////// UTILS FUNCTIONS ///////////////////////////////////

function confirm_user_email($email, $code_for_check, $show_errors)
{
	$username = null;
	$valid = check_rate_limit(function () use ($email, $code_for_check, &$username) {
		$username = call_username_by_email_for_review($email);
		if (!$username) {
			return false;
		}
		$code = call_user_data($username, 'info', false, 'code');
		return trim($code) != null && $code_for_check != null && $code == $code_for_check;
	});

	if (!$valid) {
		if ($show_errors) {
			trigger_error($username ? 'Confirmation code does not match' : 'Username not found');
		}
		return false;
	}

	$email = call_user_data($username, 'info', false, 'email-for-review');
	write_user_data($username, 'info', false, 'email', $email);
	delete_user_data($username, 'info', false, 'code');
	delete_user_data($username, 'info', false, 'email-for-review');
	return true;
}

function create_user_space($username, $passwd, $level = 0, $email = null, $displayname = null, $push_email_for_review = false, $show_errors = false)
{

	if (!test_username($username, true)) {

		return false;
	}

	if (user_exists($username)) {

		trigger_error('This username allready exists');
		return false;
	}

	if ($email != null && call_username_by_email($email) == true) {

		trigger_error('This email allready exists');
		return false;
	}

	//level
	write_user_data($username, 'info', false, 'level', $level);

	//save password
	write_user_data($username, 'info', false, 'passwd', $passwd);

	//save password
	$date_and_time = date("jS F Y");
	write_user_data($username, 'info', false, 'date', $date_and_time);

	//write email on temp folder and send a code to confirm it
	if ($email == true && $push_email_for_review == true) {

		//not send the confirm email here, please use register form and handle this at your self
		write_user_data($username, 'info', false, 'email-for-review', strtolower($email));

		//confirm code
		$code = rand(1000, 9999);
		write_user_data($username, 'info', false, 'code', $code);
	} elseif ($email == true) {

		write_user_data($username, 'info', false, 'email', strtolower($email));
	}

	return true;
}

function send_passwd_to_user_email($username, $subject, $message_text, $url_with_no_params, $show_errors = false, $footer_end_text = 'Kind regards')
{
	if (!user_exists($username)) {
		if ($show_errors) {
			trigger_error('Username not found');
		}
		return false;
	}

	$passwd = call_user_data($username, 'info', false, 'passwd');
	$email = call_user_email($username);
	if ($passwd == null) {
		if ($show_errors) {
			trigger_error('Password not found');
		}
		return false;
	}

	$password_html = htmlspecialchars((string) $passwd, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$message_text = str_replace('[[passwd]]', $password_html, (string) $message_text);
	$url = false;
	if ($url_with_no_params) {
		$url = update_url_params(array('email' => $email, 'passwd' => $passwd), $url_with_no_params, true);
		$url = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$message_text = str_replace('[[login-url]]', $url, $message_text);
	}

	return system_email($email, $subject, $message_text, $password_html, $url, false, $footer_end_text);
}
/**
 * Raw-like mail wrapper using PHPMailer and stored SMTP settings.
 * - No captcha checks
 * - No template/header/footer/box rendering
 * - Supports optional raw-like headers (string or array)
 */
function mail_with_php_mailler($to, $subject, $message, $headers = null, $copy_to = false)
{
	// Fall back to the native PHP mail function when PHPMailer is unavailable.
	if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
		trigger_warning('PHPMailer class not found. Email delivery is falling back to the native PHP mail() function.');

		$native_headers = [];

		if (is_string($headers) && trim($headers) !== '') {
			$native_headers[] = trim($headers);
		} elseif (is_array($headers)) {
			$native_headers = $headers;
		}

		$native_headers_text = implode("\r\n", $native_headers);
		$decoded_message = html_entity_decode((string) $message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$is_html = $decoded_message !== strip_tags($decoded_message) || preg_match('/<[^>]+>/', $decoded_message);

		if ($is_html && stripos($native_headers_text, 'Content-Type:') === false) {
			$native_headers[] = 'MIME-Version: 1.0';
			$native_headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		if ($copy_to) {
			$native_headers[] = 'Bcc: ' . strtolower($copy_to);
		}

		$native_to = is_array($to) ? implode(', ', $to) : $to;

		return mail($native_to, (string) $subject, (string) $message, implode("\r\n", $native_headers));
	}

	// PHPMailer namespace
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);

	// Load users.json for SMTP settings
	$db = null;
	if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/users.json')) {
		$db = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json'), true);
	} else {
		$db = array();
	}

	// Sender name from smtp-name if available
	$email_name = $db && isset($db['users']['global']['info'][1]['smtp-name'])
		? $db['users']['global']['info'][1]['smtp-name']
		: call_user_data('global', 'info', false, 'name');

	$default_server_email = 'contact@' . $_SERVER['HTTP_HOST'];
	$default_from_email = isset($db['users']['global']['info'][1]['smtp-username'])
		? $db['users']['global']['info'][1]['smtp-username']
		: $default_server_email;

	$is_html = false;
	$content_type_explicit = false;
	$from_set = false;
	$reply_to_set = false;

	try {
		// SMTP configuration if credentials exist
		if (
			isset($db['users']['global']['info'][1]['smtp-host']) &&
			isset($db['users']['global']['info'][1]['smtp-username']) &&
			isset($db['users']['global']['info'][1]['smtp-password'])
		) {
			$mail->isSMTP();
			$mail->Host = $db['users']['global']['info'][1]['smtp-host'];
			$mail->SMTPAuth = true;
			$mail->Username = $db['users']['global']['info'][1]['smtp-username'];
			$mail->Password = $db['users']['global']['info'][1]['smtp-password'];
			$mail->SMTPSecure = $db['users']['global']['info'][1]['smtp-encryption'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port = $db['users']['global']['info'][1]['smtp-port'] ?? 587;
		} else {
			$mail->isMail();
		}

		// Charset and encoding for Greek
		$mail->CharSet = 'UTF-8';
		$mail->Encoding = 'base64';

		// Anti-spam measures (same as send.php)
		$mail->XMailer = '';
		$mail->Priority = 3;
		$mail->addCustomHeader('X-MSMail-Priority', 'Normal');
		$mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
		$mail->DKIM_domain = null;
		$mail->DKIM_selector = null;

		// Parse optional raw-like headers (string or array)
		$header_lines = [];
		if (is_string($headers) && trim($headers) !== '') {
			$header_lines = preg_split('/\r\n|\r|\n/', $headers);
		} elseif (is_array($headers)) {
			$header_lines = $headers;
		}

		foreach ($header_lines as $header_line) {
			if (!is_string($header_line)) {
				continue;
			}
			$header_line = trim($header_line);
			if ($header_line === '' || strpos($header_line, ':') === false) {
				continue;
			}

			list($header_name, $header_value) = explode(':', $header_line, 2);
			$header_name = trim($header_name);
			$header_value = trim($header_value);
			$header_lc = mb_strtolower($header_name);

			if ($header_value === '') {
				continue;
			}

			switch ($header_lc) {
				case 'from':
					if (preg_match('/^(.+?)\s*<([^>]+)>$/', $header_value, $m)) {
						$mail->setFrom(trim($m[2]), trim(str_replace('"', '', $m[1])));
					} else {
						$mail->setFrom(trim($header_value), $email_name);
					}
					$from_set = true;
					break;

				case 'reply-to':
					$reply_parts = explode(',', $header_value);
					foreach ($reply_parts as $reply_part) {
						$reply_part = trim($reply_part);
						if ($reply_part === '') {
							continue;
						}
						if (preg_match('/^(.+?)\s*<([^>]+)>$/', $reply_part, $m)) {
							$mail->addReplyTo(trim($m[2]), trim(str_replace('"', '', $m[1])));
						} else {
							$mail->addReplyTo($reply_part);
						}
					}
					$reply_to_set = true;
					break;

				case 'cc':
					$cc_parts = explode(',', $header_value);
					foreach ($cc_parts as $cc_part) {
						$cc_part = trim($cc_part);
						if ($cc_part !== '') {
							$mail->addCC($cc_part);
						}
					}
					break;

				case 'bcc':
					$bcc_parts = explode(',', $header_value);
					foreach ($bcc_parts as $bcc_part) {
						$bcc_part = trim($bcc_part);
						if ($bcc_part !== '') {
							$mail->addBCC($bcc_part);
						}
					}
					break;

				case 'content-type':
					$content_type_explicit = true;
					if (stripos($header_value, 'text/html') !== false) {
						$is_html = true;
					} elseif (stripos($header_value, 'text/plain') !== false) {
						$is_html = false;
					}
					if (preg_match('/charset\s*=\s*([a-zA-Z0-9\-_]+)/i', $header_value, $m)) {
						$mail->CharSet = trim($m[1]);
					}
					break;

				default:
					$mail->addCustomHeader($header_name, $header_value);
					break;
			}
		}

		if (!$from_set) {
			$mail->setFrom($default_from_email, $email_name);
		}

		// To (supports comma-separated recipients for raw-mail similarity)
		$to_parts = is_array($to) ? $to : explode(',', (string) $to);
		foreach ($to_parts as $to_part) {
			$to_part = trim((string) $to_part);
			if ($to_part !== '') {
				$mail->addAddress($to_part);
			}
		}

		// Optional copy/BCC parameter support (same style as system_email)
		if ($copy_to == true) {
			$mail->addBCC(strtolower($copy_to));
		}

		if (!$reply_to_set) {
			$mail->addReplyTo($default_from_email, $email_name);
		}

		// Auto-detect HTML when Content-Type was not explicitly provided
		if (!$content_type_explicit) {
			$message_str = (string) $message;
			$decoded_message = html_entity_decode($message_str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($decoded_message !== strip_tags($decoded_message) || preg_match('/<[^>]+>/', $decoded_message)) {
				$is_html = true;
			}
		}

		$mail->isHTML($is_html);
		$mail->Subject = (string) $subject;
		$mail->Body = (string) $message;

		if (!$is_html) {
			$mail->AltBody = (string) $message;
		} else {
			$mail->AltBody = trim(strip_tags((string) $message));
		}

		if ($mail->send()) {
			return true;
		} else {
			trigger_error($mail->ErrorInfo);
			return false;
		}
	} catch (Exception $e) {
		trigger_error($e->getMessage());
		return false;
	}
}

// Recover the old email template for backward compatibility and use mail_with_php_mailler() for delivery.
function system_email($to, $subject, $message, $box_code = false, $box_link = false, $copy_to = false, $footer_end_text = 'Kind regards')
{

	// Build HTML body (same template as before)
	$domain = preg_replace('/^www\./i', '', parse_url(site_url_des(), PHP_URL_HOST));
	$subject = mb_ucfirst($subject) . " - " . $domain;

	if ($box_code == true) {
		if ($box_link == true && $box_link != null) {
			$box = "<br><br><p><a href=\"$box_link\" style=\"display: inline-block;padding:13px;border: 2px dashed black;text-decoration: none;color:black\"><code>$box_code</code></a></p>";
		} else {
			$box = "<br><br><p><a href=\"#\" style=\"display: inline-block;padding:13px;border: 2px dashed black;text-decoration: none;color:black\"><code>$box_code</code></a></p>";
		}
	} else {
		$box = null;
	}

	// HTML header and footer
	$html_header = "<html><head><title>" . $subject . "</title></head><body>";
	$html_footer = "<br>";
	$html_footer .= "<p>";
	$html_footer .= htmlspecialchars($footer_end_text, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "<br><hr style='border:0;height:0;border-top: 1px solid rgba(0, 0, 0, 0.1); border-bottom: 1px solid rgba(255, 255, 255, 0.7)'>";
	$html_footer .= "<a target=\"_blank\" style=\"color:forestgreen\" href=\"" . site_url_des() . "/\"><strong>" . $domain . "</strong></a>";
	$html_footer .= " <strong>/</strong> ";
	$html_footer .= "<a style=\"color:darkslateblue\" href=\"" . site_url_des() . "\"><strong>" . site_url_des() . "</strong></a>";
	$html_footer .= "</p></body></html>";

	// Final HTML body
	$html = $html_header . "<i>$message</i>" . $box . $html_footer;

	return mail_with_php_mailler($to, $subject, $html, null, $copy_to);
}

function format_listing_index($category, $location = false)
{

	//csv was removed from here and goes to url-rewrites
	if ($location == true && !is_category_remote($category)) {

		$try_url = site_url_des() . "/web/search.php?category=" . $category . "&location=" . $location;
	} else {

		$try_url = site_url_des() . "/web/search.php?category=" . $category;
	}
	return $try_url;
}

function test_username($username, $show_errors = false)
{

	$allowed = array(".", "-", "_", "@"); //you can add here more value, you want to allow.
	if (ctype_alnum(str_replace($allowed, '', $username))) {

		return true;
	} else {

		if ($show_errors == true) {
			trigger_error('username is not valid');
		}
		return false;
	}
}

function test_passwords($passwd, $passwd_2, $show_errors = false)
{

	if ($passwd == null or slugify($passwd) == '-') {

		trigger_error('passwords is empty');
		return false;
	} elseif ($passwd_2 == null or slugify($passwd_2) == '-') {

		if ($show_errors == true) {
			trigger_error('passwords 2 is empty');
		}
		return false;
	} elseif (strlen($passwd) < 6) {

		if ($show_errors == true) {
			trigger_error('Password must be at least 6 characters');
		}
		return false;
	} elseif ($passwd == $passwd_2) {

		return true;
	} else {

		if ($show_errors == true) {
			trigger_error('passwords not match');
		}
		return false;
	}
}

function getUsernameFromUrl($url)
{
	// Use a regular expression to match the username in the URL
	if (preg_match('/\/users\/([^\/]+)\//', $url, $matches)) {
		return $matches[1];
	}

	return null;
}


function obfuscate_telephone($telephone)
{
	$obfuscated = '';
	for ($i = 0; $i < strlen($telephone); $i++) {
		$char = $telephone[$i];
		$obfuscated .= '&#' . ord($char) . ';';
	}
	return $obfuscated;
}

function generate_telephone_image($telephone)
{
	$image = imagecreate(200, 20);
	$backgroundColor = imagecolorallocate($image, 255, 255, 255); // white background
	$textColor = imagecolorallocate($image, 0, 0, 0); // black text
	imagefilledrectangle($image, 0, 0, 200, 20, $backgroundColor);
	imagestring($image, 5, 10, 2, $telephone, $textColor);

	$tempFile = tempnam(sys_get_temp_dir(), 'tel_');
	imagepng($image, $tempFile);
	$imageData = file_get_contents($tempFile);
	unlink($tempFile);
	imagedestroy($image);

	return 'data:image/png;base64,' . base64_encode($imageData);
}

function format_stars($n)
{

	$n = intval($n);
	$n = round($n, 0);
	$d = null;
	if ($n == 0 or $n = null) {
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 1) {
		$d .= '🌟';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 2) {
		$d .= '⭐';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 3) {
		$d .= '⭐';
		$d .= '🌟';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 4) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '☆';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 5) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '🌟';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 6) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '☆';
		$d .= '☆';
	} elseif ($n == 7) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '🌟';
		$d .= '☆';
	} elseif ($n == 8) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '☆';
	} elseif ($n == 9) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '🌟';
	} elseif ($n == 10) {
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
		$d .= '⭐';
	}
	return $d;
}

function get_max_key_from_files($files)
{
	$max_key = 0;
	foreach ($files as $file) {
		$basename = basename($file);
		if (preg_match('/-(\d+)(?=\.[^.]+$)/', $basename, $matches)) {
			$key_number = (int) $matches[1];
			if ($key_number > $max_key) {
				$max_key = $key_number;
			}
		}
	}
	return $max_key;
}

/////////////////////////////////////// USERS BUFFER /////////////////////////////

function setup_ums_buffer($html)
{

	// να μην κάνει τίποτα αμα ειναι μέσα στο admin
	if (is_iframe()) {

		return $html;
	}

	$tools_markup = '';

	//handle user links
	if (is_user_super_power(USERNAME, PASSWD) && THIS_USERNAME != 'global' && THIS_USERNAME != null) {

		//delete user
		if (user_exists(THIS_USERNAME)) {

			$url = update_url_params(array('delete-account' => true));
			$tools_markup .= "<a style=\"text-decoration:none;color:blueviolet;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">Delete this user &#128302</small></a><br>";
		}
	}

	//handle listing links
	if (can_profile_write(USERNAME, PASSWD, THIS_USERNAME) && TODAY != null) {

		if (str_contains(get_current_url(), "edit.php")) {

			//delete listing
			$url = update_url_params(array('delete-listing' => true, 'today' => TODAY));
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">Delete listing &#10060</small></a><br>";

			//view listing link
			$target = site_url_des() . "/web/view.php";
			$url = update_url_params(array('today' => TODAY), $target);
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">View listing 🔍</small></a><br>";

			//hide/show listing
			$hidden_state = call_user_data(THIS_USERNAME, 'listings', false, 'hidden');
			$action = ($hidden_state ? 'show-listing' : 'hide-listing');
			$symbol = ($hidden_state ? '👀' : '🙈');
			$url = update_url_params(array($action => true, 'today' => TODAY));
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">" . ($hidden_state ? 'Show' : 'Hide') . " listing $symbol</small></a><br>";
		} elseif (str_contains(get_current_url(), "view.php")) {

			//delete listing
			$url = update_url_params(array('delete-listing' => true, 'today' => TODAY));
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">Delete listing &#10060</small></a><br>";

			//edit listing
			$target = site_url_des() . "/web/edit.php";
			$url = update_url_params(array('today' => TODAY), $target);
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">Edit listing &#9997</small></a><br>";

			//new listing
			$url = update_url_params(array('today' => null), $target);
			$tools_markup .= "<a style=\"text-decoration:none;color:#043a7e;text-shadow: 1px 1px white;\" href=\"$url\"><small style=\"font-size:12pt\">New listing ➕</small></a><br>";
		}
	}

	if (!empty($tools_markup)) {
		$container = "<style>#floating-tools-div > a > small > img {width: 0.4em !important;height: 0.4em !important}</style>" .
			"<div style=\"background:transparent;display: inline-block;position: fixed;bottom: 0;right: 0;z-index: 9999999999;text-align: right;padding:6px\" id=\"floating-tools-div\">$tools_markup</div>";

		if (strpos($html, 'id="floating-tools-div"') !== false) {
			$html = preg_replace('/(<div[^>]*id="floating-tools-div"[^>]*>)(.*?)(<\/div>)/s', "$1$2$tools_markup$3", $html);
		} else {
			$html = str_replace('</body>', "$container</body>", $html);
		}
	}

	return $html;
}

function is_plan_expired($username)
{
	$expires = call_user_data($username, 'info', false, 'plan-expires');
	$expires_time = strtotime($expires);
	$today_time = time();
	if ($expires == '-1') {
		return false;
	} elseif ($expires_time < $today_time) {
		return true;
	}
	return false;
}

/**
 * Επιστρέφει την τιμή ενός πακέτου από το products.json με βάση το plan name.
 * 
 * @param string $plan_name Το κλειδί του πακέτου
 * @return string|null Η τιμή ή null
 */
function get_product_price_by_plan_key($plan_key)
{
	$json_path = $_SERVER['DOCUMENT_ROOT'] . "/products.json";
	if (!file_exists($json_path)) {
		return '';
	}
	$products_data = json_decode(file_get_contents($json_path), true);
	if (isset($products_data['products'][$plan_key]['price'])) {
		return (string) $products_data['products'][$plan_key]['price'];
	}
	return '';
}

/**
 * Returns the display name of a product from products.json based on the plan name.
 * 
 * @param string $plan_name The key of the package
 * @return string The name or the key if not found
 */
function get_product_name_by_plan_key($plan_key)
{
	if ($plan_key === null) {
		return $plan_key;
	}
	$json_path = $_SERVER['DOCUMENT_ROOT'] . '/products.json';
	if (!file_exists($json_path)) {
		return $plan_key;
	}
	$products_data = json_decode(file_get_contents($json_path), true);
	if (isset($products_data['products'][$plan_key]['name'])) {
		return $products_data['products'][$plan_key]['name'];
	}
	return $plan_key;
}

function calculate_and_store_invoice_amounts($username, $amount_cents, $currency)
{
	$amount_total = $amount_cents / 100;

	// Load products.json to get VAT rate
	$vat_rate = 0;
	$country = call_user_data($username, 'billing', false, 'country');
	if (empty($country)) {
		$country = 'GR'; // Default to GR
	}

	$json_path = $_SERVER['DOCUMENT_ROOT'] . '/products.json';
	if (file_exists($json_path)) {
		$data = json_decode(file_get_contents($json_path), true);
		// Look for VAT rate based on user's country
		if (isset($data['vat'][$country])) {
			$vat_rate = (float) $data['vat'][$country];
		}
	}

	// Calculate Net and VAT
	// Total = Net * (1 + Rate/100)  =>  Net = Total / (1 + Rate/100)
	if ($vat_rate > 0) {
		$net_price = $amount_total / (1 + ($vat_rate / 100));
		$vat_amount = $amount_total - $net_price;
	} else {
		$net_price = $amount_total;
		$vat_amount = 0;
	}

	// Format decimals (2 decimal places)
	$net_price = number_format($net_price, 2, '.', '');
	$vat_amount = number_format($vat_amount, 2, '.', '');
	$amount_total = number_format($amount_total, 2, '.', '');

	// Write to DB
	write_user_data($username, 'billing', false, 'pending-invoice', 'true');
	write_user_data($username, 'billing', false, 'pending-invoice-total-price', (string) $amount_total);
	write_user_data($username, 'billing', false, 'pending-invoice-net-price', (string) $net_price);
	write_user_data($username, 'billing', false, 'pending-invoice-vat-amount', (string) $vat_amount);
	write_user_data($username, 'billing', false, 'pending-invoice-vat-rate', (string) $vat_rate);
	write_user_data($username, 'billing', false, 'pending-invoice-currency', $currency);
}

///////////////////////////////////// USERS ACTIONS /////////////////////////////

// Cli run patch
if (php_sapi_name() === 'cli') {
	$_SERVER['DOCUMENT_ROOT'] = __DIR__ . "/..";
}

// load composer autoload
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
}

// load functions
include_once $_SERVER['DOCUMENT_ROOT'] . '/common/functions.php';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/common/polyfills.php')) {
	include_once $_SERVER['DOCUMENT_ROOT'] . '/common/polyfills.php';
}

// παραγωγη σελιδας
ob_start('setup_ums_buffer');

//define username id
if (!defined('USERNAME')) {

	if (isset($_GET['username']) && $_GET['username'] != null) {

		define("USERNAME", strtolower($_GET['username']));
	} elseif (isset($_POST['username']) && $_POST['username'] != null) {

		define("USERNAME", strtolower($_POST['username']));
	} elseif (isset($_COOKIE['username']) && $_COOKIE['username'] != null) {

		define("USERNAME", strtolower($_COOKIE['username']));
	} else {

		// Generate a random anonymous username
		$anonymousUsername = "ip-" . crc32($_SERVER['REMOTE_ADDR']);
		define("USERNAME", $anonymousUsername);
	}
}

//define user password
if (!defined('PASSWD')) {

	if (isset($_GET['passwd']) && $_GET['passwd'] != null) {

		define("PASSWD", strtolower($_GET['passwd']));
	} elseif (isset($_POST['passwd']) && $_POST['passwd'] != null) {

		define("PASSWD", strtolower($_POST['passwd']));
	} elseif (isset($_COOKIE['passwd']) && $_COOKIE['passwd'] != null) {

		define("PASSWD", strtolower($_COOKIE['passwd']));
	} else {

		define("PASSWD", rand(1000000, 99999999));
	}
}

// Define username of the current viewing page
if (!defined('THIS_USERNAME')) {

	// Get the current URL
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	$url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	if (isset($_GET['u']) && $_GET['u'] != null) {

		// Get the username from get parameter
		define('THIS_USERNAME', $_GET['u']);
	}

	//find this username by today avaiable only if today is on main database with call user data
	elseif (isset($_GET['today']) && $_GET['today'] != null) {

		//now find the real username of the listing
		$userme = call_username_by_today($_GET['today']);
		if ($userme == null) { //small bug
			$userme = USERNAME;
		}
		define('THIS_USERNAME', $userme);
	}
	//note: evgala to /users/ gt den xreiazete mperdeotan me tous anonimus xristes kai den xreiazete gt ta rithmizo me to rewrites ayta pleon
	else {

		// Else push the username to make shelf changes on his self
		define('THIS_USERNAME', USERNAME);
	}
}

//define version
if (!defined('UMS_VERSION')) {
	define("UMS_VERSION", date("ymd", filemtime(__FILE__)));
}

//define today of this data or set new
if (!defined('TODAY')) {

	if (isset($_GET['today']) && $_GET['today'] != null) {
		define("TODAY", $_GET['today']); //important
	} elseif (isset($_POST['today']) && $_POST['today'] != null) {
		define("TODAY", $_POST['today']); //important
	} else {
		define("TODAY", date("YmdHis")); //important
	}
}

//field
if (!defined('THIS_FIELD')) {

	if (isset($_GET['s']) && $_GET['s'] != null) {
		define("THIS_FIELD", $_GET['s']); //important
	} elseif (isset($_POST['s']) && $_POST['s'] != null) {
		define("THIS_FIELD", $_POST['s']); //important
	} elseif (call_user_field_from_today(TODAY)) {
		define("THIS_FIELD", call_user_field_from_today(TODAY)); //important
	} else {
		define("THIS_FIELD", "info"); //important
	}
}

//define current category
if (!defined('CATEGORY') && function_exists('call_current_category')) {

	define('CATEGORY', call_current_category()); // Define the CATEGORY constant
}

//define current location
if (!defined('LOCATION') && function_exists('call_current_location')) {

	define('LOCATION', call_current_location()); // Define the LOCATION constant
}

//delete account action
if (isset($_GET['delete-account']) && is_user_super_power(USERNAME, PASSWD)) {

	//todo: backup??? or not
	delete_directory($_SERVER['DOCUMENT_ROOT'] . '/users/' . THIS_USERNAME); //only for admin
	trigger_error("User " . THIS_USERNAME . " deleted");
}

//delete listing
if (isset($_GET['delete-listing']) && can_profile_write(USERNAME, PASSWD, THIS_USERNAME)) {

	//today
	if (isset($_GET['today'])) {

		$today = $_GET['today'];
		delete_user_data(THIS_USERNAME, 'listings', $today, false);
		trigger_error("Listing " . $_GET['today'] . " deleted");
	} else {
		trigger_error("No listing to delete");
	}

	//redirect
	reload_with_params(array('delete-listing' => null));
}

//show listing
if (isset($_GET['show-listing']) && can_profile_write(USERNAME, PASSWD, THIS_USERNAME)) {

	//write hidden
	write_user_data(THIS_USERNAME, 'listings', $_GET['today'], 'hidden', 0);

	//redirect
	reload_with_params(array('show-listing' => null));
}

//hide listing
if (isset($_GET['hide-listing']) && can_profile_write(USERNAME, PASSWD, THIS_USERNAME)) {

	//write hidden
	write_user_data(THIS_USERNAME, 'listings', $_GET['today'], 'hidden', 1);

	//redirect
	reload_with_params(array('hide-listing' => null));
}

//reference code if is not login
if (isset($_GET['r']) && !is_user_login(USERNAME, PASSWD)) {

	// put reference code on cookies
	setcookie('ref', $_GET['r'], time() + (86400 * 30), '/');

} elseif ((isset($_GET['r']) || isset($_COOKIE['ref'])) && is_user_login(USERNAME, PASSWD)) {

	$code = isset($_GET['r']) ? $_GET['r'] : $_COOKIE['ref'];

	$ref_file = $_SERVER['DOCUMENT_ROOT'] . '/common/references.json';
	if (file_exists($ref_file)) {
		$refs = json_decode(file_get_contents($ref_file), true);
		$email = array_search($code, $refs);

		if ($email) {
			write_user_data(THIS_USERNAME, 'info', false, 'reference-user', $email);
		}
	}
}

/////////////////////////////// CATEGORIES LOCATIONS /////////////////////////////

function call_categories()
{
	static $categories_cache = null;

	if ($categories_cache !== null) {
		return $categories_cache;
	}

	$db = loadCategoriesDb();
	if (is_array($db) && isset($db['categories'])) {
		$categories = array_keys($db['categories']);

		// Exclude "Menu" and "Χωρίς κατηγορία"..
		$exclude_categories = array('Menu', 'Χωρίς κατηγορία', 'Uncategorized');
		$categories = array_diff($categories, $exclude_categories);
		$categories_cache = $categories;
		return $categories;
	} else {
		$categories_cache = array();
		return array();
	}
}

function call_category_childs($category)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache) && array_key_exists($category, $categories_cache)) {
		return isset($categories_cache[$category]['subcategories']) ? array_keys($categories_cache[$category]['subcategories']) : array();
	} else {
		return array();
	}
}

function call_category_parent($subcategory)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache)) {
		foreach ($categories_cache as $mainCategory => $data) {
			if (isset($data['subcategories']) && array_key_exists($subcategory, $data['subcategories'])) {
				return $mainCategory;
			}
		}
	}
	return null;
}

function call_category_image($category)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	return isset($categories_cache[$category]['image']) ? $categories_cache[$category]['image'] : null;
}

function call_category_description($category)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (isset($categories_cache[$category]['description'])) {
		return $categories_cache[$category]['description'];
	} else {
		return call_category_child_description($category);
	}
}

function call_category_child_description($category, $location = false)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache)) {
		foreach ($categories_cache as $main_category => $data) {
			if (isset($data['subcategories'][$category])) {
				return $data['subcategories'][$category]['description'];
			}
		}
	}
	return null;
}

function is_category_remote($category, $location = false)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache)) {
		foreach ($categories_cache as $main_category => $data) {
			if (isset($data['subcategories'][$category])) {
				return $data['subcategories'][$category]['remote_status'];
			}
		}
	}
	return false;
}

function is_category_hidden($category, $location = false)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache)) {
		foreach ($categories_cache as $main_category => $data) {
			if (isset($data['subcategories'][$category])) {
				return isset($data['subcategories'][$category]['hidden_status']) ?
					$data['subcategories'][$category]['hidden_status'] : false;
			}
		}
	}
	return false;
}

function call_category_keywords($category)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (isset($categories_cache[$category]['keywords'])) {
		return $categories_cache[$category]['keywords'];
	} else {
		return call_category_child_keywords($category);
	}
}

function call_category_child_keywords($category, $location = false)
{
	static $categories_cache = null;

	if ($categories_cache == null) {
		$db = loadCategoriesDb();
		$categories_cache = isset($db['categories']) ? $db['categories'] : array();
	}

	if (is_array($categories_cache)) {
		foreach ($categories_cache as $main_category => $data) {
			if (isset($data['subcategories'][$category])) {
				return $data['subcategories'][$category]['keywords'];
			}
		}
	}
	return null;
}

function call_locations()
{
	static $locations_cache = null;

	if ($locations_cache == null) {
		$db = loadLocationsDb();
		$locations_cache = isset($db['locations']) ? $db['locations'] : array();
	}

	if (is_array($locations_cache)) {
		return array_keys($locations_cache);
	} else {
		return array();
	}
}

function call_location_childs($location)
{
	static $locations_cache = null;

	if ($locations_cache == null) {
		$db = loadLocationsDb();
		$locations_cache = isset($db['locations']) ? $db['locations'] : array();
	}

	if (is_array($locations_cache) && array_key_exists($location, $locations_cache)) {
		return $locations_cache[$location];
	} else {
		return array();
	}
}

function call_location_parent($location)
{
	static $locations_cache = null;

	if ($locations_cache == null) {
		$db = loadLocationsDb();
		$locations_cache = isset($db['locations']) ? $db['locations'] : array();
	}

	if (is_array($locations_cache)) {
		foreach ($locations_cache as $mainLocation => $subLocations) {
			if (in_array($location, $subLocations)) {
				return $mainLocation;
			}
		}
	}
	return false;
}

function loadCategoriesDb()
{
	static $categories_db_cache = null;

	if ($categories_db_cache !== null) {
		return $categories_db_cache;
	}

	$path = $_SERVER['DOCUMENT_ROOT'] . '/categories.json';
	$data = [];
	if (file_exists($path)) {
		$data = json_decode(file_get_contents($path), true) ?: [];
	}

	$categories_db_cache = $data;
	return $data;
}

function loadLocationsDb()
{
	static $locations_db_cache = null;

	if ($locations_db_cache !== null) {
		return $locations_db_cache;
	}

	$path = $_SERVER['DOCUMENT_ROOT'] . '/locations.json';
	$data = [];
	if (file_exists($path)) {
		$data = json_decode(file_get_contents($path), true) ?: [];
	}

	$locations_db_cache = $data;
	return $data;
}

function call_current_category()
{

	//now we use only category from get variable from url rewrites
	if (isset($_GET['category'])) {

		$_GET['category'] = stripslashes($_GET['category']);
		return $_GET['category'];
	}

	//if category is not found then call the category from the file
	else {
		return false;
	}
}

function call_current_location()
{

	if (isset($_GET['location'])) {

		$_GET['location'] = stripslashes($_GET['location']);
		return $_GET['location'];
	}

	//if location is not found then call the location from the file
	else {
		return false;
	}
}

// Here clean all anonymous/invalid users once every 24 hours
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/users-clean.txt') || (time() - (int) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users-clean.txt')) >= 86400) {
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/users-clean.txt', time());

	// Clean anonymous/invalid users using the loaded JSON DB instead of external username call
	$db = loadJsonDb();

	// If users key missing or not an array, nothing to do
	if (!isset($db['users']) || !is_array($db['users'])) {
	} else {

		// Collect usernames from the DB keys
		$usernames = array_keys($db['users']);

		// We'll collect users to delete to avoid modifying array during iteration
		$toDelete = [];

		foreach ($usernames as $username) {

			// If user is anonymous, mark for deletion
			if (is_user_anonymous($username)) {
				$toDelete[] = $username;
			}
		}

		// Perform deletions
		foreach ($toDelete as $username) {
			// Delete user from the database if present
			if (isset($db['users'][$username])) {
				unset($db['users'][$username]);
			}

			// Delete user from file system
			$userDir = $_SERVER['DOCUMENT_ROOT'] . '/users/' . $username;
			if (is_dir($userDir)) {
				delete_directory($userDir);
			}
		}

		// Save the updated database back to storage
		saveJsonDb($db);
	}
}
