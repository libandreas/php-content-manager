<?php
// Κοινό template άρθρου: το demo-news.php στέλνει το αναγνωριστικό στο today.
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-2.php";

$article_id = $_GET['today'] ?? '';
$article = [];
if (is_string($article_id) && preg_match('/^\d+$/D', $article_id)) {
    $article = read_news_article($article_id);
}
if (!$article) {
    http_response_code(404);
}
$page_title = $article ? $article['title'] : 'Το άρθρο δεν βρέθηκε';
$page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

// Το Content 2 προσθέτει αυτόματα τον σύνδεσμο επεξεργασίας για τον διαχειριστή.
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
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
    <main>
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($article): ?>
            <?php if ($article['image'] !== ''): ?>
                <img src="<?= htmlspecialchars($article['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($article['image-alt'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <?php // Το Content 2 μετατρέπει το Markdown του διαχειριστή σε HTML. ?>
            <article class="html"><?= $article['html'] ?></article>
        <?php else: ?>
            <p>Το άρθρο που ζητήσατε δεν υπάρχει.</p>
        <?php endif; ?>
        <p><a href="/demo-news.php?page=<?= $page ?>">Επιστροφή στα άρθρα</a></p>
    </main>
</body>
</html>
