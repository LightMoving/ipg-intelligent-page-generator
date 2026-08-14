
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }

        document.addEventListener('DOMContentLoaded', callback);
    }

    ready(function () {
        var previewButton = document.getElementById('precision_duplicate_preview_generation');
        var previewBox = document.getElementById('precision_duplicate_preview_box');

        if (!previewButton || !previewBox) {
            return;
        }

        var modal = document.createElement('div');
        modal.className = 'precision-duplicate-preview-modal';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="precision-duplicate-preview-modal-overlay" data-precision-preview-close="1"></div>' +
            '<div class="precision-duplicate-preview-modal-dialog" role="dialog" aria-modal="true">' +
                '<button type="button" class="precision-duplicate-preview-modal-close" data-precision-preview-close="1" aria-label="Close preview">×</button>' +
                '<div class="precision-duplicate-preview-modal-inner">' +
                    '<div class="precision-duplicate-preview-modal-body"></div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        var modalBody = modal.querySelector('.precision-duplicate-preview-modal-body');

        function openModal() {
            if (!previewBox.innerHTML.trim()) {
                return;
            }

            previewBox.classList.add('is-modal-source');

            if (previewBox.parentNode !== modalBody) {
                modalBody.appendChild(previewBox);
            }

            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (event) {
            var close = event.target.closest('[data-precision-preview-close="1"]');

            if (close) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        document.addEventListener('click', function (event) {
            var generateButton = event.target.closest('.precision-duplicate-preview-generate-inline');

            if (!generateButton) {
                return;
            }

            event.preventDefault();

            var submitButton =
                document.getElementById('precision_duplicate_generate_submit') ||
                document.querySelector('[name="precision_duplicate_bulk_submit"]');

            if (submitButton) {
                submitButton.click();
            }
        });

        var observer = new MutationObserver(function () {
            if (previewBox.innerHTML.trim()) {
                window.setTimeout(openModal, 30);
            }
        });

        observer.observe(previewBox, {
            childList: true,
            subtree: true
        });

        previewButton.addEventListener('click', function () {
            window.setTimeout(function () {
                if (previewBox.innerHTML.trim()) {
                    openModal();
                }
            }, 180);
        });
    });
})();

// V2.3.3: reinforce roadmap two-column layout if legacy admin markup is browser-normalized.
document.addEventListener('DOMContentLoaded', function () {
    var dashboard = document.querySelector('.precision-duplicate-two-column-dashboard');
    if (!dashboard) {
        return;
    }

    dashboard.classList.add('precision-duplicate-roadmap-layout-ready');
});


document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.precision-duplicate-advanced-toggle');
    var body = document.querySelector('.precision-duplicate-advanced-body');

    if (!toggle || !body) {
        return;
    }

    var card = toggle.closest('.precision-duplicate-card');

    function setAdvancedState(open) {
        if (open) {
            body.style.setProperty('display', 'block', 'important');
            toggle.setAttribute('data-open', 'true');

            if (card) {
                card.classList.add('is-open');
            }
        } else {
            body.style.setProperty('display', 'none', 'important');
            toggle.setAttribute('data-open', 'false');

            if (card) {
                card.classList.remove('is-open');
            }
        }

        var label = toggle.querySelector('.precision-duplicate-toggle-label');
        if (label) {
            label.textContent = open ? 'Hide' : 'Show';
        }
    }

    setAdvancedState(false);

    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var isOpen = toggle.getAttribute('data-open') === 'true';
        setAdvancedState(!isOpen);
    });
});


document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('precision_duplicate_generate_memberium_map');

    if (!button) {
        return;
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var mapField = document.getElementById('precision_duplicate_memberium_tag_map');
        var startNumberField = document.getElementById('precision_duplicate_helper_start_number');
        var startTagField = document.getElementById('precision_duplicate_helper_start_tag_id');
        var incrementField = document.getElementById('precision_duplicate_helper_increment');

        if (!mapField || !startNumberField || !startTagField || !incrementField) {
            alert('The Sequential Tag Helper fields could not be found.');
            return;
        }

        var startNumber = parseInt(startNumberField.value, 10);
        var startTagId = parseInt(startTagField.value, 10);
        var increment = parseInt(incrementField.value, 10);

        if (isNaN(startNumber) || isNaN(startTagId) || isNaN(increment) || increment === 0) {
            alert('Please enter a valid start page number, start tag ID, and increment.');
            return;
        }

        var rangeStartField =
            document.getElementById('precision_duplicate_range_start') ||
            document.getElementById('precision_duplicate_start') ||
            document.querySelector('[name="precision_duplicate_range_start"]') ||
            document.querySelector('[name="precision_duplicate_start"]');

        var rangeEndField =
            document.getElementById('precision_duplicate_range_end') ||
            document.getElementById('precision_duplicate_end') ||
            document.querySelector('[name="precision_duplicate_range_end"]') ||
            document.querySelector('[name="precision_duplicate_end"]');

        var rangeStart = rangeStartField ? parseInt(rangeStartField.value, 10) : startNumber;
        var rangeEnd = rangeEndField ? parseInt(rangeEndField.value, 10) : startNumber;

        if (isNaN(rangeStart) || isNaN(rangeEnd) || rangeEnd < rangeStart) {
            rangeStart = startNumber;
            rangeEnd = startNumber;
        }

        var currentTagId = startTagId + ((rangeStart - startNumber) * increment);
        var lines = [];

        for (var n = rangeStart; n <= rangeEnd; n++) {
            lines.push(n + '=' + currentTagId);
            currentTagId += increment;
        }

        mapField.value = lines.join("\n");
        mapField.dispatchEvent(new Event('input', { bubbles: true }));
        mapField.dispatchEvent(new Event('change', { bubbles: true }));
    });
});


document.addEventListener('DOMContentLoaded', function () {
    var previewButton = document.getElementById('precision_duplicate_preview_generation');
    var generateSubmitButton = document.getElementById('precision_duplicate_generate_submit');
    var generationStatusField = document.getElementById('precision_duplicate_generation_status');

    function getGenerationStatus() {
        return generationStatusField && generationStatusField.value === 'publish' ? 'publish' : 'draft';
    }

    function setGenerationStatus(status) {
        status = status === 'publish' ? 'publish' : 'draft';
        if (generationStatusField) {
            generationStatusField.value = status;
        }
        if (generateSubmitButton) {
            generateSubmitButton.textContent = status === 'publish' ? 'Publish Pages Immediately' : 'Generate Draft Pages';
        }

        var previewBox = document.getElementById('precision_duplicate_preview_box');
        if (previewBox) {
            var statusRadios = previewBox.querySelectorAll('input[name="precision_duplicate_generation_status_choice"]');
            statusRadios.forEach(function(radio) {
                radio.checked = radio.value === status;
            });

            var warning = previewBox.querySelector('.precision-duplicate-publish-warning');
            var outputWrapper = previewBox.querySelector('.precision-duplicate-generation-output');
            if (warning) {
                warning.style.display = status === 'publish' ? 'block' : 'none';
            }
            if (outputWrapper) {
                outputWrapper.classList.toggle('is-publish', status === 'publish');
            }

            var badge = previewBox.querySelector('.precision-duplicate-output-badge');
            if (badge) {
                badge.classList.toggle('is-publish', status === 'publish');
                badge.classList.toggle('is-draft', status !== 'publish');
                badge.textContent = 'Output: ' + (status === 'publish' ? 'Published Pages' : 'Draft Pages');
            }

            var inlineButton = previewBox.querySelector('.precision-duplicate-preview-generate-inline');
            if (inlineButton) {
                var total = parseInt(inlineButton.getAttribute('data-total'), 10) || 0;
                inlineButton.textContent = status === 'publish'
                    ? 'Publish ' + total + ' Page' + (total === 1 ? '' : 's') + ' Immediately'
                    : 'Generate ' + total + ' Draft Page' + (total === 1 ? '' : 's');
            }
        }
    }

    setGenerationStatus(getGenerationStatus());

    if (!previewButton) {
        return;
    }

    function getValue(id, fallback) {
        var field = document.getElementById(id) || document.querySelector('[name="' + id + '"]');
        return field ? field.value : fallback;
    }

    function replaceTokens(template, context) {
        if (!template) {
            return '';
        }

        return template
            .replaceAll('{n}', String(context.n))
            .replaceAll('{prev}', String(context.prev))
            .replaceAll('{next}', String(context.next))
            .replaceAll('{range_start}', String(context.rangeStart))
            .replaceAll('{range_end}', String(context.rangeEnd));
    }

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\-_]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    function parseMap(raw) {
        var map = {};
        String(raw || '').split(/\r?\n/).forEach(function(line) {
            line = line.trim();
            if (!line) {
                return;
            }

            var match = line.match(/^(\d+)\s*[:=]\s*(\d+)$/);
            if (match) {
                map[parseInt(match[1], 10)] = match[2];
                return;
            }

            match = line.match(/(\d+)[^\d]+(\d+)\)?$/);
            if (match) {
                map[parseInt(match[1], 10)] = match[2];
            }
        });

        return map;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    previewButton.addEventListener('click', function (event) {
        event.preventDefault();

        var rangeStart = parseInt(getValue('precision_duplicate_range_start', getValue('precision_duplicate_start', '1')), 10);
        var rangeEnd = parseInt(getValue('precision_duplicate_range_end', getValue('precision_duplicate_end', '1')), 10);

        if (isNaN(rangeStart) || isNaN(rangeEnd) || rangeEnd < rangeStart) {
            alert('Please enter a valid generation range before previewing.');
            return;
        }

        var titlePattern = getValue('precision_duplicate_title_pattern', 'Page {n}');
        var slugPattern = getValue('precision_duplicate_slug_pattern', 'page-{n}');
        var tagPattern = getValue('precision_duplicate_tag_pattern', '');
        var memberiumMapRaw = getValue('precision_duplicate_memberium_tag_map', '');
        var memberiumMap = parseMap(memberiumMapRaw);
        var total = (rangeEnd - rangeStart) + 1;
        var maxRows = Math.min(total, 10);
        var rows = [];

        for (var i = 0; i < maxRows; i++) {
            var n = rangeStart + i;
            var context = {
                n: n,
                prev: n - 1,
                next: n + 1,
                rangeStart: rangeStart,
                rangeEnd: rangeEnd
            };

            var title = replaceTokens(titlePattern, context);
            var slug = slugify(replaceTokens(slugPattern, context));
            var tag = replaceTokens(tagPattern, context);
            var memberiumId = memberiumMap[n] || '';

            rows.push(
                '<tr>' +
                    '<td>' + escapeHtml(n) + '</td>' +
                    '<td>' + escapeHtml(title) + '</td>' +
                    '<td>' + escapeHtml(slug) + '</td>' +
                    '<td>' + escapeHtml(tag || '—') + '</td>' +
                    '<td>' + escapeHtml(memberiumId || '—') + '</td>' +
                '</tr>'
            );
        }

        var box = document.getElementById('precision_duplicate_preview_box');

        if (!box) {
            return;
        }

        var note = total > maxRows
            ? 'Showing first ' + maxRows + ' of ' + total + ' pages. +' + (total - maxRows) + ' more will be generated.'
            : 'Showing all ' + total + ' page' + (total === 1 ? '' : 's') + ' to be generated.';

        var selectedStatus = getGenerationStatus();
        var outputLabel = selectedStatus === 'publish' ? 'Published Pages' : 'Draft Pages';
        var outputClass = selectedStatus === 'publish' ? 'is-publish' : 'is-draft';
        var inlineActionLabel = selectedStatus === 'publish'
            ? 'Publish ' + total + ' Page' + (total === 1 ? '' : 's') + ' Immediately'
            : 'Generate ' + total + ' Draft Page' + (total === 1 ? '' : 's');

        box.innerHTML =
            '<div class="precision-duplicate-preview-header">' +
                '<strong>Preview Generation</strong>' +
                '<span>Total pages to generate: ' + escapeHtml(total) + '</span>' +
                '<span class="precision-duplicate-output-badge ' + outputClass + '">Output: ' + escapeHtml(outputLabel) + '</span>' +
            '</div>' +
            '<div class="precision-duplicate-preview-table-wrap">' +
                '<table class="precision-duplicate-preview-table">' +
                    '<thead>' +
                        '<tr>' +
                            '<th>#</th>' +
                            '<th>Title</th>' +
                            '<th>Slug</th>' +
                            '<th>Value</th>' +
                            '<th>Memberium Tag ID</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>' + rows.join('') + '</tbody>' +
                '</table>' +
            '</div>' +
            '<div class="precision-duplicate-preview-note">' +
                '<div class="precision-duplicate-generation-output" role="group" aria-label="Generation output">' +
                    '<div class="precision-duplicate-generation-output-options">' +
                        '<label class="precision-duplicate-output-option"><input type="radio" name="precision_duplicate_generation_status_choice" value="draft"' + (selectedStatus === 'draft' ? ' checked' : '') + '> <span>Create Draft Pages</span></label>' +
                        '<label class="precision-duplicate-output-option"><input type="radio" name="precision_duplicate_generation_status_choice" value="publish"' + (selectedStatus === 'publish' ? ' checked' : '') + '> <span>Publish Immediately</span></label>' +
                    '</div>' +
                    '<p class="description precision-duplicate-publish-warning" style="' + (selectedStatus === 'publish' ? 'display:block;' : 'display:none;') + '">Published pages will become visible on your site immediately.</p>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">' +
                    '<span>' + escapeHtml(note) + '</span>' +
                    '<button type="button" class="button button-primary precision-duplicate-preview-generate-inline" data-total="' + escapeHtml(total) + '">' +
                        escapeHtml(inlineActionLabel) +
                    '</button>' +
                '</div>' +
            '</div>';

        box.classList.add('is-visible');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    document.addEventListener('change', function(event) {
        if (event.target && event.target.name === 'precision_duplicate_generation_status_choice') {
            setGenerationStatus(event.target.value);
        }
    });

    document.addEventListener('click', function(event) {
        var inlineButton = event.target.closest('.precision-duplicate-preview-generate-inline');

        if (!inlineButton) {
            return;
        }

        event.preventDefault();

        if (generateSubmitButton) {
            generateSubmitButton.click();
        }
    });
});


(function() {
                    function precisionDuplicateForceColumns() {
                        var dash = document.querySelector('.precision-duplicate-two-column-dashboard');
                        if (!dash) { return; }
                        dash.classList.add('precision-duplicate-roadmap-layout-ready');
                        dash.style.display = 'grid';
                        dash.style.gap = '28px';
                        dash.style.alignItems = 'start';
                        dash.style.width = '100%';
                        dash.style.maxWidth = '100%';
                        dash.style.gridTemplateColumns = (dash.offsetWidth > 760)
                            ? 'minmax(0, 1.42fr) minmax(340px, .82fr)'
                            : '1fr';
                    }
                    precisionDuplicateForceColumns();
                    window.addEventListener('resize', precisionDuplicateForceColumns);
                    window.addEventListener('load', precisionDuplicateForceColumns);
                    setTimeout(precisionDuplicateForceColumns, 250);
                })();


(function() {
                    var wrap = document.querySelector('.precision-duplicate-page-search-wrap');
                    var searchInput = document.getElementById('precision_duplicate_page_search');
                    var sourceInput = document.getElementById('precision_duplicate_source_page');
                    var resultsBox = document.getElementById('precision_duplicate_page_search_results');
                    var spinner = document.getElementById('precision_duplicate_page_search_spinner');
                    var timer = null;

                    if (!wrap || !searchInput || !sourceInput || !resultsBox) {
                        return;
                    }

                    function setLoading(isLoading) {
                        if (!spinner) {
                            return;
                        }
                        if (isLoading) {
                            spinner.classList.add('is-active');
                        } else {
                            spinner.classList.remove('is-active');
                        }
                    }

                    function showMessage(message) {
                        resultsBox.style.display = 'block';
                        resultsBox.innerHTML = '<div class="precision-duplicate-page-search-message">' + message + '</div>';
                    }

                    function hideResults() {
                        resultsBox.style.display = 'none';
                        resultsBox.innerHTML = '';
                    }

                    function escapeHtml(value) {
                        return String(value).replace(/[&<>"]/g, function(match) {
                            return ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;'}[match]);
                        });
                    }

                    function renderResults(results) {
                        if (!results || !results.length) {
                            showMessage((window.ipgIntelligentPageGenerator && window.ipgIntelligentPageGenerator.i18n.noMatchingPages) || 'No matching pages found.');
                            return;
                        }

                        resultsBox.style.display = 'block';
                        resultsBox.innerHTML = results.map(function(item) {
                            var title = item.title || ((window.ipgIntelligentPageGenerator && window.ipgIntelligentPageGenerator.i18n.untitledPage) || 'Untitled page');
                            var meta = 'ID ' + item.id + ' • ' + (item.status || 'page');
                            return '<button type="button" class="precision-duplicate-page-search-result" data-page-id="' + item.id + '">' +
                                '<span class="precision-duplicate-page-search-title">' + escapeHtml(title) + '</span>' +
                                '<span class="precision-duplicate-page-search-meta">' + escapeHtml(meta) + '</span>' +
                            '</button>';
                        }).join('');
                    }

                    function runSearch() {
                        var term = searchInput.value.trim();
                        if (term.length < 2) {
                            hideResults();
                            return;
                        }

                        setLoading(true);
                        var url = ajaxurl + '?action=precision_duplicate_search_pages&nonce=' + encodeURIComponent(wrap.getAttribute('data-nonce')) + '&term=' + encodeURIComponent(term);

                        fetch(url, {credentials: 'same-origin'})
                            .then(function(response) { return response.json(); })
                            .then(function(data) {
                                setLoading(false);
                                if (!data || !data.success) {
                                    showMessage((window.ipgIntelligentPageGenerator && window.ipgIntelligentPageGenerator.i18n.searchFailed) || 'Search failed. Please try again.');
                                    return;
                                }
                                renderResults(data.data.results || []);
                            })
                            .catch(function() {
                                setLoading(false);
                                showMessage((window.ipgIntelligentPageGenerator && window.ipgIntelligentPageGenerator.i18n.searchFailed) || 'Search failed. Please try again.');
                            });
                    }

                    searchInput.addEventListener('input', function() {
                        clearTimeout(timer);
                        timer = setTimeout(runSearch, 250);
                    });

                    resultsBox.addEventListener('click', function(event) {
                        var button = event.target.closest('.precision-duplicate-page-search-result');
                        if (!button) {
                            return;
                        }
                        sourceInput.value = button.getAttribute('data-page-id');
                        searchInput.value = button.querySelector('.precision-duplicate-page-search-title').textContent;
                        hideResults();
                        sourceInput.focus();
                    });
                }());


(function() {
                    document.addEventListener('DOMContentLoaded', function() {
                        var manualMode = document.querySelector('input[name="precision_duplicate_generation_mode"][value="manual"]');
                        var rangeMode = document.querySelector('input[name="precision_duplicate_generation_mode"][value="range"]');
                        var manualTitles = document.getElementById('precision_duplicate_titles');

                        if (!manualMode || !manualTitles) {
                            return;
                        }

                        function openAdvancedOptions() {
                            var advancedBody = manualTitles.closest('.precision-duplicate-advanced-body');
                            var advancedCard = manualTitles.closest('.precision-duplicate-card');
                            var toggle = advancedCard ? advancedCard.querySelector('.precision-duplicate-advanced-toggle') : null;
                            var label = toggle ? toggle.querySelector('.precision-duplicate-toggle-label') : null;

                            if (advancedBody) {
                                advancedBody.style.setProperty('display', 'block', 'important');
                            }

                            if (advancedCard) {
                                advancedCard.classList.add('is-open');
                            }

                            if (toggle) {
                                toggle.setAttribute('data-open', 'true');
                            }

                            if (label) {
                                label.textContent = 'Hide';
                            }

                            return advancedCard || manualTitles;
                        }

                        function guideToManualTitles() {
                            var target = openAdvancedOptions();

                            window.setTimeout(function() {
                                if (target && target.scrollIntoView) {
                                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }
                            }, 80);

                            window.setTimeout(function() {
                                manualTitles.focus({ preventScroll: true });
                            }, 520);

                            if (target && target.classList) {
                                target.classList.add('precision-duplicate-guided-highlight');
                                window.setTimeout(function() {
                                    target.classList.remove('precision-duplicate-guided-highlight');
                                }, 1800);
                            }
                        }

                        manualMode.addEventListener('change', function() {
                            if (manualMode.checked) {
                                guideToManualTitles();
                            }
                        });

                        if (rangeMode) {
                            rangeMode.addEventListener('change', function() {
                                var rangeStart = document.getElementById('precision_duplicate_range_start');
                                if (rangeMode.checked && rangeStart) {
                                    window.setTimeout(function() {
                                        rangeStart.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        rangeStart.focus({ preventScroll: true });
                                    }, 80);
                                }
                            });
                        }
                    });
                }());


/* IPG v2.5.18 preview flash cleanup */
(function () {
    function ipgHideInlinePreviewSource() {
        var previewBox = document.getElementById('precision_duplicate_preview_box');
        if (previewBox) {
            previewBox.classList.add('ipg-preview-modal-source');
            previewBox.setAttribute('aria-hidden', 'true');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ipgHideInlinePreviewSource);
    } else {
        ipgHideInlinePreviewSource();
    }

    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('#precision_duplicate_preview_generation')) {
            ipgHideInlinePreviewSource();
        }
    }, true);
})();

