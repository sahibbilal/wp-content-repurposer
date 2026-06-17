/* globals wcrData, jQuery */
(function ($) {
    'use strict';

    // ── Tab switching ─────────────────────────────────────────────────────────
    $(document).on('click', '.wcr-tab', function () {
        var tab = $(this).data('tab');
        $('.wcr-tab').removeClass('active');
        $('.wcr-panel').removeClass('active');
        $(this).addClass('active');
        $('#wcr-panel-' + tab).addClass('active');
    });

    // ── Repurpose button ──────────────────────────────────────────────────────
    $('#wcr-btn').on('click', function () {
        var postId = $('#post_ID').val();
        var tone   = $('#wcr-tone').val();

        $('#wcr-error').hide();
        $('#wcr-results').hide();
        $('#wcr-btn').prop('disabled', true);
        $('#wcr-spinner').show();

        $.post(wcrData.ajaxUrl, {
            action:  'wcr_repurpose',
            nonce:   wcrData.nonce,
            post_id: postId,
            tone:    tone
        })
        .done(function (res) {
            if (!res.success) {
                showError(res.data ? res.data.message : 'An error occurred.');
                return;
            }

            var d = res.data;

            // ── LinkedIn ──────────────────────────────────────────────────────
            $('#wcr-linkedin').val(d.linkedin);
            var liChars = (d.linkedin || '').length;
            $('#wcr-panel-linkedin .wcr-chars').text(liChars + ' characters');

            // ── Twitter Thread ────────────────────────────────────────────────
            $('#wcr-twitter').val(d.twitter);
            renderThread(d.twitter);

            // ── Email ─────────────────────────────────────────────────────────
            $('#wcr-email').val(d.email);

            $('#wcr-results').show();

            // Auto-switch to first tab.
            $('.wcr-tab[data-tab="linkedin"]').trigger('click');
        })
        .fail(function () {
            showError('Network error. Please try again.');
        })
        .always(function () {
            $('#wcr-btn').prop('disabled', false);
            $('#wcr-spinner').hide();
        });
    });

    // ── Render Twitter thread as cards ────────────────────────────────────────
    function renderThread(text) {
        var $container = $('#wcr-thread-container').empty();
        if (!text) return;

        // Split on tweet numbers: "1/", "2/", etc.
        var tweets = text.split(/(?=\d+\/)/);

        tweets.forEach(function (tweet) {
            tweet = tweet.trim();
            if (!tweet) return;

            var len   = tweet.length;
            var over  = len > 280;
            var $card = $('<div class="wcr-tweet-card">');

            // Extract number if present.
            var numMatch = tweet.match(/^(\d+\/)/);
            if (numMatch) {
                $('<div class="wcr-tweet-num">').text('Tweet ' + numMatch[1]).appendTo($card);
                tweet = tweet.slice(numMatch[0].length).trim();
            }

            $('<p class="wcr-tweet-text">').text(tweet).appendTo($card);
            $('<div class="wcr-tweet-len' + (over ? ' over' : '') + '">').text(len + ' / 280').appendTo($card);
            $container.append($card);
        });
    }

    // ── Copy to clipboard ─────────────────────────────────────────────────────
    $(document).on('click', '.wcr-copy', function () {
        var target  = $(this).data('target');
        var $copied = $(this).siblings('.wcr-copied');
        var text    = $('#' + target).val();

        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flashCopied($copied);
            });
        } else {
            // Fallback.
            var $tmp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            flashCopied($copied);
        }
    });

    function flashCopied($el) {
        $el.show();
        setTimeout(function () { $el.hide(); }, 2500);
    }

    // ── Error display ─────────────────────────────────────────────────────────
    function showError(msg) {
        $('#wcr-error p').text(msg);
        $('#wcr-error').show();
    }

}(jQuery));
