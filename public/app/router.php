<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/oauth.php';
require_once __DIR__ . '/helpers/icons.php';
require_once __DIR__ . '/helpers/slugify.php';
require_once __DIR__ . '/helpers/seo.php';
require_once __DIR__ . '/helpers/schema.php';
require_once __DIR__ . '/helpers/upload.php';
require_once __DIR__ . '/helpers/feed-ingest.php';
require_once __DIR__ . '/helpers/encryption.php';
require_once __DIR__ . '/helpers/audit-log.php';
require_once __DIR__ . '/helpers/rate-limit.php';
require_once __DIR__ . '/helpers/public-copy.php';
require_once __DIR__ . '/helpers/piece-render.php';
require_once __DIR__ . '/helpers/art-piece-generation.php';
require_once __DIR__ . '/helpers/site-theme-generation.php';
require_once __DIR__ . '/helpers/navigation.php';
require_once __DIR__ . '/helpers/admin-navigation.php';
require_once __DIR__ . '/helpers/features.php';
require_once __DIR__ . '/helpers/platform-ui.php';
require_once __DIR__ . '/helpers/reorder.php';
require_once __DIR__ . '/lib/ai/AiProviderClient.php';
require_once __DIR__ . '/models/AdminIdentity.php';
require_once __DIR__ . '/models/PlatformUser.php';
require_once __DIR__ . '/models/Page.php';
require_once __DIR__ . '/models/PageSection.php';
require_once __DIR__ . '/models/Form.php';
require_once __DIR__ . '/models/ArtPieceStarterTemplate.php';
require_once __DIR__ . '/models/PostSection.php';
require_once __DIR__ . '/models/SiteSettings.php';
require_once __DIR__ . '/models/Category.php';
require_once __DIR__ . '/models/Exhibit.php';
require_once __DIR__ . '/models/Collection.php';
require_once __DIR__ . '/models/MediaFile.php';
require_once __DIR__ . '/models/NavigationItem.php';
require_once __DIR__ . '/models/BlogCategory.php';
require_once __DIR__ . '/models/BlogPost.php';
require_once __DIR__ . '/models/Comment.php';
require_once __DIR__ . '/models/Reaction.php';
require_once __DIR__ . '/models/ExhibitMediaItem.php';
require_once __DIR__ . '/models/PlatformArtPiece.php';
require_once __DIR__ . '/models/PlatformArtPieceVersion.php';
require_once __DIR__ . '/models/FeedSource.php';
require_once __DIR__ . '/models/SiteAsset.php';
require_once __DIR__ . '/models/MediaAsset.php';
require_once __DIR__ . '/models/SiteThemeSnapshot.php';
require_once __DIR__ . '/models/SiteThemeCode.php';
require_once __DIR__ . '/models/UserAiSettings.php';
require_once __DIR__ . '/models/PlatformConnection.php';
require_once __DIR__ . '/models/PlatformOAuthApp.php';
require_once __DIR__ . '/models/PlatformCollection.php';
require_once __DIR__ . '/controllers/Admin/AuthController.php';
require_once __DIR__ . '/controllers/UserAuthController.php';
require_once __DIR__ . '/controllers/SharedAuthController.php';
require_once __DIR__ . '/controllers/UserProfileController.php';
require_once __DIR__ . '/controllers/Admin/PagesController.php';
require_once __DIR__ . '/controllers/Admin/FormsAdminController.php';
require_once __DIR__ . '/controllers/Admin/PortfolioController.php';
require_once __DIR__ . '/controllers/Admin/BlogAdminController.php';
require_once __DIR__ . '/controllers/Admin/BlogCategoriesAdminController.php';
require_once __DIR__ . '/controllers/Admin/MediaController.php';
require_once __DIR__ . '/controllers/Admin/TrashController.php';
require_once __DIR__ . '/controllers/Admin/NavigationController.php';
require_once __DIR__ . '/controllers/Admin/FeedSourcesAdminController.php';
require_once __DIR__ . '/controllers/Admin/SiteIdentityAdminController.php';
require_once __DIR__ . '/controllers/Admin/FeaturesAdminController.php';
require_once __DIR__ . '/controllers/Admin/PublicCopyAdminController.php';
require_once __DIR__ . '/controllers/Admin/UserProfilesAdminController.php';
require_once __DIR__ . '/lib/syndication/AdapterFactory.php';
require_once __DIR__ . '/controllers/Admin/PlatformConnectionsAdminController.php';
require_once __DIR__ . '/controllers/Admin/PiecesAdminController.php';
require_once __DIR__ . '/controllers/Admin/PlatformCollectionsAdminController.php';
require_once __DIR__ . '/controllers/MediaServeController.php';
require_once __DIR__ . '/controllers/PortfolioController.php';
require_once __DIR__ . '/controllers/PiecesController.php';
require_once __DIR__ . '/controllers/CollectionsController.php';
require_once __DIR__ . '/controllers/EmbedController.php';
require_once __DIR__ . '/controllers/ImmersiveController.php';
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/controllers/BlogController.php';
require_once __DIR__ . '/controllers/CommentController.php';
require_once __DIR__ . '/controllers/OgController.php';
require_once __DIR__ . '/controllers/PageController.php';
require_once __DIR__ . '/controllers/CronController.php';

$publicRoutes = [
    ['GET',  '/og/posts/([0-9]+)',                          [OgController::class, 'postImage']],
    ['GET',  '/blog',                                        [BlogController::class, 'index']],
    ['GET',  '/blog/posts/([0-9]+)',                        [BlogController::class, 'show']],
    ['GET',  '/blog/categories',                            [BlogController::class, 'categories']],
    ['GET',  '/api/posts/([0-9]+)/full',                          [BlogController::class, 'full']],
    ['GET',  '/api/posts/([0-9]+)/comments',                      [BlogController::class, 'commentsJson']],
    ['POST', '/api/posts/([0-9]+)/comments',                      [BlogController::class, 'commentSubmit']],
    ['GET',  '/api/pieces/([0-9]+)/comments',                     [PiecesController::class, 'commentsJson']],
    ['POST', '/api/pieces/([0-9]+)/comments',                     [PiecesController::class, 'commentSubmit']],
    ['GET',  '/api/exhibits/([a-z0-9-]+)/comments',               [PortfolioController::class, 'exhibitCommentsJson']],
    ['POST', '/api/exhibits/([a-z0-9-]+)/comments',               [PortfolioController::class, 'exhibitCommentSubmit']],
    ['GET',  '/api/exhibit-collections/([a-z0-9-]+)/comments',    [PortfolioController::class, 'collectionCommentsJson']],
    ['POST', '/api/exhibit-collections/([a-z0-9-]+)/comments',    [PortfolioController::class, 'collectionCommentSubmit']],
    ['POST', '/api/comments/([0-9]+)/edit',                       [CommentController::class, 'update']],
    ['POST', '/api/comments/([0-9]+)/delete',                     [CommentController::class, 'delete']],
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
    ['GET', '/portfolio/collections',                [PortfolioController::class, 'redirectCollectionsArchive']],
    ['GET', '/portfolio/exhibit-collections',        [PortfolioController::class, 'collectionsIndex']],
    ['GET', '/portfolio/exhibits',                   [PortfolioController::class, 'exhibitsIndex']],
    ['GET', '/portfolio/platform-collections',       [PortfolioController::class, 'platformCollectionsIndex']],
    ['GET', '/portfolio/pieces',                     [PortfolioController::class, 'piecesIndex']],
    ['GET', '/portfolio/categories',                 [PortfolioController::class, 'redirectCategoriesArchive']],
    ['GET', '/portfolio/art-media',                  [PortfolioController::class, 'categories']],
    ['GET', '/portfolio/category/([a-z0-9-]+)',      [PortfolioController::class, 'redirectCategory']],
    ['GET', '/portfolio/art-media/([a-z0-9-]+)',     [PortfolioController::class, 'category']],
    ['GET', '/portfolio/collection/([a-z0-9-]+)',    [PortfolioController::class, 'collection']],
    ['GET', '/portfolio/exhibit/([a-z0-9-]+)',       [PortfolioController::class, 'exhibit']],

    ['GET', '/pieces',                               [PiecesController::class, 'index']],
    ['GET', '/pieces/([0-9]+)/download',             [PiecesController::class, 'download']],
    ['GET', '/pieces/([0-9]+)',                      [PiecesController::class, 'show']],
    ['GET', '/collections',                          [CollectionsController::class, 'index']],
    ['GET', '/collections/([a-z0-9-]+)/download',    [CollectionsController::class, 'download']],
    ['GET', '/collections/([a-z0-9-]+)',             [CollectionsController::class, 'show']],
    ['GET', '/embed/posts/([0-9]+)',                 [EmbedController::class, 'post']],
    ['GET', '/embed/pieces/([0-9]+)',                [EmbedController::class, 'piece']],
    ['GET', '/embed/pieces/([0-9]+)/data',           [EmbedController::class, 'pieceData']],
    ['GET', '/immersive/pieces/([0-9]+)',            [ImmersiveController::class, 'piece']],
    ['GET', '/immersive/images/([A-Za-z0-9_-]+)',    [ImmersiveController::class, 'image']],
    ['GET', '/immersive/collections/([a-z0-9-]+)',    [ImmersiveController::class, 'collection']],
    ['GET', '/immersive/exhibits/([a-z0-9-]+)',       [ImmersiveController::class, 'redirectCollection']],

    ['GET', '/api/site',                             [ApiController::class, 'site']],
    ['GET', '/api/navigation',                       [ApiController::class, 'navigation']],
    ['GET', '/api/pages',                            [ApiController::class, 'pages']],
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
    ['GET', '/api/exhibits/([a-z0-9-]+)',            [ApiController::class, 'redirectCollection']],
    ['GET',  '/api/media-assets/([0-9]+)',            [ApiController::class, 'mediaAsset']],
    ['GET', '/api/media/([^/]+)/collections',         [ApiController::class, 'mediaAssetCollections']],
    ['GET', '/api/media/([^/]+)',                    [ApiController::class, 'mediaAssetByFilename']],
    ['GET', '/api/profile-photos/([^/]+)',          [ApiController::class, 'profilePhoto']],
    ['GET',  '/api/runtimes/(.+)',                   [ApiController::class, 'runtimeAsset']],
    ['POST', '/api/cron/publish-posts',              [CronController::class, 'publishPosts']],
    ['POST', '/api/cron/refresh-feeds',              [CronController::class, 'refreshFeeds']],

    // Public user auth — fixed paths MUST precede the catch-all /user/([a-z0-9_-]+)
    ['GET',  '/user/login',                           [UserAuthController::class, 'loginForm']],
    ['GET',  '/user/logout',                          [UserAuthController::class, 'logout']],
    ['GET',  '/user/auth/github/start',               [UserAuthController::class, 'oauthStart']],
    ['GET',  '/user/auth/google/start',               [UserAuthController::class, 'oauthStart']],

    // Shared OAuth callback — one callback URL per provider, registered once
    // with GitHub/Google, used by both admin and member login (disambiguated
    // internally via which pending session state matches).
    ['GET',  '/auth/github/callback',                 [SharedAuthController::class, 'oauthCallback']],
    ['GET',  '/auth/google/callback',                 [SharedAuthController::class, 'oauthCallback']],
    ['GET',  '/user/settings',                        [UserProfileController::class, 'settings']],
    ['POST', '/user/settings/profile',                [UserProfileController::class, 'settingsProfileUpdate']],
    ['POST', '/user/settings/photo',                  [UserProfileController::class, 'settingsPhotoUpload']],
    ['POST', '/user/settings/style',                  [UserProfileController::class, 'settingsStyleUpdate']],
    ['GET',  '/user/([a-z0-9_-]+)',                   [UserProfileController::class, 'show']],
];

$adminRoutes = [
    ['GET',  '/admin',                      [AuthController::class, 'dashboard']],
    ['GET',  '/admin/setup',                [AuthController::class, 'setup']],
    ['GET',  '/admin/login',                [AuthController::class, 'loginForm']],
    ['GET',  '/admin/auth/github/start',    [AuthController::class, 'oauthStart']],
    ['GET',  '/admin/auth/google/start',    [AuthController::class, 'oauthStart']],
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

    ['GET',  '/admin/forms',                           [FormsAdminController::class, 'index']],
    ['GET',  '/admin/forms/create',                    [FormsAdminController::class, 'create']],
    ['POST', '/admin/forms/create',                    [FormsAdminController::class, 'store']],
    ['GET',  '/admin/forms/([0-9]+)/edit',             [FormsAdminController::class, 'edit']],
    ['POST', '/admin/forms/([0-9]+)/edit',             [FormsAdminController::class, 'update']],
    ['GET',  '/admin/forms/([0-9]+)/fields/create',    [FormsAdminController::class, 'fieldCreate']],
    ['POST', '/admin/forms/([0-9]+)/fields/create',    [FormsAdminController::class, 'fieldStore']],
    ['GET',  '/admin/forms/fields/([0-9]+)/edit',      [FormsAdminController::class, 'fieldEdit']],
    ['POST', '/admin/forms/fields/([0-9]+)/edit',      [FormsAdminController::class, 'fieldUpdate']],
    ['POST', '/admin/forms/fields/([0-9]+)/delete',    [FormsAdminController::class, 'fieldDelete']],

    ['GET',  '/admin/posts',                             [BlogAdminController::class, 'postsIndex']],
    ['GET',  '/admin/posts/calendar',                    [BlogAdminController::class, 'postCalendar']],
    ['GET',  '/admin/posts/create',                      [BlogAdminController::class, 'postCreate'], 'blog'],
    ['POST', '/admin/posts/create',                      [BlogAdminController::class, 'postStore'], 'blog'],
    ['POST', '/admin/blog/categories/create-inline',     [BlogAdminController::class, 'categoryCreateInline'], 'blog'],
    ['GET',  '/admin/posts/([0-9]+)/edit',               [BlogAdminController::class, 'postEdit']],
    ['POST', '/admin/posts/([0-9]+)/edit',               [BlogAdminController::class, 'postUpdate']],
    ['POST', '/admin/posts/([0-9]+)/delete',             [BlogAdminController::class, 'postDelete']],

    ['GET',  '/admin/comments',                  [BlogAdminController::class, 'commentsIndex']],
    ['POST', '/admin/comments/([0-9]+)/delete',  [BlogAdminController::class, 'commentDelete']],
    ['POST', '/admin/reactions/([0-9]+)/delete', [BlogAdminController::class, 'reactionDelete']],

    ['GET',  '/admin/exhibits',                 [PortfolioAdminController::class, 'exhibitsIndex']],
    ['GET',  '/admin/exhibits/create',          [PortfolioAdminController::class, 'exhibitCreate'], 'exhibits'],
    ['POST', '/admin/exhibits/create',          [PortfolioAdminController::class, 'exhibitStore'], 'exhibits'],
    ['GET',  '/admin/exhibits/([0-9]+)/edit',   [PortfolioAdminController::class, 'exhibitEdit']],
    ['POST', '/admin/exhibits/([0-9]+)/edit',   [PortfolioAdminController::class, 'exhibitUpdate']],
    ['POST', '/admin/exhibits/([0-9]+)/delete', [PortfolioAdminController::class, 'exhibitDelete']],
    ['POST', '/admin/exhibits/reorder',         [PortfolioAdminController::class, 'exhibitReorder']],

    ['GET',  '/admin/categories',                     [BlogCategoriesAdminController::class, 'index']],
    ['GET',  '/admin/categories/create',              [BlogCategoriesAdminController::class, 'create'], 'blog'],
    ['POST', '/admin/categories/create',              [BlogCategoriesAdminController::class, 'store'], 'blog'],
    ['GET',  '/admin/categories/([0-9]+)/edit',       [BlogCategoriesAdminController::class, 'edit']],
    ['POST', '/admin/categories/([0-9]+)/edit',       [BlogCategoriesAdminController::class, 'update']],
    ['POST', '/admin/categories/([0-9]+)/delete',     [BlogCategoriesAdminController::class, 'delete']],
    ['POST', '/admin/categories/reorder',             [BlogCategoriesAdminController::class, 'reorder']],

    ['GET',  '/admin/art-media',                       [PortfolioAdminController::class, 'categoriesIndex']],
    ['GET',  '/admin/art-media/create',                [PortfolioAdminController::class, 'categoryCreate']],
    ['POST', '/admin/art-media/create',                [PortfolioAdminController::class, 'categoryStore']],
    ['POST', '/admin/art-media/create-inline',         [PortfolioAdminController::class, 'categoryCreateInline']],
    ['GET',  '/admin/art-media/([0-9]+)/edit',         [PortfolioAdminController::class, 'categoryEdit']],
    ['POST', '/admin/art-media/([0-9]+)/edit',         [PortfolioAdminController::class, 'categoryUpdate']],
    ['POST', '/admin/art-media/([0-9]+)/delete',       [PortfolioAdminController::class, 'categoryDelete']],
    ['POST', '/admin/art-media/reorder',               [PortfolioAdminController::class, 'categoryReorder']],

    ['GET',  '/admin/exhibit-collections',             [PortfolioAdminController::class, 'collectionsIndex']],
    ['GET',  '/admin/exhibit-collections/create',      [PortfolioAdminController::class, 'collectionCreate'], 'exhibit_collections'],
    ['POST', '/admin/exhibit-collections/create',      [PortfolioAdminController::class, 'collectionStore'], 'exhibit_collections'],
    ['POST', '/admin/exhibit-collections/create-inline', [PortfolioAdminController::class, 'collectionCreateInline'], 'exhibit_collections'],
    ['GET',  '/admin/exhibit-collections/([0-9]+)/edit', [PortfolioAdminController::class, 'collectionEdit']],
    ['POST', '/admin/exhibit-collections/([0-9]+)/edit', [PortfolioAdminController::class, 'collectionUpdate']],
    ['POST', '/admin/exhibit-collections/([0-9]+)/delete', [PortfolioAdminController::class, 'collectionDelete']],
    ['POST', '/admin/exhibit-collections/reorder',     [PortfolioAdminController::class, 'collectionReorder']],

    ['GET',  '/admin/collections',                     [PortfolioAdminController::class, 'collectionsIndex']],
    ['GET',  '/admin/collections/create',              [PortfolioAdminController::class, 'collectionCreate'], 'exhibit_collections'],
    ['POST', '/admin/collections/create',              [PortfolioAdminController::class, 'collectionStore'], 'exhibit_collections'],
    ['POST', '/admin/collections/create-inline',       [PortfolioAdminController::class, 'collectionCreateInline'], 'exhibit_collections'],
    ['GET',  '/admin/collections/([0-9]+)/edit',       [PortfolioAdminController::class, 'collectionEdit']],
    ['POST', '/admin/collections/([0-9]+)/edit',       [PortfolioAdminController::class, 'collectionUpdate']],
    ['POST', '/admin/collections/([0-9]+)/delete',     [PortfolioAdminController::class, 'collectionDelete']],
    ['POST', '/admin/collections/reorder',             [PortfolioAdminController::class, 'collectionReorder']],

    ['GET',  '/admin/media',                 [MediaAdminController::class, 'index']],
    ['GET',  '/admin/media/library',         [MediaAdminController::class, 'library']],
    ['POST', '/admin/media/upload',          [MediaAdminController::class, 'upload']],
    ['POST', '/admin/media/import',          [MediaAdminController::class, 'import']],
    ['POST', '/admin/media/poster-upload',   [MediaAdminController::class, 'uploadPoster']],
    ['POST', '/admin/media/([0-9]+)/confirm',[MediaAdminController::class, 'confirmFile']],
    ['POST', '/admin/media/([0-9]+)/discard',[MediaAdminController::class, 'discardDraft']],
    ['POST', '/admin/media/([0-9]+)/update', [MediaAdminController::class, 'updateFile']],
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
    ['POST', '/admin/pieces/reorder',          [PiecesAdminController::class, 'reorder']],
    ['GET',  '/admin/pieces/library',          [PiecesAdminController::class, 'library']],
    ['GET',  '/admin/ai/profiles',             [PiecesAdminController::class, 'aiProfilesLibrary'], 'ai'],
    ['GET',  '/admin/pieces/generate',         [PiecesAdminController::class, 'generateForm'], 'ai_pieces_code'],
    ['POST', '/admin/pieces/generate',         [PiecesAdminController::class, 'generate'], 'ai_pieces_code'],
    ['GET',  '/admin/pieces/generate/preview', [PiecesAdminController::class, 'generatePreview'], 'ai_pieces_code'],
    ['POST', '/admin/pieces/generate/regenerate', [PiecesAdminController::class, 'generateRegenerate'], 'ai_pieces_code'],
    ['POST', '/admin/pieces/generate/save',    [PiecesAdminController::class, 'generateSave'], 'ai_pieces_code'],
    ['POST', '/admin/pieces/refine-ai',        [PiecesAdminController::class, 'refineAi'], 'ai_pieces_code'],
    ['GET',  '/admin/pieces/templates',        [PiecesAdminController::class, 'templates']],
    ['GET',  '/admin/pieces/templates/([0-9]+)/edit', [PiecesAdminController::class, 'templateEdit']],
    ['POST', '/admin/pieces/templates/([0-9]+)/edit', [PiecesAdminController::class, 'templateUpdate']],
    ['GET',  '/admin/pieces/create',           [PiecesAdminController::class, 'create'], 'pieces'],
    ['POST', '/admin/pieces/create',           [PiecesAdminController::class, 'store'], 'pieces'],
    ['GET',  '/admin/pieces/([0-9]+)/edit',              [PiecesAdminController::class, 'edit']],
    ['POST', '/admin/pieces/([0-9]+)/edit',              [PiecesAdminController::class, 'update']],
    ['POST', '/admin/pieces/([0-9]+)/refine-save',       [PiecesAdminController::class, 'refineSave'], 'ai_pieces_code'],
    ['POST', '/admin/pieces/([0-9]+)/capture-thumbnail', [PiecesAdminController::class, 'captureThumbnail']],
    ['POST', '/admin/pieces/([0-9]+)/set-status',        [PiecesAdminController::class, 'setStatus']],
    ['POST', '/admin/pieces/([0-9]+)/delete',            [PiecesAdminController::class, 'delete']],
    ['GET',  '/admin/pieces/([0-9]+)/versions', [PiecesAdminController::class, 'versions']],
    ['GET',  '/admin/pieces/([0-9]+)/versions/create', [PiecesAdminController::class, 'versionCreate'], 'pieces'],
    ['POST', '/admin/pieces/([0-9]+)/versions/create', [PiecesAdminController::class, 'versionStore'], 'pieces'],
    ['GET',  '/admin/pieces/([0-9]+)/versions/([0-9]+)/edit', [PiecesAdminController::class, 'versionEdit']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/edit', [PiecesAdminController::class, 'versionUpdate']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/delete', [PiecesAdminController::class, 'versionDelete']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/set-current', [PiecesAdminController::class, 'versionSetCurrent']],
    ['POST', '/admin/pieces/([0-9]+)/versions/([0-9]+)/fork', [PiecesAdminController::class, 'versionFork'], 'pieces'],

    ['GET',  '/admin/platform-collections',         [PlatformCollectionsAdminController::class, 'index']],
    ['POST', '/admin/platform-collections/reorder', [PlatformCollectionsAdminController::class, 'reorder']],
    ['GET',  '/admin/platform-collections/create',  [PlatformCollectionsAdminController::class, 'create'], 'platform_collections'],
    ['POST', '/admin/platform-collections/create',  [PlatformCollectionsAdminController::class, 'store'], 'platform_collections'],
    ['GET',  '/admin/platform-collections/([0-9]+)/edit', [PlatformCollectionsAdminController::class, 'edit']],
    ['POST', '/admin/platform-collections/([0-9]+)/edit', [PlatformCollectionsAdminController::class, 'update']],
    ['POST', '/admin/platform-collections/([0-9]+)/capture-thumbnail', [PlatformCollectionsAdminController::class, 'captureThumbnail']],
    ['POST', '/admin/platform-collections/([0-9]+)/delete', [PlatformCollectionsAdminController::class, 'delete']],
    ['GET',  '/admin/platform-collections/library', [PlatformCollectionsAdminController::class, 'library']],

    ['GET',  '/admin/feed-sources',        [FeedSourcesAdminController::class, 'index']],
    ['GET',  '/admin/feed-sources/create', [FeedSourcesAdminController::class, 'create'], 'blog'],
    ['POST', '/admin/feed-sources/create', [FeedSourcesAdminController::class, 'store'], 'blog'],
    ['GET',  '/admin/feed-sources/([0-9]+)/edit', [FeedSourcesAdminController::class, 'edit']],
    ['POST', '/admin/feed-sources/([0-9]+)/edit', [FeedSourcesAdminController::class, 'update']],
    ['POST', '/admin/feed-sources/([0-9]+)/delete', [FeedSourcesAdminController::class, 'delete']],
    ['POST', '/admin/feed-sources/([0-9]+)/ingest', [FeedSourcesAdminController::class, 'ingest'], 'blog'],
    ['POST', '/admin/feed-sources/approve', [FeedSourcesAdminController::class, 'approveImport'], 'blog'],
    ['POST', '/admin/feed-sources/reject',  [FeedSourcesAdminController::class, 'rejectImport']],

    ['GET',  '/admin/features',      [FeaturesAdminController::class, 'index']],
    ['POST', '/admin/features/save', [FeaturesAdminController::class, 'save']],

    ['GET',  '/admin/public-copy',      [PublicCopyAdminController::class, 'index']],
    ['POST', '/admin/public-copy/save', [PublicCopyAdminController::class, 'save']],

    ['GET',  '/admin/site-identity', [SiteIdentityAdminController::class, 'index']],
    ['POST', '/admin/site-identity/settings', [SiteIdentityAdminController::class, 'settingsUpdate']],
    ['POST', '/admin/site-identity/navigation-order', [SiteIdentityAdminController::class, 'navigationOrderUpdate']],
    ['POST', '/admin/site-identity/assets', [SiteIdentityAdminController::class, 'assetCreate']],
    ['POST', '/admin/site-identity/assets/([0-9]+)/delete', [SiteIdentityAdminController::class, 'assetDelete']],
    ['POST', '/admin/site-identity/media/([0-9]+)/delete', [SiteIdentityAdminController::class, 'mediaAssetDelete']],
    ['GET',  '/admin/site-identity/theme-code',           [SiteIdentityAdminController::class, 'themeCodeLoad']],
    ['POST', '/admin/site-identity/theme-save-named',     [SiteIdentityAdminController::class, 'themeSaveNamed']],
    ['POST', '/admin/site-identity/theme-reset-defaults', [SiteIdentityAdminController::class, 'themeResetDefaults']],
    ['POST', '/admin/site-identity/theme-generate', [SiteIdentityAdminController::class, 'themeGenerate'], 'ai_theme'],
    ['POST', '/admin/site-identity/theme-refine', [SiteIdentityAdminController::class, 'themeRefine'], 'ai_theme'],
    ['POST', '/admin/site-identity/theme-save', [SiteIdentityAdminController::class, 'themeSave']],
    ['POST', '/admin/site-identity/theme-revert/([0-9]+)', [SiteIdentityAdminController::class, 'themeRevert']],

    ['GET',  '/admin/user-profiles', [UserProfilesAdminController::class, 'index']],
    ['GET',  '/admin/user-profiles/([a-zA-Z0-9_-]+)/edit', [UserProfilesAdminController::class, 'userEdit']],
    ['POST', '/admin/user-profiles/([a-zA-Z0-9_-]+)/edit', [UserProfilesAdminController::class, 'userUpdate']],
    ['GET',  '/admin/ai-settings', [UserProfilesAdminController::class, 'aiSettingsIndex']],
    ['POST', '/admin/ai-settings/vendor', [UserProfilesAdminController::class, 'vendorUpdate']],
    ['GET',  '/admin/ai-settings/personas/create', [UserProfilesAdminController::class, 'personaCreate']],
    ['POST', '/admin/ai-settings/personas/create', [UserProfilesAdminController::class, 'personaStore']],
    ['GET',  '/admin/ai-settings/personas/([0-9]+)/edit', [UserProfilesAdminController::class, 'personaEdit']],
    ['POST', '/admin/ai-settings/personas/([0-9]+)/edit', [UserProfilesAdminController::class, 'personaUpdate']],
    ['POST', '/admin/ai-settings/personas/([0-9]+)/delete', [UserProfilesAdminController::class, 'personaDelete']],
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
    ['GET',  '/admin/platform-connections/oauth-apps/([a-z-]+)/edit', [PlatformConnectionsAdminController::class, 'oauthAppEdit']],
    ['POST', '/admin/platform-connections/oauth-apps/([a-z-]+)/edit', [PlatformConnectionsAdminController::class, 'oauthAppUpdate']],

    ['POST', '/admin/ai/process', [PiecesAdminController::class, 'aiProcessText'], 'ai'],
    ['POST', '/admin/ai/describe-image', [PiecesAdminController::class, 'aiDescribeImage'], 'ai_alt_text'],

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
if ($method === 'HEAD') {
    $method = 'GET';
}

foreach ([...$publicRoutes, ...$adminRoutes] as $route) {
    [$routeMethod, $pattern, $handler] = $route;
    if ($method !== $routeMethod || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
        continue;
    }

    // Optional trailing feature key: 4th element (no extra args) or 5th
    // (after the extra-args array). Content-safe gating — only creation and
    // AI routes carry a key; manage/browse routes never do.
    $featureKey = null;
    if (isset($route[4]) && is_string($route[4])) {
        $featureKey = $route[4];
    } elseif (isset($route[3]) && is_string($route[3])) {
        $featureKey = $route[3];
    }
    if ($featureKey !== null && !feature_enabled($featureKey)) {
        feature_blocked_response($featureKey, $method, $pattern);
        exit;
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
