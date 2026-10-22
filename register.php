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
if (isset($_POST['register'])) {
    $username = strtolower(trim((string) ($_POST['account'] ?? '')));
    $name = trim((string) ($_POST['display-name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $repeat = (string) ($_POST['password-repeat'] ?? '');
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,39}$/D', $username) || $username === 'global' || str_starts_with($username, 'ip-')) {
        $message = 'Χρησιμοποιήστε 3 έως 40 λατινικούς χαρακτήρες, αριθμούς ή κάτω παύλα για το όνομα χρήστη.';
    } elseif (strlen(slugify($name)) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Συμπληρώστε όνομα και έγκυρο email.';
    } elseif ($password === '' || $repeat === '' || !test_passwords($password, $repeat)) {
        $message = 'Οι κωδικοί πρέπει να συμφωνούν και να έχουν τουλάχιστον 6 χαρακτήρες.';
    } elseif (user_exists($username) || call_username_by_email($email)) {
        $message = 'Το όνομα χρήστη ή το email χρησιμοποιείται ήδη.';
    } elseif (create_user_space($username, $password, 0, $email, $name, false)) {
        // Το demo δημιουργεί απλό χρήστη και γράφει χωριστά το εμφανιζόμενο όνομα.
        write_display_name($username, $name);
        header('Location: /login.php');
        exit;
    } else {
        $message = 'Η εγγραφή δεν ολοκληρώθηκε.';
    }
}
?>
<!DOCTYPE html>
<html lang="el-GR">

<head>
    <title>Demo εγγραφής</title>
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
    <h1>Demo εγγραφής</h1>

    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo-token'], ENT_QUOTES, 'UTF-8') ?>">
        <p><label>Όνομα χρήστη <input name="account" required></label></p>
        <p><label>Εμφανιζόμενο όνομα <input name="display-name" required></label></p>
        <p><label>Email <input type="email" name="email" required></label></p>
        <p><label>Κωδικός <input type="password" name="password" minlength="6" autocomplete="new-password" required></label></p>
        <p><label>Επανάληψη κωδικού <input type="password" name="password-repeat" minlength="6" autocomplete="new-password" required></label></p>
        <button name="register">Εγγραφή</button>
    </form>
</main>
</body>

</html>

