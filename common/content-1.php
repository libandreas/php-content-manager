<?php

//παραγωγη σελιδας μεσω buffer
function setup_cms1_buffer($html)
{

	// βάζει το link για να επιστρέψει στην προηγούμενη σελίδα
	if (str_contains(get_current_url(), "/" . basename(__FILE__)) && !is_iframe()) {

		//pull back
		$url = urldecode($_GET['t']);
		$html = attach_admin_link($url, "Return", "Blue", $html, "↩️");
	}

	// να μην κάνει τίποτα αμα ειναι μέσα στο admin
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	//attach links
	if (is_user_admin_back_end() && !is_inside_any_admin()) {

		$target = path2url(__FILE__);
		$today = isset($_GET['today']) ? $_GET['today'] : "1"; //if today is not set, default to 1
		$current_url = urlencode(get_current_url());
		$u = isset($_GET['u']) ? $_GET['u'] : "global";
		$s = isset($_GET['s']) ? $_GET['s'] : "info";
		$url = update_url_params(array('t' => $current_url, 'u' => $u, 's' => $s, 'today' => $today), $target);
		$html = attach_admin_link($url, "Edit DB", "black", $html, "&#128302");
	}
	return $html;
}

/**
 * Strips any PHP code from the given HTML content.
 *
 * @param string $html The HTML content that may contain PHP code.
 * @return string The HTML content with PHP code removed.
 */
function strip_php_code_from_html($html)
{
	// Regular expression to match PHP tags
	$pattern = '/<\?php.*?\?>/s';

	// Replace PHP tags with an empty string
	$clean_html = preg_replace($pattern, '', $html);

	return $clean_html;
}

/**
 * Detect if a value contains HTML markup and should use Quill editor.
 *
 * @param mixed $value
 * @return bool
 */
function value_contains_html_markup_for_editor_1($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return false;
	}

	$decoded_value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	if ($decoded_value !== strip_tags($decoded_value)) {
		return true;
	}

	return preg_match('/<[^>]+>/', $decoded_value) === 1;
}

//run the buffer on output
ob_start('setup_cms1_buffer');

// load functions
include_once __DIR__ . '/functions.php'; //new for wordpress config
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && !is_user_admin_back_end()) {
	header('Cache-Control: no-store');
	$returnParams = $_GET;
	unset($returnParams['username'], $returnParams['passwd'], $returnParams['login'], $returnParams['logout'], $returnParams['delete-option']);
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


//αποθηκευση στη βαση δεδομενων
if (isset($_POST["save-options"]) && is_user_admin_back_end()) {
	$exclude_variables = array('save-options', 'duplicate-object', 'new-variant-name', 'new-variant-value');

	// Load JSON database
	$db = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json'), true);
	if (!is_array($db)) {
		$db = ['users' => []];
	}

	// Initialize structure if needed
	if (!isset($db['users'][$_GET['u']])) {
		$db['users'][$_GET['u']] = [];
	}
	if (!isset($db['users'][$_GET['u']][$_GET['s']])) {
		$db['users'][$_GET['u']][$_GET['s']] = [];
	}
	if (!isset($db['users'][$_GET['u']][$_GET['s']][$_GET['today']])) {
		$db['users'][$_GET['u']][$_GET['s']][$_GET['today']] = [];
	}

	//save new data
	if ($_POST["new-variant-name"] != null or trim($_POST["new-variant-name"]) != null && $_POST["new-variant-value"] != null or trim($_POST["new-variant-value"]) != null) {
		$name = str_replace("_", "-", $_POST["new-variant-name"]);
		$name = slugify($name);
		$value = trim($_POST["new-variant-value"]);
		$db['users'][$_GET['u']][$_GET['s']][$_GET['today']][$name] = $value;
	}

	//εδώ γράφει και τα υπόλοιπα
	foreach ($_POST as $key => $value) {
		//αν ειναι στην κατηγορία που δεν πρέπει
		if (in_array($key, $exclude_variables)) {
			continue;
		}

		if (isset($key)) {
			$db['users'][$_GET['u']][$_GET['s']][$_GET['today']][$key] = trim($value);
		}
	}

	// Save back to file
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json', json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

	// Prevent Form Resubmission via PRG Pattern
	reload_with_params();
}

//διαγραφή από βαση δεδομενων
if (isset($_GET["delete-option"]) && is_user_admin_back_end()) {
	$db = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json'), true);

	if (isset($db['users'][$_GET['u']][$_GET['s']][$_GET['today']][$_GET["variant"]])) {
		unset($db['users'][$_GET['u']][$_GET['s']][$_GET['today']][$_GET["variant"]]);
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json', json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	reload_with_params(array('delete-option' => false, 'variant' => false));
}

// upload image (former upload.php)
if (isset($_GET['upload-image']) && isset($_GET['variant']) && is_user_admin_back_end()) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-1.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body"><?php

	// load static file
	$username = $_GET['u'];
	$field = $_GET['s'];
	$today = $_GET['today'];
	$variant = $_GET['variant'];

	// Construct file paths
	$baseDir = $_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/";
	$pattern = "$baseDir$variant.*";

	// Handle image removal
	if (isset($_GET['remove'])) {
		$existingFiles = glob($pattern);
		if (!empty($existingFiles)) {
			foreach ($existingFiles as $file) {
				@unlink($file);
			}
		}
		reload_with_params(array('remove' => false), false, false, false, true);
	}

	// Handle file upload
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
		// Validate image type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $_FILES['fileToUpload']['tmp_name']);
		finfo_close($finfo);

		echo '<code>';

		if (strpos($mime, 'image/') === 0) {
			// Create directory if needed
			if (!is_dir($baseDir)) {
				@mkdir($baseDir, 0755, true);
			}

			// Remove existing files with same name pattern
			$existingFiles = glob($pattern);
			if (!empty($existingFiles)) {
				foreach ($existingFiles as $file) {
					@unlink($file);
				}
			}

			// Get file extension and save
			$ext = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);
			$targetFile = "$baseDir$variant.$ext";

			if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
				@chmod($targetFile, 0644);
			}
		}

		echo '</code>';
	}

	// Get current file URL
	$file = null;
	$existingFiles = glob($pattern);
	if (!empty($existingFiles)) {
		$file = site_url_des() . str_replace($_SERVER["DOCUMENT_ROOT"], '', $existingFiles[0]);
	}

	// Convert relative to absolute path if needed
	if (isset($file) && $file != null && strpos($file, ",") !== false) {
		$file = null;
	}

	// Keep as-is; uploader preview source is derived from filesystem path only.

	// Delete URL
	$delete_url = update_url_params(array('remove' => 'yes', 'variant' => $variant));

	// Display image if exists
	if (!isset($_GET['remove']) && $file != null && is_image($file)) {
		echo "<a target='_custom' class=\"uploader-preview-link\" href=\"$file\"><img class=\"uploader-preview-media\" src=\"$file\"></a>";
		echo "<a href=\"$delete_url\" class=\"uploader-delete-btn\">&#10005;</a>";
	}

	// Upload form
	echo '<label class="uploader-label">Drag and drop or upload image here.</label>';
	echo "<form id=\"form_$variant\" class=\"uploader-form\" method=\"post\" enctype=\"multipart/form-data\">";
	echo "<input id=\"upload_$variant\" class=\"uploader-file-input\" type=\"file\" name=\"fileToUpload\" />";
	echo '</form>';
	?>
	</body>

	</html> <?php
	exit();
}

// upload video
if (isset($_GET['upload-video']) && isset($_GET['variant']) && is_user_admin_back_end()) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-1.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body"><?php

	// load static file
	$username = $_GET['u'];
	$field = $_GET['s'];
	$today = $_GET['today'];
	$variant = $_GET['variant'];

	// Construct file paths
	$baseDir = $_SERVER["DOCUMENT_ROOT"] . "/users/$username/$field/$today/";
	$pattern = "$baseDir$variant.*";

	// Handle video removal
	if (isset($_GET['remove'])) {
		$existingFiles = glob($pattern);
		if (!empty($existingFiles)) {
			foreach ($existingFiles as $file) {
				@unlink($file);
			}
		}
		reload_with_params(array('remove' => false), false, false, false, true);
	}

	// Handle file upload
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
		// Validate video type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $_FILES['fileToUpload']['tmp_name']);
		finfo_close($finfo);

		echo '<code>';

		if (strpos($mime, 'video/') === 0) {
			// Create directory if needed
			if (!is_dir($baseDir)) {
				@mkdir($baseDir, 0755, true);
			}

			// Remove existing files with same name pattern
			$existingFiles = glob($pattern);
			if (!empty($existingFiles)) {
				foreach ($existingFiles as $file) {
					@unlink($file);
				}
			}

			// Get file extension and save
			$ext = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);
			$targetFile = "$baseDir$variant.$ext";

			if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
				@chmod($targetFile, 0644);
			}
		}

		echo '</code>';
	}

	// Get current file URL
	$file = null;
	$existingFiles = glob($pattern);
	if (!empty($existingFiles)) {
		$file = site_url_des() . str_replace($_SERVER["DOCUMENT_ROOT"], '', $existingFiles[0]);
	}

	// Keep as-is; uploader preview source is derived from filesystem path only.

	// Delete URL
	$delete_url = update_url_params(array('remove' => 'yes', 'variant' => $variant));

	// Display video if exists
	if (!isset($_GET['remove']) && $file != null && is_video($file)) {
		echo "<video controls class=\"uploader-preview-media\"><source src=\"$file\" type=\"video/mp4\"></video>";
		echo "<a href=\"$delete_url\" class=\"uploader-delete-btn\">&#10005;</a>";
	}

	// Upload form
	echo '<label class="uploader-label">Drag and drop or upload video here.</label>';
	echo "<form id=\"form_$variant\" class=\"uploader-form\" method=\"post\" enctype=\"multipart/form-data\">";
	echo "<input id=\"upload_$variant\" class=\"uploader-file-input\" type=\"file\" name=\"fileToUpload\" />";
	echo '</form>';
	?>
	</body>

	</html>
	<?php
	exit();
}

//αν ειναι σελιδα
if (realpath(__FILE__) != realpath($_SERVER['SCRIPT_FILENAME'])) {

	return;
}

?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex,nofollow">
	<title>Galaxy Content Manager - Users</title>
	<meta name="description" content="Manage users and their settings in Galaxy Content Manager.">
	<link rel="stylesheet" href="content-2.css">

	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
	<link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>&#127756;</text></svg>">
	<script defer src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
	<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
	<script defer src="content-1.js"></script>
	<link rel="icon" href="icon.png" type="image/png">
</head>

<body class="admin-view admin-view-cms1-bg">
	<h2>🌌 Galaxy Content Manager</h2>
	<p><small>Manage users and their settings.</small></p>

	<?php
	$json_data = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json'), true);
	if (!is_array($json_data)) {
		$json_data = ['users' => []];
	}

	$append_unique = function ($base, $extra) {
		$normalized = [];
		foreach ($base as $value) {
			$value = trim((string) $value);
			if ($value === '' || in_array($value, $normalized, true)) {
				continue;
			}
			$normalized[] = $value;
		}
		foreach ($extra as $value) {
			$value = trim((string) $value);
			if ($value === '' || in_array($value, $normalized, true)) {
				continue;
			}
			$normalized[] = $value;
		}
		return $normalized;
	};

	$users = isset($json_data['users']) && is_array($json_data['users'])
		? array_keys($json_data['users'])
		: [];

	$users_root = $_SERVER['DOCUMENT_ROOT'] . '/users';
	$users_from_fs = [];
	$fields_from_fs_index = [];
	$dates_from_fs_index = [];
	if (is_dir($users_root)) {
		$root = rtrim($users_root, '/\\');
		$matched_files = array_merge(
			glob($root . '/*/*/*/*.*') ?: [],
			glob($root . '/*/*/*/*/*.*') ?: []
		);

		if (is_array($matched_files)) {
			$normalized_root = str_replace('\\', '/', rtrim($users_root, '/\\'));
			foreach ($matched_files as $matched_file) {
				if (!is_file($matched_file)) {
					continue;
				}

				$normalized_file = str_replace('\\', '/', $matched_file);
				if (strpos($normalized_file, $normalized_root . '/') !== 0) {
					continue;
				}

				$relative_path = substr($normalized_file, strlen($normalized_root) + 1);
				// Accept both:
				// 1) users/u/s/today/variant.ext
				// 2) users/u/s/today/variant/file.ext
				if (
					!preg_match('#^([^/]+)/([^/]+)/([^/]+)/([^/]+)\.[^/]+$#', $relative_path, $m)
					&& !preg_match('#^([^/]+)/([^/]+)/([^/]+)/([^/]+)/[^/]+\.[^/]+$#', $relative_path, $m)
				) {
					continue;
				}

				$fs_user = trim($m[1]);
				$fs_field = trim($m[2]);
				$fs_today = trim($m[3]);

				if ($fs_user === '' || $fs_field === '' || $fs_today === '') {
					continue;
				}

				$users_from_fs[$fs_user] = true;
				if (!isset($fields_from_fs_index[$fs_user])) {
					$fields_from_fs_index[$fs_user] = [];
				}
				$fields_from_fs_index[$fs_user][$fs_field] = true;

				if (!isset($dates_from_fs_index[$fs_user])) {
					$dates_from_fs_index[$fs_user] = [];
				}
				if (!isset($dates_from_fs_index[$fs_user][$fs_field])) {
					$dates_from_fs_index[$fs_user][$fs_field] = [];
				}
				$dates_from_fs_index[$fs_user][$fs_field][$fs_today] = true;
			}
		}
	}
	$users_from_fs = array_keys($users_from_fs);
	sort($users_from_fs, SORT_NATURAL | SORT_FLAG_CASE);
	$users = $append_unique($users, $users_from_fs);

	$selected_user = isset($_GET['u']) && $_GET['u'] !== '' ? $_GET['u'] : ($users[0] ?? 'global');
	if (!in_array($selected_user, $users, true)) {
		$users[] = $selected_user;
	}

	$fields = [];
	if (isset($json_data['users'][$selected_user]) && is_array($json_data['users'][$selected_user])) {
		$fields = array_keys($json_data['users'][$selected_user]);
	}

	$fields_from_fs = array_keys($fields_from_fs_index[$selected_user] ?? []);
	sort($fields_from_fs, SORT_NATURAL | SORT_FLAG_CASE);
	$fields = $append_unique($fields, $fields_from_fs);

	$selected_field = isset($_GET['s']) && $_GET['s'] !== '' ? $_GET['s'] : ($fields[0] ?? 'info');
	if (!in_array($selected_field, $fields, true)) {
		$fields[] = $selected_field;
	}

	$dates = [];
	if (isset($json_data['users'][$selected_user][$selected_field]) && is_array($json_data['users'][$selected_user][$selected_field])) {
		$dates = array_keys($json_data['users'][$selected_user][$selected_field]);
	}

	$dates_from_fs = array_keys($dates_from_fs_index[$selected_user][$selected_field] ?? []);
	sort($dates_from_fs, SORT_NATURAL | SORT_FLAG_CASE);
	$dates = $append_unique($dates, $dates_from_fs);

	$selected_today = isset($_GET['today']) && $_GET['today'] !== '' ? $_GET['today'] : ($dates[0] ?? '1');
	if (!in_array($selected_today, $dates, true)) {
		$dates[] = $selected_today;
	}

	// Build media variants from current directory only: /users/{u}/{s}/{today}
	$media_variants = [];
	$current_media_dir = $users_root . '/' . $selected_user . '/' . $selected_field . '/' . $selected_today;
	if (is_dir($current_media_dir)) {
		$direct_files = glob(rtrim($current_media_dir, '/\\') . '/*.*') ?: [];
		$nested_files = glob(rtrim($current_media_dir, '/\\') . '/*/*.*') ?: [];
		$all_media_files = array_merge($direct_files, $nested_files);

		foreach ($all_media_files as $media_file) {
			if (!is_file($media_file)) {
				continue;
			}

			$variant_name = '';
			$relative_media_path = str_replace('\\', '/', substr(str_replace('\\', '/', $media_file), strlen(str_replace('\\', '/', rtrim($current_media_dir, '/\\'))) + 1));
			if (strpos($relative_media_path, '/') !== false) {
				$variant_name = basename(dirname($media_file));
			} else {
				$variant_name = pathinfo($media_file, PATHINFO_FILENAME);
			}

			$variant_name = trim((string) $variant_name);
			if ($variant_name === '') {
				continue;
			}

			$media_type = null;
			if (is_image($media_file)) {
				$media_type = 'image';
			} elseif (is_video($media_file)) {
				$media_type = 'video';
			}

			if ($media_type !== null) {
				$media_variants[$variant_name] = $media_type;
			}
		}
	}
	if (empty($media_variants)) {
		$media_variants = [
			'image' => 'image',
			'video' => 'video'
		];
	}
	ksort($media_variants, SORT_NATURAL | SORT_FLAG_CASE);
	?>

	<?php if (is_user_admin_back_end()) { ?>

		<form id="myForm" method="post">

			<input class="admin-pad-5" value="Save" name="save-options" type="submit">
			<label>Select username:</label>
			<select class="admin-pad-5 admin-location-select">
				<option>Select username</option>
				<?php
				foreach ($users as $select_user) {
					$select_user = basename($select_user);
					$select_user_view = str_replace("-", " ", $select_user);
					$select_user_view = ucwords($select_user_view);
					$s_param = $selected_field ?: 'info';
					$today_param = $selected_today ?: '1';
					$url = update_url_params(array('u' => $select_user, 's' => $s_param, 'today' => $today_param));
					$selected = ($selected_user == $select_user) ? ' selected' : '';
					echo "<option value=\"$url\"$selected>";
					echo $select_user_view;
					echo '</option>';
				}
				?>
			</select>
			<label>Select field:</label>
			<select class="admin-pad-5 admin-location-select">
				<option>Select field</option>
				<?php
				foreach ($fields as $field_option) {
					$select_field_view = str_replace("-", " ", $field_option);
					$select_field_view = ucwords($select_field_view);
					$today_param = $selected_today ?: '1';
					$url = update_url_params(array('s' => $field_option, 'today' => $today_param, 'u' => $selected_user));
					$selected = ($selected_field == $field_option) ? ' selected' : '';
					echo "<option value=\"$url\"$selected>";
					echo $select_field_view;
					echo '</option>';
				}
				?>
			</select>

			<label>Select today:</label>
			<select class="admin-pad-5 admin-location-select">
				<option>Select today</option>
				<?php
				foreach ($dates as $today_option) {
					$url = update_url_params(array('today' => $today_option, 'u' => $selected_user, 's' => $selected_field));
					$selected = ($selected_today == $today_option) ? ' selected' : '';
					echo "<option value=\"$url\"$selected>";
					echo $today_option;
					echo '</option>';
				}
				?>
			</select>

			<hr>
			<?php
			// Get all variants for the current field
			$variants = array();
			$original_variants = array();

			if (isset($json_data['users'][$selected_user][$selected_field][$selected_today])) {
				$data = $json_data['users'][$selected_user][$selected_field][$selected_today];

				// Filter out default fields
				$excluded = ['image'];

				// Create an associative array to maintain key-value relationship
				foreach ($data as $key => $value) {
					if (
						!in_array(strtolower($key), array_map('strtolower', $excluded)) &&
						!preg_match('/^(category|location|image)-/i', $key)
					) {
						$original_variants[$key] = $key;
						$variants[$key] = str_replace(['-', '_'], ' ', mb_ucfirst($key));
					}
				}
			}
			?>

			<table class="admin-table-dimgray" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td class="mobile-block admin-col-main">
							<p><u><strong>Insert new data:</strong></u></p>

							<p>
								<label>
									<strong><span class="admin-text-danger">New Data:</span></strong>
									<br>
									<textarea rows="1" placeholder="Type Name.." type="text" id="new-variant-name" name="new-variant-name" class="admin-textarea-full"></textarea>

									<textarea placeholder="Type Value.." name="new-variant-value" class="admin-textarea-full js-auto-resize"></textarea>
								</label>
							</p>
							<p>
								<u><strong>Current data:</strong></u>
							</p>
							<?php foreach ($variants as $original_variant => $display_variant):
								$escaped_original = htmlspecialchars($original_variant);
								$escaped_display = htmlspecialchars($display_variant);
								$value = '';
								if (isset($json_data['users'][$selected_user][$selected_field][$selected_today][$original_variant])) {
									$value = $json_data['users'][$selected_user][$selected_field][$selected_today][$original_variant];
									$value = is_array($value) ? implode(',', $value) : (string) $value;
								}
								$trimmed_value = trim($value);
								$escaped_value = htmlspecialchars($trimmed_value);
								$has_html_value = value_contains_html_markup_for_editor_1($trimmed_value);
								?>
								<p>
									<label>
										<?php echo $escaped_display; ?>:
										<sup>
											<a href="<?php echo update_url_params(array('delete-option' => true, 'variant' => $original_variant)); ?>" class="admin-text-danger js-confirm-delete">[x]</a>
										</sup>
										<br>
										<?php if ($has_html_value): ?>
											<div class="quill-editor-container" data-target-id="<?php echo $escaped_original; ?>"></div>
											<textarea placeholder="Type Value.." id="<?php echo $escaped_original; ?>" name="<?php echo $escaped_original; ?>" class="admin-textarea-full quill-html-field" style="display:none"><?php echo $escaped_value; ?></textarea>
										<?php else: ?>
											<textarea placeholder="Type Value.." id="<?php echo $escaped_original; ?>" name="<?php echo $escaped_original; ?>" class="admin-textarea-full js-auto-resize"><?php echo $escaped_value; ?></textarea>
										<?php endif; ?>
									</label>
								</p>
							<?php endforeach ?>
						</td>
						<td class="desktop admin-col-gap">&nbsp;</td>
						<td class="mobile-block admin-col-side">
							<hr class="mobile">
							<?php $media_index = 0; ?>
							<?php foreach ($media_variants as $media_variant => $media_type): ?>
								<?php if ($media_index > 0): ?>
									<hr class="mobile admin-mobile-mt-16">
								<?php endif; ?>
								<p class="admin-mb-0">
									<u><strong>
											<?php
											$variant_label = ucwords(str_replace(['-', '_'], ' ', $media_variant));
											echo htmlspecialchars($variant_label);
											?>:
										</strong></u>
								</p>
								<br>
								<p>
									<label>
										<iframe class="uploader-iframe" scrolling="no" src="<?php echo update_url_params(array('u' => $selected_user, 's' => $selected_field, $media_type === 'video' ? 'upload-video' : 'upload-image' => true, 'variant' => $media_variant, 'today' => $selected_today), path2url(__FILE__)); ?>"></iframe>
									</label>
								</p>
								<?php $media_index++; ?>
							<?php endforeach ?>
						</td>
					</tr>
				</tbody>
			</table>

		</form>
	<?php } else { ?>
		<p>You are not logged in as admin</p>
	<?php }
	?>

	<?php if (!is_iframe()) { ?>
		<div id="copyright"><small>&copy; Andreas Express &bull; Program Ver:
				<?php echo date("ymd", filemtime(__FILE__)) ?> &bull; All Rights Reserved
			</small></div>
	<?php } ?>

</body>

</html>
