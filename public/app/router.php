<?php

declare(strict_types=1);

require __DIR__ . '/helpers/auth.php';
require __DIR__ . '/helpers/oauth.php';
require __DIR__ . '/helpers/slugify.php';
require __DIR__ . '/helpers/seo.php';
require __DIR__ . '/helpers/upload.php';
require __DIR__ . '/helpers/feed-ingest.php';
require __DIR__ . '/helpers/encryption.php';
require __DIR__ . '/helpers/piece-render.php';
require __DIR__ . '/helpers/art-piece-generation.php';
require __DIR__ . '/helpers/navigation.php';
require __DIR__ . '/lib/ai/AiProviderClient.php';
require __DIR__ . '/models/AdminIdentity.php';
require __DIR__ . '/models/PlatformUser.php';
require __DIR__ . '/models/Page.php';
require __DIR__ . '/models/PageSection.php';
require __DIR__ . '/models/SiteSettings.php';
require __DIR__ . '/models/Category.php';
require __DIR__ . '/models/Exhibit.php';
require __DIR__ . '/models/Collection.php';
require __DIR__ . '/models/MediaFile.php';
require __DIR__ . '/models/NavigationItem.php';
require __DIR__ . '/models/BlogCategory.php';
require __DIR__ . '/models/BlogPost.php';
require __DIR__ . '/models/Comment.php';
require __DIR__ . '/models/Reaction.php';
require __DIR__ . '/models/ExhibitMediaItem.php';
require __DIR__ . '/models/PlatformArtPiece.php';
require __DIR__ . '/models/PlatformArtPieceVersion.php';
require __DIR__ . '/models/FeedSource.php';
require __DIR__ . '/models/SiteAsset.php';
require __DIR__ . '/models/MediaAsset.php';
require __DIR__ . '/models/UserAiSettings.php';
require __DIR__ . '/models/PlatformConnection.php';
require __DIR__ . '/models/PlatformCollection.php';
require __DIR__ . '/controllers/Admin/AuthController.php';
require __DIR__ . '/controllers/Admin/PagesController.php';
require __DIR__ . '/controllers/Admin/PortfolioController.php';
require __DIR__ . '/controllers/Admin/BlogAdminController.php';
require __DIR__ . '/controllers/Admin/MediaController.php';
require __DIR__ . '/controllers/Admin/TrashController.php';
require __DIR__ . '/controllers/Admin/NavigationController.php';
require __DIR__ . '/controllers/Admin/FeedSourcesAdminController.php';
require __DIR__ . '/controllers/Admin/SiteIdentityAdminController.php';
require __DIR__ . '/controllers/Admin/UserProfilesAdminController.php';
require __DIR__ . '/lib/syndication/AdapterFactory.php';
require __DIR__ . '/controllers/Admin/PlatformConnectionsAdminController.php';
require __DIR__ . '/controllers/Admin/PiecesAdminController.php';
require __DIR__ . '/controllers/Admin/PlatformCollectionsAdminController.php';
require __DIR__ . '/controllers/MediaServeController.php';
require __DIR__ . '/controllers/PortfolioController.php';
require __DIR__ . '/controllers/PiecesController.php';
require __DIR__ . '/controllers/CollectionsController.php';
require __DIR__ . '/controllers/EmbedController.php';
require __DIR__ . '/controllers/ImmersiveController.php';
require __DIR__ . '/controllers/ApiController.php';
require __DIR__ . '/controllers/BlogController.php';
require __DIR__ . '/controllers/PageController.php';

$publicRoutes = [
    ['GET', '/blog',                              [BlogController::class, 'index']],
    ['GET', '/blog/posts/([0-9]+)',              [BlogController::class, 'show']],
    ['GET', '/blog/categories',                  [BlogController::class, 'categories']],
    ['GET', '/blog/category/([a-z0-9-]+)',       [BlogController::class, 'category']],
    ['GET', '/blog/feeds',                       [BlogController::class, 'feeds']],
    ['GET', '/search',                           [BlogController::class, 'search']],

    ['GET', '/posts/([0-9]+)',                   [BlogController::class, 'redirectPost']],
    ['GET', '/categories/([a-z0-9-]+)',          [BlogController::class, 'redirectCategory']],
    ['GET', '/feeds',                            [BlogController::class, 'permanentRedirect'], ['/blog/feeds']],
    ['GET', '/p/([a-z0-9-]+)',                   [BlogController::class, 'redirectPage']],
    ['GET', '/feed.xml',                         [BlogController::class, 'atom']],
    ['GET', '/atom',                             [BlogController::class, 'atom']],
    ['GET', '/feed.json',                        [BlogController::class, 'jsonFeed']],
    ['GET', '/jsonfeed',                         [BlogController::class, 'jsonFeed']],
    ['GET', '/export.json',                      [BlogController::class, 'jsonFeed']],
    ['GET', '/export/json',                      [BlogController::class, 'jsonFeed']],
    ['GET', '/feeds/mf2',                        [BlogController::class, 'mf2']],

    ['GET', '/blog/category/([a-z0-9-]+)/feed\.xml',  [BlogController::class, 'categoryFeed'], ['xml']],
    ['GET', '/blog/category/([a-z0-9-]+)/feed\.json', [BlogController::class, 'categoryFeed'], ['json']],
    ['GET', '/categories/([a-z0-9-]+)/(?:feed\.xml|atom|feeds/atom)',      [BlogController::class, 'redirectCategoryFeed'], ['xml']],
    ['GET', '/categories/([a-z0-9-]+)/(?:feed\.json|jsonfeed|feeds/json)', [BlogController::class, 'redirectCategoryFeed'], ['json']],

    ['GET', '/([a-z0-9-]+)/feed\.xml',  [PageController::class, 'feed'], ['xml']],
    ['GET', '/([a-z0-9-]+)/feed\.json', [PageController::class, 'feed'], ['json']],
    ['GET', '/p/([a-z0-9-]+)/(?:feed\.xml|atom|feeds/atom)',      [BlogController::class, 'redirectPageFeed'], ['xml']],
    ['GET', '/p/([a-z0-9-]+)/(?:feed\.json|jsonfeed|feeds/json)', [BlogController::class, 'redirectPageFeed'], ['json']],

    ['GET', '/media/([0-9]+)', [MediaServeController::class, 'media']],
    ['GET', '/image/([0-9]+)', [MediaServeController::class, 'image']],

    ['GET', '/portfolio',                            [PortfolioController::class, 'gallery']],
    ['GET', '/portfolio/categories',                 [PortfolioController::class, 'categories']],
    ['GET', '/portfolio/category/([a-z0-9-]+)',      [PortfolioController::class, 'category']],
    ['GET', '/portfolio/collection/([a-z0-9-]+)',    [PortfolioController::class, 'collection']],
    ['GET', '/portfolio/exhibit/([a-z0-9-]+)',       [PortfolioController::class, 'exhibit']],

    ['GET', '/pieces',                               [PiecesController::class, 'index']],
    ['GET', '/pieces/([0-9]+)',                      [PiecesController::class, 'show']],
    ['GET', '/collections',                          [CollectionsController::class, 'index']],
    ['GET', '/collections/([a-z0-9-]+)',             [CollectionsController::class, 'show']],
    ['GET', '/embed/posts/([0-9]+)',                 [EmbedController::class, 'post']],
    ['GET', '/embed/pieces/([0-9]+)',                [EmbedController::class, 'piece']],
    ['GET', '/embed/pieces/([0-9]+)/data',           [EmbedController::class, 'pieceData']],
    ['GET', '/immersive/pieces/([0-9]+)',            [ImmersiveController::class, 'piece']],
    ['GET', '/immersive/images/([A-Za-z0-9_-]+)',    [ImmersiveController::class, 'image']],
    ['GET', '/immersive/collections/([a-z0-9-]+)',    [ImmersiveController::class, 'collection']],

    ['GET', '/api/feeds',                            [ApiController::class, 'feedsCatalog']],
    ['GET', '/api/feeds/atom',                       [BlogController::class, 'atom']],
    ['GET', '/api/feeds/json',                       [BlogController::class, 'jsonFeed']],
    ['GET', '/api/feeds/mf2',                        [BlogController::class, 'mf2']],
    ['GET', '/api/posts',                            [ApiController::class, 'posts']],
    ['GET', '/api/posts/([0-9]+)',                   [ApiController::class, 'post']],
    ['GET', '/api/categories',                       [ApiController::class, 'categories']],
    ['GET', '/api/categories/([a-z0-9-]+)',          [ApiController::class, 'category']],
    ['GET', '/api/categories/([a-z0-9-]+)/posts',    [ApiController::class, 'categoryPosts']],
    ['GET', '/api/p/([a-z0-9-]+)',                   [ApiController::class, 'page']],
    ['GET', '/api/p/([a-z0-9-]+)/feeds/atom',        [PageController::class, 'feed'], ['xml']],
    ['GET', '/api/p/([a-z0-9-]+)/feeds/json',        [PageController::class, 'feed'], ['json']],
    ['GET', '/api/art-pieces',                       [ApiController::class, 'artPieces']],
    ['GET', '/api/art-pieces/([0-9]+)',              [ApiController::class, 'artPiece']],
    ['GET', '/api/art-pieces/([0-9]+)/versions',     [ApiController::class, 'artPieceVersions']],
    ['GET', '/api/collections',                      [ApiController::class, 'collections']],
    ['GET', '/api/collections/([a-z0-9-]+)',         [ApiController::class, 'collection']],
    ['GET', '/api/collections/([a-z0-9-]+)/items',   [ApiController::class, 'collectionItems']],
    ['GET',  '/api/media-assets/([0-9]+)',            [ApiController::class, 'mediaAsset']],
    ['GET', '/api/media/([^/]+)/collections',         [ApiController::class, 'mediaAssetCollections']],
    ['GET', '/api/media/([^/]+)',                    [ApiController::class, 'mediaAssetByFilename']],
    ['GET', '/api/profile-photos/([^/]+)',          [ApiController::class, 'profilePhoto']],
    ['GET', '/api/runtimes/(.+)',                    [ApiController::class, 'runtimeAsset']],
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

    ['GET',  '/admin/posts',                  [BlogAdminController::class, 'postsIndex']],
    ['GET',  '/admin/posts/create',           [BlogAdminController::class, 'postCreate']],
    ['POST', '/admin/posts/create',           [BlogAdminController::class, 'postStore']],
    ['GET',  '/admin/posts/([0-9]+)/edit',    [BlogAdminController::class, 'postEdit']],
    ['POST', '/admin/posts/([0-9]+)/edit',    [BlogAdminController::class, 'postUpdate']],
    ['POST', '/admin/posts/([0-9]+)/delete',  [BlogAdminController::class, 'postDelete']],

    ['GET',  '/admin/comments',                  [BlogAdminController::class, 'commentsIndex']],
    ['POST', '/admin/comments/([0-9]+)/delete',  [BlogAdminController::class, 'commentDelete']],
    ['POST', '/admin/reactions/([0-9]+)/delete', [BlogAdminController::class, 'reactionDelete']],

    ['GET',  '/admin/exhibits',                 [PortfolioAdminController::class, 'exhibitsIndex']],
    ['GET',  '/admin/exhibits/create',          [PortfolioAdminController::class, 'exhibitCreate']],
    ['POST', '/admin/exhibits/create',          [PortfolioAdminController::class, 'exhibitStore']],
    ['GET',  '/admin/exhibits/([0-9]+)/edit',   [PortfolioAdminController::class, 'exhibitEdit']],
    ['POST', '/admin/exhibits/([0-9]+)/edit',   [PortfolioAdminController::class, 'exhibitUpdate']],
    ['POST', '/admin/exhibits/([0-9]+)/delete', [PortfolioAdminController::class, 'exhibitDelete']],
    ['POST', '/admin/exhibits/reorder',         [PortfolioAdminController::class, 'exhibitReorder']],

    ['GET',  '/admin/categories',                     [PortfolioAdminController::class, 'categoriesIndex']],
    ['GET',  '/admin/categories/create',              [PortfolioAdminController::class, 'categoryCreate']],
    ['POST', '/admin/categories/create',              [PortfolioAdminController::class, 'categoryStore']],
    ['POST', '/admin/categories/create-inline',       [PortfolioAdminController::class, 'categoryCreateInline']],
    ['GET',  '/admin/categories/([0-9]+)/edit',       [PortfolioAdminController::class, 'categoryEdit']],
    ['POST', '/admin/categories/([0-9]+)/edit',       [PortfolioAdminController::class, 'categoryUpdate']],
    ['POST', '/admin/categories/([0-9]+)/delete',     [PortfolioAdminController::class, 'categoryDelete']],
    ['POST', '/admin/categories/reorder',             [PortfolioAdminController::class, 'categoryReorder']],

    ['GET',  '/admin/collections',                     [PortfolioAdminController::class, 'collectionsIndex']],
    ['GET',  '/admin/collections/create',              [PortfolioAdminController::class, 'collectionCreate']],
    ['POST', '/admin/collections/create',              [PortfolioAdminController::class, 'collectionStore']],
    ['POST', '/admin/collections/create-inline',       [PortfolioAdminController::class, 'collectionCreateInline']],
    ['GET',  '/admin/collections/([0-9]+)/edit',       [PortfolioAdminController::class, 'collectionEdit']],
    ['POST', '/admin/collections/([0-9]+)/edit',       [PortfolioAdminController::class, 'collectionUpdate']],
    ['POST', '/admin/collections/([0-9]+)/delete',     [PortfolioAdminController::class, 'collectionDelete']],
    ['POST', '/admin/collections/reorder',             [PortfolioAdminController::class, 'collectionReorder']],

    ['GET',  '/admin/media',                 [MediaAdminController::class, 'index']],
    ['GET',  '/admin/media/library',         [MediaAdminController::class, 'library']],
    ['POST', '/admin/media/upload',          [MediaAdminController::class, 'upload']],
    ['POST', '/admin/media/import',          [MediaAdminController::class, 'import']],
    ['POST', '/admin/media/([0-9]+)/trash',  [MediaAdminController::class, 'trash']],
    ['POST', '/admin/media/([0-9]+)/destroy',[MediaAdminController::class, 'destroy']],
    ['POST', '/admin/media/asset/([0-9]+)/update',  [MediaAdminController::class, 'assetUpdate']],
    ['POST', '/admin/media/asset/([0-9]+)/trash',   [MediaAdminController::class, 'assetTrash']],
    ['POST', '/admin/media/asset/([0-9]+)/destroy', [MediaAdminController::class, 'assetDestroy']],

    ['GET',  '/admin/trash',         [TrashController::class, 'index']],
    ['POST', '/admin/trash/restore', [TrashController::class, 'restore']],
    ['POST', '/admin/trash/purge',   [TrashController::class, 'purge']],
    ['POST', '/admin/trash/empty',   [TrashController::class, 'empty']],

    ['GET',  '/admin/pieces',                  [PiecesAdminController::class, 'index']],
    ['GET',  '/admin/pieces/library',          [PiecesAdminController::class, 'library']],
    ['GET',  '/admin/ai/profiles',             [PiecesAdminController::class, 'aiProfilesLibrary']],
    ['GET',  '/admin/pieces/generate',         [PiecesAdminController::class, 'generateForm']],
    ['POST', '/admin/pieces/generate',         [PiecesAdminController::class, 'generate']],
    ['POST', '/admin/pieces/generate/save',    [PiecesAdminController::class, 'generateSave']],
    ['POST', '/admin/pieces/refine-ai',        [PiecesAdminController::class, 'refineAi']],
    ['GET',  '/admin/pieces/create',           [PiecesAdminController::class, 'create']],
    ['POST', '/admin/pieces/create',           [PiecesAdminController::class, 'store']],
    ['GET',  '/admin/pieces/([0-9]+)/edit',    [PiecesAdminController::class, 'edit']],
    ['POST', '/admin/pieces/([0-9]+)/edit',    [PiecesAdminController::class, 'update']],
    ['POST', '/admin/pieces/([0-9]+)/delete',  [PiecesAdminController::class, 'delete']],
    ['GET',  '/admin/pieces/([0-9]+)/versions', [PiecesAdminController::class, 'versions']],
    ['GET',  '/admin/pieces/([0-9]+)/versions/create', [PiecesAdminController::class, 'versionCreate']],
    ['POST', '/admin/pieces/([0-9]+)/versions/create', [PiecesAdminController::class, 'versionStore']],
    ['GET',  '/admin/pieces/([0-9]+)/versions/([0-9]+)/edit', [PiecesAdminController::class, 'versionEdit']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/edit', [PiecesAdminController::class, 'versionUpdate']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/delete', [PiecesAdminController::class, 'versionDelete']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/set-current', [PiecesAdminController::class, 'versionSetCurrent']],

    ['GET',  '/admin/platform-collections',         [PlatformCollectionsAdminController::class, 'index']],
    ['GET',  '/admin/platform-collections/create',  [PlatformCollectionsAdminController::class, 'create']],
    ['POST', '/admin/platform-collections/create',  [PlatformCollectionsAdminController::class, 'store']],
    ['GET',  '/admin/platform-collections/([0-9]+)/edit', [PlatformCollectionsAdminController::class, 'edit']],
    ['POST', '/admin/platform-collections/([0-9]+)/edit', [PlatformCollectionsAdminController::class, 'update']],
    ['POST', '/admin/platform-collections/([0-9]+)/delete', [PlatformCollectionsAdminController::class, 'delete']],
    ['GET',  '/admin/platform-collections/library', [PlatformCollectionsAdminController::class, 'library']],

    ['GET',  '/admin/feed-sources',        [FeedSourcesAdminController::class, 'index']],
    ['GET',  '/admin/feed-sources/create', [FeedSourcesAdminController::class, 'create']],
    ['POST', '/admin/feed-sources/create', [FeedSourcesAdminController::class, 'store']],
    ['GET',  '/admin/feed-sources/([0-9]+)/edit', [FeedSourcesAdminController::class, 'edit']],
    ['POST', '/admin/feed-sources/([0-9]+)/edit', [FeedSourcesAdminController::class, 'update']],
    ['POST', '/admin/feed-sources/([0-9]+)/delete', [FeedSourcesAdminController::class, 'delete']],
    ['POST', '/admin/feed-sources/([0-9]+)/ingest', [FeedSourcesAdminController::class, 'ingest']],
    ['POST', '/admin/feed-sources/approve', [FeedSourcesAdminController::class, 'approveImport']],
    ['POST', '/admin/feed-sources/reject',  [FeedSourcesAdminController::class, 'rejectImport']],

    ['GET',  '/admin/site-identity', [SiteIdentityAdminController::class, 'index']],
    ['POST', '/admin/site-identity/settings', [SiteIdentityAdminController::class, 'settingsUpdate']],
    ['POST', '/admin/site-identity/assets', [SiteIdentityAdminController::class, 'assetCreate']],
    ['POST', '/admin/site-identity/assets/([0-9]+)/delete', [SiteIdentityAdminController::class, 'assetDelete']],
    ['POST', '/admin/site-identity/media/([0-9]+)/delete', [SiteIdentityAdminController::class, 'mediaAssetDelete']],

    ['GET',  '/admin/user-profiles', [UserProfilesAdminController::class, 'index']],
    ['GET',  '/admin/user-profiles/([a-zA-Z0-9_-]+)/edit', [UserProfilesAdminController::class, 'userEdit']],
    ['POST', '/admin/user-profiles/([a-zA-Z0-9_-]+)/edit', [UserProfilesAdminController::class, 'userUpdate']],
    ['GET',  '/admin/user-profiles/settings/create', [UserProfilesAdminController::class, 'settingsCreate']],
    ['POST', '/admin/user-profiles/settings/create', [UserProfilesAdminController::class, 'settingsStore']],
    ['GET',  '/admin/user-profiles/settings/([0-9]+)/edit', [UserProfilesAdminController::class, 'settingsEdit']],
    ['POST', '/admin/user-profiles/settings/([0-9]+)/edit', [UserProfilesAdminController::class, 'settingsUpdate']],
    ['POST', '/admin/user-profiles/settings/([0-9]+)/delete', [UserProfilesAdminController::class, 'settingsDelete']],
    ['GET',  '/admin/user-profiles/keys/create', [UserProfilesAdminController::class, 'keyCreate']],
    ['POST', '/admin/user-profiles/keys/create', [UserProfilesAdminController::class, 'keyStore']],
    ['GET',  '/admin/user-profiles/keys/([0-9]+)/edit', [UserProfilesAdminController::class, 'keyEdit']],
    ['POST', '/admin/user-profiles/keys/([0-9]+)/edit', [UserProfilesAdminController::class, 'keyUpdate']],
    ['POST', '/admin/user-profiles/keys/([0-9]+)/delete', [UserProfilesAdminController::class, 'keyDelete']],

    ['GET',  '/admin/platform-connections', [PlatformConnectionsAdminController::class, 'index']],
    ['GET',  '/admin/platform-connections/create', [PlatformConnectionsAdminController::class, 'create']],
    ['POST', '/admin/platform-connections/create', [PlatformConnectionsAdminController::class, 'store']],
    ['GET',  '/admin/platform-connections/([0-9]+)/edit', [PlatformConnectionsAdminController::class, 'edit']],
    ['POST', '/admin/platform-connections/([0-9]+)/edit', [PlatformConnectionsAdminController::class, 'update']],
    ['POST', '/admin/platform-connections/([0-9]+)/delete', [PlatformConnectionsAdminController::class, 'delete']],
    ['GET',  '/admin/platform-connections/syndications/create', [PlatformConnectionsAdminController::class, 'syndicationCreate']],
    ['POST', '/admin/platform-connections/syndications/create', [PlatformConnectionsAdminController::class, 'syndicationStore']],
    ['POST', '/admin/platform-connections/syndications/([0-9]+)/delete', [PlatformConnectionsAdminController::class, 'syndicationDelete']],
    ['POST', '/admin/platform-connections/publish', [PlatformConnectionsAdminController::class, 'publish']],
    ['GET',  '/admin/platform-connections/auth/([a-z-]+)/start',    [PlatformConnectionsAdminController::class, 'oauthStart']],
    ['GET',  '/admin/platform-connections/auth/([a-z-]+)/callback', [PlatformConnectionsAdminController::class, 'oauthCallback']],
    ['GET',  '/admin/platform-connections/diagnostics', [PlatformConnectionsAdminController::class, 'diagnostics']],

    ['POST', '/admin/ai/process', [PiecesAdminController::class, 'aiProcessText']],
    ['POST', '/admin/ai/describe-image', [PiecesAdminController::class, 'aiDescribeImage']],

    ['POST', '/admin/user-profiles/([a-zA-Z0-9_-]+)/photo', [UserProfilesAdminController::class, 'userPhotoUpload']],

    ['GET',  '/admin/navigation',                 [NavigationController::class, 'index']],
    ['POST', '/admin/navigation/external',        [NavigationController::class, 'externalStore']],
    ['POST', '/admin/navigation/([0-9]+)/label',  [NavigationController::class, 'labelUpdate']],
    ['POST', '/admin/navigation/reorder',         [NavigationController::class, 'reorder']],
    ['POST', '/admin/navigation/([0-9]+)/toggle', [NavigationController::class, 'toggle']],
    ['POST', '/admin/navigation/([0-9]+)/target', [NavigationController::class, 'toggleTarget']],
    ['POST', '/admin/navigation/([0-9]+)/delete', [NavigationController::class, 'delete']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

foreach ([...$publicRoutes, ...$adminRoutes] as $route) {
    [$routeMethod, $pattern, $handler] = $route;
    if ($method !== $routeMethod || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
        continue;
    }

    array_shift($matches);
    if (isset($route[3]) && is_array($route[3])) {
        $matches = array_merge($route[3], $matches);
    }
    call_user_func_array($handler, $matches);
    exit;
}

// No match: unmatched /portfolio/*, /media|image/[id], and /admin/* fall
// through to public/index.php's normal $routes lookup, which renders AH's
// existing 404 page.
