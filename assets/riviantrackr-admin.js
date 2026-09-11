(function($) {
    $(document).ready(function() {
        var adminData = window.RivianTrackrAdmin || {};
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var slideMs = reduceMotion ? 0 : 200;

        // --- Helpers -------------------------------------------------------

        // admin-ajax.php answers "0" / "-1" (unknown action, expired session)
        // as a bare string, so never assume response.data exists.
        function responseMessage(response, fallback) {
            if (response && typeof response === 'object' && response.data && response.data.message) {
                return String(response.data.message);
            }
            if (response === '0' || response === 0 || response === '-1' || response === -1) {
                return 'Your session has expired. Please reload the page and try again.';
            }
            return fallback;
        }

        function isSuccess(response) {
            return !!(response && typeof response === 'object' && response.success);
        }

        // Build result markup with the message as text, never as HTML.
        function statusSpan(color, prefix, message) {
            return $('<span></span>').css('color', color).text((prefix ? prefix + ' ' : '') + message);
        }

        function resultBox(type, prefix, message) {
            var $p = $('<p></p>');
            if (prefix) {
                $p.append($('<strong></strong>').text(prefix + ' '));
            }
            $p.append(document.createTextNode(message));
            return $('<div></div>').addClass('riviantrackr-test-result ' + type).append($p);
        }

        var COLOR_OK = '#10b981';
        var COLOR_ACCENT = '#fba919';
        var COLOR_ERR = '#ef4444';
        var COLOR_MUTED = '#6e6e73';

        // --- Settings page: CSS modal / reset ---
        var modal = $('#riviantrackr-default-css-modal');
        var dialog = modal.find('.riviantrackr-modal-content');
        var textarea = $('#riviantrackr-custom-css');
        var lastFocused = null;

        function openModal() {
            lastFocused = document.activeElement;
            modal.addClass('riviantrackr-modal-open');
            var closeBtn = $('#riviantrackr-close-modal').get(0);
            if (closeBtn) {
                closeBtn.focus();
            } else if (dialog.length) {
                dialog.get(0).focus();
            }
        }

        function closeModal() {
            if (!modal.hasClass('riviantrackr-modal-open')) return;
            modal.removeClass('riviantrackr-modal-open');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        $('#riviantrackr-reset-css').on('click', function() {
            if (confirm('Reset custom CSS? This will clear all your custom styles.')) {
                textarea.val('');
            }
        });

        $('#riviantrackr-view-default-css').on('click', openModal);
        $('#riviantrackr-close-modal').on('click', closeModal);

        modal.on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        $(document).on('keydown', function(e) {
            if (!modal.hasClass('riviantrackr-modal-open')) return;

            if (e.key === 'Escape') {
                closeModal();
                return;
            }

            // Keep Tab inside the dialog while it is open.
            if (e.key === 'Tab' && dialog.length) {
                var focusable = dialog.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                if (!focusable.length) return;
                var first = focusable.get(0);
                var last = focusable.get(focusable.length - 1);
                if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog.get(0))) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // --- Settings page: Advanced toggle ---
        $('#riviantrackr-advanced-toggle').on('click', function() {
            var $btn = $(this);
            var $settings = $('#riviantrackr-advanced-settings');
            var isHidden = $settings.is(':hidden');
            if (isHidden) {
                $settings.slideDown(slideMs);
                $btn.text('Hide Advanced Settings').attr('aria-expanded', 'true');
            } else {
                $settings.slideUp(slideMs);
                $btn.text('Show Advanced Settings').attr('aria-expanded', 'false');
            }
        });

        // --- Settings page: Test API Key (Anthropic) ---
        $('#riviantrackr-test-anthropic-key-btn').on('click', function() {
            var btn = $(this);
            var useConstant = adminData.useAnthropicKeyConstant || false;
            var apiKey = useConstant ? '__USE_CONSTANT__' : $('#riviantrackr-anthropic-api-key').val().trim();
            var resultDiv = $('#riviantrackr-test-anthropic-result');

            if (!useConstant && !apiKey) {
                resultDiv.empty().append(resultBox('error', '', 'Please enter an Anthropic API key first.'));
                return;
            }

            btn.prop('disabled', true).text('Testing...');
            resultDiv.empty().append(resultBox('info', '', 'Testing Anthropic API key...'));

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_test_api_key',
                    api_key: apiKey,
                    nonce: adminData.testKeyNonce || ''
                },
                success: function(response) {
                    btn.prop('disabled', false).text('Test Connection');

                    if (isSuccess(response)) {
                        resultDiv.empty().append(resultBox('success', '✓', responseMessage(response, 'API key is valid.')));
                    } else {
                        resultDiv.empty().append(resultBox('error', '✗ Test failed:', responseMessage(response, 'Unknown error.')));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Test Connection');
                    resultDiv.empty().append(resultBox('error', '', 'Request failed. Please try again.'));
                }
            });
        });

        // --- Settings page: Refresh Models ---
        $('#riviantrackr-refresh-models-btn').on('click', function() {
            var btn = $(this);
            var resultSpan = $('#riviantrackr-refresh-models-result');
            var nonce = btn.data('nonce');

            btn.prop('disabled', true).text('Refreshing...');
            resultSpan.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_refresh_models',
                    nonce: nonce
                },
                success: function(response) {
                    btn.prop('disabled', false).text('Refresh Models');
                    if (isSuccess(response)) {
                        resultSpan.empty().append(statusSpan(COLOR_ACCENT, '✓', responseMessage(response, 'Model list refreshed.')));
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        resultSpan.empty().append(statusSpan(COLOR_ERR, '✗', responseMessage(response, 'Could not refresh models.')));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Refresh Models');
                    resultSpan.empty().append(statusSpan(COLOR_ERR, '✗', 'Request failed. Please try again.'));
                }
            });
        });

        // --- Settings page: GDPR Purge ---
        $('#riviantrackr-gdpr-purge-btn').on('click', function() {
            if (!confirm('This will permanently replace all stored search query text with SHA-256 hashes. This cannot be undone. Continue?')) return;

            var btn = $(this);
            var resultSpan = $('#riviantrackr-gdpr-purge-result');
            btn.prop('disabled', true).text('Anonymizing...');
            resultSpan.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_gdpr_purge_queries',
                    nonce: adminData.gdprPurgeNonce || ''
                },
                success: function(response) {
                    btn.prop('disabled', false).text('Anonymize Existing Queries');
                    if (isSuccess(response)) {
                        resultSpan.empty().append(statusSpan(COLOR_OK, '', responseMessage(response, 'Done.')));
                    } else {
                        resultSpan.empty().append(statusSpan(COLOR_ERR, '', responseMessage(response, 'Could not anonymize queries.')));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Anonymize Existing Queries');
                    resultSpan.empty().append(statusSpan(COLOR_ERR, '', 'Request failed. Please try again.'));
                }
            });
        });

        // --- Settings page: Clear Cache ---
        $('#riviantrackr-clear-cache-btn').on('click', function() {
            var btn = $(this);
            var resultSpan = $('#riviantrackr-clear-cache-result');
            var nonce = btn.data('nonce');

            btn.prop('disabled', true).text('Clearing...');
            resultSpan.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_clear_cache',
                    nonce: nonce
                },
                success: function(response) {
                    btn.prop('disabled', false).text('Clear Cache Now');
                    if (isSuccess(response)) {
                        resultSpan.empty().append(statusSpan(COLOR_ACCENT, '✓', responseMessage(response, 'Cache cleared.')));
                    } else {
                        resultSpan.empty().append(statusSpan(COLOR_ERR, '✗', responseMessage(response, 'Could not clear cache.')));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Clear Cache Now');
                    resultSpan.empty().append(statusSpan(COLOR_ERR, '✗', 'Request failed. Please try again.'));
                }
            });
        });

        // --- Analytics page: Purge Spam ---
        $('#riviantrackr-purge-spam-btn').on('click', function() {
            var btn = $(this);
            var resultSpan = $('#riviantrackr-purge-spam-result');
            var nonce = btn.data('nonce');

            if (!confirm('This will scan all log entries and permanently delete those matching spam patterns. Continue?')) {
                return;
            }

            btn.prop('disabled', true).text('Scanning...');
            resultSpan.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_purge_spam',
                    nonce: nonce
                },
                success: function(response) {
                    btn.prop('disabled', false).text('Scan & Remove Spam');
                    if (isSuccess(response)) {
                        var deleted = (response.data && response.data.deleted) || 0;
                        resultSpan.empty().append(statusSpan(deleted > 0 ? COLOR_OK : COLOR_MUTED, '', responseMessage(response, 'Scan complete.')));
                        if (deleted > 0) {
                            setTimeout(function() { location.reload(); }, 2000);
                        }
                    } else {
                        resultSpan.empty().append(statusSpan(COLOR_ERR, '', responseMessage(response, 'Could not scan for spam.')));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Scan & Remove Spam');
                    resultSpan.empty().append(statusSpan(COLOR_ERR, '', 'Request failed. Please try again.'));
                }
            });
        });

        // --- Analytics page: Bulk delete ---
        var $selectAll = $('#riviantrackr-select-all');
        var $deleteBtn = $('#riviantrackr-bulk-delete-btn');
        var $resultSpan = $('#riviantrackr-bulk-delete-result');

        function updateDeleteBtn() {
            var checked = $('.riviantrackr-row-check:checked').length;
            $deleteBtn.toggle(checked > 0).text('Delete Selected (' + checked + ')');
        }

        function updateSelectAll() {
            var total = $('.riviantrackr-row-check').length;
            var checked = $('.riviantrackr-row-check:checked').length;
            $selectAll.prop('checked', total > 0 && total === checked);
            $selectAll.prop('indeterminate', checked > 0 && checked < total);
        }

        $selectAll.on('change', function() {
            $('.riviantrackr-row-check').prop('checked', this.checked);
            $selectAll.prop('indeterminate', false);
            updateDeleteBtn();
        });

        $(document).on('change', '.riviantrackr-row-check', function() {
            updateSelectAll();
            updateDeleteBtn();
        });

        $deleteBtn.on('click', function() {
            var ids = $('.riviantrackr-row-check:checked').map(function() { return this.value; }).get();
            if (!ids.length) return;

            if (!confirm('Delete ' + ids.length + ' selected log entries? This cannot be undone.')) return;

            $deleteBtn.prop('disabled', true).text('Deleting...');
            $resultSpan.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'riviantrackr_bulk_delete_logs',
                    nonce: adminData.bulkDeleteNonce || '',
                    ids: ids.join(',')
                },
                success: function(response) {
                    $deleteBtn.prop('disabled', false);
                    if (isSuccess(response)) {
                        $resultSpan.empty().append(statusSpan(COLOR_OK, '', responseMessage(response, 'Entries deleted.')));
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $resultSpan.empty().append(statusSpan(COLOR_ERR, '', responseMessage(response, 'Could not delete entries.')));
                        updateDeleteBtn();
                    }
                },
                error: function() {
                    $deleteBtn.prop('disabled', false);
                    $resultSpan.empty().append(statusSpan(COLOR_ERR, '', 'Request failed. Please try again.'));
                    updateDeleteBtn();
                }
            });
        });
    });
})(jQuery);
