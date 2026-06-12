<?php

declare(strict_types=1);

class AdminController
{
    // ── Auth ──────────────────────────────────────────────────────────────

    public static function loginForm(): void
    {
        if (!empty($_SESSION['admin_identity_id'])) {
            header('Location: /admin');
            exit;
        }
        $error = $_GET['error'] ?? null;
        $detail = oauth_is_local_request() ? trim((string) ($_GET['detail'] ?? '')) : '';
        require dirname(__DIR__) . '/views/admin/login.php';
    }

    public static function oauthStart(): void
    {
        $provider = self::requestedProvider();
        $config = oauth_provider_config($provider);
        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            header('Location: /admin/login?error=provider');
            exit;
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = [
            'provider' => $provider,
            'value' => $state,
        ];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => oauth_redirect_uri($provider),
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
        ];

        if ($provider === 'google') {
            $params['access_type'] = 'online';
            $params['prompt'] = 'select_account';
        }

        header('Location: ' . $config['auth_url'] . '?' . http_build_query($params));
        exit;
    }

    public static function oauthCallback(): void
    {
        $provider = self::requestedProvider();
        $state = $_SESSION['oauth_state'] ?? null;

        if (!is_array($state) || ($state['provider'] ?? null) !== $provider || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
            header('Location: /admin/login?error=state');
            exit;
        }
        unset($_SESSION['oauth_state']);

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: /admin/login?error=oauth');
            exit;
        }

        try {
            $profile = self::fetchOauthProfile($provider, $code);
            if (!oauth_allowed_identity($provider, $profile)) {
                header('Location: /admin/login?error=denied');
                exit;
            }

            $identityId = AdminIdentity::upsertFromProfile([
                'provider' => $provider,
                'provider_subject' => (string) $profile['provider_subject'],
                'email' => $profile['email'] ?? null,
                'display_name' => (string) $profile['display_name'],
                'avatar_url' => $profile['avatar_url'] ?? null,
            ]);
            $identity = AdminIdentity::find($identityId);
            if (!$identity) {
                throw new RuntimeException('Identity could not be loaded after login.');
            }

            admin_login_identity($identity);
            header('Location: /admin');
            exit;
        } catch (Throwable $e) {
            error_log('[admin-oauth] ' . $provider . ': ' . $e->getMessage());

            $query = 'error=oauth';
            if (oauth_is_local_request()) {
                $query .= '&detail=' . rawurlencode(oauth_debug_detail($e));
            }

            header('Location: /admin/login?' . $query);
            exit;
        }
    }

    public static function logout(): void
    {
        admin_logout();
        header('Location: /admin/login');
        exit;
    }

    private static function requestedProvider(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#/admin/auth/(github|google)/#', $path, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException('Unknown OAuth provider.');
    }

    private static function fetchOauthProfile(string $provider, string $code): array
    {
        $config = oauth_provider_config($provider);
        $tokenResponse = oauth_http_request(
            'POST',
            $config['token_url'],
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query([
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'redirect_uri' => oauth_redirect_uri($provider),
                'grant_type' => 'authorization_code',
            ])
        );
        $tokenPayload = json_decode($tokenResponse['body'], true);
        $accessToken = is_array($tokenPayload) ? (string) ($tokenPayload['access_token'] ?? '') : '';
        if ($accessToken === '') {
            $errorDescription = is_array($tokenPayload) ? (string) ($tokenPayload['error_description'] ?? $tokenPayload['error'] ?? '') : '';
            throw new RuntimeException('OAuth token exchange failed.' . ($errorDescription !== '' ? ' ' . $errorDescription : ''));
        }

        if ($provider === 'github') {
            $userResponse = oauth_http_request('GET', $config['user_url'], [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'FornesusArtAdminOAuth/1.0',
            ]);
            $user = json_decode($userResponse['body'], true);
            if (!is_array($user) || empty($user['id']) || empty($user['login'])) {
                throw new RuntimeException('GitHub profile could not be loaded from the provider response.');
            }

            $email = isset($user['email']) && $user['email'] !== '' ? (string) $user['email'] : null;
            if ($email === null) {
                $emailResponse = oauth_http_request('GET', $config['emails_url'], [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'User-Agent' => 'FornesusArtAdminOAuth/1.0',
                ]);
                $emails = json_decode($emailResponse['body'], true);
                if (is_array($emails)) {
                    foreach ($emails as $entry) {
                        if (!empty($entry['primary']) && !empty($entry['verified']) && !empty($entry['email'])) {
                            $email = (string) $entry['email'];
                            break;
                        }
                    }
                }
            }

            return [
                'provider_subject' => (string) $user['id'],
                'login' => (string) $user['login'],
                'email' => $email,
                'display_name' => (string) ($user['name'] ?: $user['login']),
                'avatar_url' => (string) ($user['avatar_url'] ?? ''),
            ];
        }

        $userResponse = oauth_http_request('GET', $config['userinfo_url'], [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);
        $user = json_decode($userResponse['body'], true);
        if (!is_array($user) || empty($user['sub']) || empty($user['email'])) {
            throw new RuntimeException('Google profile could not be loaded from the provider response.');
        }

        return [
            'provider_subject' => (string) $user['sub'],
            'email' => (string) $user['email'],
            'display_name' => (string) ($user['name'] ?? $user['email']),
            'avatar_url' => (string) ($user['picture'] ?? ''),
        ];
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    public static function dashboard(): void
    {
        admin_check();
        $artworkCount  = (int) db()->query('SELECT COUNT(*) FROM artworks  WHERE deleted_at IS NULL')->fetchColumn();
        $categoryCount = (int) db()->query('SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL')->fetchColumn();
        $exhibitCount  = (int) db()->query('SELECT COUNT(*) FROM exhibits  WHERE deleted_at IS NULL')->fetchColumn();
        $messageCount  = (int) db()->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
        $trashCount    = Artwork::trashedCount() + Category::trashedCount() + Exhibit::trashedCount() + MediaFile::trashedCount();
        require dirname(__DIR__) . '/views/admin/dashboard.php';
    }

    // ── Artworks ──────────────────────────────────────────────────────────

    public static function artworksIndex(): void
    {
        admin_check();
        $artworks   = Artwork::all();
        $categories = Category::all();
        require dirname(__DIR__) . '/views/admin/artworks/index.php';
    }

    public static function artworkCreate(): void
    {
        admin_check();
        $categories = Category::all();
        $allExhibits = Exhibit::all();
        $assignedCategoryIds = [];
        $assignedExhibitIds = [];
        $artwork    = ['media_items' => []];
        $error      = null;
        require dirname(__DIR__) . '/views/admin/artworks/form.php';
    }

    public static function artworkStore(): void
    {
        admin_check();

        try {
            $data = self::resolveArtworkData(null);
            $artworkId = Artwork::create($data);
            ArtworkMediaItem::syncForArtwork($artworkId, $data['media_items']);
            Artwork::syncCategories($artworkId, $data['category_ids']);
            Exhibit::syncForArtwork($artworkId, $data['exhibit_ids']);
            header('Location: /admin/artworks');
        } catch (Throwable $e) {
            $categories = Category::all();
            $allExhibits = Exhibit::all();
            $artwork    = self::draftArtworkFromPost(null);
            $assignedCategoryIds = $artwork['category_ids'];
            $assignedExhibitIds = $artwork['exhibit_ids'];
            $error      = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/artworks/form.php';
        }
        exit;
    }

    public static function artworkEdit(string $id): void
    {
        admin_check();
        $artwork = Artwork::find((int) $id);
        if (!$artwork) {
            header('Location: /admin/artworks');
            exit;
        }
        $categories = Category::all();
        $allExhibits = Exhibit::all();
        $assignedCategoryIds = Artwork::categoryIds((int) $id);
        $assignedExhibitIds = Exhibit::exhibitIdsForArtwork((int) $id);
        $error      = null;
        require dirname(__DIR__) . '/views/admin/artworks/form.php';
    }

    public static function artworkUpdate(string $id): void
    {
        admin_check();

        try {
            $data = self::resolveArtworkData((int) $id);
            Artwork::update((int) $id, $data);
            ArtworkMediaItem::syncForArtwork((int) $id, $data['media_items']);
            Artwork::syncCategories((int) $id, $data['category_ids']);
            Exhibit::syncForArtwork((int) $id, $data['exhibit_ids']);
            header('Location: /admin/artworks');
        } catch (Throwable $e) {
            $artwork    = self::draftArtworkFromPost((int) $id);
            $categories = Category::all();
            $allExhibits = Exhibit::all();
            $assignedCategoryIds = $artwork['category_ids'];
            $assignedExhibitIds = $artwork['exhibit_ids'];
            $error      = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/artworks/form.php';
        }
        exit;
    }

    public static function artworkDelete(string $id): void
    {
        admin_check();
        Artwork::softDelete((int) $id);
        header('Location: /admin/artworks');
        exit;
    }

    public static function artworkReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $stmt = db()->prepare('UPDATE artworks SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i, $id]);
        }
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function categoryReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $stmt = db()->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i, $id]);
        }
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function artworkOrder(string $id): void
    {
        admin_check();
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $stmt = db()->prepare('UPDATE artworks SET sort_order = ? WHERE id = ?');
        $stmt->execute([$sort, (int) $id]);
        header('Location: /admin/artworks');
        exit;
    }

    // ── Categories ────────────────────────────────────────────────────────

    public static function categoriesIndex(): void
    {
        admin_check();
        $categories = Category::all();
        require dirname(__DIR__) . '/views/admin/categories/index.php';
    }

    public static function categoryCreate(): void
    {
        admin_check();
        $category = null;
        $error    = null;
        require dirname(__DIR__) . '/views/admin/categories/form.php';
    }

    public static function categoryStore(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $category = null;
            $error    = 'Name is required.';
            require dirname(__DIR__) . '/views/admin/categories/form.php';
            return;
        }
        $postedSlug = trim($_POST['slug'] ?? '');
        $slug       = $postedSlug ? slugify($postedSlug) : unique_category_slug($name);
        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail(null, 'category');
        } catch (\RuntimeException $e) {
            $category = null;
            $error    = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/categories/form.php';
            return;
        }
        $desc = trim($_POST['description'] ?? '') ?: null;
        Category::create($name, $slug, 0, $thumbType ?: null, $thumbValue ?: null, $desc);
        header('Location: /admin/categories');
        exit;
    }

    public static function categoryCreateInline(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required.']);
            exit;
        }

        try {
            $slug = unique_category_slug($name);
            $id = Category::create($name, $slug);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }


    public static function categoryEdit(string $id): void
    {
        admin_check();
        $category = Category::find((int) $id);
        if (!$category) {
            header('Location: /admin/categories');
            exit;
        }
        $error = null;
        require dirname(__DIR__) . '/views/admin/categories/form.php';
    }

    public static function categoryUpdate(string $id): void
    {
        admin_check();
        $existing = Category::find((int) $id);
        $name     = trim($_POST['name'] ?? '');
        if (!$name) {
            $category = $existing;
            $error    = 'Name is required.';
            require dirname(__DIR__) . '/views/admin/categories/form.php';
            return;
        }
        $postedSlug = trim($_POST['slug'] ?? '');
        $slug       = $postedSlug ? slugify($postedSlug) : unique_category_slug($name, (int) $id);
        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail($existing, 'category');
        } catch (\RuntimeException $e) {
            $category = $existing;
            $error    = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/categories/form.php';
            return;
        }
        $desc = trim($_POST['description'] ?? '') ?: null;
        Category::update((int) $id, $name, $slug, (int) ($existing['sort_order'] ?? 0), $thumbType ?: null, $thumbValue ?: null, $desc);
        header('Location: /admin/categories');
        exit;
    }

    public static function categoryDelete(string $id): void
    {
        admin_check();
        Category::softDelete((int) $id);
        header('Location: /admin/categories');
        exit;
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    public static function pagesLegacyRedirect(): void
    {
        admin_check();
        header('Location: /admin/pages', true, 302);
        exit;
    }

    public static function pagesIndex(): void
    {
        admin_check();
        $pages = Page::all();
        foreach ($pages as &$page) {
            $navVisibility = NavigationItem::visibilityByPageId((int) $page['id']);
            $page['nav_is_visible'] = $navVisibility === null ? !empty($page['show_in_nav']) : $navVisibility;
        }
        unset($page);
        require dirname(__DIR__) . '/views/admin/pages/index.php';
    }

    public static function pageCreate(): void
    {
        admin_check();
        $page = null;
        $pageError = null;
        require dirname(__DIR__) . '/views/admin/pages/form.php';
    }

    public static function pageStore(): void
    {
        admin_check();

        try {
            $data = self::resolvePageData(null);
            $pageId = Page::create($data);
            NavigationItem::syncPageItem($data + ['id' => $pageId], !empty($data['show_in_nav']));
            header('Location: /admin/pages/' . $pageId . '/edit');
        } catch (Throwable $e) {
            $page = null;
            $pageError = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/pages/form.php';
        }
        exit;
    }

    public static function pageEdit(string $id): void
    {
        admin_check();
        $page = Page::find((int) $id);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }
        $navVisibility = NavigationItem::visibilityByPageId((int) $id);
        if ($navVisibility !== null) {
            $page['show_in_nav'] = $navVisibility ? 1 : 0;
        }

        $sections = PageSection::allForPage((int) $id);
        $pageError = null;
        require dirname(__DIR__) . '/views/admin/pages/form.php';
    }

    public static function pageUpdate(string $id): void
    {
        admin_check();
        $page = Page::find((int) $id);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        try {
            $data = self::resolvePageData((int) $id);
            Page::update((int) $id, $data);
            NavigationItem::syncPageItem($data + ['id' => (int) $id]);
            header('Location: /admin/pages/' . (int) $id . '/edit?saved=1');
        } catch (Throwable $e) {
            $page = array_merge($page, $_POST);
            $sections = PageSection::allForPage((int) $id);
            $pageError = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/pages/form.php';
        }
        exit;
    }

    public static function pageDelete(string $id): void
    {
        admin_check();
        Page::softDelete((int) $id);
        header('Location: /admin/pages');
        exit;
    }

    public static function pagesTrash(): void
    {
        admin_check();
        $pages = Page::trashed();
        require dirname(__DIR__) . '/views/admin/pages/trash.php';
    }

    public static function pageRestore(string $id): void
    {
        admin_check();
        Page::restore((int) $id);
        header('Location: /admin/pages/trash');
        exit;
    }

    public static function pageHardDelete(string $id): void
    {
        admin_check();
        Page::hardDelete((int) $id);
        header('Location: /admin/pages/trash');
        exit;
    }

    public static function pageToggleNav(string $id): void
    {
        admin_check();
        $showInNav = Page::toggleNav((int) $id);
        $page = Page::find((int) $id);
        if ($page) {
            $page['show_in_nav'] = $showInNav ? 1 : 0;
            NavigationItem::syncPageItem($page);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'show_in_nav' => (int) $showInNav]);
        exit;
    }

    public static function pagesTrashEmpty(): void
    {
        admin_check();
        foreach (Page::trashed() as $page) {
            Page::hardDelete((int) $page['id']);
        }
        header('Location: /admin/pages/trash');
        exit;
    }

    public static function pageReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Page::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function navigationIndex(): void
    {
        admin_check();
        $navigationReady = NavigationItem::isAvailable();
        $visibleItems = NavigationItem::adminItems(true);
        $hiddenItems = NavigationItem::adminItems(false);
        $navigationMode = $navigationReady ? 'registry' : 'legacy';
        $navigationError = $_GET['error'] ?? null;
        require dirname(__DIR__) . '/views/admin/navigation.php';
    }

    public static function navigationExternalStore(): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }

        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $visibility = $_POST['visibility'] ?? 'visible';

        if ($label === '' || $url === '') {
            header('Location: /admin/navigation?error=missing');
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            header('Location: /admin/navigation?error=url');
            exit;
        }

        NavigationItem::createExternal(
            $label,
            $url,
            $visibility === 'visible',
            !empty($_POST['open_in_new_tab'])
        );

        header('Location: /admin/navigation');
        exit;
    }

    public static function navigationLabelUpdate(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }

        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            header('Location: /admin/navigation?error=label');
            exit;
        }

        NavigationItem::updateExternalLabel((int) $id, $label);
        header('Location: /admin/navigation');
        exit;
    }

    public static function navigationReorder(): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo '{"ok":false,"error":"migration-required"}';
            exit;
        }
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $visibility = $_POST['visibility'] ?? 'visible';
        NavigationItem::reorder($visibility === 'visible', $ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function navigationToggle(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::toggleVisibility((int) $id);
        header('Location: /admin/navigation');
        exit;
    }

    public static function navigationDelete(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::deleteExternal((int) $id);
        header('Location: /admin/navigation');
        exit;
    }

    public static function navigationToggleTarget(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::toggleExternalTarget((int) $id);
        header('Location: /admin/navigation');
        exit;
    }

    public static function pageSectionCreate(string $pageId): void
    {
        admin_check();
        $page = Page::find((int) $pageId);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        $section = null;
        $sectionError = null;
        require dirname(__DIR__) . '/views/admin/pages/section-form.php';
    }

    public static function pageSectionStore(string $pageId): void
    {
        admin_check();
        $page = Page::find((int) $pageId);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            $section = null;
            $sectionError = 'Content is required.';
            require dirname(__DIR__) . '/views/admin/pages/section-form.php';
            return;
        }

        PageSection::create((int) $pageId, $heading, $content);
        header('Location: /admin/pages/' . (int) $pageId . '/edit');
        exit;
    }

    public static function pageSectionEdit(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if (!$section) {
            header('Location: /admin/pages');
            exit;
        }
        $page = Page::find((int) $section['page_id']);
        $sectionError = null;
        require dirname(__DIR__) . '/views/admin/pages/section-form.php';
    }

    public static function pageSectionUpdate(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if (!$section) {
            header('Location: /admin/pages');
            exit;
        }

        $page = Page::find((int) $section['page_id']);
        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            $sectionError = 'Content is required.';
            require dirname(__DIR__) . '/views/admin/pages/section-form.php';
            return;
        }

        PageSection::update((int) $sectionId, $heading, $content);
        header('Location: /admin/pages/' . (int) $section['page_id'] . '/edit');
        exit;
    }

    public static function pageSectionDelete(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if ($section) {
            PageSection::delete((int) $sectionId);
            header('Location: /admin/pages/' . (int) $section['page_id'] . '/edit');
            exit;
        }

        header('Location: /admin/pages');
        exit;
    }

    public static function pageSectionReorder(string $pageId): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        PageSection::reorder((int) $pageId, $ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    // ── Messages ──────────────────────────────────────────────────────────

    public static function messagesIndex(): void
    {
        admin_check();
        $messages = ContactMessage::all();
        require dirname(__DIR__) . '/views/admin/messages.php';
    }

    public static function messageToggleRead(string $id): void
    {
        admin_check();
        ContactMessage::toggleRead((int) $id);
        header('Location: /admin/messages');
        exit;
    }

    public static function messageToggleFlag(string $id): void
    {
        admin_check();
        ContactMessage::toggleFlagged((int) $id);
        header('Location: /admin/messages');
        exit;
    }

    public static function messageTogglePin(string $id): void
    {
        admin_check();
        ContactMessage::togglePinned((int) $id);
        header('Location: /admin/messages');
        exit;
    }

    public static function messageReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        ContactMessage::reorder(array_values($ids));
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function messageDelete(string $id): void
    {
        admin_check();
        ContactMessage::softDelete((int) $id);
        header('Location: /admin/messages');
        exit;
    }

    // ── Internal ──────────────────────────────────────────────────────────

    private static function resolveArtworkData(?int $existingId): array
    {
        $existing = $existingId ? Artwork::find($existingId) : null;
        $title = trim($_POST['title'] ?? '');
        $year = trim($_POST['year'] ?? '');
        $artistName = trim($_POST['artist_name'] ?? '');
        $medium = trim($_POST['medium'] ?? '');
        $dimensions = trim($_POST['dimensions'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $placardNotes = trim($_POST['placard_notes'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);

        if (!$title) {
            throw new InvalidArgumentException('Title is required.');
        }

        $postedSlug = trim($_POST['slug'] ?? '');
        $slug = $postedSlug
            ? slugify($postedSlug)
            : ($existingId
                ? (($existing['slug'] ?? null) ?: unique_slug($title, $existingId))
                : unique_slug($title));

        $thumbType = $_POST['thumbnail_type'] ?? 'link';
        if ($thumbType === 'upload' && !empty($_FILES['thumbnail_upload']['name'])) {
            $thumbValue = upload_image($_FILES['thumbnail_upload'], 'thumbnails');
        } elseif ($thumbType === 'link') {
            $thumbValue = trim($_POST['thumbnail_link'] ?? '') ?: null;
            if ($thumbValue === null) $thumbType = null;
        } else {
            $thumbType  = null;
            $thumbValue = null;
        }

        $mediaItems = self::resolveArtworkMediaItems($existing);
        if ($mediaItems === [] && $existing) {
            $mediaItems = Artwork::resolvedMediaItems($existing);
        }
        if ($mediaItems === []) {
            throw new InvalidArgumentException('Add at least one artwork slide.');
        }

        $legacyPiece = Artwork::legacyPieceFromMediaItems($mediaItems);

        return [
            'title'            => $title,
            'slug'             => $slug,
            'year'             => $year,
            'artist_name'      => $artistName,
            'medium'           => $medium,
            'dimensions'       => $dimensions,
            'description'      => $desc,
            'placard_notes'    => $placardNotes,
            'thumbnail_type'   => $thumbType,
            'thumbnail_value'  => $thumbValue,
            'piece_type'       => $legacyPiece['piece_type'],
            'piece_value'      => $legacyPiece['piece_value'],
            'sort_order'       => $sort,
            'media_items'      => $mediaItems,
            'category_ids'     => array_map('intval', $_POST['category_ids'] ?? []),
            'exhibit_ids'      => array_map('intval', $_POST['exhibit_ids'] ?? []),
        ];
    }

    private static function resolveArtworkMediaItems(?array $existing): array
    {
        $kinds = $_POST['media_kind'] ?? [];
        $mediaIds = $_POST['media_file_id'] ?? [];
        $posterIds = $_POST['poster_media_file_id'] ?? [];
        $alts = $_POST['alt_text'] ?? [];
        $titles = $_POST['slide_title'] ?? [];
        $captions = $_POST['caption'] ?? [];
        $iframeHtml = $_POST['iframe_html'] ?? [];
        $items = [];

        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }

            if (!in_array($kind, ['image', 'video', 'iframe'], true)) {
                throw new InvalidArgumentException('Invalid artwork slide type.');
            }

            $mediaFileId = (int) ($mediaIds[$index] ?? 0);
            $posterMediaId = (int) ($posterIds[$index] ?? 0);
            $alt = trim((string) ($alts[$index] ?? ''));
            $slideTitle = trim((string) ($titles[$index] ?? ''));
            $caption = trim((string) ($captions[$index] ?? ''));
            $iframe = trim((string) ($iframeHtml[$index] ?? ''));

            if ($kind === 'iframe') {
                if ($iframe === '' || stripos($iframe, '<iframe') === false || Artwork::extractIframeSourcePublic($iframe) === null) {
                    throw new InvalidArgumentException('Iframe slides require valid iframe HTML with a usable src.');
                }

                $items[] = [
                    'media_kind' => 'iframe',
                    'media_file_id' => null,
                    'iframe_html' => $iframe,
                    'poster_media_file_id' => null,
                    'alt_text' => $alt ?: null,
                    'title' => $slideTitle ?: null,
                    'caption' => $caption ?: null,
                ];
                continue;
            }

            if ($mediaFileId <= 0) {
                throw new InvalidArgumentException(ucfirst($kind) . ' slides require a media asset.');
            }
            if (!MediaFile::isActiveOfKind($mediaFileId, $kind)) {
                throw new InvalidArgumentException('Selected asset does not match the slide type.');
            }
            if ($posterMediaId > 0 && !MediaFile::isActiveOfKind($posterMediaId, 'image')) {
                throw new InvalidArgumentException('Video posters must be image assets.');
            }

            $items[] = [
                'media_kind' => $kind,
                'media_file_id' => $mediaFileId,
                'iframe_html' => null,
                'poster_media_file_id' => $posterMediaId > 0 ? $posterMediaId : null,
                'alt_text' => $alt ?: null,
                'title' => $slideTitle ?: null,
                'caption' => $caption ?: null,
            ];
        }

        return $items;
    }

    private static function draftArtworkFromPost(?int $existingId): array
    {
        $existing = $existingId ? Artwork::find($existingId) : null;

        return [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'slug' => trim((string) ($_POST['slug'] ?? ($existing['slug'] ?? ''))),
            'year' => trim((string) ($_POST['year'] ?? ($existing['year'] ?? ''))),
            'artist_name' => trim((string) ($_POST['artist_name'] ?? ($existing['artist_name'] ?? ''))),
            'medium' => trim((string) ($_POST['medium'] ?? ($existing['medium'] ?? ''))),
            'dimensions' => trim((string) ($_POST['dimensions'] ?? ($existing['dimensions'] ?? ''))),
            'category_ids' => array_map('intval', $_POST['category_ids']
                ?? ($existing ? Artwork::categoryIds((int) $existing['id']) : [])),
            'exhibit_ids' => array_map('intval', $_POST['exhibit_ids']
                ?? ($existing ? Exhibit::exhibitIdsForArtwork((int) $existing['id']) : [])),
            'description' => trim((string) ($_POST['description'] ?? ($existing['description'] ?? ''))),
            'placard_notes' => trim((string) ($_POST['placard_notes'] ?? ($existing['placard_notes'] ?? ''))),
            'sort_order' => (int) ($_POST['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            'thumbnail_value' => trim((string) ($_POST['thumbnail_link'] ?? ($existing['thumbnail_value'] ?? ''))),
            'media_items' => self::draftMediaItemsFromPost($existing),
        ];
    }

    private static function draftMediaItemsFromPost(?array $existing): array
    {
        $kinds = $_POST['media_kind'] ?? [];
        if ($kinds === []) {
            return $existing ? Artwork::resolvedMediaItems($existing) : [];
        }

        $items = [];
        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }

            $mediaFileId = (int) (($_POST['media_file_id'] ?? [])[$index] ?? 0);
            $posterMediaFileId = (int) (($_POST['poster_media_file_id'] ?? [])[$index] ?? 0);
            $items[] = ArtworkMediaItem::normalizeForDisplay([
                'media_kind' => $kind,
                'media_file_id' => $mediaFileId > 0 ? $mediaFileId : null,
                'iframe_html' => trim((string) (($_POST['iframe_html'] ?? [])[$index] ?? '')),
                'poster_media_file_id' => $posterMediaFileId > 0 ? $posterMediaFileId : null,
                'alt_text' => trim((string) (($_POST['alt_text'] ?? [])[$index] ?? '')),
                'title' => trim((string) (($_POST['slide_title'] ?? [])[$index] ?? '')),
                'caption' => trim((string) (($_POST['caption'] ?? [])[$index] ?? '')),
                'source_url' => $mediaFileId > 0 ? '/media/' . $mediaFileId : null,
                'poster_url' => $posterMediaFileId > 0 ? '/media/' . $posterMediaFileId : null,
            ]);
        }

        return $items;
    }

    // Resolves thumbnail_type + thumbnail_value from POST/FILES.
    // $existing is the current DB row (or null for create).
    // $prefix distinguishes file/link field names ('category', 'exhibit').
    private static function resolveThumbnail(?array $existing, string $prefix): array
    {
        $type = $_POST['thumbnail_type'] ?? 'link';
        if ($type === 'upload' && !empty($_FILES['thumbnail_upload']['name'])) {
            $value = upload_image($_FILES['thumbnail_upload'], $prefix . 's');
        } elseif ($type === 'link') {
            $value = trim($_POST['thumbnail_link'] ?? '') ?: null;
            if ($value === null) $type = null;
        } else {
            $type  = null;
            $value = null;
        }
        return [$type, $value];
    }

    private static function resolvePageData(?int $existingId): array
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $slugInput = trim($_POST['slug'] ?? '');
        $slug = Page::validateSlug($slugInput !== '' ? $slugInput : $title, $existingId ?? 0);
        $template = $_POST['template'] ?? 'standard';
        if (!in_array($template, ['standard', 'contact'], true)) {
            throw new InvalidArgumentException('Invalid page template.');
        }
        if ($template === 'contact' && $slug !== 'contact') {
            throw new InvalidArgumentException('The contact template must use the slug "contact".');
        }
        if ($template !== 'contact' && $slug === 'contact') {
            throw new InvalidArgumentException('The slug "contact" is reserved for the contact page template.');
        }

        $status = $_POST['status'] ?? 'published';
        if (!in_array($status, ['published', 'draft'], true)) {
            throw new InvalidArgumentException('Invalid page status.');
        }

        return [
            'title'            => $title,
            'slug'             => $slug,
            'status'           => $status,
            'template'         => $template,
            'nav_label'        => trim($_POST['nav_label'] ?? ''),
            'show_in_nav'      => !empty($_POST['show_in_nav']) ? 1 : 0,
            'meta_title'       => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'og_title'         => trim($_POST['og_title'] ?? ''),
            'og_description'   => trim($_POST['og_description'] ?? ''),
            'og_image'         => trim($_POST['og_image'] ?? ''),
            'sort_order'       => (int) ($_POST['sort_order'] ?? ($existingId ? (Page::find($existingId)['sort_order'] ?? 0) : 0)),
        ];
    }

    // ── Exhibits ──────────────────────────────────────────────────────────

    public static function exhibitsIndex(): void
    {
        admin_check();
        $exhibits = Exhibit::allWithArtworkCount();
        require dirname(__DIR__) . '/views/admin/exhibits/index.php';
    }

    public static function exhibitCreate(): void
    {
        admin_check();
        $exhibit    = null;
        $allArtworks = Artwork::all();
        $assigned   = [];
        $error      = null;
        require dirname(__DIR__) . '/views/admin/exhibits/form.php';
    }

    public static function exhibitStore(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $exhibit     = null;
            $allArtworks = Artwork::all();
            $assigned    = [];
            $error       = 'Name is required.';
            require dirname(__DIR__) . '/views/admin/exhibits/form.php';
            return;
        }
        $postedSlug = trim($_POST['slug'] ?? '');
        $slug       = $postedSlug ? slugify($postedSlug) : Exhibit::uniqueSlug($name);
        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail(null, 'exhibit');
        } catch (\RuntimeException $e) {
            $exhibit     = null;
            $allArtworks = Artwork::all();
            $assigned    = [];
            $error       = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/exhibits/form.php';
            return;
        }
        $id = Exhibit::create([
            'name'            => $name,
            'slug'            => $slug,
            'description'     => trim($_POST['description'] ?? ''),
            'thumbnail_type'  => $thumbType,
            'thumbnail_value' => $thumbValue,
            'sort_order'      => 0,
        ]);
        Exhibit::syncArtworks($id, array_map('intval', $_POST['artwork_ids'] ?? []));
        header('Location: /admin/exhibits');
        exit;
    }

    public static function exhibitCreateInline(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required.']);
            exit;
        }

        try {
            $slug = Exhibit::uniqueSlug($name);
            $id = Exhibit::create([
                'name'            => $name,
                'slug'            => $slug,
                'description'     => '',
                'thumbnail_type'  => null,
                'thumbnail_value' => null,
                'sort_order'      => 0,
            ]);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }


    public static function exhibitEdit(string $id): void
    {
        admin_check();
        $exhibit = Exhibit::find((int) $id);
        if (!$exhibit) {
            header('Location: /admin/exhibits');
            exit;
        }
        $allArtworks = Artwork::all();
        $assigned    = Exhibit::artworkIds((int) $id);
        $error       = null;
        require dirname(__DIR__) . '/views/admin/exhibits/form.php';
    }

    public static function exhibitUpdate(string $id): void
    {
        admin_check();
        $existing = Exhibit::find((int) $id);
        $name     = trim($_POST['name'] ?? '');
        if (!$name) {
            $exhibit     = $existing;
            $allArtworks = Artwork::all();
            $assigned    = Exhibit::artworkIds((int) $id);
            $error       = 'Name is required.';
            require dirname(__DIR__) . '/views/admin/exhibits/form.php';
            return;
        }
        $postedSlug = trim($_POST['slug'] ?? '');
        $slug       = $postedSlug ? slugify($postedSlug) : Exhibit::uniqueSlug($name, (int) $id);
        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail($existing, 'exhibit');
        } catch (\RuntimeException $e) {
            $exhibit     = $existing;
            $allArtworks = Artwork::all();
            $assigned    = Exhibit::artworkIds((int) $id);
            $error       = $e->getMessage();
            require dirname(__DIR__) . '/views/admin/exhibits/form.php';
            return;
        }
        Exhibit::update((int) $id, [
            'name'            => $name,
            'slug'            => $slug,
            'description'     => trim($_POST['description'] ?? ''),
            'thumbnail_type'  => $thumbType,
            'thumbnail_value' => $thumbValue,
            'sort_order'      => (int) ($existing['sort_order'] ?? 0),
        ]);
        Exhibit::syncArtworks((int) $id, array_map('intval', $_POST['artwork_ids'] ?? []));
        header('Location: /admin/exhibits');
        exit;
    }

    public static function exhibitDelete(string $id): void
    {
        admin_check();
        Exhibit::softDelete((int) $id);
        header('Location: /admin/exhibits');
        exit;
    }

    public static function exhibitReorder(): void
    {
        admin_check();
        $ids  = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $stmt = db()->prepare('UPDATE exhibits SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i, $id]);
        }
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    // ── Media Library ─────────────────────────────────────────────────────

    public static function mediaIndex(): void
    {
        admin_check();
        $files = MediaFile::all();
        require dirname(__DIR__) . '/views/admin/media.php';
    }

    public static function mediaLibrary(): void
    {
        admin_check();
        header('Content-Type: application/json');
        $files = array_map(static function (array $file): array {
            $mime = (string) ($file['mime_type'] ?? '');
            $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';

            return $file + [
                'kind' => $kind,
                'url' => '/media/' . $file['id'],
                'legacy_url' => $kind === 'image' ? '/image/' . $file['id'] : null,
            ];
        }, MediaFile::all());
        echo json_encode($files);
        exit;
    }

    public static function mediaUpload(): void
    {
        admin_check();
        header('Content-Type: application/json');
        if (empty($_FILES['media_file']['name'])) {
            echo json_encode(['ok' => false, 'error' => 'No file provided.']);
            exit;
        }
        try {
            $asset = upload_media_auto($_FILES['media_file']);

            echo json_encode([
                'ok' => true,
                'id' => $asset['id'],
                'mime_type' => $asset['mime_type'],
                'url' => $asset['url'],
                'legacy_url' => $asset['legacy_url'],
                'kind' => str_starts_with($asset['mime_type'], 'video/') ? 'video' : 'image',
            ]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function mediaImport(): void
    {
        admin_check();
        header('Content-Type: application/json');
        $url = trim($_POST['url'] ?? '');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid URL.']);
            exit;
        }
        $limit = 8 * 1024 * 1024;
        $ctx   = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => true]]);
        $data  = @file_get_contents($url, false, $ctx, 0, $limit + 1);
        if ($data === false || $data === '') {
            echo json_encode(['ok' => false, 'error' => 'Could not fetch the URL.']);
            exit;
        }
        if (strlen($data) > $limit) {
            echo json_encode(['ok' => false, 'error' => 'Image exceeds 8 MB limit.']);
            exit;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($data);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) {
            echo json_encode(['ok' => false, 'error' => 'URL does not point to a supported image type.']);
            exit;
        }
        $id = MediaFile::create($data, $mime, basename(parse_url($url, PHP_URL_PATH) ?: 'imported-image'));
        echo json_encode(['ok' => true, 'id' => $id, 'url' => "/media/$id", 'legacy_url' => "/image/$id", 'kind' => 'image']);
        exit;
    }

    public static function mediaTrash(string $id): void
    {
        admin_check();
        MediaFile::softDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }

    public static function mediaDestroy(string $id): void
    {
        admin_check();
        MediaFile::hardDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }

    // ── Recycle Bin ───────────────────────────────────────────────────────

    public static function trashIndex(): void
    {
        admin_check();
        $tab        = $_GET['tab'] ?? 'artworks';
        $artworks   = Artwork::trashed();
        $categories = Category::trashed();
        $exhibits   = Exhibit::trashed();
        $mediaFiles = MediaFile::trashed();
        $messages   = ContactMessage::trashed();
        require dirname(__DIR__) . '/views/admin/trash.php';
    }

    public static function trashRestore(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        $id   = (int) ($_POST['id'] ?? 0);
        match ($type) {
            'artwork'  => Artwork::restore($id),
            'category' => Category::restore($id),
            'exhibit'  => Exhibit::restore($id),
            'media'    => MediaFile::restore($id),
            'message'  => ContactMessage::restore($id),
            default    => null,
        };
        $tab = match ($type) {
            'artwork'  => 'artworks',
            'category' => 'categories',
            'exhibit'  => 'exhibits',
            'message'  => 'messages',
            default    => $type,
        };
        header("Location: /admin/trash?tab={$tab}");
        exit;
    }

    public static function trashPurge(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        $id   = (int) ($_POST['id'] ?? 0);
        match ($type) {
            'artwork'  => Artwork::hardDelete($id),
            'category' => Category::hardDelete($id),
            'exhibit'  => Exhibit::hardDelete($id),
            'media'    => MediaFile::hardDelete($id),
            'message'  => ContactMessage::hardDelete($id),
            default    => null,
        };
        $tab = match ($type) {
            'artwork'  => 'artworks',
            'category' => 'categories',
            'exhibit'  => 'exhibits',
            'message'  => 'messages',
            default    => $type,
        };
        header("Location: /admin/trash?tab={$tab}");
        exit;
    }

    public static function trashEmpty(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        switch ($type) {
            case 'artworks':
                foreach (Artwork::trashed() as $a) {
                    Artwork::hardDelete((int) $a['id']);
                }
                break;
            case 'categories':
                foreach (Category::trashed() as $c) {
                    Category::hardDelete((int) $c['id']);
                }
                break;
            case 'exhibits':
                foreach (Exhibit::trashed() as $e) {
                    Exhibit::hardDelete((int) $e['id']);
                }
                break;
            case 'media':
                foreach (MediaFile::trashed() as $f) {
                    MediaFile::hardDelete((int) $f['id']);
                }
                break;
            case 'messages':
                foreach (ContactMessage::trashed() as $m) {
                    ContactMessage::hardDelete((int) $m['id']);
                }
                break;
        }
        header("Location: /admin/trash?tab={$type}");
        exit;
    }
}
