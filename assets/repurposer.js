/* globals wcrData, jQuery */
(function ($) {
    'use strict';

    // ── On page load: restore saved output ───────────────────────────────────
    $(function () {
        var s = wcrData.saved || {};

        if ( s.linkedin || s.twitter || s.email ) {
            populateRepurpose( s.linkedin, s.twitter, s.email );
            $('#wcr-results').show();
            activateTab('linkedin');
        }

        if ( s.blogTitle || s.blogContent ) {
            $('#wcr-blog-title-out').val( s.blogTitle );
            $('#wcr-blog-content-out').val( s.blogContent );
            $('#wcr-blog-result').show();
        }
    });

    // ── Tab switching ─────────────────────────────────────────────────────────
    $(document).on('click', '.wcr-tab', function () {
        activateTab( $(this).data('tab') );
    });

    function activateTab(tab) {
        $('.wcr-tab').removeClass('active');
        $('.wcr-panel').removeClass('active');
        $('.wcr-tab[data-tab="' + tab + '"]').addClass('active');
        $('#wcr-panel-' + tab).addClass('active');
    }

    // ── Repurpose button ──────────────────────────────────────────────────────
    $('#wcr-btn').on('click', function () {
        var postId = $('#post_ID').val();
        var tone   = $('#wcr-tone').val();

        if ( !postId ) {
            showError('Save the post as a draft first, then click Repurpose.');
            return;
        }

        startSpinner('Repurposing with Claude…');

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
            populateRepurpose(d.linkedin, d.twitter, d.email);
            $('#wcr-results').show();
            activateTab('linkedin');
        })
        .fail(function () { showError('Network error. Please try again.'); })
        .always(stopSpinner);
    });

    function populateRepurpose(linkedin, twitter, email) {
        // LinkedIn
        $('#wcr-linkedin').val(linkedin);
        $('#wcr-panel-linkedin .wcr-chars').text( (linkedin || '').length + ' characters' );

        // Twitter
        $('#wcr-twitter').val(twitter);
        renderThread(twitter);

        // Email
        $('#wcr-email').val(email);
    }

    // ── Blog post: show notes form ────────────────────────────────────────────
    $('#wcr-blog-btn').on('click', function () {
        // If the post already has content, pre-fill the topic with the post title.
        var title = $('#title').val() || '';
        if ( title && !$('#wcr-blog-notes').val() ) {
            // Leave notes blank; user sees the working title via the label hint.
        }
        $('#wcr-blog-notes-wrap').slideToggle(150);
        $('#wcr-error').hide();
    });

    $('#wcr-blog-cancel-btn').on('click', function () {
        $('#wcr-blog-notes-wrap').slideUp(150);
    });

    // ── Blog post: generate ───────────────────────────────────────────────────
    $('#wcr-blog-generate-btn').on('click', function () {
        var postId = $('#post_ID').val() || 0;
        var tone   = $('#wcr-tone').val();
        var idea   = $('#wcr-blog-notes').val().trim();

        if ( !idea ) {
            $('#wcr-blog-notes').focus();
            showError('Describe your idea first — even one sentence is enough.');
            return;
        }

        startSpinner('Reading your site, then writing with Claude…');
        $('#wcr-blog-notes-wrap').slideUp(150);

        $.post(wcrData.ajaxUrl, {
            action:  'wcr_generate_blog',
            nonce:   wcrData.nonce,
            post_id: postId,
            idea:    idea,
            tone:    tone
        })
        .done(function (res) {
            if (!res.success) {
                showError(res.data ? res.data.message : 'An error occurred.');
                return;
            }
            var d = res.data;
            $('#wcr-blog-title-out').val(d.title);
            $('#wcr-blog-content-out').val(d.content);
            $('#wcr-blog-result').show();
        })
        .fail(function () { showError('Network error. Please try again.'); })
        .always(stopSpinner);
    });

    // ── Insert blog post into editor ──────────────────────────────────────────
    $('#wcr-blog-insert-btn').on('click', function () {
        var newTitle   = $('#wcr-blog-title-out').val();
        var newContent = $('#wcr-blog-content-out').val();

        // Update post title field.
        if (newTitle) {
            $('#title').val(newTitle);
            // Trigger WP title-slug sync.
            $('#title').trigger('blur');
        }

        // Insert into block editor (Gutenberg) or classic editor.
        if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
            try {
                var blocks = wp.blocks.parse(newContent);
                if (!blocks || !blocks.length) {
                    // Wrap as paragraph blocks.
                    blocks = newContent.split(/\n\n+/).map(function(para) {
                        return wp.blocks.createBlock('core/paragraph', { content: para.trim() });
                    });
                }
                wp.data.dispatch('core/block-editor').resetBlocks(blocks);

                if (newTitle) {
                    wp.data.dispatch('core/editor').editPost({ title: newTitle });
                }

                flashInserted();
                return;
            } catch(e) {
                // Fall through to classic editor.
            }
        }

        // Classic editor fallback.
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor ) {
            tinyMCE.activeEditor.setContent(newContent.replace(/\n/g, '<br>'));
            flashInserted();
        } else {
            var $textarea = $('#content');
            if ($textarea.length) {
                $textarea.val(newContent);
                flashInserted();
            } else {
                showError('Could not find the editor. Copy the content manually.');
            }
        }
    });

    function flashInserted() {
        var $btn = $('#wcr-blog-insert-btn');
        var orig = $btn.text();
        $btn.text('✓ Inserted!').prop('disabled', true);
        setTimeout(function() { $btn.text(orig).prop('disabled', false); }, 2500);
    }

    // ── Render Twitter thread as cards ────────────────────────────────────────
    function renderThread(text) {
        var $container = $('#wcr-thread-container').empty();
        if (!text) return;

        var tweets = text.split(/(?=\d+\/)/);

        tweets.forEach(function (tweet) {
            tweet = tweet.trim();
            if (!tweet) return;

            var len   = tweet.length;
            var over  = len > 280;
            var $card = $('<div class="wcr-tweet-card">');

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
            navigator.clipboard.writeText(text).then(function () { flashCopied($copied); });
        } else {
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

    // ── Spinner helpers ───────────────────────────────────────────────────────
    function startSpinner(label) {
        $('#wcr-btn, #wcr-blog-btn, #wcr-blog-generate-btn').prop('disabled', true);
        $('#wcr-spinner-label').text(label || 'Generating with Claude…');
        $('#wcr-spinner').show();
        $('#wcr-error').hide();
    }

    function stopSpinner() {
        $('#wcr-btn, #wcr-blog-btn, #wcr-blog-generate-btn').prop('disabled', false);
        $('#wcr-spinner').hide();
    }

    // ── Error display ─────────────────────────────────────────────────────────
    function showError(msg) {
        $('#wcr-error p').text(msg);
        $('#wcr-error').show();
    }

}(jQuery));
