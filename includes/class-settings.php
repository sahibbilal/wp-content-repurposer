<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCR_Settings {

    public function __construct() {
        add_action( 'admin_menu',             array( $this, 'add_menu' ) );
        add_action( 'admin_init',             array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_wcr_preview_site', array( $this, 'handle_preview_site' ) );
    }

    public function add_menu() {
        add_options_page(
            'Content Repurposer',
            'Content Repurposer',
            'manage_options',
            'wp-content-repurposer',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wcr_settings', 'wcr_claude_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'wcr_settings', 'wcr_default_tone',   array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'wcr_settings', 'wcr_post_types',     array( 'sanitize_callback' => array( $this, 'sanitize_post_types' ) ) );
    }

    public function sanitize_post_types( $value ) {
        if ( ! is_array( $value ) ) return array( 'post' );
        return array_map( 'sanitize_text_field', $value );
    }

    public function enqueue( $hook ) {
        if ( $hook !== 'settings_page_wp-content-repurposer' ) return;
        wp_enqueue_script( 'wcr-settings', WCR_URL . 'assets/settings.js', array( 'jquery' ), WCR_VERSION, true );
        wp_localize_script( 'wcr-settings', 'wcrSettings', array(
            'nonce'        => wp_create_nonce( 'wcr_nonce' ),
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'savedContext' => get_option( 'wcr_site_context', '' ),
            'savedDate'    => get_option( 'wcr_site_context_date', '' ),
        ) );
    }

    public function render_page() {
        $all_post_types = get_post_types( array( 'public' => true ), 'objects' );
        $enabled_types  = get_option( 'wcr_post_types', array( 'post' ) );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span>✍️</span> WP Content Repurposer — Settings
            </h1>

            <!-- ── API & Preferences ──────────────────────────────────────── -->
            <form method="post" action="options.php" style="max-width:680px;margin-top:20px;">
                <?php settings_fields( 'wcr_settings' ); ?>

                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px;margin-bottom:20px;">
                    <h2 style="margin-top:0;">Claude API</h2>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:160px;">API Key</th>
                            <td>
                                <input type="password"
                                       name="wcr_claude_api_key"
                                       value="<?php echo esc_attr( get_option( 'wcr_claude_api_key', '' ) ); ?>"
                                       style="width:100%;" placeholder="sk-ant-..." autocomplete="off" />
                                <p class="description">
                                    Get your key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.
                                    Stored in your WordPress database. Never shared.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Tone</th>
                            <td>
                                <select name="wcr_default_tone">
                                    <?php
                                    $current = get_option( 'wcr_default_tone', 'professional' );
                                    $tones   = array(
                                        'professional' => 'Professional',
                                        'casual'       => 'Casual & Conversational',
                                        'educational'  => 'Educational & Authoritative',
                                    );
                                    foreach ( $tones as $val => $label ) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr( $val ),
                                            selected( $current, $val, false ),
                                            esc_html( $label )
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Enable On</th>
                            <td>
                                <?php foreach ( $all_post_types as $pt ) : ?>
                                    <label style="display:block;margin-bottom:4px;">
                                        <input type="checkbox"
                                               name="wcr_post_types[]"
                                               value="<?php echo esc_attr( $pt->name ); ?>"
                                               <?php checked( in_array( $pt->name, $enabled_types, true ) ); ?> />
                                        <?php echo esc_html( $pt->label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
            </form>

            <!-- ── Site Content Preview ───────────────────────────────────── -->
            <div style="max-width:680px;margin-top:32px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px;">
                <h2 style="margin-top:0;">🔍 Site Content Preview</h2>
                <p style="color:#555;margin-top:0;">
                    When you click <strong>Generate Blog Post</strong> in the post editor, Claude reads your site first —
                    its name, tagline, categories, and recent posts — so the output fits your niche naturally.
                    Click below to see exactly what Claude receives.
                </p>

                <?php
                $saved_context = get_option( 'wcr_site_context', '' );
                $saved_date    = get_option( 'wcr_site_context_date', '' );
                $has_context   = ! empty( $saved_context );
                ?>

                <?php if ( $has_context ) : ?>
                <div id="wcr-site-status" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;
                     background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;padding:10px 14px;">
                    <span style="color:#16a34a;font-size:18px;">✓</span>
                    <span style="font-size:13px;color:#166534;">
                        Site content read on <strong><?php echo esc_html( $saved_date ); ?></strong>
                        — Claude will use this when generating blog posts.
                    </span>
                </div>
                <?php else : ?>
                <div id="wcr-site-status" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;
                     background:#fff7ed;border:1px solid #fed7aa;border-radius:4px;padding:10px 14px;">
                    <span style="color:#ea580c;font-size:18px;">!</span>
                    <span style="font-size:13px;color:#9a3412;">
                        Site content not read yet. Click <strong>Read Site Content</strong> before generating blog posts.
                    </span>
                </div>
                <?php endif; ?>

                <div style="display:flex;align-items:center;gap:10px;">
                    <button id="wcr-read-site-btn" class="button <?php echo $has_context ? 'button-secondary' : 'button-primary'; ?>" type="button">
                        🔍 <?php echo $has_context ? 'Re-read Site Content' : 'Read Site Content'; ?>
                    </button>
                    <span id="wcr-site-spinner" style="display:none;">
                        <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                        Reading your site…
                    </span>
                </div>

                <div id="wcr-site-preview" style="<?php echo $has_context ? 'display:block;' : 'display:none;'; ?>margin-top:16px;">
                    <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:.5px;">
                        What Claude sees about your site
                    </p>
                    <textarea id="wcr-site-context-out"
                              readonly
                              style="width:100%;min-height:260px;font-family:monospace;font-size:12px;
                                     line-height:1.6;border:1px solid #dcdcde;border-radius:4px;
                                     padding:12px;background:#f6f7f7;color:#1d2327;resize:vertical;
                                     box-sizing:border-box;"><?php echo esc_textarea( $saved_context ); ?></textarea>
                    <p style="margin:6px 0 0;font-size:12px;color:#888;">
                        Saved to WordPress options. Re-read whenever you add new posts or change your site's focus.
                    </p>
                </div>

                <div id="wcr-site-error" style="display:none;margin-top:12px;" class="notice notice-error inline"><p></p></div>
            </div>
        </div>
        <?php
    }

    // ── AJAX: return site context for preview ──────────────────────────────────

    public function handle_preview_site() {
        while ( ob_get_level() ) { ob_end_clean(); }
        check_ajax_referer( 'wcr_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $repurposer = new WCR_Repurposer();
        $context    = $repurposer->gather_site_context();

        // Save so blog generation can reuse without re-reading.
        update_option( 'wcr_site_context', $context );
        update_option( 'wcr_site_context_date', current_time( 'mysql' ) );

        wp_send_json_success( array( 'context' => $context ) );
    }
}
