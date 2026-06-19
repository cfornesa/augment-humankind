    </main>

    <?php
    $_ahFooterSettings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
    $_ahCopyrightLine = trim((string) ($_ahFooterSettings['copyright_line'] ?? ''));
    $_ahFooterCredit = trim((string) ($_ahFooterSettings['footer_credit'] ?? ''));
    ?>
    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= e($_ahCopyrightLine !== '' ? $_ahCopyrightLine : app_site_name()) ?><?= $_ahFooterCredit !== '' ? '. ' . e($_ahFooterCredit) : '' ?></p>
        <nav aria-label="Footer navigation">
            <a href="/">Home</a>
            <a href="/portfolio">Portfolio</a>
            <a href="/blog">Blog</a>
            <a href="/contact">Contact</a>
        </nav>
    </footer>
    <script src="/assets/js/main.js" defer></script>
</body>
</html>
