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
            'nonce'   => wp_create_nonce( 'wcr_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
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

                <button id="wcr-read-site-btn" class="button button-secondary" type="button">
                    🔍 Read Site Content
                </button>
                <span id="wcr-site-spinner" style="display:none;margin-left:10px;">
                    <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                    Reading…
                </span>

                <div id="wcr-site-preview" style="display:none;margin-top:16px;">
                    <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:.5px;">
                        What Claude sees about your site
                    </p>
                    <textarea id="wcr-site-context-out"
                              readonly
                              style="width:100%;min-height:260px;font-family:monospace;font-size:12px;
                                     line-height:1.6;border:1px solid #dcdcde;border-radius:4px;
                                     padding:12px;background:#f6f7f7;color:#1d2327;resize:vertical;
                                     box-sizing:border-box;"></textarea>
                    <p style="margin:6px 0 0;font-size:12px;color:#888;">
                        This context is sent to Claude automatically every time you generate a blog post.
                        It is never stored — built fresh from your live site on each request.
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

        wp_send_json_success( array( 'context' => $context ) );
    }
}
