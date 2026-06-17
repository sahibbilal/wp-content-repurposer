/* globals wcrSettings, jQuery */
(function ($) {
    'use strict';

    // On load: if saved context exists, it's already in the textarea (PHP-rendered).
    // The button label and status badge are also rendered server-side.

    $('#wcr-read-site-btn').on('click', function () {
        $('#wcr-site-spinner').show();
        $('#wcr-read-site-btn').prop('disabled', true);
        $('#wcr-site-error').hide();

        $.post(wcrSettings.ajaxUrl, {
            action: 'wcr_preview_site',
            nonce:  wcrSettings.nonce,
        })
        .done(function (res) {
            if (!res.success) {
                showError(res.data ? res.data.message : 'An error occurred.');
                return;
            }

            var ctx = res.data.context;

            // Update textarea.
            $('#wcr-site-context-out').val(ctx);
            $('#wcr-site-preview').show();

            // Update status badge to green.
            var now = new Date().toLocaleString();
            $('#wcr-site-status').css({
                background: '#f0fdf4',
                border: '1px solid #bbf7d0'
            }).html(
                '<span style="color:#16a34a;font-size:18px;">✓</span>' +
                '<span style="font-size:13px;color:#166534;">' +
                    'Site content read on <strong>' + now + '</strong>' +
                    ' — Claude will use this when generating blog posts.' +
                '</span>'
            );

            // Update button label.
            $('#wcr-read-site-btn').text('🔍 Re-read Site Content').removeClass('button-primary').addClass('button-secondary');
        })
        .fail(function () { showError('Network error. Please try again.'); })
        .always(function () {
            $('#wcr-site-spinner').hide();
            $('#wcr-read-site-btn').prop('disabled', false);
        });
    });

    function showError(msg) {
        $('#wcr-site-error p').text(msg);
        $('#wcr-site-error').show();
    }

}(jQuery));
