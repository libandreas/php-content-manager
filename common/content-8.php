<?php
/**
 * CONTENT-8: Monaco Code Editor
 * Purpose: Admin file editor using Monaco (VS Code engine) for editing PHP, JS, CSS, HTML files
 * Detects binary files and prevents saving; supports syntax highlighting
 * 
 * @deprecated This program is no longer actively used or maintained
 */

function is_binary_file_content($file_path)
{

	if (!is_string($file_path) || $file_path === '' || !is_file($file_path) || !is_readable($file_path)) {
		return false;
	}

	if (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo !== false) {
			$mime_type = finfo_file($finfo, $file_path);
			finfo_close($finfo);

			if (is_string($mime_type)) {
				$mime_type = strtolower($mime_type);
				if (str_starts_with($mime_type, 'text/')) {
					return false;
				}

				$known_text_mime_types = [
					'application/json',
					'application/xml',
					'application/javascript',
					'application/x-javascript',
					'application/x-httpd-php',
					'application/x-sh',
					'application/x-empty',
					'inode/x-empty',
				];

				if (in_array($mime_type, $known_text_mime_types, true)) {
					return false;
				}

				if (str_contains($mime_type, 'charset=')) {
					return false;
				}
			}
		}
	}

	$sample = file_get_contents($file_path, false, null, 0, 4096);
	if ($sample === false || $sample === '') {
		return false;
	}

	if (str_contains($sample, "\0")) {
		return true;
	}

	if (function_exists('mb_check_encoding') && mb_check_encoding($sample, 'UTF-8')) {
		return false;
	}

	return false;
}

function format_edit_link_file_monaco($target_file, $target_line = null, $url = false)
{

	if ($url == false) {

		return false;
	}

	//if is admin
	if (is_user_admin_back_end()) {

		//edit php file
		$location = path2url(__FILE__);
		$url = urlencode($url);
		$url_with_vscode = update_url_params(array('f' => $target_file, 'l' => $target_line, 't' => $url), $location);
	} else {

		$url_with_vscode = '#';
	}

	return $url_with_vscode;
}

function setup_cms8_buffer($html)
{

	// βάζει το link για να επιστρέψει στην προηγούμενη σελίδα
	if (str_contains(get_current_url(), "/" . basename(__FILE__))) {

		//pull back
		if (isset($_GET['t'])) {

			$url = urldecode($_GET['t']);
		} else {

			$url = update_url_params(array('f' => false), path2url($_GET['f']));
		}
		$html = attach_admin_link($url, "View Front", "Blue", $html, "↩️");
	}

	// να μην κάνει τίποτα αμα ειναι μέσα στο admin
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// attach links
	$put = null;
	if (is_user_admin_back_end()) {

		//an exei wordpress template to link na paei sto template
		if (function_exists('get_page_template_slug') && function_exists('get_the_id')) {

			global $template;
			$template_slug = get_page_template_slug(get_the_id());
			if ($template_slug != null) {

				$preview_file = $_SERVER['DOCUMENT_ROOT'] . "/" . $template_slug;
				$preview_file = normalize_path($preview_file);
			} elseif ($template != null) {

				$preview_file = normalize_path($template);
			} else {

				$preview_file = $_SERVER['SCRIPT_FILENAME'];
			}
		} else {

			$preview_file = $_SERVER['SCRIPT_FILENAME'];
		}

		//edit php file
		$url = get_current_url();
		$url_with_monaco = format_edit_link_file_monaco($preview_file, false, $url);

		//if file is null
		$put .= "<a class=\"cms-link\" style=\"text-decoration:none;color:purple;text-shadow: 1px 1px white\" href=\"" . $url_with_monaco . "\"><small style=\"font-size:12pt\">Edit source 📝</small></a><br>";
	}

	//push
	$html = push_admin_link_on_html($html, $put, str_replace(".php", "", basename(__FILE__)));
	return $html;
}

// load functions
include_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

if (PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && !is_user_admin_back_end()) {
	header('Cache-Control: no-store');
	$returnParams = $_GET;
	unset($returnParams['username'], $returnParams['passwd'], $returnParams['login'], $returnParams['logout'], $returnParams['delete-option'], $returnParams['reset-original']);
	$returnParams['login_return'] = '1';
	$returnUrl = path2url(__FILE__) . '?' . http_build_query($returnParams);
	$loginUrl = update_url_params(['t' => urlencode($returnUrl)], path2url($_SERVER['DOCUMENT_ROOT'] . '/common/content-0.php'));
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


// note: από εδώ και πέρα το αρχείο λειτουργεί μονο όταν καλείτε απευθείας
ob_start("setup_cms8_buffer");

// αποθήκευση
if (isset($_POST['save-button']) && is_user_admin_back_end()) {

	$f = $_GET['f'];

	if (!is_binary_file_content($f)) {

		// Get content
		$content = $_POST['editorContent'];

		// Save
		create_path(dirname($f));
		file_put_contents($f, $content);
	}
}

//αν ειναι σελιδα
if (realpath(__FILE__) != realpath($_SERVER['SCRIPT_FILENAME'])) {

	return;
}

// call content
$html = 'binary file not supported';
if (!is_binary_file_content($_GET['f'])) {
	$html = file_get_contents_or_null($_GET['f']);
}

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex,nofollow">
	<title>Galaxy Content Manager - Source Editor</title>
	<meta name="description" content="Edit page source in Galaxy Content Manager.">
	<link rel="stylesheet" href="content-2.css">
	<link rel="stylesheet" href="responsive.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
	<link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>&#127756;</text></svg>">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.21.2/min/vs/loader.min.js"></script>
	<script defer type="module" src="content-8.js"></script>
	<link rel="icon" href="icon.png" type="image/png">
</head>

<body class="admin-view" style="font-family: serif;background: white;">
	<h2>🌌 Galaxy Content Manager</h2>
	<p><small>Edit the source code of your pages.</small></p>

	<?php if (is_user_admin_back_end()) { ?>

		<!--floating pani-->
		<div id="pani"></div>
		<div id="myForm">

			<!--here is the floating editor with fallback!-->
			<form method="post" style="display:inline-block;">
				<div id="editor"></div>
				<textarea id="editorContent" name="editorContent" style="display:none;"><?php echo htmlspecialchars($html, ENT_QUOTES, 'UTF-8') ?></textarea>
				<input style="padding: 5px" value="Save" id="save-button" name="save-button" type="submit">
			</form>

			<label class="desktop" style="color:white">Select file:</label>
			<select class="desktop" style="padding:5px;width:500px" onchange="location = this.options[this.selectedIndex].value;">
				<option>Select file</option>
				<?php
				$files = array_merge(
					glob($_SERVER['DOCUMENT_ROOT'] . '/*.*'),
					glob($_SERVER['DOCUMENT_ROOT'] . '/*/*.*'),
					glob($_SERVER['DOCUMENT_ROOT'] . '/*/*/*.*'),
					glob($_SERVER['DOCUMENT_ROOT'] . '/*/*/*/*.*')
				);

				$files = array_filter($files, 'is_file');

				foreach ($files as $select_file) {
					if (strpos($select_file, 'common') !== false || strpos($select_file, 'wp-') !== false || strpos($select_file, 'node_modules') !== false or strpos($select_file, 'ckeditor') !== false or strpos($select_file, 'font-awesome') !== false) {
						continue;
					}
					$url = update_url_params(array('f' => $select_file), false, true);
					$select_file = normalize_path($select_file);
					$_GET['f'] = normalize_path($_GET['f']);
					$selected = isset($_GET['f']) && $_GET['f'] === $select_file ? ' selected' : '';
					echo "<option value=\"$url\"$selected>";
					echo $select_file;
					echo '</option>';
				}
				?>
			</select>

		</div>
	<?php } else { ?>
		<p>You are not logged in as admin test</p>
	<?php } ?>

</body>

</html>
