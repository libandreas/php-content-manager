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
$name = call_user_display_name(USERNAME);
$bio = call_user_data(USERNAME, 'info', false, 'bio');
if (isset($_POST['save'])) {
    $name = trim((string) ($_POST['display-name'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    if (strlen(slugify($name)) < 3) {
        $message = 'Συμπληρώστε ένα όνομα με τουλάχιστον τρεις χαρακτήρες.';
    } else {
        write_display_name(USERNAME, $name);
        write_user_data(USERNAME, 'info', false, 'bio', $bio);
        header('Location: /profile.php');
        exit;
    }
}
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo επεξεργασίας προφίλ</title>
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
    <h1>Demo επεξεργασίας προφίλ</h1>

    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <p><label>Εμφανιζόμενο όνομα <input name="display-name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required></label></p>
        <p><label>Λίγα λόγια για εμένα<br><textarea name="bio" rows="6" cols="50"><?= htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
        <button name="save">Αποθήκευση</button>
    </form>
</main>
</body>

</html>

