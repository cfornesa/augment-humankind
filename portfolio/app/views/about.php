<?php
$pageTitle  = 'About — Fornesus Art';
$activePage = 'about';
$metaTitle = 'About — Fornesus Art';
$metaDescription = seo_excerpt($sections[0]['content'] ?? '', 170)
    ?: 'Learn more about Fornesus Art and get in touch.';
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$canonicalUrl = seo_absolute_url('/about');

ob_start();
?>
<div class="about-page">
    <header class="page-header">
        <h1 class="page-title">About</h1>
    </header>

    <div class="about-content">
        <?php foreach ($sections as $section): ?>
            <section class="bio-section"<?= $section['heading'] ? ' aria-labelledby="about-section-' . (int) $section['id'] . '"' : '' ?>>
                <?php if ($section['heading']): ?>
                    <h2 class="bio-heading" id="about-section-<?= (int) $section['id'] ?>"><?= htmlspecialchars($section['heading']) ?></h2>
                <?php endif ?>
                <div class="bio-text">
                    <?= $section['content'] ?>
                </div>
            </section>
        <?php endforeach ?>

        <?php if (empty($sections)): ?>
            <section class="bio-section">
                <div class="bio-text bio-placeholder">No biography has been written yet.</div>
            </section>
        <?php endif ?>

        <section class="contact-section" aria-labelledby="about-contact-heading">
            <h2 class="bio-heading" id="about-contact-heading">Correspondence</h2>

            <?php if ($contactSent): ?>
                <p class="contact-sent" role="status">Your message has been received.</p>
            <?php else: ?>
                <?php if ($contactError ?? null): ?>
                    <p class="contact-error" role="alert"><?= htmlspecialchars($contactError) ?></p>
                <?php endif ?>
                <form class="contact-form" method="POST" action="/about" id="contact-form-about">
                    <div class="field-row field-row-honeypot" aria-hidden="true">
                        <label for="contact-website-about">Website</label>
                        <input type="checkbox" id="contact-website-about" name="website" value="1" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="form_rendered_at" value="<?= time() ?>">
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-about" value="">
                    <div class="field-row">
                        <label for="contact-name">Name</label>
                        <input
                            type="text"
                            id="contact-name"
                            name="name"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            autocomplete="name"
                            autocapitalize="words"
                            spellcheck="false"
                            required
                        >
                    </div>
                    <div class="field-row">
                        <label for="contact-email">Email</label>
                        <input
                            type="email"
                            id="contact-email"
                            name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            autocomplete="email"
                            inputmode="email"
                            autocapitalize="off"
                            spellcheck="false"
                            required
                        >
                    </div>
                    <div class="field-row">
                        <label for="contact-message">Message</label>
                        <textarea
                            id="contact-message"
                            name="message"
                            rows="6"
                            autocapitalize="sentences"
                            spellcheck="true"
                            enterkeyhint="send"
                            required
                        ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="contact-submit">Send</button>
                </form>
                <?php if ($recaptchaSiteKey): ?>
                    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSiteKey) ?>"></script>
                    <script>
                    (function () {
                        var form = document.getElementById('contact-form-about');
                        var tokenField = document.getElementById('g-recaptcha-response-about');
                        if (!form || !tokenField || typeof grecaptcha === 'undefined') {
                            return;
                        }
                        form.addEventListener('submit', function (event) {
                            if (form.dataset.recaptchaSubmitted === 'true') {
                                return;
                            }
                            event.preventDefault();
                            grecaptcha.ready(function () {
                                grecaptcha.execute('<?= htmlspecialchars($recaptchaSiteKey) ?>', { action: 'contact' })
                                    .then(function (token) {
                                        tokenField.value = token;
                                        form.dataset.recaptchaSubmitted = 'true';
                                        form.submit();
                                    })
                                    .catch(function () {
                                        form.dataset.recaptchaSubmitted = 'true';
                                        form.submit();
                                    });
                            });
                        });
                    })();
                    </script>
                <?php endif ?>
            <?php endif ?>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
