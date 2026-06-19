<?php

declare(strict_types=1);

class SharedAuthController
{
    public static function oauthCallback(): void
    {
        $provider = self::requestedProvider();

        if (AuthController::handlesPendingCallback($provider)) {
            AuthController::handleCallback($provider);
            return;
        }

        if (UserAuthController::handlesPendingCallback($provider)) {
            UserAuthController::handleCallback($provider);
            return;
        }

        header('Location: /admin/login?error=state');
        exit;
    }

    private static function requestedProvider(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#/auth/(github|google)/callback#', $path, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException('Unknown OAuth provider.');
    }
}
