<?php
// Το Content 3 προσθέτει τον σύνδεσμο επεξεργασίας για συνδεδεμένο διαχειριστή.
require_once __DIR__ . '/common/content-3.php';
include_once $_SERVER["DOCUMENT_ROOT"] . "/common/content-9.php";
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Χειροποίητα εργαλεία PHP για ιστοσελίδες που εξελίσσονται μαζί με τις ιδέες σου.">
    <title>Demo Page 1 - HTML</title>
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
    <nav aria-label="Παραδείγματα CMS"><a href="/demo-news.php">Ιστολόγιο</a> <a href="/demo-page-2.php">Σελίδες με panel</a> <a href="/demo-page-3.php">Το εργαστήριο</a> <a href="/demo-page-4.php">Ζωντανή επεξεργασία</a></nav>
    <main>
        <h1>Ένα δικό μου CMS, στα μέτρα των δικών μου ιδεών</h1>
        <p class="text-intro">Μια χειροποίητη ιστοσελίδα αξίζει και τα δικά της εργαλεία. Αυτό το project είναι η δική μου εξερεύνηση στον κόσμο της διαχείρισης περιεχομένου.</p>
        <section class="html">
            <h2>Η χαρά τού να φτιάχνεις κάτι δικό σου</h2>
            <p>Σελίδες, άρθρα και χρήστες συναντιούνται σε μια συλλογή εργαλείων με PHP. Η ιδέα είναι να κρατάς την ελευθερία στον σχεδιασμό σου και να μπορείς να εξελίσσεις το περιεχόμενο πιο εύκολα.</p>
            <p>Ένα μικρό CMS μπορεί να γίνει το εργαστήριο για το επόμενο blog, την προσωπική σου σελίδα ή μια εφαρμογή που ακόμη σχεδιάζεις. Αυτό είναι που κάνει την κατασκευή του τόσο ενδιαφέρουσα!</p>
        </section>
    </main>
</body>
</html>
