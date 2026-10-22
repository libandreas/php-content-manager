<?php
/**
 * New Error handler, please use only trigger_error and this makes the rest job ;)
 * Πληροφορίες: Το αν θα δείχνει τα λάθη του προγραμματος θα εξαρταται απο τις ρυθμίσεις php που έχει ο κάθε server
 */

function new_error_handler($errno, $errstr, $errfile, $errline)
{

	//let the error fall to the default handler if is cli
	if (php_sapi_name() == "cli") {
		return false;
	}

	//if is iframe fall to default
	elseif (!empty($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe') {
		return false;
	}

	// Exclude notice types from stack trace
	$exclude_stack = [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED];
	$stack_trace = "";
	if (!in_array($errno, $exclude_stack) && function_exists('get_formatted_stack_trace')) {
		$stack_trace = get_formatted_stack_trace(1);
	}

	// Log the error
	write_error_string_to_file("$errstr in $errfile on line $errline" . $stack_trace, $errno);

	//if is user custom error
	if ($errno == '1024' or $errno == 512) {
		//note: το escape είναι για το is broken html να μην το βγάλει
		$errstr = "<strong>" . ucfirst(htmlspecialchars($errstr)) . "</strong>"; //escape all html charters on user messages and make all bold

		//push error link on error message
		if (function_exists('url2path') && function_exists('format_edit_link_file')) {
			$erredit = format_edit_link_file($errfile, $errline);
			$errstr = "<a class=\"erh-message\" href=\"$erredit\">" . $errstr . "</a>";
		} else {
			$errstr = "<a class=\"erh-message\">" . $errstr . "</a>";
		}
	}
	//if is program error then build with file and line
	else {

		// if is production check via host
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$is_localhost = ($host === 'localhost' || $host === '127.0.0.1');

		// If NOT localhost, do not show errors (production mode)
		if (!$is_localhost) {
			return false;
		}

		//build without link
		$errstr = $errstr . " in <strong>" . $errfile . "</strong> on the line <strong>" . $errline . "</strong>"; //use bold handling on program errors

		//push error link on error message
		if (function_exists('url2path') && function_exists('format_edit_link_file')) {
			$erredit = format_edit_link_file($errfile, $errline);
			$errstr = "<a class=\"erh-message\" href=\"$erredit\">" . $errstr . "</a>";
		} else {
			$errstr = "<a class=\"erh-message\">" . $errstr . "</a>";
		}
	}

	// Generate a unique error ID based on error type and message (sanitized for HTML ID)
	$error_type = "UNKNOWN";
	switch ($errno) {
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_USER_ERROR:
		case '1024':
		case '512':
			$error_type = "FATAL";
			break;
		case E_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
			$error_type = "WARNING";
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			$error_type = "NOTICE";
			break;
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$error_type = "DEPRECATED";
			break;
		case E_STRICT:
			$error_type = "STRICT";
			break;
	}

	// Create a stable message hash for the ID
	$msg_hash = substr(md5(strip_tags($errstr)), 0, 10);
	$error_id = "erh_" . $error_type . "_" . $msg_hash;

	$page_error = "<div id=\"{$error_id}\" class=\"erh-notification erh-error new-error-handler erh-auto-hide\" data-error-type=\"{$error_type}\">" . $errstr . "<span class=\"erh-close\" title=\"Close\">&#10006;</span></div>" . PHP_EOL;
	echo $page_error;

	//if xdebug is active then fall to the php system including debugger
	if (function_exists("xdebug_is_debugger_active") && xdebug_is_debugger_active()) {
		return false;
	} elseif (!function_exists('xdebug_is_debugger_active')) {
		function xdebug_is_debugger_active()
		{
			return false;
		}
	}

	// Error logging disabled
	return true; //return true to skip error processing by php
}

function trigger_correct($message)
{

	// Check if it's a CLI environment
	if (php_sapi_name() == "cli") {
		return false;
	}

	// Check if it's an iframe
	if (!empty($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe') {
		return false;
	}

	// Prepare the message
	$message = "<strong>" . ucfirst(htmlspecialchars($message)) . "</strong>";

	// Create the div with forest green color
	$correct_div = "<div class=\"erh-notification erh-correct trigger-correct erh-auto-hide\"><span class=\"erh-message\">" . $message . "</span><span class=\"erh-close\" title=\"Close\">&#10006;</span></div>" . PHP_EOL;
	echo $correct_div;

	// Error logging disabled
	return true;
}

function trigger_notice($message)
{

	// Check if it's a CLI environment
	if (php_sapi_name() == "cli") {
		return false;
	}

	// Check if it's an iframe
	if (!empty($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe') {
		return false;
	}

	// Log the notice
	write_error_string_to_file($message);

	// Prepare the message
	$message = "<strong>" . ucfirst(htmlspecialchars($message)) . "</strong>";

	// Create the div with orange color (matching E_NOTICE)
	$notice_div = "<div class=\"erh-notification erh-notice trigger-notice erh-auto-hide\"><span class=\"erh-message\">" . $message . "</span><span class=\"erh-close\" title=\"Close\">&#10006;</span></div>" . PHP_EOL;

	echo $notice_div;

	// Error logging disabled
	return true;
}

function trigger_warning($message)
{
    // Skip in CLI environment
    if (PHP_SAPI === 'cli') {
        return false;
    }

    // Skip in iframe
    if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe') {
        return false;
    }

    // Log the warning without stack trace
    write_error_string_to_file($message);

    // Prepare the message
    $message = "<strong>" . ucfirst(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</strong>";

    // Create the div with orangered color (matching E_WARNING)
    echo "<div class=\"erh-notification erh-warning trigger-warning erh-auto-hide\"><span class=\"erh-message\">" . $message . "</span><span class=\"erh-close\" title=\"Close\">&#10006;</span></div>" . PHP_EOL;

    return true;
}

/**
 * Call this patch with buffer to fix all errors for this handler
 * @param mixed $html
 *
 * @return [type]
 */
function setup_errors_buffer($html)
{
	if (!empty($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe') { //dont use is any admin we push now also on admin okkk??
		return $html;
	}

	// Add required JS/CSS
	if (strpos($html, 'errors-handler.js') === false) {
		$html = str_ireplace('</head>', '<script defer src="/common/errors-handler.js"></script></head>', $html);
	}
	if (strpos($html, 'errors-handler.css') === false) {
		$html = str_ireplace('</head>', '<link rel="stylesheet" href="/common/errors-handler.css"></head>', $html);
	}

	// Capture errors using class detection
	preg_match_all('/<div[^>]*?class=["\'][^"\']*?erh-notification[^"\']*?["\'][^>]*?>.*?<\/div>/s', $html, $matches);
	$errors = isset($matches[0]) ? $matches[0] : array();

	if (!empty($errors)) {
		// Remove original errors
		$html = preg_replace('/<div[^>]*?class=["\'][^"\']*?erh-notification[^"\']*?["\'][^>]*?>.*?<\/div>/s', '', $html);

		// Prepend to body with fallback
		$html = preg_replace('/(<body\b[^>]*>)/i', '$1' . implode(PHP_EOL, $errors), $html, 1, $count);
		if ($count === 0) {
			$html = implode(PHP_EOL, $errors) . $html;
		}
	}

	return $html;
}

function get_formatted_stack_trace($ignore_frames = 1)
{
	$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	$output = "\nStack trace:\n";
	$count = 0;

	// Skip the frames used by the error handler infrastructure
	for ($i = 0; $i < $ignore_frames; $i++) {
		array_shift($trace);
	}

	foreach ($trace as $t) {
		$file = isset($t['file']) ? $t['file'] : '[internal function]';
		$line = isset($t['line']) ? "({$t['line']})" : "";
		$function = isset($t['function']) ? $t['function'] : 'unknown';
		$class = isset($t['class']) ? $t['class'] : '';
		$type = isset($t['type']) ? $t['type'] : '';

		$args = ""; // Arguments ignored

		$output .= "#{$count} {$file}{$line}: {$class}{$type}{$function}({$args})\n";
		$count++;
	}
	$output .= "#{$count} {main}";

	return $output;
}

function write_error_string_to_file($string, $error_no = 0)
{
	// Use the same format as PHP's error_log: [date time timezone] message
	$log_message = "[" . date("d-M-Y H:i:s") . " " . date_default_timezone_get() . "] " . $string . "\n";

	// Write to daily file
	$error_log_path = $_SERVER['DOCUMENT_ROOT'] . '/errors.log';

	// Append to the central error log file
	file_put_contents($error_log_path, $log_message, FILE_APPEND | LOCK_EX);
}

// Set PHP error_log to use the same` central location
ini_set('error_log', $_SERVER["DOCUMENT_ROOT"] . '/errors.log');

//start
set_error_handler("new_error_handler");
ob_start("setup_errors_buffer");

return;
