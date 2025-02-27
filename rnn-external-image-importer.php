<?php
/**
 * Plugin Name: RNN External Image Importer
 * Plugin URI: https://roneon.com/en/plugins/
 * Description: Harici sitelerden çağrılan görselleri yerel sitenize yüklemek için kullanılır.
 * Version: 1.0.0
 * Author: İlhan OĞLAKÇIOĞLU
 * Author URI: https://roneon.com/en/plugins/
 * Text Domain: rnn-external-image-importer
 * Domain Path: /languages
 *
 * Yapımcı: roneon - https://roneon.com/en/plugins/
 *
 * PHP 7.4 ve üzeri sürümlerde sorunsuz çalışır.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RNN_EII_VERSION', '1.0.0' );
define( 'RNN_EII_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RNN_EII_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Dil dosyalarını yükle
function rnn_eii_load_textdomain() {
    load_plugin_textdomain( 'rnn-external-image-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'rnn_eii_load_textdomain' );

// Sadece yönetici panelinde kullanılacak dosyaları dahil et
if ( is_admin() ) {
    require_once RNN_EII_PLUGIN_DIR . 'includes/admin-page.php';
    require_once RNN_EII_PLUGIN_DIR . 'includes/ajax-handlers.php';
}