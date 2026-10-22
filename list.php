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

// Διαβάζουμε μόνο τις καταχωρίσεις του συνδεδεμένου χρήστη.
$listings = call_user_todays(USERNAME, 'listings');
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo καταχωρίσεων</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
<nav aria-label="Demo χρηστών">
    <a href="/list.php">Καταχωρίσεις</a>
    <a href="/profile.php">Προφίλ</a>
    <a href="/login.php">Σύνδεση</a>
    <a href="/register.php">Εγγραφή</a>
</nav>
<main>
    <h1>Demo καταχωρίσεων</h1>

    <p><a href="/edit.php">Νέα καταχώριση</a></p>
    <?php if (!$listings) { ?>
        <p>Δεν έχετε καταχωρίσεις ακόμη.</p>
    <?php } ?>
    <?php foreach ($listings as $listing_id) { ?>
        <article>
            <h2><?= htmlspecialchars(call_user_data(USERNAME, 'listings', $listing_id, 'title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= nl2br(htmlspecialchars(call_user_data(USERNAME, 'listings', $listing_id, 'description'), ENT_QUOTES, 'UTF-8')) ?></p>
            <a href="/edit.php?id=<?= rawurlencode((string) $listing_id) ?>">Επεξεργασία</a>
        </article>
    <?php } ?>
</main>
</body>

</html>

