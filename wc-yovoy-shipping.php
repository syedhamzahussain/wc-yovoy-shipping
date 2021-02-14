<?php
/**
 * Plugin Name: YoVoy
 *
 * @package WooCommerce-YoVoy-Shipping
 *
 * Description: Woocommerce YoVoy Shipping Integration.
 * Version: 1.1.1.14
 * Text Domain: wcys
 * Domain Path: /languages
 * WC tested up to: 4.8.0
 */
if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'wcys_check_dependencies');

/* Add admin notice */
add_action('admin_notices', 'wcys_admin_notice');

/**
 * Runs only when the plugin is activated.
 */
function wcys_admin_notice_activation_hook() {
    /* Create transient data */
    set_transient('wcys-admin-notice', true, 5);
}

/**
 * Admin Notice on Activation.
 */
function wcys_admin_notice() {
    /* Check transient, if available display notice */
    if (get_transient('wcys-admin-notice')) {
        ?>
        <div  class="updated notice is-dismissible">
            <p style="font-size: 13px;">
                <?php
                /* translators: 1: Plugin Name */
                echo sprintf(esc_attr__('Thanks for using %s. Please configure settings from WooCommerce Settings page', 'wcys'), wp_kses_post('<b>' . esc_attr__('Yovoy Shipping', 'wcys') . '</b>'));
                ?>
            </p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient('wcys-admin-notice');
    }
}

/**
 * Check dependenicies for plugin
 */
function wcys_check_dependencies() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        // Deactivate the plugin.
        deactivate_plugins(__FILE__);

        // Throw an error in the WordPress admin console.
        /* translators: 1: Plugin Name */
        esc_attr_e('In order to activate this plugin please make sure to install and activate the following plugin(s):', 'wcys');
        ?>
        <ol style="margin-top: 4px;margin-bottom: 0;font-size: 14px;"><li>WooCommerce</li></ol>
        <?php
        die();
    } else {
        wcys_admin_notice_activation_hook();
    }
}

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

/**
 * Check if WooCommerce Bookings & WooCommerce are active
 */
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {


    define('WCYS_PLUGIN_DIR', __DIR__);
    define('WCYS_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WCYS_DEFAULT_BEHAVIOR', 'fixed');
    define('WCYS_ABSPATH', dirname(__FILE__));

    /**
     * Include dependencies
     */
    include_once WCYS_PLUGIN_DIR . '/includes/class-wcys-setting.php';
    include_once WCYS_PLUGIN_DIR . '/includes/class-wcys-customer-checkout.php';
    include_once WCYS_PLUGIN_DIR . '/includes/class-wcys-shipping-method.php';
}
