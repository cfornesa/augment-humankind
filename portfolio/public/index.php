<?php

declare(strict_types=1);

// PHP built-in server: serve static files (CSS, JS, images) directly
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/helpers/auth.php';
require dirname(__DIR__) . '/app/helpers/oauth.php';
require dirname(__DIR__) . '/app/helpers/recaptcha.php';
require dirname(__DIR__) . '/app/helpers/seo.php';
require dirname(__DIR__) . '/app/models/MediaFile.php';
require dirname(__DIR__) . '/app/models/AdminIdentity.php';
require dirname(__DIR__) . '/app/models/ArtworkMediaItem.php';
require dirname(__DIR__) . '/app/helpers/upload.php';
require dirname(__DIR__) . '/app/helpers/slugify.php';
require dirname(__DIR__) . '/app/models/Category.php';
require dirname(__DIR__) . '/app/models/Artwork.php';
require dirname(__DIR__) . '/app/models/Exhibit.php';
require dirname(__DIR__) . '/app/models/BioSection.php';
require dirname(__DIR__) . '/app/models/Page.php';
require dirname(__DIR__) . '/app/models/PageSection.php';
require dirname(__DIR__) . '/app/models/NavigationItem.php';
require dirname(__DIR__) . '/app/models/ContactMessage.php';
require dirname(__DIR__) . '/app/controllers/GalleryController.php';
require dirname(__DIR__) . '/app/controllers/WorkController.php';
require dirname(__DIR__) . '/app/controllers/AboutController.php';
require dirname(__DIR__) . '/app/controllers/PageController.php';
require dirname(__DIR__) . '/app/controllers/CategoriesController.php';
require dirname(__DIR__) . '/app/controllers/ExhibitController.php';
require dirname(__DIR__) . '/app/controllers/AdminController.php';
require dirname(__DIR__) . '/app/controllers/ImageController.php';
require dirname(__DIR__) . '/app/controllers/MediaController.php';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Strip trailing slash except root
if ($uri !== '/' && str_ends_with($uri, '/')) {
    header('Location: ' . rtrim($uri, '/'), true, 301);
    exit;
}

// Route table
$routes = [
    // Image serving (blob storage)
    ['GET',  '/image/([0-9]+)',                [ImageController::class,      'serve']],
    ['GET',  '/media/([0-9]+)',                [MediaController::class,      'serve']],

    // Public
    ['GET',  '/',                              [GalleryController::class,    'index']],
    ['GET',  '/categories',                    [CategoriesController::class, 'index']],
    ['GET',  '/category/([a-z0-9-]+)',         [CategoriesController::class, 'show']],
    ['GET',  '/exhibit/([a-z0-9-]+)',          [ExhibitController::class,    'show']],
    ['GET',  '/about',                         [PageController::class,       'legacyAbout']],
    ['GET',  '/contact',                       [PageController::class,       'contactPage']],
    ['POST', '/about',                         [PageController::class,       'contact']],
    ['POST', '/contact',                       [PageController::class,       'contact']],
    ['GET',  '/work/([a-z0-9-]+)',             [WorkController::class,       'show']],

    // Admin auth
    ['GET',  '/admin',                         [AdminController::class, 'dashboard']],
    ['GET',  '/admin/login',                   [AdminController::class, 'loginForm']],
    ['GET',  '/admin/auth/github/start',       [AdminController::class, 'oauthStart']],
    ['GET',  '/admin/auth/github/callback',    [AdminController::class, 'oauthCallback']],
    ['GET',  '/admin/auth/google/start',       [AdminController::class, 'oauthStart']],
    ['GET',  '/admin/auth/google/callback',    [AdminController::class, 'oauthCallback']],
    ['GET',  '/admin/logout',                  [AdminController::class, 'logout']],

    // Admin artworks
    ['GET',  '/admin/artworks',                [AdminController::class, 'artworksIndex']],
    ['GET',  '/admin/artworks/create',         [AdminController::class, 'artworkCreate']],
    ['POST', '/admin/artworks/create',         [AdminController::class, 'artworkStore']],
    ['GET',  '/admin/artworks/([0-9]+)/edit',  [AdminController::class, 'artworkEdit']],
    ['POST', '/admin/artworks/([0-9]+)/edit',  [AdminController::class, 'artworkUpdate']],
    ['POST', '/admin/artworks/([0-9]+)/delete',[AdminController::class, 'artworkDelete']],
    ['POST', '/admin/artworks/([0-9]+)/order', [AdminController::class, 'artworkOrder']],
    ['POST', '/admin/artworks/reorder',        [AdminController::class, 'artworkReorder']],

    // Admin categories
    ['GET',  '/admin/categories',                      [AdminController::class, 'categoriesIndex']],
    ['GET',  '/admin/categories/create',               [AdminController::class, 'categoryCreate']],
    ['POST', '/admin/categories/create',               [AdminController::class, 'categoryStore']],
    ['POST', '/admin/categories/create-inline',        [AdminController::class, 'categoryCreateInline']],
    ['GET',  '/admin/categories/([0-9]+)/edit',        [AdminController::class, 'categoryEdit']],
    ['POST', '/admin/categories/([0-9]+)/edit',        [AdminController::class, 'categoryUpdate']],
    ['POST', '/admin/categories/([0-9]+)/delete',      [AdminController::class, 'categoryDelete']],
    ['POST', '/admin/categories/reorder',              [AdminController::class, 'categoryReorder']],

    // Admin exhibits
    ['GET',  '/admin/exhibits',                        [AdminController::class, 'exhibitsIndex']],
    ['GET',  '/admin/exhibits/create',                 [AdminController::class, 'exhibitCreate']],
    ['POST', '/admin/exhibits/create',                 [AdminController::class, 'exhibitStore']],
    ['POST', '/admin/exhibits/create-inline',          [AdminController::class, 'exhibitCreateInline']],
    ['GET',  '/admin/exhibits/([0-9]+)/edit',          [AdminController::class, 'exhibitEdit']],
    ['POST', '/admin/exhibits/([0-9]+)/edit',          [AdminController::class, 'exhibitUpdate']],
    ['POST', '/admin/exhibits/([0-9]+)/delete',        [AdminController::class, 'exhibitDelete']],
    ['POST', '/admin/exhibits/reorder',                [AdminController::class, 'exhibitReorder']],

    // Admin pages
    ['GET',  '/admin/bio',                             [AdminController::class, 'pagesLegacyRedirect']],
    ['GET',  '/admin/pages',                           [AdminController::class, 'pagesIndex']],
    ['GET',  '/admin/pages/create',                    [AdminController::class, 'pageCreate']],
    ['POST', '/admin/pages/create',                    [AdminController::class, 'pageStore']],
    ['GET',  '/admin/pages/trash',                     [AdminController::class, 'pagesTrash']],
    ['POST', '/admin/pages/trash/empty',               [AdminController::class, 'pagesTrashEmpty']],
    ['POST', '/admin/pages/([0-9]+)/restore',          [AdminController::class, 'pageRestore']],
    ['POST', '/admin/pages/([0-9]+)/hard-delete',      [AdminController::class, 'pageHardDelete']],
    ['POST', '/admin/pages/([0-9]+)/toggle-nav',       [AdminController::class, 'pageToggleNav']],
    ['GET',  '/admin/pages/([0-9]+)/edit',             [AdminController::class, 'pageEdit']],
    ['POST', '/admin/pages/([0-9]+)/edit',             [AdminController::class, 'pageUpdate']],
    ['POST', '/admin/pages/([0-9]+)/delete',           [AdminController::class, 'pageDelete']],
    ['POST', '/admin/pages/reorder',                   [AdminController::class, 'pageReorder']],
    ['GET',  '/admin/pages/([0-9]+)/sections/create',  [AdminController::class, 'pageSectionCreate']],
    ['POST', '/admin/pages/([0-9]+)/sections/create',  [AdminController::class, 'pageSectionStore']],
    ['GET',  '/admin/pages/sections/([0-9]+)/edit',    [AdminController::class, 'pageSectionEdit']],
    ['POST', '/admin/pages/sections/([0-9]+)/edit',    [AdminController::class, 'pageSectionUpdate']],
    ['POST', '/admin/pages/sections/([0-9]+)/delete',  [AdminController::class, 'pageSectionDelete']],
    ['POST', '/admin/pages/([0-9]+)/sections/reorder', [AdminController::class, 'pageSectionReorder']],
    ['GET',  '/admin/navigation',                      [AdminController::class, 'navigationIndex']],
    ['POST', '/admin/navigation/external',             [AdminController::class, 'navigationExternalStore']],
    ['POST', '/admin/navigation/([0-9]+)/label',       [AdminController::class, 'navigationLabelUpdate']],
    ['POST', '/admin/navigation/reorder',              [AdminController::class, 'navigationReorder']],
    ['POST', '/admin/navigation/([0-9]+)/toggle',      [AdminController::class, 'navigationToggle']],
    ['POST', '/admin/navigation/([0-9]+)/target',      [AdminController::class, 'navigationToggleTarget']],
    ['POST', '/admin/navigation/([0-9]+)/delete',      [AdminController::class, 'navigationDelete']],

    // Admin messages
    ['GET',  '/admin/messages',                        [AdminController::class, 'messagesIndex']],
    ['POST', '/admin/messages/reorder',                [AdminController::class, 'messageReorder']],
    ['POST', '/admin/messages/([0-9]+)/toggle-read',   [AdminController::class, 'messageToggleRead']],
    ['POST', '/admin/messages/([0-9]+)/toggle-flag',   [AdminController::class, 'messageToggleFlag']],
    ['POST', '/admin/messages/([0-9]+)/toggle-pin',    [AdminController::class, 'messageTogglePin']],
    ['POST', '/admin/messages/([0-9]+)/delete',        [AdminController::class, 'messageDelete']],

    // Admin media library
    ['GET',  '/admin/media/library',                   [AdminController::class, 'mediaLibrary']],
    ['POST', '/admin/media/import',                    [AdminController::class, 'mediaImport']],
    ['GET',  '/admin/media',                           [AdminController::class, 'mediaIndex']],
    ['POST', '/admin/media/upload',                    [AdminController::class, 'mediaUpload']],
    ['POST', '/admin/media/([0-9]+)/trash',            [AdminController::class, 'mediaTrash']],
    ['POST', '/admin/media/([0-9]+)/destroy',          [AdminController::class, 'mediaDestroy']],

    // Admin recycle bin
    ['GET',  '/admin/trash',                           [AdminController::class, 'trashIndex']],
    ['POST', '/admin/trash/restore',                   [AdminController::class, 'trashRestore']],
    ['POST', '/admin/trash/purge',                     [AdminController::class, 'trashPurge']],
    ['POST', '/admin/trash/empty',                     [AdminController::class, 'trashEmpty']],
];

$routes[] = ['GET', '/([a-z0-9-]+)', [PageController::class, 'show']];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($method !== $routeMethod) {
        continue;
    }
    $regex = '#^' . $pattern . '$#';
    if (preg_match($regex, $uri, $matches)) {
        array_shift($matches);
        call_user_func_array([$handler[0], $handler[1]], $matches);
        exit;
    }
}

// 404
http_response_code(404);
require dirname(__DIR__) . '/app/views/404.php';
