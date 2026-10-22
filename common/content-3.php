<?php

// Scan HTML for custom fields with class pattern text-* or html-*
function scan_custom_fields_from_html_3($html)
{
	if ($html == null) {
		return [];
	}

	// Exclude default keys that are handled separately in the CMS
	$excluded_defaults = ['html'];

	$custom_fields = [];

	// PHP templates can confuse DOMDocument before the real markup is reached,
	// so scan raw class attributes instead of building a DOM.
	if (preg_match_all('/\bclass\s*=\s*(["\'])(.*?)\1/isu', $html, $matches)) {
		foreach ($matches[2] as $classes) {
			$class_array = preg_split('/\s+/', trim(html_entity_decode($classes, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

			foreach ($class_array as $class) {
				if (in_array($class, $excluded_defaults, true)) {
					continue;
				}

				// Check if class matches pattern text-* or html-*
				if (preg_match('/^(text|html)-(.+)$/', $class, $matches)) {
					$field_name = $matches[1] . '-' . $matches[2];
					$custom_fields[] = $field_name;
				}
			}
		}
	}

	// Remove duplicates and return
	return array_unique($custom_fields);
}

// Clean editor source before loading it into Quill.
function clean_cms3_editor_html($html)
{
	if ($html == null) {
		return $html;
	}

	$html = str_replace(["\r\n", "\r"], "\n", $html);
	$html = preg_replace('/>\s+</u', '><', $html);
	$html = preg_replace('/<p(?:\s[^>]*)?>\s*(?:&nbsp;|\x{00a0}|<br\s*\/?>)?\s*<\/p>/iu', '', $html);
	$html = preg_replace('/[ \t]*\n+[ \t]*/u', ' ', $html);

	return trim($html);
}

// Text fields treat source line endings as noise, but preserve real user breaks as <br>.
function clean_cms3_text_for_textarea($text)
{
	if ($text == null) {
		return $text;
	}

	$line_break_marker = '%%CMS3_TEXT_BR%%';
	$text = preg_replace('/<br\s*\/?>/iu', $line_break_marker, $text);
	$text = str_replace(["\r\n", "\r"], "\n", $text);
	$text = preg_replace('/[ \t]*\n+[ \t]*/u', ' ', $text);
	$text = preg_replace('/[ \t]{2,}/u', ' ', $text);
	$text = str_replace($line_break_marker, "\n", $text);

	return trim($text);
}

function prepare_cms3_text_for_save($text)
{
	if ($text == null) {
		return $text;
	}

	$text = str_replace(["\r\n", "\r"], "\n", trim($text));
	$text = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $text);

	return str_replace("\n", '<br>', $text);
}

// Render page through output buffer
function setup_cms3_buffer($html)
{

	// Add a link to return to the previous page
	if (str_contains(get_current_url(), "/" . basename(__FILE__)) && !is_iframe()) {

		//pull back
		$url = urldecode($_GET['t']);
		if (isset($_GET['today'])) {
			$url = update_url_params(array('today' => $_GET['today']), $url);
		}
		$html = attach_admin_link($url, "Return", "Blue", $html, "&#8617;");
	}

	// Do nothing when running inside admin/iframe context
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// attach links
	if (is_user_admin_back_end()) {

		// link to edit
		$target = path2url(__FILE__);

		//preview file
		$preview_file = $_SERVER['SCRIPT_FILENAME'];

		// update url
		$current_url = urlencode(get_current_url());
		$url = update_url_params(array('f' => $preview_file, 't' => $current_url), $target);

		// attach the link
		$html = attach_admin_link($url, 'Edit page', 'blue', $html, '&#9997;');
	}

	return $html;
}

// load functions
include_once __DIR__ . '/functions.php';
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


// Start output buffering
ob_start("setup_cms3_buffer");

// upload image (content-3 uploader)
if (isset($_GET['upload-image']) && isset($_GET['variant']) && is_user_admin_back_end() && isset($_GET['f'])) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-3.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body uploader-body-blue">
		<?php

		// load static file
		$username = 'global';
		$field = 'news';
		$today = $_GET['today'] ?? date('YmdHis');
		$variant = $_GET['variant'];
		$editor_file = isset($_GET['f']) ? urldecode($_GET['f']) : null;
		$editor_html = ($editor_file && file_exists($editor_file)) ? file_get_contents($editor_file) : null;
		$can_update_image_tag = ($editor_html && has_img_tag_with_class_image($editor_html));

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

			if (strpos($mime, 'image/') === 0 && $can_update_image_tag) {
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

					// Write full uploaded image path into page HTML (.image src)
					if ($editor_file && file_exists($editor_file)) {
						$page_html = file_get_contents($editor_file);
						$uploaded_src = path2url($targetFile);
						$page_html = update_main_image($page_html, $uploaded_src, read_title($page_html));
						file_put_contents($editor_file, $page_html);
					}
				}
			}

			echo '</code>';
		}

		if ($editor_file && file_exists($editor_file)) {
			$editor_html = file_get_contents($editor_file);
		}

		// Read current media src from page HTML (source of truth)
		$file = read_main_image($editor_html);

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

// upload video (content-3 uploader)
if (isset($_GET['upload-video']) && isset($_GET['variant']) && is_user_admin_back_end()) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-3.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body uploader-body-blue">
		<?php

		// load static file
		$username = 'global';
		$field = 'news';
		$today = $_GET['today'] ?? date('YmdHis');
		$variant = $_GET['variant'];
		$editor_file = isset($_GET['f']) ? urldecode($_GET['f']) : null;
		$editor_html = ($editor_file && file_exists($editor_file)) ? file_get_contents($editor_file) : null;
		$can_update_video_tag = ($editor_html && has_video_tag_with_class_video($editor_html));

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

			if (strpos($mime, 'video/') === 0 && $can_update_video_tag) {
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

					// Write full uploaded video path into page HTML (<video>/<source> src)
					if ($editor_file && file_exists($editor_file)) {
						$page_html = file_get_contents($editor_file);
						$uploaded_src = path2url($targetFile);
						$page_html = update_main_video($page_html, $uploaded_src);
						file_put_contents($editor_file, $page_html);
					}
				}
			}

			echo '</code>';
		}

		if ($editor_file && file_exists($editor_file)) {
			$editor_html = file_get_contents($editor_file);
		}

		// Read current media src from page HTML (source of truth)
		$file = read_main_video($editor_html);

		// Delete URL
		$delete_url = update_url_params(array('remove' => 'yes', 'variant' => $variant));

		// Display video if exists
		if (!isset($_GET['remove']) && is_file($file)) {
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

if (isset($_POST['delete-static-page']) && isset($_GET['f']) && is_user_admin_back_end()) {

	// Delete the file
	unlink(urldecode($_GET['f']));

	// Redirect
	$go = $_SERVER['DOCUMENT_ROOT'] . "/index.html";
	reload_with_params(array('f' => $go, 't' => path2url($go)));
}

// Rename and save
if (isset($_POST['save-static-page']) && isset($_POST['f']) && isset($_POST['old-f']) && is_user_admin_back_end()) {

	// Load static file
	$html = file_get_contents_or_download($_POST['old-f']);

	// If post f is empty then use title for slufigy a slug
	if ($_POST['f'] == null) {
		$ext = get_extension($_POST['old-f']);
		$basename = slugify($_POST['title']) . "." . $ext;
		$_POST['f'] = str_replace(basename($_POST['old-f']), $basename, $_POST['old-f']);
	}

	// Handle URL preprocessing
	if ($_POST['f'] != $_POST['old-f']) {
		if (file_exists($_POST['f'])) {
			trigger_error('Sorry file already exists');
		} else {

			// Write new file and clean up old one
			file_put_contents($_POST['f'], $html);
			if (file_exists($_POST['old-f'])) {
				unlink($_POST['old-f']);
			}

			// Reload
			reload_with_params(array('f' => $_POST['f'], 't' => path2url($_POST['f'])));
		}
	}

	// html contents
	$html = normalize_tags($html);

	// title
	$html = update_title($html, $_POST['title']);
	$html = update_h1($html, $_POST['title']);

	// meta description (update both meta tag and class element)
	$html = update_meta_description($html, $_POST['description']);
	$html = update_elem_by_class($html, "description", $_POST['description']);

	// meta keywords (update both meta tag and class element)
	$html = update_meta_keywords($html, $_POST['keywords']);
	$html = update_elem_by_class($html, "keywords", $_POST['keywords']);

	// update the modified date (use file's last modification time from filesystem)
	$file_timestamp = file_exists($_POST['f']) ? filemtime($_POST['f']) : time();
	$date_of_file = date(read_date_format($_POST['date-format']), $file_timestamp);
	$html = update_elem_by_class($html, "last-update", $date_of_file);

	// now update also the main html
	if (trim($_POST['html']) == '') {
		$_POST['html'] = null;
	}
	$html = update_main_html($html, $_POST['html']);

	// Update custom fields from text-* and html-* classes.
	$custom_fields = scan_custom_fields_from_html_3($html);

	// Deterministic order: text-*, html-*
	$text_custom_fields = [];
	$html_custom_fields = [];
	foreach ($custom_fields as $field_key) {
		if (strpos($field_key, 'text-') === 0) {
			$text_custom_fields[] = $field_key;
		} elseif (strpos($field_key, 'html-') === 0) {
			$html_custom_fields[] = $field_key;
		}
	}

	$all_fields = array_merge($text_custom_fields, $html_custom_fields);

	foreach ($all_fields as $field) {
		if (isset($_POST[$field])) {
			$value = $_POST[$field];
			if (strpos($field, 'text-') === 0) {
				$value = prepare_cms3_text_for_save($value);
			}
			$html = update_elem_by_class($html, $field, $value);
		}
	}

	// Media src updates are handled inside uploaders only.
	// Here we only preserve current media src and refresh OG data.
	$image = read_main_image($html);
	$og_tags = array(
		'og:title' => read_title($html),
		'og:description' => read_description_limited($html),
		'og:image' => $image,
		'og:url' => path2url($_GET['f']),
	);
	$html = update_og_meta_tags($html, $og_tags);

	//save html
	file_put_contents($_POST['f'], $html);

	// Prevent Form Resubmission via PRG Pattern
	reload_with_params();
}

// Duplicate page
if (isset($_POST['duplicate-static-page']) && isset($_GET['f']) && is_user_admin_back_end()) {

	$html = file_get_contents(urldecode($_GET['f']));

	$new_file = str_replace(basename(urldecode($_GET['f'])), 'new.html', urldecode($_GET['f']));
	$new_url = path2url($new_file);

	//save
	$html = update_title($html, 'New page from ' . read_title($html));
	file_put_contents($new_file, $html);

	$url = update_url_params(array('f' => $new_file, 't' => $new_url));
	header('Location: ' . $url);
	exit();
}

// If this file is included, stop before rendering the admin page
if (realpath(__FILE__) != realpath($_SERVER['SCRIPT_FILENAME']) || empty($_GET['f'])) {

	return;
}

// fallback file if f is not setup
if (!isset($_GET['f']) && file_exists($_SERVER['DOCUMENT_ROOT'] . "/index.html")) {

	$_GET['f'] = $_SERVER['DOCUMENT_ROOT'] . "/index.html";
} elseif (!isset($_GET['f']) && file_exists($_SERVER['DOCUMENT_ROOT'] . "/index.php")) {

	$_GET['f'] = $_SERVER['DOCUMENT_ROOT'] . "/index.php";
} elseif (!isset($_GET['f'])) {

	$_GET['f'] = $_SERVER['DOCUMENT_ROOT'] . "/index.html";
}

if (file_exists($_GET['f'])) {
	$html = file_get_contents($_GET['f']);
} else {
	$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galaxy Content Manager - Pages</title>
    <meta name="description" content="Edit and manage pages in Galaxy Content Manager.">
</head>
<body>
    <h1>Galaxy Content Manager - Pages</h1>
    <p>Please select a file to edit.</p>
</body>
</html>';
}

// Remove the f parameter if there is not real file
if (!file_exists(urldecode($_GET['f']))) {
	$disabled = 'disabled';
} else {
	$disabled = '';
}

// Media namespace ids for uploaders only (separate image/video handling)
$image_today = date('YmdHis');
$image_tag_src = read_main_image($html);
if ($image_tag_src) {
	$parsed_image_url = parse_url($image_tag_src);

	if (isset($parsed_image_url['query'])) {
		parse_str($parsed_image_url['query'], $image_query_params);
		if (isset($image_query_params['today']) && preg_match('/^\d{8,14}$/', (string) $image_query_params['today'])) {
			$image_today = (string) $image_query_params['today'];
		}
	}
}

$video_today = date('YmdHis');
$video_tag_src = read_main_video($html);
if ($video_tag_src) {
	$parsed_video_url = parse_url($video_tag_src);

	if (isset($parsed_video_url['query'])) {
		parse_str($parsed_video_url['query'], $video_query_params);
		if (isset($video_query_params['today']) && preg_match('/^\d{8,14}$/', (string) $video_query_params['today'])) {
			$video_today = (string) $video_query_params['today'];
		}
	}
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex,nofollow">
	<title>Galaxy Content Manager - Pages</title>
	<meta name="description" content="Edit and manage pages in Galaxy Content Manager.">
	<link rel="stylesheet" href="content-2.css">

	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
	<link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>&#127756;</text></svg>">
	<script defer src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<!-- Quill Resources -->
	<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
	<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
	<script defer src="content-3.js"></script>
	<link rel="icon" href="icon.png" type="image/png">
</head>

<body class="admin-view admin-view-cms3-bg">
	<h2>🌌 Galaxy Content Manager</h2>
	<p><small>Edit and manage your pages.</small></p>
	<?php if (is_user_admin_back_end()) { ?>
		<form id="myForm" method="post">
			<input <?php echo $disabled ?> class="admin-pad-5" value="Save" name="save-static-page" type="submit">
			<input <?php echo $disabled ?> class="admin-pad-5" value="Duplicate / New" name="duplicate-static-page" type="submit">
			<input <?php echo $disabled ?> class="admin-pad-5 js-confirm-delete-button" value="Delete" name="delete-static-page" type="submit">
			<hr>
			<table class="admin-table-dimgray" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td class="mobile-block admin-col-main">

							<p><u><strong>Title / Filename / ID:</strong></u></p>
							<p>
								<label>
									<?php
									$title = read_title($html);
									?>
									Title:
									<br class="mobile">
									<input class="mobile-input admin-field-control" type="text" placeholder="Place here your title:" value="<?php echo $title; ?>" name="title">
								</label>
							</p>
							<p>
								<label>
									File:
									<br class="mobile">
									<?php if (basename(urldecode($_GET['f'])) != 'new.html') { ?>
										<input class="mobile-input admin-field-control" type="text" name='f' value="<?php echo urldecode($_GET['f']); ?>">
									<?php } else { ?>
										<input class="mobile-input admin-field-control" placeholder="Keep empty for auto generated slug" type="text" name='f'>
									<?php } ?>
									<input name="old-f" type="hidden" value="<?php echo urldecode($_GET['f']); ?>">
								</label>
							</p>

							<hr>
							<p class="admin-mb-0"><u><strong>Meta Data:</strong></u></p>

							<p>
								<label>
									<?php
									// Read from meta tag first, fallback to class element
									$description = read_meta_description($html);
									if (empty($description)) {
										$description = read_elem_by_class($html, "description");
									}
									?>
									Meta description:
									<br>
									<input class="mobile-input admin-field-control" type="text" name="description" value="<?php echo $description; ?>">
								</label>
							</p>
							<p>
								<label>
									<?php
									// Read from meta tag first, fallback to class element
									$keywords = read_meta_keywords($html);
									if (empty($keywords)) {
										$keywords = read_elem_by_class($html, "keywords");
									}
									?>
									Meta keywords:
									<br>
									<input class="mobile-input admin-field-control" type="text" name='keywords' value="<?php echo $keywords; ?>">
								</label>
							</p>

							<p>
								<label>
									<?php
									$date_format = read_date_format($html);
									?>
									Date format:
									<br>
									<input class="mobile-input admin-field-control" type="text" name='date-format' value="<?php echo $date_format; ?>">
									<br>
								</label>
							</p>

							<?php
							// Ordered fields: html, text-*, html-*
							$content = clean_cms3_editor_html(read_main_html($html));

							$custom_keys = scan_custom_fields_from_html_3($html);
							$text_custom_keys = [];
							$html_custom_keys = [];
							foreach ($custom_keys as $k) {
								if (strpos($k, 'text-') === 0) {
									$text_custom_keys[] = $k;
								} elseif (strpos($k, 'html-') === 0) {
									$html_custom_keys[] = $k;
								}
							}
							?>

							<p>
								<label>
									Main Content (Html):
									<br>
									<div id="quill-editor-container" class="quill-editor-container" data-target-id="quill-html-content"></div>
									<textarea id="quill-html-content" class="mobile-input admin-hidden" name="html"><?php echo $content; ?></textarea>
								</label>
							</p>

							<?php foreach ($text_custom_keys as $key): ?>
								<p>
									<label>
										<?= ucwords(str_replace(['-', '_'], ' ', $key)) ?>:
										<br>
										<textarea class="mobile-input admin-field-control" name="<?= $key ?>"><?= clean_cms3_text_for_textarea(read_elem_by_class($html, $key)) ?></textarea>
									</label>
								</p>
							<?php endforeach ?>

							<?php foreach ($html_custom_keys as $key): ?>
								<?php $editor_id = 'quill-' . preg_replace('/[^a-z0-9_-]/i', '-', $key); ?>
								<p>
									<label>
										<?= ucwords(str_replace(['-', '_'], ' ', $key)) ?>:
										<br>
										<div class="quill-editor-container" data-target-id="<?= $editor_id ?>"></div>
										<textarea id="<?= $editor_id ?>" class="mobile-input admin-hidden" name="<?= $key ?>"><?= clean_cms3_editor_html(read_elem_by_class($html, $key)) ?></textarea>
									</label>
								</p>
							<?php endforeach ?>

						</td>
						<td class="desktop admin-col-gap">&nbsp;</td>
						<td class="mobile-block admin-col-side">
							<hr class="mobile">
							<p class="admin-mb-0"><u><strong>Featured image:</strong></u></p>
							<br>
							<iframe class="uploader-iframe" scrolling="no" src="<?php echo update_url_params(array('upload-image' => true, 'f' => urldecode($_GET['f']), 'variant' => 'image', 'today' => $image_today), site_url_des() . "/common/content-3.php"); ?>"></iframe>

							<hr class="mobile admin-mobile-mt-16">
							<p class="admin-mb-0"><u><strong>Featured video:</strong></u></p>
							<br>
							<iframe class="uploader-iframe" scrolling="no" src="<?php echo update_url_params(array('upload-video' => true, 'f' => urldecode($_GET['f']), 'variant' => 'video', 'today' => $video_today), site_url_des() . "/common/content-3.php"); ?>"></iframe>
						</td>
					</tr>
				</tbody>
			</table>

		</form>
	<?php } else { ?>
		<p>You are not logged in as admin</p>
	<?php } ?>

	<?php if (!is_iframe()) { ?>
		<div id="copyright"><small>&copy; Andreas Express &bull; Program Ver:
				<?php echo date('ymd', filemtime(__FILE__)); ?> &bull; All Rights Reserved
			</small></div>
		<?php
	}
	?>

</body>

</html>
