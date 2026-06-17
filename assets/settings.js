/* globals wcrSettings, jQuery */
(function ($) {
    'use strict';

    $('#wcr-read-site-btn').on('click', function () {
        $('#wcr-site-spinner').show();
        $('#wcr-read-site-btn').prop('disabled', true);
        $('#wcr-site-error').hide();
        $('#wcr-site-preview').hide();

        $.post(wcrSettings.ajaxUrl, {
            action: 'wcr_preview_site',
            nonce:  wcrSettings.nonce,
        })
        .done(function (res) {
            if (!res.success) {
                $('#wcr-site-error p').text(res.data ? res.data.message : 'An error occurred.');
                $('#wcr-site-error').show();
                return;
            }
            $('#wcr-site-context-out').val(res.data.context);
            $('#wcr-site-preview').show();
        })
        .fail(function () {
            $('#wcr-site-error p').text('Network error. Please try again.');
            $('#wcr-site-error').show();
        })
        .always(function () {
            $('#wcr-site-spinner').hide();
            $('#wcr-read-site-btn').prop('disabled', false);
        });
    });

}(jQuery));
