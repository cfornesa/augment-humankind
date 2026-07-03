// Drag-and-drop row reordering for admin tables
document.querySelectorAll('tbody[data-reorder-url]').forEach(tbody => {
    const url = tbody.dataset.reorderUrl;
    const visibility = tbody.dataset.reorderVisibility || '';
    const statusId = tbody.dataset.reorderStatus || 'reorder-status';
    const allowNarrowDrag = tbody.dataset.reorderNarrow === 'true';
    let dragging = null;

    function canDragRows() {
        return allowNarrowDrag || window.innerWidth > 1024;
    }

    function saveOrder() {
        const ids = [...tbody.querySelectorAll('tr[data-id]')]
            .map(r => r.dataset.id)
            .join(',');

        const status = document.getElementById(statusId);
        const body = new URLSearchParams({ ids });
        if (visibility) {
            body.set('visibility', visibility);
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(r => r.json())
        .then(() => {
            if (status) { status.textContent = 'Order saved.'; setTimeout(() => status.textContent = '', 2000); }
        });
    }

    tbody.querySelectorAll('tr').forEach(row => {
        row.setAttribute('draggable', 'true');

        row.addEventListener('dragstart', e => {
            if (!canDragRows()) {
                e.preventDefault();
                return;
            }
            dragging = row;
            row.classList.add('drag-active');
            e.dataTransfer.effectAllowed = 'move';
        });

        row.addEventListener('dragend', () => {
            if (!canDragRows()) return;
            row.classList.remove('drag-active');
            tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
            dragging = null;

            saveOrder();
        });

        row.addEventListener('dragover', e => {
            if (!canDragRows()) return;
            e.preventDefault();
            if (!dragging || dragging === row) return;
            const rect = row.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            tbody.insertBefore(dragging, after ? row.nextSibling : row);
        });

        row.addEventListener('dragenter', e => {
            if (!canDragRows()) return;
            e.preventDefault();
            if (row !== dragging) row.classList.add('drag-over');
        });

        row.addEventListener('dragleave', () => {
            if (!canDragRows()) return;
            row.classList.remove('drag-over');
        });
    });

    tbody.querySelectorAll('[data-reorder-move]').forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr[data-id]');
            if (!row) return;
            if (button.dataset.reorderMove === 'up' && row.previousElementSibling) {
                tbody.insertBefore(row, row.previousElementSibling);
                saveOrder();
                row.querySelector('[data-reorder-move="up"]')?.focus();
            } else if (button.dataset.reorderMove === 'down' && row.nextElementSibling) {
                tbody.insertBefore(row.nextElementSibling, row);
                saveOrder();
                row.querySelector('[data-reorder-move="down"]')?.focus();
            }
        });
    });
});

// Drag-and-drop checkbox list ordering for forms that persist submitted order
document.querySelectorAll('[data-checkbox-sortable]').forEach(list => {
    let dragging = null;

    list.querySelectorAll('label[draggable="true"]').forEach(label => {
        label.addEventListener('dragstart', event => {
            dragging = label;
            label.classList.add('drag-active');
            event.dataTransfer.effectAllowed = 'move';
        });

        label.addEventListener('dragend', () => {
            label.classList.remove('drag-active');
            list.querySelectorAll('label').forEach(item => item.classList.remove('drag-over'));
            dragging = null;
        });

        label.addEventListener('dragover', event => {
            event.preventDefault();
            if (!dragging || dragging === label) return;
            const rect = label.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            list.insertBefore(dragging, after ? label.nextSibling : label);
        });

        label.addEventListener('dragenter', event => {
            event.preventDefault();
            if (label !== dragging) label.classList.add('drag-over');
        });

        label.addEventListener('dragleave', () => label.classList.remove('drag-over'));
    });
});

// Portfolio archives: load the next batch from the same route on scroll
(function () {
    function responsiveBatchSize() {
        return 3;
    }

    async function fetchBatch(fetchUrl, nextOffset, pageSize) {
        const url = new URL(fetchUrl, window.location.origin);
        url.searchParams.set('partial', '1');
        url.searchParams.set('offset', String(nextOffset));
        url.searchParams.set('limit', String(pageSize));
        const response = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'fetch' },
        });
        if (!response.ok) throw new Error(`Request failed with ${response.status}`);
        return response.text();
    }

    function applyBatch(html, grid, listing, status) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const batch = template.content.querySelector('[data-listing-batch]');
        if (!batch) throw new Error('Missing listing batch payload.');

        while (batch.firstChild) grid.appendChild(batch.firstChild);

        const newOffset = Number.parseInt(batch.dataset.nextOffset || '', 10) || 0;
        const hasMore = batch.dataset.hasMore === 'true';
        listing.dataset.nextOffset = String(newOffset);
        listing.dataset.hasMore = hasMore ? 'true' : 'false';

        if (status) {
            status.textContent = hasMore
                ? `Showing ${grid.children.length} items so far.`
                : `All ${grid.children.length} items loaded.`;
        }
        return hasMore;
    }

    // --- See More button mode (gallery page) ---
    document.querySelectorAll('[data-see-more-listing]').forEach(listing => {
        const grid   = listing.querySelector('[data-listing-grid]');
        const btn    = listing.querySelector('[data-listing-see-more-btn]');
        const status = listing.querySelector('[data-listing-status]');
        const fetchUrl = listing.dataset.fetchUrl || window.location.pathname;
        let nextOffset = Number.parseInt(listing.dataset.nextOffset || '0', 10) || 0;
        let hasMore = listing.dataset.hasMore === 'true';
        let loading = false;

        if (!grid || !btn || !hasMore) {
            btn?.setAttribute('hidden', '');
            return;
        }

        btn.addEventListener('click', async () => {
            if (loading || !hasMore) return;
            loading = true;
            btn.disabled = true;
            btn.textContent = 'Loading…';

            try {
                const pageSize = responsiveBatchSize();
                const html = await fetchBatch(fetchUrl, nextOffset, pageSize);
                hasMore = applyBatch(html, grid, listing, status);
                nextOffset = Number.parseInt(listing.dataset.nextOffset || '', 10) || nextOffset;
                if (!hasMore) btn.setAttribute('hidden', '');
                else {
                    btn.disabled = false;
                    btn.textContent = 'See More';
                }
            } catch (error) {
                if (status) status.textContent = 'Could not load more items right now.';
                btn.disabled = false;
                btn.textContent = 'See More';
                console.error(error);
            } finally {
                loading = false;
            }
        });
    });

    // --- Infinite scroll mode (archive pages) ---
    document.querySelectorAll('[data-lazy-listing]').forEach(listing => {
        const grid = listing.querySelector('[data-listing-grid]');
        const sentinel = listing.querySelector('[data-listing-sentinel]');
        const status = listing.querySelector('[data-listing-status]');
        const fetchUrl = listing.dataset.fetchUrl || window.location.pathname;
        let hasMore = listing.dataset.hasMore === 'true';
        let loading = false;

        // Override page size with responsive value
        listing.dataset.pageSize = String(responsiveBatchSize());
        let nextOffset = Number.parseInt(listing.dataset.nextOffset || '0', 10) || 0;

        if (!grid || !sentinel || !hasMore) {
            sentinel?.classList.add('is-hidden');
            return;
        }

        async function loadMore() {
            if (loading || !hasMore) return;
            loading = true;
            if (status) status.textContent = 'Loading more…';

            try {
                const pageSize = responsiveBatchSize();
                const html = await fetchBatch(fetchUrl, nextOffset, pageSize);
                hasMore = applyBatch(html, grid, listing, status);
                nextOffset = Number.parseInt(listing.dataset.nextOffset || '', 10) || nextOffset;

                if (!hasMore) {
                    sentinel.classList.add('is-hidden');
                    observer.disconnect();
                }
            } catch (error) {
                if (status) status.textContent = 'Could not load more items right now.';
                console.error(error);
            } finally {
                loading = false;
            }
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) loadMore();
            });
        }, { rootMargin: '320px 0px' });

        observer.observe(sentinel);
    });
})();

// Portfolio work page: lazy-loaded artwork carousel
(function () {
    const carousel = document.querySelector('[data-artwork-carousel]');
    if (!carousel) return;

    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const prevBtn = carousel.querySelector('[data-carousel-prev]');
    const nextBtn = carousel.querySelector('[data-carousel-next]');
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    const titleEl = carousel.querySelector('[data-carousel-title]');
    const captionEl = carousel.querySelector('[data-carousel-caption]');
    let activeIndex = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    if (activeIndex < 0) activeIndex = 0;

    function teardownSlide(slide) {
        const kind = slide.dataset.kind;
        if (kind === 'iframe') {
            slide.innerHTML = '<div class="work-slide-placeholder"><span>IFRAME loads when activated</span></div>';
            return;
        }

        const video = slide.querySelector('video');
        if (video) {
            video.pause();
        }
    }

    function ensureSlideContent(slide) {
        const kind = slide.dataset.kind;
        if (slide.dataset.loaded === 'true' && kind !== 'iframe') return;

        if (kind === 'image') {
            const img = document.createElement('img');
            img.className = 'work-image';
            img.src = slide.dataset.source;
            img.alt = slide.dataset.alt || '';
            img.decoding = 'async';
            slide.innerHTML = '';
            slide.appendChild(img);
            slide.dataset.loaded = 'true';
            return;
        }

        if (kind === 'video') {
            const video = document.createElement('video');
            video.className = 'work-video';
            video.controls = true;
            video.preload = 'metadata';
            video.src = slide.dataset.source;
            if (slide.dataset.poster) video.poster = slide.dataset.poster;
            slide.innerHTML = '';
            slide.appendChild(video);
            slide.dataset.loaded = 'true';
            return;
        }

        if (kind === 'iframe') {
            const wrap = document.createElement('div');
            wrap.className = 'work-embed';
            wrap.innerHTML = slide.dataset.iframeHtml || '';
            slide.innerHTML = '';
            slide.appendChild(wrap);
        }
    }

    function syncUi() {
        slides.forEach((slide, index) => {
            const isActive = index === activeIndex;
            slide.classList.toggle('is-active', isActive);
            slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            if (isActive) ensureSlideContent(slide);
            else teardownSlide(slide);
        });

        dots.forEach((dot, index) => {
            const isActive = index === activeIndex;
            dot.classList.toggle('is-active', isActive);
            dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (prevBtn) prevBtn.disabled = activeIndex === 0;
        if (nextBtn) nextBtn.disabled = activeIndex === slides.length - 1;

        if (titleEl) {
            titleEl.textContent = slides[activeIndex]?.dataset.title || '';
        }

        if (captionEl) {
            captionEl.textContent = slides[activeIndex]?.dataset.caption || '';
        }
    }

    function goTo(index) {
        if (index < 0 || index >= slides.length || index === activeIndex) return;
        activeIndex = index;
        syncUi();
    }

    prevBtn?.addEventListener('click', () => goTo(activeIndex - 1));
    nextBtn?.addEventListener('click', () => goTo(activeIndex + 1));
    dots.forEach(dot => dot.addEventListener('click', () => goTo(Number(dot.dataset.index || 0))));

    // iOS Safari: synthesised click events can be dropped on absolutely-positioned controls
    prevBtn?.addEventListener('touchend', (e) => {
        e.preventDefault();
        prevBtn.click();
    }, { passive: false });

    nextBtn?.addEventListener('touchend', (e) => {
        e.preventDefault();
        nextBtn.click();
    }, { passive: false });

    dots.forEach(dot => {
        dot.addEventListener('touchend', (e) => {
            e.preventDefault();
            dot.click();
        }, { passive: false });
    });

    carousel.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(activeIndex - 1);
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(activeIndex + 1);
        }
    });

    syncUi();
})();

// Generic slug auto-fill: any input[id$="-name"] → sibling input[id$="-slug"]
['cat', 'exhibit', 'page'].forEach(prefix => {
    const nameInput = document.getElementById(prefix + '-name');
    const slugInput = document.getElementById(prefix + '-slug');
    if (!nameInput || !slugInput) return;

    let autoFill = slugInput.value === '';
    slugInput.addEventListener('input', () => { autoFill = slugInput.value === ''; });
    nameInput.addEventListener('input', () => {
        if (!autoFill) return;
        slugInput.value = nameInput.value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-');
    });
});

// Admin artwork form: ordered mixed-media carousel builder
(function () {
    const builder = document.querySelector('[data-artwork-media-builder]');
    if (!builder) return;

    const list = builder.querySelector('[data-slide-list]');
    const templates = {
        image: document.getElementById('artwork-slide-template-image'),
        video: document.getElementById('artwork-slide-template-video'),
        iframe: document.getElementById('artwork-slide-template-iframe'),
        content: document.getElementById('artwork-slide-template-content'),
    };

    function assetUrlFor(kind, asset) {
        if (!asset) return '';
        if (kind === 'image') return asset.legacy_url || asset.url || '';
        return asset.url || '';
    }

    function hydratePreview(card, kind, assetUrl, posterUrl = '') {
        const preview = card.querySelector('[data-slide-preview]');
        if (!preview) return;

        preview.innerHTML = '';
        if (kind === 'image' && assetUrl) {
            const img = document.createElement('img');
            img.src = assetUrl;
            img.alt = '';
            preview.appendChild(img);
            return;
        }

        if (kind === 'video' && assetUrl) {
            const video = document.createElement('video');
            video.src = assetUrl;
            if (posterUrl) video.poster = posterUrl;
            video.muted = true;
            video.preload = 'metadata';
            preview.appendChild(video);
            return;
        }

        const empty = document.createElement('div');
        empty.className = kind === 'iframe' ? 'artwork-slide-preview-embed' : 'artwork-slide-preview-empty';
        empty.textContent = kind === 'iframe' ? 'Iframe embed slide' : `No ${kind} selected yet`;
        preview.appendChild(empty);
    }

    function setActiveSlide(card) {
        list.querySelectorAll('[data-slide-item]').forEach(c => c.classList.add('is-collapsed'));
        card.classList.remove('is-collapsed');
    }

    function renumber() {
        [...list.querySelectorAll('[data-slide-item]')].forEach((card, index) => {
            card.querySelectorAll('input[name], textarea[name]').forEach(field => {
                if (!field.name) return;
                field.name = field.name.replace(/\[\d+\]/, `[${index}]`).replace(/\[__INDEX__\]/, `[${index}]`);
            });
        });
    }

    function bindCard(card) {
        const kind = card.dataset.kind;
        const removeBtn = card.querySelector('[data-remove-slide]');
        const assetBtn = card.querySelector('[data-slide-pick-asset]');
        const posterBtn = card.querySelector('[data-slide-pick-poster]');
        const assetUrlInput = card.querySelector('[data-slide-asset-url]');
        const posterUrlInput = card.querySelector('[data-slide-poster-url]');
        const mediaIdField = card.querySelector('[data-field="media_file_id"]');
        const posterIdField = card.querySelector('[data-field="poster_media_file_id"]');

        card.draggable = true;
        card.querySelector('[data-edit-slide]')?.addEventListener('click', () => setActiveSlide(card));

        removeBtn?.addEventListener('click', () => {
            card.remove();
            renumber();
        });

        assetBtn?.addEventListener('click', () => {
            if (!window.openMediaPicker) return;
            window.openMediaPicker(result => {
                if (!result?.id) return;
                mediaIdField.value = result.id;
                assetUrlInput.value = assetUrlFor(kind, result);
                hydratePreview(card, kind, assetUrlInput.value, posterUrlInput?.value || '');
            }, 'select', { mode: assetBtn.dataset.pickerMode || kind });
        });

        posterBtn?.addEventListener('click', () => {
            if (!window.openMediaPicker) return;
            window.openMediaPicker(result => {
                if (!result?.id) return;
                posterIdField.value = result.id;
                posterUrlInput.value = result.legacy_url || result.url || '';
                hydratePreview(card, 'video', assetUrlInput?.value || '', posterUrlInput.value);
            }, 'select', { mode: 'image' });
        });
    }

    let dragging = null;
    list.addEventListener('dragstart', event => {
        const card = event.target.closest('[data-slide-item]');
        if (!card) return;
        dragging = card;
        card.classList.add('drag-active');
    });

    list.addEventListener('dragend', () => {
        if (!dragging) return;
        dragging.classList.remove('drag-active');
        dragging = null;
        renumber();
    });

    list.addEventListener('dragover', event => {
        event.preventDefault();
        const over = event.target.closest('[data-slide-item]');
        if (!dragging || !over || over === dragging) return;
        const rect = over.getBoundingClientRect();
        const after = event.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragging, after ? over.nextSibling : over);
    });

    function addSlide(kind) {
        const template = templates[kind];
        if (!template) return;
        const index = list.querySelectorAll('[data-slide-item]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const card = wrap.firstElementChild;
        list.appendChild(card);
        bindCard(card);

        const tiptapNew = card.querySelector('[data-tiptap-new]');
        if (tiptapNew) {
            tiptapNew.removeAttribute('data-tiptap-new');
            tiptapNew.setAttribute('data-tiptap', '');
            if (window.initTiptap) window.initTiptap(tiptapNew);
        }

        renumber();
        setActiveSlide(card);

        const assetBtn = card.querySelector('[data-slide-pick-asset]');
        if (assetBtn && kind !== 'iframe' && kind !== 'content') {
            assetBtn.click();
        }
    }

    builder.querySelectorAll('[data-add-slide]').forEach(btn => {
        btn.addEventListener('click', () => addSlide(btn.dataset.addSlide));
    });

    list.querySelectorAll('[data-slide-item]').forEach(bindCard);
    renumber();
    const firstSlide = list.querySelector('[data-slide-item]');
    if (firstSlide) setActiveSlide(firstSlide);
})();

// Admin artwork form: custom multiselect with inline category/exhibit creation
(function () {
    function initMultiselects() {
        document.querySelectorAll('.multiselect-control').forEach(control => {
            const name = control.dataset.name;
            const placeholder = control.dataset.placeholder || 'Select...';
            const tagsContainer = control.querySelector('.multiselect-tags');
            const searchInput = control.querySelector('.multiselect-search');
            const dropdown = control.querySelector('.multiselect-dropdown');
            const hiddenContainer = control.querySelector('.multiselect-hidden-inputs');
            let options = Array.from(control.querySelectorAll('.multiselect-option'));

            const addOption = document.createElement('div');
            addOption.className = 'multiselect-option-add';
            addOption.style.display = 'none';
            dropdown.appendChild(addOption);

            function updatePlaceholder() {
                searchInput.placeholder = tagsContainer.children.length > 0 ? '' : placeholder;
            }

            function addTag(id, labelName) {
                if (tagsContainer.querySelector(`[data-id="${id}"]`)) return;

                const tag = document.createElement('div');
                tag.className = 'multiselect-tag';
                tag.dataset.id = id;
                tag.innerHTML = `
                    <span>${escapeHtml(labelName)}</span>
                    <button type="button" class="multiselect-tag-remove" aria-label="Remove ${escapeHtml(labelName)}">&times;</button>
                `;
                tag.querySelector('.multiselect-tag-remove').addEventListener('click', event => {
                    event.stopPropagation();
                    removeTag(id);
                });
                tagsContainer.appendChild(tag);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${name}[]`;
                input.value = id;
                input.dataset.id = id;
                hiddenContainer.appendChild(input);

                const option = options.find(o => o.dataset.id === String(id));
                if (option) option.dataset.selected = 'true';
                updatePlaceholder();
            }

            function removeTag(id) {
                tagsContainer.querySelector(`.multiselect-tag[data-id="${id}"]`)?.remove();
                hiddenContainer.querySelector(`input[data-id="${id}"]`)?.remove();
                const option = options.find(o => o.dataset.id === String(id));
                if (option) delete option.dataset.selected;
                updatePlaceholder();
            }

            function bindOption(option) {
                option.addEventListener('click', event => {
                    event.stopPropagation();
                    if (option.dataset.selected === 'true') {
                        removeTag(option.dataset.id);
                    } else {
                        addTag(option.dataset.id, option.dataset.name);
                    }
                    searchInput.value = '';
                    options.forEach(o => o.style.display = '');
                    addOption.style.display = 'none';
                    searchInput.focus();
                });
            }

            function triggerInlineCreation(query) {
                const type = name === 'category_ids'
                    ? 'art-medium'
                    : (name === 'collection_ids' ? 'collection' : 'exhibit');
                openInlineCreateDialog(query, type, finalName => {
                    const url = type === 'art-medium'
                        ? '/admin/art-media/create-inline'
                        : (type === 'collection' ? '/admin/exhibit-collections/create-inline' : '/admin/exhibits/create-inline');
                    addOption.textContent = `Creating "${finalName}"...`;
                    addOption.style.pointerEvents = 'none';
                    searchInput.disabled = true;

                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ name: finalName }).toString(),
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(err => { throw new Error(err.error || 'Failed to create'); });
                        }
                        return res.json();
                    })
                    .then(data => {
                        const newOption = document.createElement('div');
                        newOption.className = 'multiselect-option';
                        newOption.dataset.id = data.id;
                        newOption.dataset.name = data.name;
                        newOption.textContent = data.name;
                        dropdown.insertBefore(newOption, addOption);
                        options.push(newOption);
                        bindOption(newOption);
                        addTag(data.id, data.name);
                        searchInput.value = '';
                        options.forEach(o => o.style.display = '');
                        addOption.style.display = 'none';
                    })
                    .catch(err => {
                        alert(`Error creating ${type.replace('-', ' ')}: ${err.message}`);
                    })
                    .finally(() => {
                        searchInput.disabled = false;
                        addOption.style.pointerEvents = '';
                        searchInput.focus();
                    });
                });
            }

            addOption.addEventListener('click', event => {
                event.stopPropagation();
                const query = searchInput.value.trim();
                if (query) triggerInlineCreation(query);
            });

            options.forEach(option => {
                if (option.dataset.selected === 'true') addTag(option.dataset.id, option.dataset.name);
                bindOption(option);
            });

            searchInput.addEventListener('focus', () => {
                control.classList.add('focus');
                dropdown.style.display = 'block';
            });

            control.addEventListener('click', event => {
                if (event.target !== searchInput && !event.target.closest('.multiselect-dropdown') && !event.target.closest('.multiselect-tag-remove')) {
                    searchInput.focus();
                }
            });

            document.addEventListener('click', event => {
                if (control.contains(event.target)) return;
                control.classList.remove('focus');
                dropdown.style.display = 'none';
                searchInput.value = '';
                options.forEach(option => option.style.display = '');
                addOption.style.display = 'none';
            });

            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim();
                const queryLower = query.toLowerCase();
                let exactMatchFound = false;

                options.forEach(option => {
                    const text = option.dataset.name.toLowerCase();
                    option.style.display = text.includes(queryLower) ? '' : 'none';
                    if (text === queryLower) exactMatchFound = true;
                });

                if (query && !exactMatchFound) {
                    const typeLabel = name === 'category_ids'
                        ? 'Art Medium'
                        : (name === 'collection_ids' ? 'Exhibit Collection' : 'Exhibit');
                    addOption.textContent = `+ Create ${typeLabel} "${query}"`;
                    addOption.style.display = 'block';
                } else {
                    addOption.style.display = 'none';
                }
            });

            searchInput.addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const query = searchInput.value.trim();
                if (!query) return;

                const exactMatch = options.find(option => option.style.display !== 'none' && option.dataset.name.toLowerCase() === query.toLowerCase());
                if (exactMatch) exactMatch.click();
                else triggerInlineCreation(query);
            });

            updatePlaceholder();
        });
    }

    function openInlineCreateDialog(query, type, onConfirm) {
        const dialog = document.getElementById('inline-create-dialog');
        if (!dialog) return;

        const titleEl = document.getElementById('inline-dialog-title');
        const typeEl = document.getElementById('inline-dialog-type');
        const inputEl = document.getElementById('inline-dialog-name-input');
        const confirmBtn = document.getElementById('inline-dialog-confirm-btn');
        const cancelBtn = document.getElementById('inline-dialog-cancel-btn');

        titleEl.textContent = `Create New ${type === 'category' ? 'Category' : 'Exhibit'}`;
        typeEl.textContent = type;
        inputEl.value = query;

        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

        function handleConfirm() {
            const finalName = inputEl.value.trim();
            if (!finalName) {
                alert('Name is required.');
                return;
            }
            dialog.close();
            onConfirm(finalName);
        }

        newConfirmBtn.addEventListener('click', handleConfirm);
        newCancelBtn.addEventListener('click', () => dialog.close());
        inputEl.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleConfirm();
            }
        }, { once: true });

        dialog.showModal();
        inputEl.focus();
        inputEl.select();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMultiselects);
    } else {
        initMultiselects();
    }
})();

// Blog post card: expand, comments, share, embed

(function () {
    const COMMENT_PENCIL_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>';
    const COMMENT_TRASH_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';

    function setExpandButtonState(btn, expanded) {
        const label = expanded ? (btn.dataset.expandedLabel || 'Collapse') : (btn.dataset.collapsedLabel || 'Expand');
        const ariaLabel = expanded ? (btn.dataset.expandedAriaLabel || label) : (btn.dataset.collapsedAriaLabel || label);
        const icon = expanded ? (btn.dataset.expandedIcon || '') : (btn.dataset.collapsedIcon || '');
        const iconWrap = btn.querySelector('.post-action-icon');
        const labelWrap = btn.querySelector('.btn-label');
        if (iconWrap) {
            iconWrap.innerHTML = icon;
        }
        if (labelWrap) {
            labelWrap.textContent = label;
        }
        btn.setAttribute('aria-label', ariaLabel);
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    // --- Expand panel ---
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.post-expand-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        const panel  = document.getElementById('post-expand-' + postId);
        const card = btn.closest('.blog-card');
        const preview = card?.querySelector('.blog-card-preview');
        if (!panel) return;

        const open = btn.getAttribute('aria-expanded') === 'true';
        if (open) {
            panel.hidden = true;
            preview?.classList.remove('is-hidden');
            setExpandButtonState(btn, false);
            return;
        }

        const body = panel.querySelector('.post-content-body');
        if (!body.dataset.loaded) {
            btn.disabled = true;
            const labelWrap = btn.querySelector('.btn-label');
            const previousLabel = labelWrap ? labelWrap.textContent : '';
            if (labelWrap) {
                labelWrap.textContent = 'Loading…';
            }
            try {
                const res  = await fetch('/api/posts/' + postId + '/full');
                const data = await res.json();
                body.innerHTML = data.content || '';
                body.dataset.loaded = 'true';
            } catch {
                body.textContent = 'Could not load post content.';
            }
            btn.disabled = false;
            if (labelWrap) {
                labelWrap.textContent = previousLabel;
            }
        }

        panel.hidden = false;
        preview?.classList.add('is-hidden');
        setExpandButtonState(btn, true);
    });

    // --- Comments panel ---
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.post-comments-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        const panel  = document.getElementById('post-comments-' + postId);
        if (!panel) return;

        const open = btn.getAttribute('aria-expanded') === 'true';
        if (open) {
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            return;
        }

        const list = panel.querySelector('.post-comments-list');
        if (!list.dataset.loaded) {
            try {
                await reloadComments(list);
                list.dataset.loaded = 'true';
            } catch {
                list.textContent = 'Could not load comments.';
            }
        }

        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCommentDate(value) {
        return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function commentItemMarkup(comment) {
        const id = Number(comment.id || 0);
        const name = escapeHtml(comment.author_name || 'Anonymous');
        const date = escapeHtml(formatCommentDate(comment.created_at));
        const text = escapeHtml(comment.content || '').replace(/\n/g, '<br>');
        const manageButtons = comment.can_manage ? `
            <div class="post-comment-actions">
                <button type="button"
                        class="post-comment-icon-btn"
                        data-comment-edit-toggle
                        aria-expanded="false"
                        aria-controls="comment-edit-${id}"
                        aria-label="Edit your comment">
                    ${COMMENT_PENCIL_ICON}
                </button>
                <button type="button"
                        class="post-comment-icon-btn post-comment-icon-btn-danger"
                        data-comment-delete
                        aria-label="Delete your comment">
                    ${COMMENT_TRASH_ICON}
                </button>
            </div>
        ` : '';
        const editForm = comment.can_manage ? `
            <form class="post-comment-edit-form" id="comment-edit-${id}" data-comment-id="${id}" hidden>
                <textarea name="content" maxlength="500" required>${escapeHtml(comment.content || '')}</textarea>
                <div class="post-comment-edit-actions">
                    <button type="submit" class="post-action-btn">Save</button>
                    <button type="button" class="post-action-btn" data-comment-edit-cancel>Cancel</button>
                </div>
            </form>
        ` : '';

        return `<div class="post-comment-item" data-comment-id="${id}">
            <div class="post-comment-header">
                <strong>${name} · <span class="post-comment-date">${date}</span></strong>
                ${manageButtons}
            </div>
            <p class="post-comment-content">${text}</p>
            ${editForm}
        </div>`;
    }

    function renderComments(container, comments) {
        const emptyMessage = container.dataset.emptyMessage || 'No comments yet.';
        if (!comments.length) {
            container.innerHTML = `<p class="admin-empty">${escapeHtml(emptyMessage)}</p>`;
            return;
        }
        container.innerHTML = comments.map(commentItemMarkup).join('');
    }

    async function reloadComments(container) {
        const url = container?.dataset.commentsUrl;
        if (!url) return;
        const res = await fetch(url);
        const comments = await res.json();
        renderComments(container, Array.isArray(comments) ? comments : []);
    }

    // --- Comment form submit ---
    document.addEventListener('submit', async e => {
        const form = e.target.closest('.post-comment-form');
        if (!form) return;
        e.preventDefault();

        const postId     = form.dataset.postId;
        const commentUrl = form.dataset.commentUrl || '/api/posts/' + postId + '/comments';
        const panel      = postId ? document.getElementById('post-comments-' + postId) : null;
        const list       = panel
            ? panel.querySelector('.post-comments-list')
            : form.closest('.blog-comments, .comments-section, .post-comments-panel')?.querySelector('.post-comments-list');
        const submit  = form.querySelector('[type="submit"]');
        const data    = new FormData(form);

        submit.disabled  = true;
        submit.textContent = 'Posting…';

        try {
            const res  = await fetch(commentUrl, { method: 'POST', body: new URLSearchParams(data) });
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || 'Error');
            if (list) {
                await reloadComments(list);
                list.dataset.loaded = 'true';
            }
            form.reset();
            submit.textContent = 'Posted!';
            setTimeout(() => { submit.textContent = 'Post comment'; submit.disabled = false; }, 2000);
        } catch (err) {
            alert(err.message || 'Could not post comment.');
            submit.textContent = 'Post comment';
            submit.disabled = false;
        }
    });

    document.addEventListener('click', e => {
        const toggle = e.target.closest('[data-comment-edit-toggle]');
        if (toggle) {
            const item = toggle.closest('.post-comment-item');
            const form = item?.querySelector('.post-comment-edit-form');
            if (!form) return;
            const open = toggle.getAttribute('aria-expanded') === 'true';
            form.hidden = open;
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (!open) {
                form.querySelector('textarea')?.focus();
            }
            return;
        }

        const cancel = e.target.closest('[data-comment-edit-cancel]');
        if (cancel) {
            const form = cancel.closest('.post-comment-edit-form');
            const item = cancel.closest('.post-comment-item');
            const toggleBtn = item?.querySelector('[data-comment-edit-toggle]');
            if (form) form.hidden = true;
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            return;
        }

        const deleteBtn = e.target.closest('[data-comment-delete]');
        if (!deleteBtn) return;

        const item = deleteBtn.closest('.post-comment-item');
        const list = deleteBtn.closest('.post-comments-list');
        const commentId = item?.dataset.commentId;
        if (!commentId || !list) return;
        if (!window.confirm('Delete this comment?')) return;

        deleteBtn.disabled = true;
        fetch('/api/comments/' + commentId + '/delete', { method: 'POST' })
            .then(async res => {
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Could not delete comment.');
                await reloadComments(list);
            })
            .catch(err => {
                alert(err.message || 'Could not delete comment.');
            })
            .finally(() => {
                deleteBtn.disabled = false;
            });
    });

    document.addEventListener('submit', async e => {
        const form = e.target.closest('.post-comment-edit-form');
        if (!form) return;
        e.preventDefault();

        const item = form.closest('.post-comment-item');
        const list = form.closest('.post-comments-list');
        const commentId = item?.dataset.commentId;
        const submit = form.querySelector('[type="submit"]');
        if (!commentId || !list || !submit) return;

        submit.disabled = true;
        submit.textContent = 'Saving…';

        try {
            const res = await fetch('/api/comments/' + commentId + '/edit', {
                method: 'POST',
                body: new URLSearchParams(new FormData(form)),
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || 'Could not update comment.');
            await reloadComments(list);
        } catch (err) {
            alert(err.message || 'Could not update comment.');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Save';
        }
    });

    // --- Share dialog ---
    let shareDialog = null;

    function ensureShareDialog() {
        if (shareDialog) return shareDialog;
        shareDialog = document.createElement('dialog');
        shareDialog.id = 'share-dialog';
        shareDialog.innerHTML = `
            <h2>Share this post</h2>
            <div class="share-links">
                <a class="post-action-btn" id="share-x" target="_blank" rel="noopener">X / Twitter</a>
                <a class="post-action-btn" id="share-bsky" target="_blank" rel="noopener">Bluesky</a>
                <a class="post-action-btn" id="share-li" target="_blank" rel="noopener">LinkedIn</a>
                <a class="post-action-btn" id="share-fb" target="_blank" rel="noopener">Facebook</a>
                <button class="post-action-btn" id="share-copy">Copy link</button>
                <button class="post-action-btn" id="share-close">Close</button>
            </div>`;
        document.body.appendChild(shareDialog);
        shareDialog.querySelector('#share-close').addEventListener('click', () => shareDialog.close());
        shareDialog.addEventListener('click', e => { if (e.target === shareDialog) shareDialog.close(); });
        shareDialog.querySelector('#share-copy').addEventListener('click', function () {
            const url = this.dataset.url || '';
            navigator.clipboard.writeText(url).then(() => {
                this.textContent = 'Copied!';
                setTimeout(() => { this.textContent = 'Copy link'; }, 2000);
            });
        });
        return shareDialog;
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('.post-share-btn');
        if (!btn) return;

        const title = btn.dataset.title || '';
        const path  = btn.dataset.url   || '';
        const url   = location.origin + path;
        const enc   = encodeURIComponent;

        const dialog = ensureShareDialog();
        dialog.querySelector('#share-x').href    = 'https://x.com/intent/tweet?text=' + enc(title) + '&url=' + enc(url);
        dialog.querySelector('#share-bsky').href = 'https://bsky.app/intent/compose?text=' + enc(title + ' ' + url);
        dialog.querySelector('#share-li').href   = 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc(url);
        dialog.querySelector('#share-fb').href   = 'https://www.facebook.com/sharer/sharer.php?u=' + enc(url);
        dialog.querySelector('#share-copy').dataset.url = url;
        dialog.showModal();
    });

    // --- Embed copy ---
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.post-embed-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        const src    = location.origin + '/embed/posts/' + postId;
        const code   = `<iframe src="${src}" width="100%" height="420" frameborder="0" loading="lazy"></iframe>`;
        try {
            await navigator.clipboard.writeText(code);
            const orig = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = orig; }, 2000);
        } catch {
            prompt('Copy embed code:', code);
        }
    });
})();

// Mobile navigation toggle
document.querySelectorAll('.menu-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const header = btn.closest('header');
        const open = header.classList.toggle('nav-open');
        btn.setAttribute('aria-expanded', String(open));
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('header.nav-open').forEach(h => {
            h.classList.remove('nav-open');
            const toggle = h.querySelector('.menu-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('header')) {
        document.querySelectorAll('header.nav-open').forEach(h => {
            h.classList.remove('nav-open');
            const toggle = h.querySelector('.menu-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    }
});
