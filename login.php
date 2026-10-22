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

$message = '';
if (isset($_POST['logout'])) {
    setcookie('username', '', ['expires' => time() - 3600, 'path' => '/']);
    setcookie('passwd', '', ['expires' => time() - 3600, 'path' => '/']);
    header('Location: /login.php');
    exit;
}
if (isset($_POST['login'])) {
    $username = strtolower(trim((string) ($_POST['account'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (is_user_login($username, $password)) {
        // Cookies συμβατά με το users.php, μόνο μετά από επιτυχημένη σύνδεση.
        $options = ['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'];
        setcookie('username', $username, $options);
        setcookie('passwd', $password, $options);
        session_regenerate_id(true);
        header('Location: /list.php');
        exit;
    }
    $message = 'Το όνομα χρήστη ή ο κωδικός δεν είναι σωστά.';
}
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo σύνδεσης</title>
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
    <h1>Demo σύνδεσης</h1>

    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <p><label>Όνομα χρήστη <input name="account" autocomplete="username" required></label></p>
        <p><label>Κωδικός <input type="password" name="password" autocomplete="current-password" required></label></p>
        <button name="login">Σύνδεση</button>
    </form>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <p><button name="logout">Αποσύνδεση</button></p>
    </form>
</main>
</body>

</html>

