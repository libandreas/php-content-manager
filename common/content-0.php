<?php

/**
 * Replace only the last occurrence of a string
 *
 * @param string $haystack The string to search in
 * @param string $needle The string to find
 * @param string $replace The replacement string
 * @return string The modified string
 */

function setup_login_logout()
{
	//handle login and logout requests for all pages
	if (isset($_POST['login']) or isset($_GET['login'])) {

		login_with_cookies($_POST['username'], $_POST['passwd']);
		reload_with_params(array('login' => null), false);
	} elseif (isset($_POST['logout']) or isset($_GET['logout'])) {

		logout_with_cookies();
		reload_with_params(array('logout' => null), false);
	}
}

function login_with_cookies($username, $passwd)
{

	setcookie('username', $username, time() + (86400 * 30 * 12), "/"); //86400 = 1 day
	setcookie('passwd', urlencode($passwd), time() + (86400 * 30 * 12), "/"); //86400 = 1 day
}

function logout_with_cookies()
{
	setcookie('username', '', time() - 3600, '/');
	setcookie('passwd', '', time() - 3600, '/');

	if (function_exists('wp_logout')) {
		wp_logout();
	}
}

function setup_login_buffer($html)
{

	// να μην κάνει τίποτα αμα ειναι μέσα στο admin
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// Check if the JSON file exists
	if (!file_exists($_SERVER["DOCUMENT_ROOT"] . '/users.json')) {
		return $html;
	}

	// Check if the JSON file has valid admin users
	$db = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . '/users.json'), true);
	if (!isset($db['users'])) {
		return $html;
	}

	// Check if there's at least one user with proper info and password structure
	$validUserFound = false;
	foreach ($db['users'] as $user) {
		if (isset($user['info']) && is_array($user['info'])) {
			foreach ($user['info'] as $info) {
				if (isset($info['passwd'])) {
					$validUserFound = true;
					break 2;
				}
			}
		}
	}

	if (!$validUserFound) {
		return $html;
	}

	// attach trasparent links for login - only for json custom database
	if (is_user_admin_back_end()) {

		//change password - only for db login
		$target = path2url(__FILE__);
		$current_url = urlencode(get_current_url());
		$url = update_url_params(array('t' => $current_url), $target);
		$html = attach_admin_link($url, "Change password", "black", $html, "⚙️");

		//logout
		$target = path2url(__FILE__);
		$current_url = urlencode(get_current_url());
		$url = update_url_params(array('logout' => true, 't' => $current_url), $target);
		$html = attach_admin_link($url, "Logout", "black", $html, "🔒");
	} else {
		$target = path2url(__FILE__);
		$current_url = urlencode(get_current_url());
		$url = update_url_params(array('t' => $current_url), $target);
		$html = attach_admin_link($url, "Login", "black", $html, "🔓");
	}
	return $html;
}

// load functions
include_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

// setup undefined t variable only for that file!!!!!! pls don't delete that
if (!isset($_GET['t']) && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
	$_GET['t'] = site_url_des() . "/index.html";
}

// παραγωγη σελιδας
ob_start("setup_login_buffer");

// login and logout, we remove exit here
if (isset($_GET['t']) && isset($_POST['login'])) {

	// Login and logout function
	login_with_cookies($_POST['username'], $_POST['passwd']);
	header("Location: " . urldecode($_GET['t']));
	exit;
} elseif (isset($_GET['t']) && isset($_GET['logout'])) {

	// Logout Function
	logout_with_cookies();
	header("Location: " . urldecode($_GET['t']));
	exit;
} elseif (isset($_GET['t']) && isset($_POST['change-passwd'])) {

	// Authenticate the account whose password will be changed using its cookies.
	if (!is_user_admin_back_end(true)) {
		http_response_code(403);
		trigger_error('Πρέπει να συνδεθείτε για να αλλάξετε τον κωδικό σας.', E_USER_WARNING);
		exit;
	}

	$checks_passed = true;
	if (!isset($_POST['new-passwd'], $_POST['new-passwd-check']) || !is_string($_POST['new-passwd']) || !is_string($_POST['new-passwd-check'])) {
		trigger_error("Enter the new password and its confirmation.", E_USER_WARNING);
		$checks_passed = false;
	} elseif ($_POST['new-passwd'] === '') {
		trigger_error("Password cannot be empty.", E_USER_WARNING);
		$checks_passed = false;
	} elseif ($_POST['new-passwd-check'] === '') {
		trigger_error("Password confirmation cannot be empty.", E_USER_WARNING);
		$checks_passed = false;
	} elseif ($_POST['new-passwd'] !== $_POST['new-passwd-check']) {
		trigger_error("Passwords do not match.", E_USER_WARNING);
		$checks_passed = false;
	}

	// JSON-based authentication
	$db_file = $_SERVER["DOCUMENT_ROOT"] . '/users.json';
	if (file_exists($db_file) && $checks_passed == true) {
		$username = strtolower(rawurldecode($_COOKIE['username']));
		$new_passwd = $_POST['new-passwd'];
		$db = json_decode(file_get_contents($db_file), true);

		if (isset($db['users'][$username])) {
			$user_info = &$db['users'][$username]['info'];
			$user_id = key($user_info);
			$user_info[$user_id]['passwd'] = $new_passwd;

			if (file_put_contents($db_file, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {

				login_with_cookies($username, $new_passwd);
				header("Location: " . urldecode($_GET['t']));
				exit;
			} else {
				trigger_error("Failed to update the database file.", E_USER_ERROR);
			}
		} else {
			trigger_error("User not found in database.", E_USER_WARNING);
		}
	}
}

if (realpath(__FILE__) != realpath($_SERVER['SCRIPT_FILENAME'])) {

	return;
}

// fallback user only when  am on admin login screen
if (!isset($_GET['username'])) {

	$_GET['username'] = null;
}

//fallback passwd only when  am on admin login screen
if (!isset($_GET['passwd'])) {

	$_GET['passwd'] = null;
}

include_once $_SERVER["DOCUMENT_ROOT"] . "/common/functions.php";

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex,nofollow">
	<title>Galaxy Content Manager - Login</title>
	<meta name="description" content="Sign in to Galaxy Content Manager.">
	<link rel="stylesheet" href="content-2.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
	<link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>&#127756;</text></svg>">
	<link rel="icon" href="icon.png" type="image/png">
</head>

<body class="admin-view" style="font-family: serif;background: white;">

	<?php if (is_user_admin_back_end()) { ?>

		<table align="center" cellpadding="0" cellspacing="0">
			<tbody>
				<tr>
					<td style="text-align:center; height:49px"><span style="font-size:28px; color:black;"></span></td>
				</tr>
			</tbody>
		</table>
		<hr>
		<p style="text-align:center"><br />
		<table style="box-shadow: 0px 0px 9px 0px;border: 1px solid lightslategray;width:300px" align="center" border="0" cellpadding="0" cellspacing="0" class="round">
			<tbody>
				<tr>
					<td colspan="3" style="text-align:center; width:64px">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="3" style="text-align:center; width:62px">
						<h1 style="color: #222222;font-family:'Times New Roman', Times, serif;margin-top:0">&#127756;</h1>
						<hr>
						<form name="pam" action="<?php echo update_url_params(array()) ?>" autocomplete="off" method="POST">
							<p><input required class="aput" name="new-passwd" id="password" type="text" placeholder="New password"></p>
							<p><input required class="aput" name="new-passwd-check" type="password" id="passwd" placeholder="Confirm New Password" /></p>
							<p><input class="aput" type="submit" name="change-passwd" value="Change Password" /></p>
							<p><span style="font-size:14px"><span style="font-family:arial,helvetica,sans-serif">
										<a href="<?php echo urldecode($_GET['t']); ?>">Back to front</a></span></span></p>
						</form>
					</td>
				</tr>
				<tr>
					<td colspan="3" style="text-align:center; width:62px">

						<br>
					</td>
				</tr>
			</tbody>
		</table>
		<p style="text-align:center"><br />
			&nbsp;</p>
		<hr>
	<?php } else { ?>

		<table align="center" cellpadding="0" cellspacing="0">
			<tbody>
				<tr>
					<td style="text-align:center; height:49px"><span style="font-size:28px; color:black;"></span></td>
				</tr>
			</tbody>
		</table>
		<hr>
		<p style="text-align:center"><br />
		<table style="box-shadow: 0px 0px 9px 0px;border: 1px solid lightslategray;width:300px" align="center" border="0" cellpadding="0" cellspacing="0" class="round">
			<tbody>
				<tr>
					<td colspan="3" style="text-align:center; width:64px">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="3" style="text-align:center; width:62px">
						<h1 style="color: #222222;font-family:'Times New Roman', Times, serif;margin-top:0">&#127756;</h1>
						<hr>
						<form name="pam" action="<?php echo update_url_params(array()) ?>" autocomplete="off" method="POST">
							<p><input required value="<?php echo $_GET['username'] ?>" class="aput" name="username" id="username" type="text" placeholder="Username"></p>
							<p><input required value="<?php echo $_GET['passwd'] ?>" class="aput" name="passwd" type="password" id="passwd" placeholder="Password" /></p>
							<p><input class="aput" type="submit" name="login" value="Login" /></p>
							<p><span style="font-size:14px"><span style="font-family:arial,helvetica,sans-serif">
										<a href="<?php echo urldecode($_GET['t']); ?>">Back to front</a>
									</span></span></p>
						</form>
					</td>
				</tr>
				<tr>
					<td colspan="3" style="text-align:center; width:62px">

						<br>
					</td>
				</tr>
			</tbody>
		</table>
		<p style="text-align:center"><br />
			&nbsp;</p>
		<hr>
		<?php
	}
	?>

	<?php if (!is_iframe()) { ?>
		<div id="copyright"><small>&copy; Andreas Express &bull; Program Ver:
				<?php echo date('ymd', filemtime(__FILE__)); ?> &bull; All Rights Reserved
			</small></div>
		<?php
	}
	?>

</body>

</html>
