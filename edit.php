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
$listing_id = (string) ($_GET['id'] ?? '');
if ($listing_id !== '' && (!ctype_digit($listing_id) || !in_array($listing_id, array_map('strval', call_user_todays(USERNAME, 'listings')), true))) {
    http_response_code(404);
    exit('Η καταχώριση δεν βρέθηκε.');
}
$title = $listing_id !== '' ? call_user_data(USERNAME, 'listings', $listing_id, 'title') : '';
$description = $listing_id !== '' ? call_user_data(USERNAME, 'listings', $listing_id, 'description') : '';
if (isset($_POST['save'])) {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($title === '' || $description === '') {
        $message = 'Συμπληρώστε τίτλο και περιγραφή.';
    } else {
        // Το αναγνωριστικό δημιουργείται στον server. Ο ιδιοκτήτης είναι πάντα ο συνδεδεμένος χρήστης.
        if ($listing_id === '') {
            $listing_id = date('YmdHis') . random_int(100000, 999999);
        }
        write_user_data(USERNAME, 'listings', $listing_id, 'title', $title);
        write_user_data(USERNAME, 'listings', $listing_id, 'description', $description);
        header('Location: /list.php');
        exit;
    }
}
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo επεξεργασίας καταχώρισης</title>
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
    <h1>Demo επεξεργασίας καταχώρισης</h1>

    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <p><label>Τίτλος <input name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" required></label></p>
        <p><label>Περιγραφή<br><textarea name="description" rows="8" cols="50" required><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
        <button name="save">Αποθήκευση</button>
    </form>
</main>
</body>

</html>

