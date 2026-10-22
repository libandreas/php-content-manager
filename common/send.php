<?php
/**
 * HOW TO USE SEND.PHP
 * ======================
 *
 * Include send.php in your project
 * require_once 'common/send.php';
 *
 * EXAMPLE CONTACT FORM HTML:
 * Always use name fields with middle dash - instead of underline dash
 * ==================================================================================
 * <form method="post" enctype="multipart/form-data">
 *   <input type="hidden" name="send-contact-form" value="1">
 *
 *   <!-- Required name and email sender and subject-->
 *   <input type="text" name="name" required>
 *   <input type="email" name="email" required>
 *   <input type="text" name="subject" required>
 *
 *   <!-- Optional: Other fields, put message listbox, checkbox any other you like here and use any name you like like the one listed below-->
 *   <input type="text" name="phone">
 *   <textarea name="message" required></textarea>
 *   <select name="select-something">
 *     <option value="option1">Option 1</option>
 *     <option value="option2">Option 2</option>
 *     <option value="option3">Option 3</option>
 *   </select>
 *
 *   <!-- Optional File Attachments -->
 *   <input type="file" name="attachment[]" multiple>
 *
 *   <!-- Optional: Auto-respond -->
 *   <input type="hidden" name="auto-respond" value="1">
 *   <input type="hidden" name="auto-respond-subject" value="1">
 *   <input type="hidden" name="auto-respond-contents" value="1">
 *
 *   <!-- Required: Thank you page -->
 *   <input type="hidden" name="thanks" value="/thank-you.php">
 *
 *   <button name="send-contact-form" type="submit">Send</button>
 * </form>
 *
 * FEATURES:
 * - Supports both plain contact forms and formatted emails
 * - File attachment support with multiple files
 * - Auto-respond capability
 * - Custom thank you pages
 * - UTF-8 encoding
 * - Localhost detection
 */

// PHPMailer requirements
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/errors-handler.php';
require_once __DIR__ . "/send.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Send the main email
if (isset($_POST['send-contact-form']) && $_POST['send-contact-form'] != '') {

	// Read db
	if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/users.json")) {
		$db = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/users.json");
		$db = json_decode($db, true);
	} else {
		$db = array();
	}

	// Process email from contact form
	$excluded_variables = array(
		'submit',
		'thanks',
		'complete',
		'return',
		'send-contact-form',
		'auto-respond',
		'auto-respond-title',
		'auto-respond-subject',
		'auto-respond-contents',
	);

	// Auto-respond email subject and content
	$auto_respond_subject = isset($_POST['auto-respond-subject']) && !empty($_POST['auto-respond-subject'])
		? $_POST['auto-respond-subject']
		: (isset($_POST['auto-respond-title']) && !empty($_POST['auto-respond-title'])
			? $_POST['auto-respond-title']
			: "Your Form Submission");
	$auto_respond_contents = isset($_POST['auto-respond-contents']) && !empty($_POST['auto-respond-contents'])
		? $_POST['auto-respond-contents']
		: "Your submission from " . $_SERVER['HTTP_HOST'] . " has been received. Thank you!";

	// Determine thank-you page
	if (isset($_POST['complete'])) {
		$contact_form_thanks = $_POST['complete'];
	} elseif (isset($_POST['redirect'])) {
		$contact_form_thanks = $_POST['complete'];
	} elseif (isset($_POST['thanks'])) {
		$contact_form_thanks = $_POST['thanks'];
	} else {
		$contact_form_thanks = null;
	}

	// Load the JSON file once if it exists
	$db = null;
	if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/users.json')) {
		$db = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/users.json'), true);
	} else {
		trigger_error('users.json not found');
		$db = array();
	}

	// Get sender name from smtp-name
	$host_name = $_SERVER['HTTP_HOST'] ?? '';
	$host_name = preg_replace('/:\d+$/', '', $host_name);
	$host_name = preg_replace('/^www\./i', '', $host_name);
	$host_name = preg_replace('/\.[^.]+$/', '', $host_name);
	$host_name = str_replace(array('-', '_', '/'), ' ', $host_name);
	$host_name = ucwords(trim($host_name));

	$email_name = $db && isset($db['users']['global']['info'][1]['smtp-name']) && trim($db['users']['global']['info'][1]['smtp-name']) !== ''
		? trim($db['users']['global']['info'][1]['smtp-name'])
		: ($host_name !== '' ? $host_name : 'Website');

	// Get recipient from incoming-mailto
	$incoming_mailto = $db && isset($db['users']['global']['info'][1]['incoming-mailto'])
		? trim($db['users']['global']['info'][1]['incoming-mailto'])
		: null;

	if ($incoming_mailto === null || $incoming_mailto === '') {
		trigger_error('incoming-mailto is not configured in users.json');
		return;
	}

	// Read sender address
	if (isset($_POST['email']) && $_POST['email'] != null) {
		$contact_form_address = $_POST['email'];
	}

	// Read subject / title
	if (isset($_POST['subject']) && $_POST['subject'] != null) {
		$contact_form_subject = $_POST['subject'];
	} elseif (isset($_POST['title']) && $_POST['title'] != null) {
		$contact_form_subject = $_POST['title'];
	} else {
		$contact_form_subject = "This email was received from " . $_SERVER['HTTP_HOST'] . " your feedback form located at " . $_SERVER['HTTP_HOST'];
	}

	// Load email contents fields inside message
	$contact_form_variables = array_keys($_POST);
	$contact_form_values = array_values($_POST);
	$contact_form_message = null;
	for ($i = 0; $i < count($contact_form_variables); $i++) {
		if (!in_array(strtolower($contact_form_variables[$i]), $excluded_variables)) {
			$contact_form_message .= str_replace("-", ' ', ucfirst($contact_form_variables[$i])) . ":\r\n" . $contact_form_values[$i] . "\r\n\r\n";
		}
	}

	// Add attachment handling
	$attachments = array();
	if (!empty($_FILES)) {
		foreach ($_FILES as $file) {
			if ($file['error'] === UPLOAD_ERR_OK) {
				$attachments[] = [
					'content' => base64_encode(file_get_contents($file['tmp_name'])),
					'name' => $file['name']
				];
			}
		}
	}

	// Prepare email parameters
	$mail = new PHPMailer(true);
	try {
		// Check if SMTP credentials exist
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
			$mail->SMTPSecure = $db['users']['global']['info'][1]['smtp-encryption'];
			$mail->Port = $db['users']['global']['info'][1]['smtp-port'];
		} else {
			$mail->isMail();
		}

		// Set character encoding for proper Greek display
		$mail->CharSet = 'UTF-8';
		$mail->Encoding = 'base64';

		// Anti-spam measures
		$mail->XMailer = ''; // Remove X-Mailer header which can trigger spam filters
		$mail->Priority = 3; // Normal priority

		// Additional headers to improve deliverability
		$mail->addCustomHeader('X-MSMail-Priority', 'Normal');
		$mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

		// Disable signing with DKIM for now if there's an issue with it
		$mail->DKIM_domain = null;
		$mail->DKIM_selector = null;

		// Default server email based on context (contact form vs auto-respond)
		$default_server_email = isset($_POST['auto-respond']) ? 'contact@' . $_SERVER['HTTP_HOST'] : 'contact@' . $_SERVER['HTTP_HOST'];
		$from_email = isset($db['users']['global']['info'][1]['smtp-username'])
			? $db['users']['global']['info'][1]['smtp-username']
			: $default_server_email;

		$mail->setFrom(
			$from_email,
			$email_name
		);
		// Use a noreply address from your domain as Reply-To, but include the sender's address in the subject and body
		// This improves email deliverability while maintaining the ability to reply
		$mail->addReplyTo($from_email, $email_name);

		// Record the original sender's email in the body
		$sender_name = isset($_POST['name']) ? $_POST['name'] : 'Website Visitor';

		$mail->addAddress($incoming_mailto);

		// Use plain text email format
		$mail->isHTML(false);

		// Be careful with subject line formatting - some spam filters are triggered by brackets
		// Using parentheses instead
		$mail->Subject = "Message from " . $contact_form_address . ": " . $contact_form_subject;

		// Create plain text body with sender information
		$plainBody = "From: " . $sender_name . " <" . $contact_form_address . ">\r\n\r\n";
		$plainBody .= $contact_form_message;

		$mail->Body = $plainBody;

		foreach ($attachments as $attachment) {
			$mail->addAttachment($attachment['content'], $attachment['name']);
		}

		if ($mail->send()) {

			trigger_correct('Message sent successfully');
			if (isset($contact_form_thanks)) {
				echo '<meta http-equiv="refresh" content="0;url=' . $contact_form_thanks . '">';
				echo "<style>body{display:none}</style>";
			}
		} else {

			trigger_error('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
		}

	} catch (Exception $e) {
		trigger_error("Mail Error: {$mail->ErrorInfo}");
	}
}

// Auto-respond functionality
if (isset($_POST['auto-respond']) && $_POST['auto-respond'] == '1') {
	// Send auto-respond using PHPMailer
	$mail = new PHPMailer(true);
	try {
		// Check if SMTP credentials exist
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
			$mail->SMTPSecure = $db['users']['global']['info'][1]['smtp-encryption'];
			$mail->Port = $db['users']['global']['info'][1]['smtp-port'];
		} else {
			$mail->isMail();
		}

		// Set character encoding for proper Greek display
		$mail->CharSet = 'UTF-8';
		$mail->Encoding = 'base64';

		// Anti-spam measures
		$mail->XMailer = ''; // Remove X-Mailer header which can trigger spam filters
		$mail->Priority = 3; // Normal priority

		// Additional headers to improve deliverability
		$mail->addCustomHeader('X-MSMail-Priority', 'Normal');
		$mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

		// Disable signing with DKIM for now if there's an issue with it
		$mail->DKIM_domain = null;
		$mail->DKIM_selector = null;

		// Default server email based on context (contact form vs auto-respond)
		$default_server_email = 'contact@' . $_SERVER['HTTP_HOST'];
		$from_email = isset($db['users']['global']['info'][1]['smtp-username'])
			? $db['users']['global']['info'][1]['smtp-username']
			: $default_server_email;

		$mail->setFrom(
			$from_email,
			$email_name
		);
		$mail->addAddress($contact_form_address);
		$mail->addReplyTo($from_email, $email_name);

		// Use plain text email format
		$mail->isHTML(false);
		$mail->Subject = $auto_respond_subject;
		$mail->Body = $auto_respond_contents;

		$mail->send();
	} catch (Exception $e) {
		trigger_error("Mail Error: {$mail->ErrorInfo}");
	}
}
