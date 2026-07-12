<?php
/**
 * WebinoCRM Admin Menu Manager
 *
 * This class handles the creation of the admin menus in the WordPress dashboard.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WebinoCRM_Admin_Menu {

    /**
     * Constructor. Hooks into the 'admin_menu' action.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
        add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
    }

    /**
     * Registers all the admin menu and submenu pages for the plugin.
     * NOTE: All menus removed - everything is in frontend dashboard now.
     */
    public function register_admin_menus() {
        // All admin menus removed - everything is in frontend dashboard
        // No admin menus needed
    }



    /**
     * Displays admin notices, e.g., for configuration errors or missing extensions.
     * This function remains unchanged.
     */
    public function show_admin_notices() {
        if ( get_transient( 'webino_sms_not_configured' ) ) {
            $settings_url = add_query_arg(['page' => 'webino-crm-info']);
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'WebinoCRM:', 'webinocrm' ); ?></strong>
                    <?php
                    printf(
                        wp_kses_post( __( 'The SMS service for sending reminders is not configured correctly. Please <a href="%s">check your settings</a>.', 'webinocrm' ) ),
                        esc_url( $settings_url )
                    );
                    ?>
                </p>
            </div>
            <?php
            delete_transient( 'webino_sms_not_configured' );
        }

        if ( get_transient( 'webino_soap_not_enabled' ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'WebinoCRM:', 'webinocrm' ); ?></strong>
                    <?php esc_html_e( 'The PHP SOAP extension is not enabled on your server. This is required for the ParsGreen SMS service to function. Please contact your hosting provider to enable it.', 'webinocrm' ); ?>
                </p>
            </div>
            <?php
            delete_transient( 'webino_soap_not_enabled' );
        }
    }
}