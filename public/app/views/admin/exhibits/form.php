<?php
$isEdit    = !empty($exhibit['id']);
$exhibit   = $exhibit ?? ['media_items' => []];
$pageTitle = ($isEdit ? 'Edit Work' : 'Add Work') . ' — ' . app_site_name() . ' Admin';
$needsEditor = true;
$mediaItems = $exhibit['media_items'] ?? [];
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Work' : 'Add Work' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? '/admin/exhibits/' . $exhibit['id'] . '/edit' : '/admin/exhibits/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Title *</label>
            <input type="text" name="title" value="<?= htmlspecialchars($exhibit['title'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated from title; override by typing here — do not change after publishing)</span></label>
            <input type="text" name="slug" value="<?= htmlspecialchars($exhibit['slug'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Year</label>
            <input type="text" name="year" value="<?= htmlspecialchars($exhibit['year'] ?? '') ?>" placeholder="2024">
        </div>

        <div class="form-row custom-multiselect-row" id="exhibits-multiselect-row">
            <label>Exhibit Collections</label>
            <div class="multiselect-control" data-name="collection_ids" data-placeholder="Select exhibit collections...">
                <div class="multiselect-input-wrapper">
                    <div class="multiselect-tags"></div>
                    <input type="text" class="multiselect-search" placeholder="Select exhibit collections..." autocomplete="off">
                </div>
                <div class="multiselect-dropdown">
                    <?php foreach ($allCollections as $collection): ?>
                        <div class="multiselect-option" data-id="<?= $collection['id'] ?>" data-name="<?= htmlspecialchars($collection['name']) ?>"
                             <?= in_array((string) $collection['id'], array_map('strval', $assignedCollectionIds)) ? 'data-selected="true"' : '' ?>>
                            <?= htmlspecialchars($collection['name']) ?>
                        </div>
                    <?php endforeach ?>
                </div>
                <div class="multiselect-hidden-inputs">
                    <?php foreach ($assignedCollectionIds as $collectionId): ?>
                        <input type="hidden" name="collection_ids[]" value="<?= $collectionId ?>">
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="4" data-tiptap><?= htmlspecialchars($exhibit['description'] ?? '') ?></textarea>
        </div>

        <fieldset class="form-fieldset">
            <legend>Museum Placard <span class="form-hint">(optional details shown publicly near the artwork)</span></legend>

            <div class="form-row">
                <label>Artist Name</label>
                <input type="text" name="artist_name" value="<?= htmlspecialchars($exhibit['artist_name'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label>Medium</label>
                <input type="text" name="medium" value="<?= htmlspecialchars($exhibit['medium'] ?? '') ?>" placeholder="Oil on canvas">
            </div>

            <div class="form-row">
                <label>Dimensions</label>
                <input type="text" name="dimensions" value="<?= htmlspecialchars($exhibit['dimensions'] ?? '') ?>" placeholder="24 x 36 in">
            </div>

            <div class="form-row">
                <label>Notes</label>
                <textarea name="placard_notes" rows="4" data-tiptap><?= htmlspecialchars($exhibit['placard_notes'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <div class="form-row">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= (int) ($exhibit['sort_order'] ?? 0) + 1 ?>" min="1">
        </div>

        <fieldset class="form-fieldset">
            <legend>Thumbnail <span class="form-hint">(optional)</span></legend>
            <input type="hidden" name="thumbnail_type" value="link">
            <div class="media-field-preview" id="artwork-thumb-preview">
                <?php if (!empty($exhibit['thumbnail_value'])): ?>
                    <img src="<?= htmlspecialchars($exhibit['thumbnail_value']) ?>" alt="">
                <?php endif ?>
            </div>
            <input id="artwork-thumb-url" type="text" name="thumbnail_link"
                   value="<?= htmlspecialchars($exhibit['thumbnail_value'] ?? '') ?>"
                   placeholder="No image selected" readonly>
            <div class="media-field-actions">
                <button type="button" class="picker-trigger"
                        data-picker-target="artwork-thumb-url"
                        data-picker-preview="artwork-thumb-preview"
                        data-picker-mode="image">Choose Image</button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-clear-input="artwork-thumb-url"
                        data-clear-preview="artwork-thumb-preview">Clear</button>
            </div>
        </fieldset>

        <fieldset class="form-fieldset artwork-media-builder" data-artwork-media-builder>
            <legend>Artwork Carousel *</legend>
            <p class="admin-hint">Mix images, short videos, and iframe embeds. Only the active slide loads on the public work page.</p>

            <div class="artwork-media-toolbar">
                <button type="button" class="admin-btn admin-btn-sm" data-add-slide="image">Add Image Slide</button>
                <button type="button" class="admin-btn admin-btn-sm" data-add-slide="video">Add Video Slide</button>
                <button type="button" class="admin-btn admin-btn-sm" data-add-slide="iframe">Add Iframe Slide</button>
                <button type="button" class="admin-btn admin-btn-sm" data-add-slide="content">Add Content Slide</button>
            </div>

            <div class="artwork-media-list" data-slide-list>
                <?php foreach ($mediaItems as $index => $item): ?>
                    <?php
                    $kind = $item['display_kind'] ?? $item['media_kind'] ?? 'image';
                    $assetId = (int) ($item['media_file_id'] ?? 0);
                    $posterId = (int) ($item['poster_media_file_id'] ?? 0);
                    $assetUrl = $item['source_url'] ?? ($assetId ? '/media/' . $assetId : '');
                    $legacyImageUrl = $kind === 'image' && $assetId ? '/image/' . $assetId : $assetUrl;
                    $posterUrl = $item['poster_url'] ?? ($posterId ? '/media/' . $posterId : '');
                    ?>
                    <article class="artwork-slide-card" data-slide-item data-kind="<?= htmlspecialchars($kind) ?>">
                        <div class="artwork-slide-head">
                            <span class="artwork-slide-handle" title="Drag to reorder">&#8597;</span>
                            <strong class="artwork-slide-title"><?= htmlspecialchars(ucfirst($kind)) ?> Slide</strong>
                            <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-edit-slide>Edit</button>
                            <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-remove-slide>Remove</button>
                        </div>

                        <input type="hidden" name="media_kind[<?= $index ?>]" value="<?= htmlspecialchars($kind) ?>" data-field="kind">
                        <input type="hidden" name="media_file_id[<?= $index ?>]" value="<?= $assetId ?>" data-field="media_file_id">
                        <input type="hidden" name="poster_media_file_id[<?= $index ?>]" value="<?= $posterId ?>" data-field="poster_media_file_id">

                        <div class="artwork-slide-preview" data-slide-preview>
                            <?php if ($kind === 'image' && $legacyImageUrl): ?>
                                <img src="<?= htmlspecialchars($legacyImageUrl) ?>" alt="">
                            <?php elseif ($kind === 'video' && $assetUrl): ?>
                                <video src="<?= htmlspecialchars($assetUrl) ?>" <?= $posterUrl ? 'poster="' . htmlspecialchars($posterUrl) . '"' : '' ?> muted preload="metadata"></video>
                            <?php elseif ($kind === 'content'): ?>
                                <div class="artwork-slide-preview-embed">Content slide</div>
                            <?php else: ?>
                                <div class="artwork-slide-preview-embed">Iframe embed slide</div>
                            <?php endif ?>
                        </div>

                        <div class="artwork-slide-fields">
                            <div class="form-row artwork-slide-asset-row<?= ($kind === 'iframe' || $kind === 'content') ? ' is-hidden' : '' ?>" data-slide-asset-row>
                                <label>Selected Asset</label>
                                <input type="text" value="<?= htmlspecialchars($assetUrl) ?>" readonly data-slide-asset-url>
                                <div class="media-field-actions">
                                    <button type="button" class="picker-trigger"
                                            data-slide-pick-asset
                                            data-picker-mode="<?= htmlspecialchars($kind === 'video' ? 'video' : 'image') ?>">
                                        Choose <?= htmlspecialchars($kind === 'video' ? 'Video' : 'Image') ?>
                                    </button>
                                </div>
                            </div>

                            <div class="form-row<?= $kind !== 'video' ? ' is-hidden' : '' ?>" data-slide-poster-row>
                                <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                                <input type="text" value="<?= htmlspecialchars($posterUrl) ?>" readonly data-slide-poster-url>
                                <div class="media-field-actions">
                                    <button type="button" class="picker-trigger" data-slide-pick-poster data-picker-mode="image">Choose Poster</button>
                                </div>
                            </div>

                            <div class="form-row<?= $kind !== 'iframe' ? ' is-hidden' : '' ?>" data-slide-iframe-row>
                                <label>Iframe HTML</label>
                                <textarea name="iframe_html[<?= $index ?>]" rows="5" data-field="iframe_html" placeholder="<iframe …></iframe>"><?= htmlspecialchars($item['iframe_html'] ?? '') ?></textarea>
                            </div>

                            <?php if ($kind === 'content'): ?>
                            <div class="form-row">
                                <label>Section style</label>
                                <select name="content_wrapper_class[<?= $index ?>]" data-field="content_wrapper_class">
                                    <?php
                                    $cwc = $item['content_wrapper_class'] ?? '';
                                    $cwOptions = ['' => 'None', 'mission-band' => 'Mission band', 'callout' => 'Callout', 'content-cards' => 'Content cards', 'managed-section' => 'Standard section box'];
                                    foreach ($cwOptions as $cwVal => $cwLabel):
                                    ?>
                                        <option value="<?= htmlspecialchars($cwVal, ENT_QUOTES, 'UTF-8') ?>"<?= $cwc === $cwVal ? ' selected' : '' ?>><?= htmlspecialchars($cwLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Content</label>
                                <textarea name="content_html[<?= $index ?>]" rows="8" data-tiptap data-field="content_html"><?= htmlspecialchars($item['content_html'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="content_html[<?= $index ?>]" value="">
                            <input type="hidden" name="content_wrapper_class[<?= $index ?>]" value="">
                            <?php endif ?>

                            <div class="form-row">
                                <label>Title <span class="form-hint">(optional, shown publicly above the slide)</span></label>
                                <input type="text" name="slide_title[<?= $index ?>]" value="<?= htmlspecialchars($item['title'] ?? '') ?>" maxlength="255" data-field="slide_title">
                            </div>

                            <div class="form-row<?= $kind === 'content' ? ' is-hidden' : '' ?>">
                                <label>Alt Text <span class="form-hint">(recommended for image and poster context)</span></label>
                                <input type="text" name="alt_text[<?= $index ?>]" value="<?= htmlspecialchars($item['alt_text'] ?? '') ?>" maxlength="250" data-field="alt_text">
                            </div>

                            <div class="form-row">
                                <label>Caption <span class="form-hint">(shown publicly, updates as the carousel changes)</span></label>
                                <input type="text" name="caption[<?= $index ?>]" value="<?= htmlspecialchars($item['caption'] ?? '') ?>" maxlength="250" data-field="caption">
                            </div>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>

            <template id="artwork-slide-template-image">
                <article class="artwork-slide-card" data-slide-item data-kind="image">
                    <div class="artwork-slide-head">
                        <span class="artwork-slide-handle" title="Drag to reorder">&#8597;</span>
                        <strong class="artwork-slide-title">Image Slide</strong>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-edit-slide>Edit</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-remove-slide>Remove</button>
                    </div>
                    <input type="hidden" name="media_kind[__INDEX__]" value="image" data-field="kind">
                    <input type="hidden" name="media_file_id[__INDEX__]" value="" data-field="media_file_id">
                    <input type="hidden" name="poster_media_file_id[__INDEX__]" value="" data-field="poster_media_file_id">
                    <div class="artwork-slide-preview" data-slide-preview>
                        <div class="artwork-slide-preview-empty">No image selected yet</div>
                    </div>
                    <div class="artwork-slide-fields">
                        <div class="form-row artwork-slide-asset-row" data-slide-asset-row>
                            <label>Selected Asset</label>
                            <input type="text" value="" readonly data-slide-asset-url>
                            <div class="media-field-actions">
                                <button type="button" class="picker-trigger" data-slide-pick-asset data-picker-mode="image">Choose Image</button>
                            </div>
                        </div>
                        <div class="form-row is-hidden" data-slide-poster-row>
                            <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                            <input type="text" value="" readonly data-slide-poster-url>
                            <div class="media-field-actions">
                                <button type="button" class="picker-trigger" data-slide-pick-poster data-picker-mode="image">Choose Poster</button>
                            </div>
                        </div>
                        <div class="form-row is-hidden" data-slide-iframe-row>
                            <label>Iframe HTML</label>
                            <textarea name="iframe_html[__INDEX__]" rows="5" data-field="iframe_html" placeholder="<iframe …></iframe>"></textarea>
                        </div>
                        <div class="form-row">
                            <label>Title <span class="form-hint">(optional, shown publicly above the slide)</span></label>
                            <input type="text" name="slide_title[__INDEX__]" value="" maxlength="255" data-field="slide_title">
                        </div>
                        <div class="form-row">
                            <label>Alt Text <span class="form-hint">(recommended for image and poster context)</span></label>
                            <input type="text" name="alt_text[__INDEX__]" value="" maxlength="250" data-field="alt_text">
                        </div>
                        <div class="form-row">
                            <label>Caption <span class="form-hint">(shown publicly, updates as the carousel changes)</span></label>
                            <input type="text" name="caption[__INDEX__]" value="" maxlength="250" data-field="caption">
                        </div>
                    </div>
                </article>
            </template>

            <template id="artwork-slide-template-video">
                <article class="artwork-slide-card" data-slide-item data-kind="video">
                    <div class="artwork-slide-head">
                        <span class="artwork-slide-handle" title="Drag to reorder">&#8597;</span>
                        <strong class="artwork-slide-title">Video Slide</strong>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-edit-slide>Edit</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-remove-slide>Remove</button>
                    </div>
                    <input type="hidden" name="media_kind[__INDEX__]" value="video" data-field="kind">
                    <input type="hidden" name="media_file_id[__INDEX__]" value="" data-field="media_file_id">
                    <input type="hidden" name="poster_media_file_id[__INDEX__]" value="" data-field="poster_media_file_id">
                    <div class="artwork-slide-preview" data-slide-preview>
                        <div class="artwork-slide-preview-empty">No video selected yet</div>
                    </div>
                    <div class="artwork-slide-fields">
                        <div class="form-row artwork-slide-asset-row" data-slide-asset-row>
                            <label>Selected Asset</label>
                            <input type="text" value="" readonly data-slide-asset-url>
                            <div class="media-field-actions">
                                <button type="button" class="picker-trigger" data-slide-pick-asset data-picker-mode="video">Choose Video</button>
                            </div>
                        </div>
                        <div class="form-row" data-slide-poster-row>
                            <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                            <input type="text" value="" readonly data-slide-poster-url>
                            <div class="media-field-actions">
                                <button type="button" class="picker-trigger" data-slide-pick-poster data-picker-mode="image">Choose Poster</button>
                            </div>
                        </div>
                        <div class="form-row is-hidden" data-slide-iframe-row>
                            <label>Iframe HTML</label>
                            <textarea name="iframe_html[__INDEX__]" rows="5" data-field="iframe_html" placeholder="<iframe …></iframe>"></textarea>
                        </div>
                        <div class="form-row">
                            <label>Title <span class="form-hint">(optional, shown publicly above the slide)</span></label>
                            <input type="text" name="slide_title[__INDEX__]" value="" maxlength="255" data-field="slide_title">
                        </div>
                        <div class="form-row">
                            <label>Alt Text <span class="form-hint">(recommended for image and poster context)</span></label>
                            <input type="text" name="alt_text[__INDEX__]" value="" maxlength="250" data-field="alt_text">
                        </div>
                        <div class="form-row">
                            <label>Caption <span class="form-hint">(shown publicly, updates as the carousel changes)</span></label>
                            <input type="text" name="caption[__INDEX__]" value="" maxlength="250" data-field="caption">
                        </div>
                    </div>
                </article>
            </template>

            <template id="artwork-slide-template-iframe">
                <article class="artwork-slide-card" data-slide-item data-kind="iframe">
                    <div class="artwork-slide-head">
                        <span class="artwork-slide-handle" title="Drag to reorder">&#8597;</span>
                        <strong class="artwork-slide-title">Iframe Slide</strong>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-edit-slide>Edit</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-remove-slide>Remove</button>
                    </div>
                    <input type="hidden" name="media_kind[__INDEX__]" value="iframe" data-field="kind">
                    <input type="hidden" name="media_file_id[__INDEX__]" value="" data-field="media_file_id">
                    <input type="hidden" name="poster_media_file_id[__INDEX__]" value="" data-field="poster_media_file_id">
                    <div class="artwork-slide-preview" data-slide-preview>
                        <div class="artwork-slide-preview-embed">Iframe embed slide</div>
                    </div>
                    <div class="artwork-slide-fields">
                        <div class="form-row artwork-slide-asset-row is-hidden" data-slide-asset-row>
                            <label>Selected Asset</label>
                            <input type="text" value="" readonly data-slide-asset-url>
                        </div>
                        <div class="form-row is-hidden" data-slide-poster-row>
                            <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                            <input type="text" value="" readonly data-slide-poster-url>
                        </div>
                        <div class="form-row" data-slide-iframe-row>
                            <label>Iframe HTML</label>
                            <textarea name="iframe_html[__INDEX__]" rows="5" data-field="iframe_html" placeholder="<iframe …></iframe>"></textarea>
                        </div>
                        <div class="form-row">
                            <label>Title <span class="form-hint">(optional, shown publicly above the slide)</span></label>
                            <input type="text" name="slide_title[__INDEX__]" value="" maxlength="255" data-field="slide_title">
                        </div>
                        <div class="form-row">
                            <label>Alt Text <span class="form-hint">(optional)</span></label>
                            <input type="text" name="alt_text[__INDEX__]" value="" maxlength="250" data-field="alt_text">
                        </div>
                        <div class="form-row">
                            <label>Caption <span class="form-hint">(shown publicly, updates as the carousel changes)</span></label>
                            <input type="text" name="caption[__INDEX__]" value="" maxlength="250" data-field="caption">
                        </div>
                    </div>
                </article>
            </template>
            <template id="artwork-slide-template-content">
                <article class="artwork-slide-card" data-slide-item data-kind="content">
                    <div class="artwork-slide-head">
                        <span class="artwork-slide-handle" title="Drag to reorder">&#8597;</span>
                        <strong class="artwork-slide-title">Content Slide</strong>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-edit-slide>Edit</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" data-remove-slide>Remove</button>
                    </div>
                    <input type="hidden" name="media_kind[__INDEX__]" value="content" data-field="kind">
                    <input type="hidden" name="media_file_id[__INDEX__]" value="" data-field="media_file_id">
                    <input type="hidden" name="poster_media_file_id[__INDEX__]" value="" data-field="poster_media_file_id">
                    <div class="artwork-slide-preview" data-slide-preview>
                        <div class="artwork-slide-preview-embed">Content slide</div>
                    </div>
                    <div class="artwork-slide-fields">
                        <div class="form-row artwork-slide-asset-row is-hidden" data-slide-asset-row>
                            <label>Selected Asset</label>
                            <input type="text" value="" readonly data-slide-asset-url>
                        </div>
                        <div class="form-row is-hidden" data-slide-poster-row>
                            <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                            <input type="text" value="" readonly data-slide-poster-url>
                        </div>
                        <div class="form-row is-hidden" data-slide-iframe-row>
                            <label>Iframe HTML</label>
                            <textarea name="iframe_html[__INDEX__]" rows="5" data-field="iframe_html" placeholder="<iframe …></iframe>"></textarea>
                        </div>
                        <div class="form-row">
                            <label>Section style</label>
                            <select name="content_wrapper_class[__INDEX__]" data-field="content_wrapper_class">
                                <option value="">None</option>
                                <option value="mission-band">Mission band</option>
                                <option value="callout">Callout</option>
                                <option value="content-cards">Content cards</option>
                                <option value="managed-section">Standard section box</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Content</label>
                            <textarea name="content_html[__INDEX__]" rows="8" data-tiptap-new data-field="content_html"></textarea>
                        </div>
                        <div class="form-row">
                            <label>Title <span class="form-hint">(optional, shown publicly above the slide)</span></label>
                            <input type="text" name="slide_title[__INDEX__]" value="" maxlength="255" data-field="slide_title">
                        </div>
                        <div class="form-row">
                            <label>Caption <span class="form-hint">(shown publicly, updates as the carousel changes)</span></label>
                            <input type="text" name="caption[__INDEX__]" value="" maxlength="250" data-field="caption">
                        </div>
                    </div>
                </article>
            </template>
        </fieldset>

        <div class="form-row">
            <label class="checkbox-label">
                <input type="checkbox" name="comments_enabled" value="1"
                       <?= !empty($exhibit['comments_enabled']) ? 'checked' : '' ?>>
                Enable comments on this exhibit
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Work' ?></button>
            <a href="/admin/exhibits" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>

    <dialog id="inline-create-dialog" class="inline-create-dialog">
        <div class="dialog-header">
            <h2 id="inline-dialog-title">Create New Category</h2>
        </div>
        <div class="dialog-body">
            <p>You are about to create a new <span id="inline-dialog-type">category</span> in the database. You can rename it below:</p>
            <div class="form-row">
                <input type="text" id="inline-dialog-name-input" placeholder="Name" autocomplete="off">
            </div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="admin-btn admin-btn-ghost" id="inline-dialog-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn" id="inline-dialog-confirm-btn">Create</button>
        </div>
    </dialog>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
