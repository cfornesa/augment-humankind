<?php

declare(strict_types=1);

class PageController
{
    public static function show(string $slug): void
    {
        $page = Page::safeFindPublishedBySlug($slug);
        if (!$page) {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $sections = PageSection::allForPage((int) $page['id']);
        $contactSent = $_GET['sent'] ?? false;
        $contactError = null;
        $recaptchaSiteKey = $page['template'] === 'contact' ? recaptcha_site_key() : null;
        require dirname(__DIR__) . '/views/page.php';
    }

    public static function contact(): void
    {
        $page = Page::safeFindPublishedBySlug('contact');
        if (!$page) {
            AboutController::contact();
            return;
        }

        if (spam_honeypot_tripped($_POST) || spam_timetrap_tripped($_POST)) {
            header('Location: /contact?sent=1');
            exit;
        }

        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sections         = PageSection::allForPage((int) $page['id']);
            $contactSent      = false;
            $contactError     = 'Please fill all fields with a valid email.';
            $recaptchaSiteKey = recaptcha_site_key();
            require dirname(__DIR__) . '/views/page.php';
            return;
        }

        $verification = recaptcha_verify((string) ($_POST['g-recaptcha-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null);
        $isFlagged    = $verification['score'] < recaptcha_score_threshold() ? 1 : 0;

        $stmt = db()->prepare(
            'INSERT INTO contact_messages (name, email, message, is_flagged) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $message, $isFlagged]);

        header('Location: /contact?sent=1');
        exit;
    }

    public static function legacyAbout(): void
    {
        if (!Page::safeFindPublishedBySlug('bio')) {
            AboutController::index();
            return;
        }

        header('Location: /bio', true, 301);
        exit;
    }

    public static function contactPage(): void
    {
        if (!Page::safeFindPublishedBySlug('contact')) {
            AboutController::index();
            return;
        }

        self::show('contact');
    }
}
