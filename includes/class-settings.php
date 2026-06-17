<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCR_Settings {

    public function __construct() {
        add_action( 'admin_menu',  array( $this, 'add_menu' ) );
        add_action( 'admin_init',  array( $this, 'register_settings' ) );
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

    public function render_page() {
        $all_post_types    = get_post_types( array( 'public' => true ), 'objects' );
        $enabled_types     = get_option( 'wcr_post_types', array( 'post' ) );
        ?>
        <div class="wrap">
            <h1>
                <span style="margin-right:8px;">✍️</span>
                WP Content Repurposer — Settings
            </h1>

            <form method="post" action="options.php" style="max-width:640px;margin-top:20px;">
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
        </div>
        <?php
    }
}
