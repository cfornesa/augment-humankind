    </main>

    <?php
    $_ahFooterSettings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
    $_ahCopyrightLine = trim((string) ($_ahFooterSettings['copyright_line'] ?? ''));
    $_ahFooterCredit = trim((string) ($_ahFooterSettings['footer_credit'] ?? ''));
    ?>
    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= e($_ahCopyrightLine !== '' ? $_ahCopyrightLine : app_site_name()) ?><?= $_ahFooterCredit !== '' ? '. ' . e($_ahFooterCredit) : '' ?></p>
        <nav aria-label="Footer navigation">
            <?php foreach ($navigationItems as $_ahFooterNavItem): ?>
            <a href="<?= e($_ahFooterNavItem['url']) ?>"<?= !empty($_ahFooterNavItem['target']) ? ' target="' . e($_ahFooterNavItem['target']) . '"' : '' ?>><?= e($_ahFooterNavItem['label']) ?></a>
            <?php endforeach ?>
        </nav>
    </footer>
    <script src="/assets/js/main.js" defer></script>
    <?php if (($_ahFooterSettings['theme'] ?? '') === 'celestial'): ?>
    <script src="/assets/js/cosmos.js" defer></script>
    <?php endif ?>
</body>
</html>
