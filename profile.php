<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/functions.php";
// Διατηρούμε τον κωδικό όπως δόθηκε, χωρίς μετατροπή πεζών/κεφαλαίων.
define('PASSWD', (string) ($_COOKIE['passwd'] ?? ''));
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/users.php";
// Τα demo έχουν δική τους πλοήγηση αντί για τα εργαλεία του production buffer.
ob_end_clean();
session_start();
if (empty($_SESSION['demo-token'])) {
    $_SESSION['demo-token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['demo-token'], (string) ($_POST['token'] ?? ''))) {
    http_response_code(403);
    exit('Η φόρμα έληξε. Ανανεώστε τη σελίδα.');
}
if (!is_user_login(USERNAME, PASSWD)) {
    header('Location: /login.php');
    exit;
}

$message = '';
if (isset($_POST['send-email'])) {
    // Δοκιμή αποστολής μόνο προς το email του συνδεδεμένου χρήστη.
    // Απαιτεί Composer και τις ρυθμίσεις SMTP του global χρήστη.
    $email = call_user_email(USERNAME);
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') || !call_user_data('global', 'info', false, 'smtp-host') || !call_user_data('global', 'info', false, 'smtp-username') || !call_user_data('global', 'info', false, 'smtp-password')) {
        $message = 'Εγκαταστήστε τις βιβλιοθήκες Composer και ρυθμίστε το SMTP για τη δοκιμή email.';
    } elseif (system_email($email, 'Δοκιμή users.php', 'Αυτό είναι ένα δοκιμαστικό μήνυμα από το demo προφίλ.', false, false, false, 'Με εκτίμηση')) {
        $message = 'Το μήνυμα παραδόθηκε στον διακομιστή αποστολής.';
    } else {
        $message = 'Η αποστολή απέτυχε.';
    }
}
$name = call_user_display_name(USERNAME);
$email = call_user_email(USERNAME);
$bio = call_user_data(USERNAME, 'info', false, 'bio');
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo προφίλ</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script defer src="/common/content-9.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', async () => {
            const pageUrl = new URL(location.href);
            pageUrl.search = '';
            pageUrl.hash = '';
            try {
                const response = await fetch('/common/content-9.php', {
                    method: 'POST',
                    body: new URLSearchParams({ type: 'request', location: pageUrl.href, referrer: document.referrer }),
                    credentials: 'omit',
                    cache: 'no-store',
                    keepalive: true
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
            } catch (error) {
                console.error('Analytics: αποτυχία καταγραφής επίσκεψης | message=' + error.message);
            }
        });
    </script>
</head>

<body>
<nav aria-label="Demo χρηστών">
    <a href="/list.php">Καταχωρίσεις</a>
    <a href="/profile.php">Προφίλ</a>
    <a href="/login.php">Σύνδεση</a>
    <a href="/register.php">Εγγραφή</a>
</nav>
<main>
    <h1>Demo προφίλ</h1>

    <h2><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= nl2br(htmlspecialchars($bio, ENT_QUOTES, 'UTF-8')) ?></p>
    <p><a href="/edit-profile.php">Επεξεργασία προφίλ</a></p>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <button name="send-email">Αποστολή δοκιμαστικού email σε εμένα</button>
    </form>
</main>
</body>

</html>

