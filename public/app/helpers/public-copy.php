<?php

declare(strict_types=1);

function public_copy_defaults(): array
{
    $siteName = public_copy_site_name();

    return [
        'portfolio_copy' => [
            'gallery' => [
                'eyebrow' => 'Portfolio',
                'title' => 'Gallery',
                'intro' => 'The gallery is a sampler. Each section links to a dedicated archive page with all works. Use See More to load additional items here, or browse the full archive.',
                'meta_description' => 'Browse exhibits, exhibit collections, platform collections, and generative pieces from the ' . $siteName . ' portfolio.',
                'sections' => [
                    'exhibit_collections' => [
                        'heading' => 'Exhibit Collections',
                        'description' => 'Native exhibit collections built from related exhibits.',
                        'cta_label' => 'Browse all exhibit collections',
                        'empty_state' => 'No exhibit collections have been added yet.',
                    ],
                    'exhibits' => [
                        'heading' => 'Exhibits',
                        'description' => 'Individual exhibits with their own carousel, placard, and context.',
                        'cta_label' => 'Browse all exhibits',
                        'empty_state' => 'No exhibits have been added yet.',
                    ],
                    'platform_collections' => [
                        'heading' => 'Platform Collections',
                        'description' => 'Migrated platform-native collections with public detail pages and immersive room views.',
                        'cta_label' => 'Browse all platform collections',
                        'empty_state' => 'No platform collections have been added yet.',
                    ],
                    'pieces' => [
                        'heading' => 'Art Pieces',
                        'description' => 'Migrated generative pieces, code-driven experiments, and runtime sketches.',
                        'cta_label' => 'Browse all art pieces',
                        'empty_state' => 'No art pieces have been added yet.',
                    ],
                ],
            ],
            'archives' => [
                'exhibit_collections' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Exhibit Collections',
                    'intro' => 'Native exhibit collections, each gathering related exhibits into a durable archive page.',
                    'meta_description' => 'Browse native exhibit collections from ' . $siteName . '.',
                    'empty_state' => 'Nothing has been published here yet.',
                ],
                'exhibits' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Exhibits',
                    'intro' => 'Individual exhibits with media, metadata, and collection context.',
                    'meta_description' => 'Browse exhibits from the ' . $siteName . ' portfolio.',
                    'empty_state' => 'Nothing has been published here yet.',
                ],
                'platform_collections' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Platform Collections',
                    'intro' => 'Migrated platform-native collections, each with its own public detail page and immersive mode.',
                    'meta_description' => 'Browse migrated platform collections from ' . $siteName . '.',
                    'empty_state' => 'Nothing has been published here yet.',
                ],
                'pieces' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Art Pieces',
                    'intro' => 'Generative art pieces and creative experiments from the migrated platform archive.',
                    'meta_description' => 'Browse generative art pieces from ' . $siteName . '.',
                    'empty_state' => 'Nothing has been published here yet.',
                ],
                'art_media' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Art Media',
                    'intro' => 'Piece-oriented taxonomy terms that group related art pieces across the portfolio.',
                    'meta_description' => 'Browse art media terms used to organize pieces within the ' . $siteName . ' portfolio.',
                    'empty_state' => 'Nothing has been published here yet.',
                    'index_back_label' => 'Back to gallery',
                    'index_empty_state' => 'No art media have been created yet.',
                    'detail_back_label' => 'All Art Media',
                    'detail_empty_state' => 'No pieces use this art medium yet.',
                    'categories_meta_description' => 'Explore the art media that organize pieces within the ' . $siteName . ' portfolio.',
                ],
            ],
            'detail' => [
                'collection' => [
                    'back_label' => 'Return to exhibit collections',
                    'empty_state' => 'No exhibits in this collection yet.',
                ],
                'exhibit' => [
                    'back_label' => 'Return to exhibits',
                    'media_empty_title' => 'No media yet',
                    'media_empty_body' => 'This exhibit doesn\'t have any media added yet.',
                ],
            ],
        ],
        'public_art_copy' => [
            'pieces_archive' => [
                'eyebrow' => 'Generative Art',
                'heading' => 'Art Pieces',
                'intro' => 'Creative experiments and generative art works.',
                'meta_description' => 'Generative art pieces and creative experiments.',
                'empty_default' => 'No art pieces published yet.',
                'empty_search' => 'No pieces matched your search.',
            ],
            'collections_archive' => [
                'eyebrow' => 'Curated Collections',
                'heading' => 'Collections',
                'intro' => 'Curated collections of generative art pieces and images, viewable in an immersive 3D gallery.',
                'meta_description' => 'Curated collections of generative art pieces and images.',
                'empty_default' => 'No collections published yet.',
                'empty_search' => 'No collections matched your search.',
            ],
            'piece_detail' => [
                'eyebrow' => 'Art Piece',
                'placeholder_empty' => 'This piece has no rendered version yet.',
            ],
            'collection_detail' => [
                'eyebrow' => 'Collection',
                'empty_state' => 'This collection has no items yet.',
            ],
            'shared_ui' => [
                'comments_heading' => 'Comments',
                'comments_empty' => 'No comments yet. Be the first.',
                'view_immersive_label' => 'View in Immersive / VR Mode',
                'download_piece_label' => 'Download Piece',
                'download_png_label' => 'Download PNG',
            ],
            'not_found' => [
                'eyebrow' => '404',
                'title' => 'This page is not on the map.',
                'body' => 'The page may have moved, or the address may be incorrect.',
                'cta_label' => 'Return to the gallery',
                'meta_description' => 'The requested page could not be found.',
            ],
        ],
    ];
}

function public_copy_admin_sections(): array
{
    return [
        [
            'tab'   => 'gallery',
            'title' => 'Portfolio Gallery',
            'fields' => [
                ['path' => 'portfolio_copy.gallery.eyebrow', 'label' => 'Gallery eyebrow'],
                ['path' => 'portfolio_copy.gallery.title', 'label' => 'Gallery title'],
                ['path' => 'portfolio_copy.gallery.intro', 'label' => 'Gallery intro', 'rows' => 3],
                ['path' => 'portfolio_copy.gallery.meta_description', 'label' => 'Gallery meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.exhibit_collections.heading', 'label' => 'Exhibit collections heading'],
                ['path' => 'portfolio_copy.gallery.sections.exhibit_collections.description', 'label' => 'Exhibit collections description', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.exhibit_collections.cta_label', 'label' => 'Exhibit collections CTA label'],
                ['path' => 'portfolio_copy.gallery.sections.exhibit_collections.empty_state', 'label' => 'Exhibit collections empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.exhibits.heading', 'label' => 'Exhibits heading'],
                ['path' => 'portfolio_copy.gallery.sections.exhibits.description', 'label' => 'Exhibits description', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.exhibits.cta_label', 'label' => 'Exhibits CTA label'],
                ['path' => 'portfolio_copy.gallery.sections.exhibits.empty_state', 'label' => 'Exhibits empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.platform_collections.heading', 'label' => 'Platform collections heading'],
                ['path' => 'portfolio_copy.gallery.sections.platform_collections.description', 'label' => 'Platform collections description', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.platform_collections.cta_label', 'label' => 'Platform collections CTA label'],
                ['path' => 'portfolio_copy.gallery.sections.platform_collections.empty_state', 'label' => 'Platform collections empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.pieces.heading', 'label' => 'Art pieces heading'],
                ['path' => 'portfolio_copy.gallery.sections.pieces.description', 'label' => 'Art pieces description', 'rows' => 2],
                ['path' => 'portfolio_copy.gallery.sections.pieces.cta_label', 'label' => 'Art pieces CTA label'],
                ['path' => 'portfolio_copy.gallery.sections.pieces.empty_state', 'label' => 'Art pieces empty state', 'rows' => 2],
            ],
        ],
        [
            'tab'   => 'archives',
            'title' => 'Portfolio Archives',
            'fields' => [
                ['path' => 'portfolio_copy.archives.exhibit_collections.eyebrow', 'label' => 'Exhibit collections archive eyebrow', 'group' => 'Exhibit Collections'],
                ['path' => 'portfolio_copy.archives.exhibit_collections.heading', 'label' => 'Exhibit collections archive heading'],
                ['path' => 'portfolio_copy.archives.exhibit_collections.intro', 'label' => 'Exhibit collections archive intro', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.exhibit_collections.meta_description', 'label' => 'Exhibit collections archive meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.exhibit_collections.empty_state', 'label' => 'Exhibit collections archive empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.exhibits.eyebrow', 'label' => 'Exhibits archive eyebrow', 'group' => 'Exhibits'],
                ['path' => 'portfolio_copy.archives.exhibits.heading', 'label' => 'Exhibits archive heading'],
                ['path' => 'portfolio_copy.archives.exhibits.intro', 'label' => 'Exhibits archive intro', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.exhibits.meta_description', 'label' => 'Exhibits archive meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.exhibits.empty_state', 'label' => 'Exhibits archive empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.platform_collections.eyebrow', 'label' => 'Platform collections archive eyebrow', 'group' => 'Platform Collections'],
                ['path' => 'portfolio_copy.archives.platform_collections.heading', 'label' => 'Platform collections archive heading'],
                ['path' => 'portfolio_copy.archives.platform_collections.intro', 'label' => 'Platform collections archive intro', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.platform_collections.meta_description', 'label' => 'Platform collections archive meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.platform_collections.empty_state', 'label' => 'Platform collections archive empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.pieces.eyebrow', 'label' => 'Portfolio art pieces archive eyebrow', 'group' => 'Art Pieces'],
                ['path' => 'portfolio_copy.archives.pieces.heading', 'label' => 'Portfolio art pieces archive heading'],
                ['path' => 'portfolio_copy.archives.pieces.intro', 'label' => 'Portfolio art pieces archive intro', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.pieces.meta_description', 'label' => 'Portfolio art pieces archive meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.pieces.empty_state', 'label' => 'Portfolio art pieces archive empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.art_media.eyebrow', 'label' => 'Art media archive eyebrow', 'group' => 'Art Media'],
                ['path' => 'portfolio_copy.archives.art_media.heading', 'label' => 'Art media archive heading'],
                ['path' => 'portfolio_copy.archives.art_media.intro', 'label' => 'Art media archive intro', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.art_media.meta_description', 'label' => 'Art media archive meta description', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.art_media.empty_state', 'label' => 'Art media archive empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.art_media.categories_meta_description', 'label' => 'Art media index meta description', 'rows' => 2],
            ],
        ],
        [
            'tab'   => 'detail',
            'title' => 'Portfolio Detail Chrome',
            'fields' => [
                ['path' => 'portfolio_copy.archives.art_media.index_back_label', 'label' => 'Art media index back-link label'],
                ['path' => 'portfolio_copy.archives.art_media.index_empty_state', 'label' => 'Art media index empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.archives.art_media.detail_back_label', 'label' => 'Art media detail back-link label'],
                ['path' => 'portfolio_copy.archives.art_media.detail_empty_state', 'label' => 'Art media detail empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.detail.collection.back_label', 'label' => 'Exhibit collection detail back-link label'],
                ['path' => 'portfolio_copy.detail.collection.empty_state', 'label' => 'Exhibit collection detail empty state', 'rows' => 2],
                ['path' => 'portfolio_copy.detail.exhibit.back_label', 'label' => 'Exhibit detail back-link label'],
                ['path' => 'portfolio_copy.detail.exhibit.media_empty_title', 'label' => 'Exhibit detail empty-media title'],
                ['path' => 'portfolio_copy.detail.exhibit.media_empty_body', 'label' => 'Exhibit detail empty-media body', 'rows' => 2],
            ],
        ],
        [
            'tab'   => 'art-archives',
            'title' => 'Standalone Art Archives',
            'fields' => [
                ['path' => 'public_art_copy.pieces_archive.eyebrow', 'label' => 'Pieces archive eyebrow'],
                ['path' => 'public_art_copy.pieces_archive.heading', 'label' => 'Pieces archive heading'],
                ['path' => 'public_art_copy.pieces_archive.intro', 'label' => 'Pieces archive intro', 'rows' => 2],
                ['path' => 'public_art_copy.pieces_archive.meta_description', 'label' => 'Pieces archive meta description', 'rows' => 2],
                ['path' => 'public_art_copy.pieces_archive.empty_default', 'label' => 'Pieces archive empty state', 'rows' => 2],
                ['path' => 'public_art_copy.pieces_archive.empty_search', 'label' => 'Pieces archive search-empty state', 'rows' => 2],
                ['path' => 'public_art_copy.collections_archive.eyebrow', 'label' => 'Collections archive eyebrow'],
                ['path' => 'public_art_copy.collections_archive.heading', 'label' => 'Collections archive heading'],
                ['path' => 'public_art_copy.collections_archive.intro', 'label' => 'Collections archive intro', 'rows' => 2],
                ['path' => 'public_art_copy.collections_archive.meta_description', 'label' => 'Collections archive meta description', 'rows' => 2],
                ['path' => 'public_art_copy.collections_archive.empty_default', 'label' => 'Collections archive empty state', 'rows' => 2],
                ['path' => 'public_art_copy.collections_archive.empty_search', 'label' => 'Collections archive search-empty state', 'rows' => 2],
                ['path' => 'public_art_copy.piece_detail.eyebrow', 'label' => 'Piece detail eyebrow'],
                ['path' => 'public_art_copy.piece_detail.placeholder_empty', 'label' => 'Piece detail no-rendered-version message', 'rows' => 2],
                ['path' => 'public_art_copy.collection_detail.eyebrow', 'label' => 'Collection detail eyebrow'],
                ['path' => 'public_art_copy.collection_detail.empty_state', 'label' => 'Collection detail empty state', 'rows' => 2],
            ],
        ],
        [
            'tab'   => 'shared-ui',
            'title' => 'Shared Public UI',
            'fields' => [
                ['path' => 'public_art_copy.shared_ui.comments_heading', 'label' => 'Comments heading'],
                ['path' => 'public_art_copy.shared_ui.comments_empty', 'label' => 'Comments empty state', 'rows' => 2],
                ['path' => 'public_art_copy.shared_ui.view_immersive_label', 'label' => 'Immersive action label'],
                ['path' => 'public_art_copy.shared_ui.download_piece_label', 'label' => 'Download piece label'],
                ['path' => 'public_art_copy.shared_ui.download_png_label', 'label' => 'Download PNG label'],
                ['path' => 'public_art_copy.not_found.eyebrow', 'label' => '404 eyebrow'],
                ['path' => 'public_art_copy.not_found.title', 'label' => '404 heading'],
                ['path' => 'public_art_copy.not_found.body', 'label' => '404 body', 'rows' => 2],
                ['path' => 'public_art_copy.not_found.cta_label', 'label' => '404 CTA label'],
                ['path' => 'public_art_copy.not_found.meta_description', 'label' => '404 meta description', 'rows' => 2],
            ],
        ],
    ];
}

function public_copy_value(string $path, ?string $fallback = null): string
{
    $settings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
    $defaults = public_copy_defaults();

    $stored = public_copy_path_get($settings, $path);
    if (is_scalar($stored)) {
        return (string) $stored;
    }

    $defaultValue = public_copy_path_get($defaults, $path);
    if (is_scalar($defaultValue)) {
        return (string) $defaultValue;
    }

    return $fallback ?? '';
}

function public_copy_path_get(array $source, string $path): mixed
{
    $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));
    $cursor = $source;
    foreach ($segments as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        $cursor = $cursor[$segment];
    }
    return $cursor;
}

function public_copy_path_set(array &$target, string $path, mixed $value): void
{
    $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));
    if ($segments === []) {
        return;
    }

    $cursor =& $target;
    foreach ($segments as $index => $segment) {
        if ($index === count($segments) - 1) {
            $cursor[$segment] = $value;
            return;
        }
        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }
        $cursor =& $cursor[$segment];
    }
}

function public_copy_footer_credit_html(?string $rawHtml): string
{
    return public_copy_sanitize_inline_html((string) $rawHtml);
}

function public_copy_sanitize_inline_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $wrapperId = 'public-copy-inline-root';
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $allowedTags = ['a', 'strong', 'em', 'b', 'i', 'br', 'p', 'span'];
    $wrapper = $dom->getElementById($wrapperId);
    if (!$wrapper instanceof DOMElement) {
        return '';
    }

    $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $dom, $allowedTags): void {
        if (!$node instanceof DOMElement) {
            return;
        }

        for ($child = $node->firstChild; $child !== null; $child = $nextChild) {
            $nextChild = $child->nextSibling;
            $sanitizeNode($child);
        }

        $tag = strtolower($node->tagName);
        if (!in_array($tag, $allowedTags, true)) {
            $fragment = $dom->createDocumentFragment();
            while ($node->firstChild) {
                $fragment->appendChild($node->firstChild);
            }
            $node->parentNode?->replaceChild($fragment, $node);
            return;
        }

        $href = trim((string) $node->getAttribute('href'));
        $attrsToRemove = [];
        if ($node->hasAttributes()) {
            for ($i = 0; $i < $node->attributes->length; $i++) {
                $attr = $node->attributes->item($i);
                if ($attr !== null) {
                    $attrsToRemove[] = $attr->name;
                }
            }
        }
        foreach ($attrsToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }

        if ($tag !== 'a') {
            return;
        }

        if (!public_copy_is_safe_href($href)) {
            $fragment = $dom->createDocumentFragment();
            while ($node->firstChild) {
                $fragment->appendChild($node->firstChild);
            }
            $node->parentNode?->replaceChild($fragment, $node);
            return;
        }

        $node->setAttribute('href', $href);
        if (preg_match('/^https?:/i', $href)) {
            $node->setAttribute('rel', 'noopener noreferrer');
            $node->setAttribute('target', '_blank');
        }
    };

    for ($child = $wrapper->firstChild; $child !== null; $child = $nextChild) {
        $nextChild = $child->nextSibling;
        $sanitizeNode($child);
    }

    $output = '';
    foreach (iterator_to_array($wrapper->childNodes) as $childNode) {
        $output .= $dom->saveHTML($childNode);
    }

    return trim($output);
}

function public_copy_is_safe_href(string $href): bool
{
    if ($href === '') {
        return false;
    }
    if (str_starts_with($href, '/')) {
        return true;
    }
    if (str_starts_with($href, '#')) {
        return true;
    }
    return (bool) preg_match('/^(https?:|mailto:|tel:)/i', $href);
}

function public_copy_site_name(): string
{
    if (function_exists('app_site_name')) {
        return app_site_name();
    }

    return 'My Site';
}
