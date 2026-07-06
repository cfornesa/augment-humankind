    </main>

    <?php
    $_ahFooterSettings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
    $_ahCopyrightLine = trim((string) ($_ahFooterSettings['copyright_line'] ?? ''));
    $_ahCopyrightHtml = function_exists('public_copy_footer_credit_html')
        ? public_copy_footer_credit_html($_ahCopyrightLine !== '' ? $_ahCopyrightLine : app_site_name())
        : e($_ahCopyrightLine !== '' ? $_ahCopyrightLine : app_site_name());
    $_ahFooterCreditHtml = function_exists('public_copy_footer_credit_html')
        ? public_copy_footer_credit_html((string) ($_ahFooterSettings['footer_credit'] ?? ''))
        : '';
    ?>
    <footer class="site-footer">
        <div class="site-footer-text">
            &copy; <?= date('Y') ?> <?= $_ahCopyrightHtml ?><?= $_ahFooterCreditHtml !== '' ? '. ' . $_ahFooterCreditHtml : '' ?>
        </div>
        <nav aria-label="Footer navigation">
            <?php foreach ($navigationItems as $_ahFooterNavItem): ?>
            <a href="<?= e($_ahFooterNavItem['url']) ?>"<?= !empty($_ahFooterNavItem['target']) ? ' target="' . e($_ahFooterNavItem['target']) . '"' : '' ?>><?= e($_ahFooterNavItem['label']) ?></a>
            <?php endforeach ?>
        </nav>
    </footer>
    <script src="/assets/js/main.js" defer></script>
    <?php if (!empty($_ahFooterSettings['custom_js'])): ?>
    <script><?= $_ahFooterSettings['custom_js'] ?></script>
    <?php endif ?>
</body>
</html>
