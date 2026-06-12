<?php

declare(strict_types=1);

class AboutController
{
    public static function index(): void
    {
        $sections         = BioSection::all();
        $contactSent      = $_GET['sent'] ?? false;
        $contactError     = null;
        $recaptchaSiteKey = recaptcha_site_key();
        require dirname(__DIR__) . '/views/about.php';
    }

    public static function contact(): void
    {
        if (spam_honeypot_tripped($_POST) || spam_timetrap_tripped($_POST)) {
            header('Location: /about?sent=1');
            exit;
        }

        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sections         = BioSection::all();
            $contactSent      = false;
            $contactError     = 'Please fill all fields with a valid email.';
            $recaptchaSiteKey = recaptcha_site_key();
            require dirname(__DIR__) . '/views/about.php';
            return;
        }

        $verification = recaptcha_verify((string) ($_POST['g-recaptcha-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null);
        $isFlagged    = $verification['score'] < recaptcha_score_threshold() ? 1 : 0;

        $stmt = db()->prepare(
            'INSERT INTO contact_messages (name, email, message, is_flagged) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $message, $isFlagged]);

        header('Location: /about?sent=1');
        exit;
    }
}
