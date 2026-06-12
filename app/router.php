<?php

declare(strict_types=1);

require __DIR__ . '/helpers/auth.php';
require __DIR__ . '/helpers/oauth.php';
require __DIR__ . '/helpers/slugify.php';
require __DIR__ . '/helpers/seo.php';
require __DIR__ . '/models/AdminIdentity.php';
require __DIR__ . '/models/Page.php';
require __DIR__ . '/models/PageSection.php';
require __DIR__ . '/controllers/Admin/AuthController.php';
require __DIR__ . '/controllers/Admin/PagesController.php';

$adminRoutes = [
    ['GET',  '/admin',                      [AuthController::class, 'dashboard']],
    ['GET',  '/admin/login',                [AuthController::class, 'loginForm']],
    ['GET',  '/admin/auth/github/start',    [AuthController::class, 'oauthStart']],
    ['GET',  '/admin/auth/github/callback', [AuthController::class, 'oauthCallback']],
    ['GET',  '/admin/auth/google/start',    [AuthController::class, 'oauthStart']],
    ['GET',  '/admin/auth/google/callback', [AuthController::class, 'oauthCallback']],
    ['GET',  '/admin/logout',               [AuthController::class, 'logout']],

    ['GET',  '/admin/pages',                           [PagesController::class, 'index']],
    ['GET',  '/admin/pages/create',                    [PagesController::class, 'create']],
    ['POST', '/admin/pages/create',                    [PagesController::class, 'store']],
    ['GET',  '/admin/pages/trash',                     [PagesController::class, 'trash']],
    ['POST', '/admin/pages/trash/empty',               [PagesController::class, 'trashEmpty']],
    ['POST', '/admin/pages/reorder',                   [PagesController::class, 'reorder']],
    ['POST', '/admin/pages/([0-9]+)/restore',          [PagesController::class, 'restore']],
    ['POST', '/admin/pages/([0-9]+)/hard-delete',      [PagesController::class, 'hardDelete']],
    ['GET',  '/admin/pages/([0-9]+)/edit',             [PagesController::class, 'edit']],
    ['POST', '/admin/pages/([0-9]+)/edit',             [PagesController::class, 'update']],
    ['POST', '/admin/pages/([0-9]+)/delete',           [PagesController::class, 'delete']],
    ['GET',  '/admin/pages/([0-9]+)/sections/create',  [PagesController::class, 'sectionCreate']],
    ['POST', '/admin/pages/([0-9]+)/sections/create',  [PagesController::class, 'sectionStore']],
    ['GET',  '/admin/pages/sections/([0-9]+)/edit',    [PagesController::class, 'sectionEdit']],
    ['POST', '/admin/pages/sections/([0-9]+)/edit',    [PagesController::class, 'sectionUpdate']],
    ['POST', '/admin/pages/sections/([0-9]+)/delete',  [PagesController::class, 'sectionDelete']],
    ['POST', '/admin/pages/([0-9]+)/sections/reorder', [PagesController::class, 'sectionReorder']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

foreach ($adminRoutes as [$routeMethod, $pattern, $handler]) {
    if ($method !== $routeMethod || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
        continue;
    }

    array_shift($matches);
    call_user_func_array($handler, $matches);
    exit;
}

// No match: /portfolio*, /media|image/[id], and unmatched /admin/* fall
// through to public/index.php's normal $routes lookup, which renders AH's
// existing 404 page.
