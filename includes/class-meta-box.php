<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCR_Meta_Box {

    // Post meta keys for persisted output.
    const META_LINKEDIN = '_wcr_linkedin';
    const META_TWITTER  = '_wcr_twitter';
    const META_EMAIL    = '_wcr_email';
    const META_BLOG     = '_wcr_blog_content';
    const META_BLOG_TTL = '_wcr_blog_title';

    public function __construct() {
        add_action( 'add_meta_boxes',        array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_wcr_repurpose', array( $this, 'handle_repurpose' ) );
        add_action( 'wp_ajax_wcr_generate_blog', array( $this, 'handle_generate_blog' ) );
    }

    public function register() {
        $post_types = get_option( 'wcr_post_types', array( 'post' ) );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'wcr-repurposer',
                '✍️ Repurpose Content',
                array( $this, 'render' ),
                $pt,
                'normal',
                'default'
            );
        }
    }

    public function enqueue( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;

        wp_enqueue_style(  'wcr-meta-box', WCR_URL . 'assets/repurposer.css', array(), WCR_VERSION );
        wp_enqueue_script( 'wcr-meta-box', WCR_URL . 'assets/repurposer.js',  array( 'jquery' ), WCR_VERSION, true );

        $post_id = get_the_ID();

        wp_localize_script( 'wcr-meta-box', 'wcrData', array(
            'nonce'       => wp_create_nonce( 'wcr_nonce' ),
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'settingsUrl' => admin_url( 'options-general.php?page=wp-content-repurposer' ),
            'siteReady'   => ! empty( get_option( 'wcr_site_context', '' ) ),
            'defaultTone' => get_option( 'wcr_default_tone', 'professional' ),
            // Persisted output — loaded on page load so refresh doesn't wipe results.
            'saved' => array(
                'linkedin'   => get_post_meta( $post_id, self::META_LINKEDIN, true ) ?: '',
                'twitter'    => get_post_meta( $post_id, self::META_TWITTER,  true ) ?: '',
                'email'      => get_post_meta( $post_id, self::META_EMAIL,    true ) ?: '',
                'blogTitle'  => get_post_meta( $post_id, self::META_BLOG_TTL, true ) ?: '',
                'blogContent'=> get_post_meta( $post_id, self::META_BLOG,     true ) ?: '',
            ),
        ) );
    }

    public function render( $post ) {
        $tone = get_option( 'wcr_default_tone', 'professional' );
        ?>
        <div id="wcr-box">

            <!-- ── Controls row ────────────────────────────────────────────── -->
            <div id="wcr-controls">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <label for="wcr-tone" style="font-weight:600;white-space:nowrap;">Tone:</label>
                    <select id="wcr-tone">
                        <option value="professional" <?php selected( $tone, 'professional' ); ?>>Professional</option>
                        <option value="casual"       <?php selected( $tone, 'casual' ); ?>>Casual & Conversational</option>
                        <option value="educational"  <?php selected( $tone, 'educational' ); ?>>Educational</option>
                    </select>

                    <button id="wcr-btn" class="button button-primary" type="button">
                        ✨ Repurpose This Post
                    </button>

                    <button id="wcr-blog-btn" class="button" type="button">
                        📝 Generate Blog Post
                    </button>

                    <span id="wcr-spinner" style="display:none;">
                        <span class="spinner is-active" style="float:none;margin:0;"></span>
                        <span id="wcr-spinner-label">Generating with Claude…</span>
                    </span>
                </div>

                <!-- Blog idea input (shown when Generate Blog Post is clicked) -->
                <div id="wcr-blog-notes-wrap" style="display:none;margin-top:12px;">
                    <p style="margin:0 0 8px;color:#555;font-size:13px;">
                        Claude reads your website — its name, categories, and recent posts — then writes a post that fits naturally.
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-content-repurposer' ) ); ?>" target="_blank" style="white-space:nowrap;">
                            Preview what Claude sees →
                        </a>
                    </p>
                    <label for="wcr-blog-notes" style="font-weight:600;display:block;margin-bottom:4px;">
                        Your Idea
                    </label>
                    <textarea id="wcr-blog-notes" style="width:100%;height:90px;resize:vertical;font-size:13px;" placeholder="e.g. Why most developers underestimate API rate limiting — and what to do instead&#10;&#10;Or: a beginner's guide to setting up CI/CD with GitHub Actions&#10;&#10;Be as specific or as vague as you want."></textarea>
                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                        <button id="wcr-blog-generate-btn" class="button button-primary" type="button">✨ Generate Blog Post</button>
                        <button id="wcr-blog-cancel-btn" class="button" type="button">Cancel</button>
                        <span style="font-size:12px;color:#888;margin-left:4px;">Claude reads your site first, then writes</span>
                    </div>
                </div>

                <div id="wcr-error" style="display:none;margin-top:10px;" class="notice notice-error inline"><p></p></div>
            </div>

            <!-- ── Repurpose results ────────────────────────────────────────── -->
            <div id="wcr-results" style="display:none;margin-top:20px;">
                <div id="wcr-tabs">
                    <button class="wcr-tab active" data-tab="linkedin">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        LinkedIn
                    </button>
                    <button class="wcr-tab" data-tab="twitter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        Twitter/X Thread
                    </button>
                    <button class="wcr-tab" data-tab="email">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email Newsletter
                    </button>
                </div>

                <div class="wcr-panel active" id="wcr-panel-linkedin">
                    <textarea id="wcr-linkedin" class="wcr-output"></textarea>
                    <div class="wcr-actions">
                        <button class="button wcr-copy" data-target="wcr-linkedin">📋 Copy to Clipboard</button>
                        <span class="wcr-copied" style="display:none;color:#16a34a;font-weight:600;">✓ Copied!</span>
                        <span class="wcr-chars"></span>
                    </div>
                </div>

                <div class="wcr-panel" id="wcr-panel-twitter">
                    <div id="wcr-thread-container"></div>
                    <textarea id="wcr-twitter" class="wcr-output" style="display:none;"></textarea>
                    <div class="wcr-actions">
                        <button class="button wcr-copy" data-target="wcr-twitter">📋 Copy All Tweets</button>
                        <span class="wcr-copied" style="display:none;color:#16a34a;font-weight:600;">✓ Copied!</span>
                    </div>
                </div>

                <div class="wcr-panel" id="wcr-panel-email">
                    <textarea id="wcr-email" class="wcr-output"></textarea>
                    <div class="wcr-actions">
                        <button class="button wcr-copy" data-target="wcr-email">📋 Copy to Clipboard</button>
                        <span class="wcr-copied" style="display:none;color:#16a34a;font-weight:600;">✓ Copied!</span>
                    </div>
                </div>

                <p style="margin-top:12px;font-size:12px;color:#888;">
                    Generated by Claude AI · Auto-saved to this post · Review before posting
                </p>
            </div>

            <!-- ── Blog post result ────────────────────────────────────────── -->
            <div id="wcr-blog-result" style="display:none;margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:14px;">📝 Generated Blog Post</strong>
                    <button id="wcr-blog-insert-btn" class="button button-primary" type="button">
                        ↑ Insert into Editor
                    </button>
                </div>

                <div style="margin-bottom:8px;">
                    <label style="font-weight:600;font-size:12px;color:#666;display:block;margin-bottom:3px;">TITLE</label>
                    <input type="text" id="wcr-blog-title-out" style="width:100%;font-size:15px;font-weight:600;" />
                </div>

                <div>
                    <label style="font-weight:600;font-size:12px;color:#666;display:block;margin-bottom:3px;">CONTENT</label>
                    <textarea id="wcr-blog-content-out" class="wcr-output" style="min-height:320px;"></textarea>
                </div>

                <div class="wcr-actions" style="margin-top:8px;">
                    <button class="button wcr-copy" data-target="wcr-blog-content-out">📋 Copy Content</button>
                    <span class="wcr-copied" style="display:none;color:#16a34a;font-weight:600;">✓ Copied!</span>
                    <span style="font-size:12px;color:#888;margin-left:auto;">Auto-saved · Review before publishing</span>
                </div>
            </div>

        </div>
        <?php
    }

    // ── AJAX: repurpose existing post ──────────────────────────────────────────

    public function handle_repurpose() {
        while ( ob_get_level() ) { ob_end_clean(); }
        check_ajax_referer( 'wcr_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $tone    = isset( $_POST['tone'] )    ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'professional';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'No post ID. Save the post as a draft first, then click Repurpose.' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => 'Post not found.' ) );
        }

        $content = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_content ) );

        if ( strlen( trim( $content ) ) < 100 ) {
            wp_send_json_error( array( 'message' => 'Post content is too short. Write at least a few paragraphs first.' ) );
        }

        $repurposer = new WCR_Repurposer();
        $result     = $repurposer->repurpose( $post->post_title, $content, $tone );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Persist to post meta.
        update_post_meta( $post_id, self::META_LINKEDIN, $result['linkedin'] );
        update_post_meta( $post_id, self::META_TWITTER,  $result['twitter'] );
        update_post_meta( $post_id, self::META_EMAIL,    $result['email'] );

        wp_send_json_success( $result );
    }

    // ── AJAX: generate blog post ───────────────────────────────────────────────

    public function handle_generate_blog() {
        while ( ob_get_level() ) { ob_end_clean(); }
        check_ajax_referer( 'wcr_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $idea    = isset( $_POST['idea'] )    ? sanitize_textarea_field( wp_unslash( $_POST['idea'] ) ) : '';
        $tone    = isset( $_POST['tone'] )    ? sanitize_text_field( wp_unslash( $_POST['tone'] ) )    : 'professional';

        if ( empty( trim( $idea ) ) ) {
            wp_send_json_error( array( 'message' => 'Please describe your idea before generating.' ) );
        }

        $repurposer = new WCR_Repurposer();
        $result     = $repurposer->generate_blog( $idea, $tone );

        if ( is_wp_error( $result ) ) {
            $code = $result->get_error_message();
            if ( $code === 'site_not_read' ) {
                wp_send_json_error( array(
                    'code'        => 'site_not_read',
                    'message'     => 'You need to read your site content first.',
                    'settings_url'=> admin_url( 'options-general.php?page=wp-content-repurposer' ),
                ) );
            }
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Persist to post meta if we have a post.
        if ( $post_id ) {
            update_post_meta( $post_id, self::META_BLOG_TTL, $result['title'] );
            update_post_meta( $post_id, self::META_BLOG,     $result['content'] );
        }

        wp_send_json_success( $result );
    }
}
