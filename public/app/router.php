<?php

declare(strict_types=1);

require __DIR__ . '/helpers/auth.php';
require __DIR__ . '/helpers/oauth.php';
require __DIR__ . '/helpers/slugify.php';
require __DIR__ . '/helpers/seo.php';
require __DIR__ . '/helpers/upload.php';
require __DIR__ . '/helpers/navigation.php';
require __DIR__ . '/models/AdminIdentity.php';
require __DIR__ . '/models/Page.php';
require __DIR__ . '/models/PageSection.php';
require __DIR__ . '/models/Category.php';
require __DIR__ . '/models/Exhibit.php';
require __DIR__ . '/models/MediaFile.php';
require __DIR__ . '/models/NavigationItem.php';
require __DIR__ . '/models/ArtworkMediaItem.php';
require __DIR__ . '/models/Artwork.php';
require __DIR__ . '/controllers/Admin/AuthController.php';
require __DIR__ . '/controllers/Admin/PagesController.php';
require __DIR__ . '/controllers/Admin/PortfolioController.php';
require __DIR__ . '/controllers/Admin/MediaController.php';
require __DIR__ . '/controllers/Admin/TrashController.php';
require __DIR__ . '/controllers/Admin/NavigationController.php';
require __DIR__ . '/controllers/MediaServeController.php';
require __DIR__ . '/controllers/PortfolioController.php';

$publicRoutes = [
    ['GET', '/media/([0-9]+)', [MediaServeController::class, 'media']],
    ['GET', '/image/([0-9]+)', [MediaServeController::class, 'image']],

    ['GET', '/portfolio',                            [PortfolioController::class, 'gallery']],
    ['GET', '/portfolio/categories',                 [PortfolioController::class, 'categories']],
    ['GET', '/portfolio/category/([a-z0-9-]+)',      [PortfolioController::class, 'category']],
    ['GET', '/portfolio/exhibit/([a-z0-9-]+)',       [PortfolioController::class, 'exhibit']],
    ['GET', '/portfolio/work/([a-z0-9-]+)',          [PortfolioController::class, 'work']],
];

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

    ['GET',  '/admin/artworks',                 [PortfolioAdminController::class, 'artworksIndex']],
    ['GET',  '/admin/artworks/create',          [PortfolioAdminController::class, 'artworkCreate']],
    ['POST', '/admin/artworks/create',          [PortfolioAdminController::class, 'artworkStore']],
    ['GET',  '/admin/artworks/([0-9]+)/edit',   [PortfolioAdminController::class, 'artworkEdit']],
    ['POST', '/admin/artworks/([0-9]+)/edit',   [PortfolioAdminController::class, 'artworkUpdate']],
    ['POST', '/admin/artworks/([0-9]+)/delete', [PortfolioAdminController::class, 'artworkDelete']],
    ['POST', '/admin/artworks/reorder',         [PortfolioAdminController::class, 'artworkReorder']],

    ['GET',  '/admin/categories',                     [PortfolioAdminController::class, 'categoriesIndex']],
    ['GET',  '/admin/categories/create',              [PortfolioAdminController::class, 'categoryCreate']],
    ['POST', '/admin/categories/create',              [PortfolioAdminController::class, 'categoryStore']],
    ['POST', '/admin/categories/create-inline',       [PortfolioAdminController::class, 'categoryCreateInline']],
    ['GET',  '/admin/categories/([0-9]+)/edit',       [PortfolioAdminController::class, 'categoryEdit']],
    ['POST', '/admin/categories/([0-9]+)/edit',       [PortfolioAdminController::class, 'categoryUpdate']],
    ['POST', '/admin/categories/([0-9]+)/delete',     [PortfolioAdminController::class, 'categoryDelete']],
    ['POST', '/admin/categories/reorder',             [PortfolioAdminController::class, 'categoryReorder']],

    ['GET',  '/admin/exhibits',                     [PortfolioAdminController::class, 'exhibitsIndex']],
    ['GET',  '/admin/exhibits/create',              [PortfolioAdminController::class, 'exhibitCreate']],
    ['POST', '/admin/exhibits/create',              [PortfolioAdminController::class, 'exhibitStore']],
    ['POST', '/admin/exhibits/create-inline',       [PortfolioAdminController::class, 'exhibitCreateInline']],
    ['GET',  '/admin/exhibits/([0-9]+)/edit',       [PortfolioAdminController::class, 'exhibitEdit']],
    ['POST', '/admin/exhibits/([0-9]+)/edit',       [PortfolioAdminController::class, 'exhibitUpdate']],
    ['POST', '/admin/exhibits/([0-9]+)/delete',     [PortfolioAdminController::class, 'exhibitDelete']],
    ['POST', '/admin/exhibits/reorder',             [PortfolioAdminController::class, 'exhibitReorder']],

    ['GET',  '/admin/media',                 [MediaAdminController::class, 'index']],
    ['GET',  '/admin/media/library',         [MediaAdminController::class, 'library']],
    ['POST', '/admin/media/upload',          [MediaAdminController::class, 'upload']],
    ['POST', '/admin/media/import',          [MediaAdminController::class, 'import']],
    ['POST', '/admin/media/([0-9]+)/trash',  [MediaAdminController::class, 'trash']],
    ['POST', '/admin/media/([0-9]+)/destroy',[MediaAdminController::class, 'destroy']],

    ['GET',  '/admin/trash',         [TrashController::class, 'index']],
    ['POST', '/admin/trash/restore', [TrashController::class, 'restore']],
    ['POST', '/admin/trash/purge',   [TrashController::class, 'purge']],
    ['POST', '/admin/trash/empty',   [TrashController::class, 'empty']],

    ['GET',  '/admin/navigation',                 [NavigationController::class, 'index']],
    ['POST', '/admin/navigation/external',        [NavigationController::class, 'externalStore']],
    ['POST', '/admin/navigation/([0-9]+)/label',  [NavigationController::class, 'labelUpdate']],
    ['POST', '/admin/navigation/reorder',         [NavigationController::class, 'reorder']],
    ['POST', '/admin/navigation/([0-9]+)/toggle', [NavigationController::class, 'toggle']],
    ['POST', '/admin/navigation/([0-9]+)/target', [NavigationController::class, 'toggleTarget']],
    ['POST', '/admin/navigation/([0-9]+)/delete', [NavigationController::class, 'delete']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

foreach ([...$publicRoutes, ...$adminRoutes] as [$routeMethod, $pattern, $handler]) {
    if ($method !== $routeMethod || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
        continue;
    }

    array_shift($matches);
    call_user_func_array($handler, $matches);
    exit;
}

// No match: unmatched /portfolio/*, /media|image/[id], and /admin/* fall
// through to public/index.php's normal $routes lookup, which renders AH's
// existing 404 page.
