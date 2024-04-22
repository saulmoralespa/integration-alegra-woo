<?php
/**
 * Plugin Name: Integration Alegra Woocommerce
 * Description: Integración del sistama contable y de facturación Alegra para Woocoommerce
 * Version: 0.0.3
 * Author: Saul Morales Pacheco
 * Author URI: https://saulmoralespa.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 8.6
 * WC requires at least: 4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(!defined('INTEGRATION_ALEGRA_WC_SMP_VERSION')){
    define('INTEGRATION_ALEGRA_WC_SMP_VERSION', '0.0.3');
}

add_action( 'plugins_loaded', 'integration_alegra_wc_smp_init');

function integration_alegra_wc_smp_init(): void
{
    if(!integration_alegra_wc_smp_requirements()) return;

    integration_alegra_wc_smp()->run_alegra();
}

function integration_alegra_wc_smp_notices($notice): void
{
    ?>
    <div class="error notice">
        <p><?php echo esc_html( $notice ); ?></p>
    </div>
    <?php
}

function integration_alegra_wc_smp_requirements(): bool
{

    if ( !version_compare(PHP_VERSION, '8.0.0', '>=') ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            add_action(
                'admin_notices',
                function() {
                    integration_alegra_wc_smp_notices( 'Integration Alegra Woocommerce: Requiere la versión de php >= 8.0');
                }
            );
        }
        return false;
    }


    if ( !in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
        true
    ) ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            add_action(
                'admin_notices',
                function() {
                    integration_alegra_wc_smp_notices( 'Integration Alegra Woocommerce: Requiere que se encuentre instalado y activo el plugin: Woocommerce' );
                }
            );
        }
        return false;
    }

    return true;
}

function integration_alegra_wc_smp(){
    static $plugin;
    if (!isset($plugin)){
        require_once('includes/class-integration-alegra-wc-plugin.php');
        $plugin = new Integration_Alegra_WC_Plugin(__FILE__, INTEGRATION_ALEGRA_WC_SMP_VERSION);
    }
    return $plugin;
}