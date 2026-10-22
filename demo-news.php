<?php
// Χρησιμοποιούμε την ανάγνωση Markdown του Content 2, όπως στο news.php.
// Η λίστα συνδέεται με το demo-news-template.php, το κοινό template όλων των άρθρων.
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-2.php";

$articles = read_news_entries();
krsort($articles, SORT_STRING);
$page_title = 'Αυτοσχέδια εργαλεία για τον ιστό';

// Δύο άρθρα ανά σελίδα: τα έξι παραδείγματα εμφανίζονται σε τρεις σελίδες.
$per_page = 2;
$page_count = max(1, (int) ceil(count($articles) / $per_page));
$requested_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = min($page_count, max(1, (int) $requested_page));
$page_articles = array_slice($articles, ($page - 1) * $per_page, $per_page, true);

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
    <nav aria-label="Παραδείγματα CMS">
        <a href="/demo-news.php">Ιστολόγιο</a>
        <a href="/demo-page-1.php">Demo Page 1 - HTML</a>
        <a href="/demo-page-2.php">Demo Page 2 - HTML</a>
        <a href="/demo-page-3.php">Demo Page 3 - HTML</a>
        <a href="/demo-page-4.php">Demo Page 4 - Live Edit</a>
    </nav>
    <main>
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Μικρές κατασκευές, δικές μου ιδέες και η χαρά τού να φτιάχνεις εργαλεία για τις ιστοσελίδες σου.</p>
            <?php foreach ($page_articles as $id => $entry): ?>
                <article>
                    <?php // Το today επιλέγει το άρθρο και το page διατηρεί τη σελίδα επιστροφής. ?>
                    <h2><a href="/demo-news-template.php?today=<?= rawurlencode((string) $id) ?>&amp;page=<?= $page ?>"><?= htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                    <p><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$articles): ?><p>Δεν υπάρχουν ακόμη άρθρα.</p><?php endif; ?>
            <?php if ($page_count > 1): ?>
                <nav aria-label="Σελίδες άρθρων">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Προηγούμενη</a><?php endif; ?>
                    <?php for ($number = 1; $number <= $page_count; $number++): ?>
                        <?php if ($number === $page): ?>
                            <strong aria-current="page"><?= $number ?></strong>
                        <?php else: ?>
                            <a href="?page=<?= $number ?>" aria-label="Σελίδα <?= $number ?>"><?= $number ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $page_count): ?><a href="?page=<?= $page + 1 ?>">Επόμενη</a><?php endif; ?>
                </nav>
            <?php endif; ?>
    </main>
</body>
</html>
