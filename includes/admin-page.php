<?php
/**
 * Admin Page for RNN External Image Importer
 *
 * Yapımcı: roneon - https://roneon.com/en/plugins/
 *
 * @package RNN External Image Importer
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ayarların kayıt işlemi.
 */
function rnn_eii_register_settings() {
    register_setting( 'rnn_eii_settings_group', 'rnn_eii_settings' );
}
add_action( 'admin_init', 'rnn_eii_register_settings' );

function rnn_eii_admin_menu() {
    add_menu_page(
        __( 'Roneon', 'rnn-external-image-importer' ),
        __( 'Roneon', 'rnn-external-image-importer' ),
        'manage_options',
        'rnn-eii',
        'rnn_eii_render_admin_page',
        'dashicons-admin-generic',
        6
    );
    add_submenu_page(
        'rnn-eii',
        __( 'RNN External Image Importer', 'rnn-external-image-importer' ),
        __( 'RNN External Image Importer', 'rnn-external-image-importer' ),
        'manage_options',
        'rnn-eii-importer',
        'rnn_eii_render_admin_page'
    );
}
add_action( 'admin_menu', 'rnn_eii_admin_menu' );

function rnn_eii_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    wp_enqueue_style( 'rnn-eii-admin-style', RNN_EII_PLUGIN_URL . 'assets/css/admin-style.css' );
    wp_enqueue_script( 'rnn-eii-admin-script', RNN_EII_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), RNN_EII_VERSION, true );
    wp_localize_script( 'rnn-eii-admin-script', 'rnn_eii_ajax_obj', array(
        'ajax_url'             => admin_url( 'admin-ajax.php' ),
        'nonce'                => wp_create_nonce( 'rnn_eii_nonce' ),
        'start_operation_text' => __( 'İşlemi başlat', 'rnn-external-image-importer' )
    ) );
    
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
    ?>
    <div class="wrap">
        <h1><?php _e( 'RNN External Image Importer', 'rnn-external-image-importer' ); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=general' ); ?>" class="nav-tab <?php echo ( $active_tab == 'general' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-settings"></span> <?php _e( 'Genel ayarlar', 'rnn-external-image-importer' ); ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=scan' ); ?>" class="nav-tab <?php echo ( $active_tab == 'scan' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-search"></span> <?php _e( 'Tarama', 'rnn-external-image-importer' ); ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=undo' ); ?>" class="nav-tab <?php echo ( $active_tab == 'undo' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-update"></span> <?php _e( 'Geri alma işlemleri', 'rnn-external-image-importer' ); ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=log' ); ?>" class="nav-tab <?php echo ( $active_tab == 'log' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-list-view"></span> <?php _e( 'İşlem günlüğü', 'rnn-external-image-importer' ); ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=about' ); ?>" class="nav-tab <?php echo ( $active_tab == 'about' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-info"></span> <?php _e( 'Hakkında', 'rnn-external-image-importer' ); ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=rnn-eii-importer&tab=premium' ); ?>" class="nav-tab <?php echo ( $active_tab == 'premium' ) ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-star-filled"></span> <?php _e( 'Premium Version', 'rnn-external-image-importer' ); ?>
            </a>
        </h2>
        <div class="rnn-eii-tab-content">
            <?php
            switch ( $active_tab ) {
                case 'general':
                    rnn_eii_render_general_settings();
                    break;
                case 'scan':
                    rnn_eii_render_scan_tab();
                    break;
                case 'undo':
                    rnn_eii_render_undo_tab();
                    break;
                case 'log':
                    rnn_eii_render_log_tab();
                    break;
                case 'about':
                    rnn_eii_render_about_tab();
                    break;
                case 'premium':
                    rnn_eii_render_premium_tab();
                    break;
                default:
                    rnn_eii_render_general_settings();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

function rnn_eii_render_general_settings() {
    // Genel Ayarlar formu
    $settings = get_option( 'rnn_eii_settings', array(
        'external_url'   => '',
        'posts_per_scan' => 10,
    ) );
    ?>
    <h2><?php _e( 'Genel Ayarlar', 'rnn-external-image-importer' ); ?></h2>
    <form method="post" action="options.php">
        <?php settings_fields( 'rnn_eii_settings_group' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Harici Görsel URL', 'rnn-external-image-importer' ); ?></th>
                <td>
                    <input type="text" name="rnn_eii_settings[external_url]" value="<?php echo isset( $settings['external_url'] ) ? esc_attr( $settings['external_url'] ) : ''; ?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e( 'Tarama Başına Gönderi Sayısı', 'rnn-external-image-importer' ); ?></th>
                <td>
                    <input type="number" name="rnn_eii_settings[posts_per_scan]" value="<?php echo isset( $settings['posts_per_scan'] ) ? intval( $settings['posts_per_scan'] ) : 10; ?>" />
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
}

function rnn_eii_render_scan_tab() {
    ?>
    <h2><?php _e( 'Tarama', 'rnn-external-image-importer' ); ?></h2>
    <form id="rnn-eii-scan-form">
        <table class="form-table">
            <tr>
                <th><?php _e( 'Kategori Seçimi', 'rnn-external-image-importer' ); ?></th>
                <td>
                    <?php
                    $categories = get_categories( array( 'hide_empty' => false ) );
                    echo '<select name="rnn_eii_scan_category">';
                    echo '<option value="0">' . __( 'Tüm Kategoriler', 'rnn-external-image-importer' ) . '</option>';
                    foreach ( $categories as $category ) {
                        echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
                    }
                    echo '</select>';
                    ?>
                </td>
            </tr>
        </table>
        <p>
            <input type="button" id="rnn-eii-start-scan" class="button button-primary" value="<?php _e( 'Tarama Başlat', 'rnn-external-image-importer' ); ?>">
        </p>
    </form>
    <div id="rnn-eii-scan-results">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e( 'No:', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'İçerik ID', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'Kategori', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'İçerik URL', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'Bulunan Harici Görsel Sayısı', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'İşlem Durumu', 'rnn-external-image-importer' ); ?></th>
                    <th><?php _e( 'İşlem', 'rnn-external-image-importer' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Ajax ile gelecek sonuçlar buraya eklenecek -->
            </tbody>
        </table>
    </div>
    <?php
}

function rnn_eii_render_undo_tab() {
    echo '<h2>' . __( 'Geri Alma İşlemleri', 'rnn-external-image-importer' ) . '</h2>';
    echo '<p>' . __( 'Geri alma işlemleri burada listelenecektir. İşlemler geri alındıkça bu liste güncellenecektir.', 'rnn-external-image-importer' ) . '</p>';
}

function rnn_eii_render_log_tab() {
    echo '<h2>' . __( 'İşlem Günlüğü', 'rnn-external-image-importer' ) . '</h2>';
    echo '<p>' . __( 'Gerçekleştirilen işlemlerin günlüğü burada gösterilecektir.', 'rnn-external-image-importer' ) . '</p>';
}

function rnn_eii_render_about_tab() {
    echo '<h2>' . __( 'Hakkında', 'rnn-external-image-importer' ) . '</h2>';
    echo '<p>' . __( 'Bu eklenti, harici sitelerden çağrılan görselleri yerel sunucuya aktarmak için geliştirilmiştir. Geliştirici: İlhan OĞLAKÇIOĞLU, roneon.', 'rnn-external-image-importer' ) . '</p>';
}

function rnn_eii_render_premium_tab() {
    echo '<h2>' . __( 'Premium Version', 'rnn-external-image-importer' ) . '</h2>';
    echo '<p>' . __( 'Premium özellikler ve destek seçenekleri yakında eklenecektir.', 'rnn-external-image-importer' ) . '</p>';
}
?>
