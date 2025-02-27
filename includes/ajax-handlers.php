<?php
/**
 * AJAX Handlers for RNN External Image Importer
 *
 * Yapımcı: roneon - https://roneon.com/en/plugins/
 *
 * @package RNN External Image Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tarama İşlemi:
 * Seçilen kategoriye göre gönderileri alır, gönderi içeriğinde 
 * Genel Ayarlar'da girilen external URL ile başlayan <img> etiketlerini arar.
 * Eğer gönderi external görsel içermiyor ve daha önce işlenmemişse listelenmez.
 * Daha önce işlenmişse "Tamamlandı" statüsüyle listelenir.
 */
function rnn_eii_ajax_scan_posts() {
    check_ajax_referer( 'rnn_eii_nonce', 'nonce' );

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $settings = get_option('rnn_eii_settings', array(
        'external_url'   => '',
        'posts_per_scan' => 10,
    ));

    $posts_per_page = ($category_id === 0) ? -1 : intval($settings['posts_per_scan']);
    $external_url = isset($settings['external_url']) ? esc_url_raw($settings['external_url']) : '';

    if ( empty($external_url) ) {
        wp_send_json_error(array('message' => __('Harici Görsel URL Adresi ayarlanmadı.', 'rnn-external-image-importer')));
    }

    $args = array(
        'posts_per_page' => $posts_per_page,
        'post_status'    => 'publish',
    );
    if ($category_id !== 0) {
        $args['cat'] = $category_id;
    }

    error_log('rnn_eii_ajax_scan_posts - Query Args: ' . print_r($args, true));
    $posts = get_posts($args);
    error_log('rnn_eii_ajax_scan_posts - Number of posts returned: ' . count($posts));

    // Daha önce işleme alınmış gönderiler için logları alalım.
    $logs = get_option('rnn_eii_logs', array());
    $results = array();
    foreach ($posts as $post) {
        // External URL'nin bir parçasını içeren <img> etiketlerini bulmak için (esnek regex)
        $pattern = '/<img[^>]+src=["\']([^"\']*' . preg_quote($external_url, '/') . '[^"\']*)["\']/i';
        preg_match_all($pattern, $post->post_content, $matches);
        $external_images = isset($matches[1]) ? $matches[1] : array();
        $count = count($external_images);
        error_log('Post ID: ' . $post->ID . ' - External Images Count: ' . $count);

        $processed = false;
        $log_id = '';
        foreach ($logs as $log) {
            if ($log['post_id'] == $post->ID) {
                $processed = true;
                $log_id = $log['id'];
                break;
            }
        }
        if ($count == 0 && !$processed) {
            continue;
        }
        
        $status = $processed ? __('Tamamlandı', 'rnn-external-image-importer') : __('Bulundu', 'rnn-external-image-importer');

        // Kategori bilgilerini al
        $categories = get_the_category($post->ID);
        if (empty($categories) || is_wp_error($categories)) {
            $categories = get_the_terms($post->ID, 'category');
        }
        $names = array();
        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $cat_link = get_category_link($cat->term_id);
                $names[] = '<a href="' . esc_url($cat_link) . '" target="_blank">' . esc_html($cat->name) . '</a>';
            }
            $category_name = implode(', ', $names);
        } else {
            $category_name = __('Kategori Yok', 'rnn-external-image-importer');
        }

        $results[] = array(
            'post_id'               => $post->ID,
            'category_name'         => $category_name,
            'post_url'              => get_permalink($post->ID),
            'external_images_count' => $count,
            'status'                => $status,
            'processed'             => $processed,
            'log_id'                => $log_id,
        );
    }
    wp_send_json_success($results);
}
add_action('wp_ajax_rnn_eii_scan_posts', 'rnn_eii_ajax_scan_posts');


/**
 * İşlem Başlatma:
 * Seçilen içerikteki external URL'ye sahip görselleri yerelleştirir,
 * görselleri WordPress medya kütüphanesine indirir,
 * içerikteki external URL'leri yeni URL ile günceller,
 * varsa <a> etiketlerini kaldırır,
 * gönderiyi günceller ve orijinal içeriği log kaydında saklar.
 */
function rnn_eii_ajax_start_operation() {
    check_ajax_referer('rnn_eii_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    error_log('[RNN_EII] rnn_eii_ajax_start_operation triggered for post_id: ' . $post_id);

    if (!$post_id) {
        error_log('[RNN_EII] Geçersiz içerik ID alındı.');
        wp_send_json_error(array('message' => __('Geçersiz içerik ID.', 'rnn-external-image-importer')));
    }

    $post = get_post($post_id);
    if (!$post) {
        error_log('[RNN_EII] Post bulunamadı: ' . $post_id);
        wp_send_json_error(array('message' => __('Gönderi bulunamadı.', 'rnn-external-image-importer')));
    }

    // Orijinal içeriği sakla (geri alma için)
    $original_content = $post->post_content;

    $settings = get_option('rnn_eii_settings', array('external_url' => ''));
    $external_url = isset($settings['external_url']) ? esc_url_raw($settings['external_url']) : '';
    if (empty($external_url)) {
        error_log('[RNN_EII] Harici Görsel URL ayarlanmadı.');
        wp_send_json_error(array('message' => __('Harici Görsel URL ayarlanmadı.', 'rnn-external-image-importer')));
    }

    // External URL içeren <img> etiketlerini bulmak için (esnek regex)
    $pattern = '/<img[^>]+src=["\']([^"\']*' . preg_quote($external_url, '/') . '[^"\']*)["\']/i';
    preg_match_all($pattern, $original_content, $matches);
    $external_images = isset($matches[1]) ? $matches[1] : array();
    if (empty($external_images)) {
        error_log('[RNN_EII] Gönderide external görsel bulunamadı.');
        wp_send_json_error(array('message' => __('Gönderide harici görsel bulunamadı.', 'rnn-external-image-importer')));
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $changed_images = 0;
    $new_content = $original_content;

    // External görselleri yerelleştir ve URL'leri güncelle
    foreach ($external_images as $image_url) {
        error_log('[RNN_EII] Processing image: ' . $image_url);
        $new_image = media_sideload_image($image_url, $post_id, '', 'src');
        if (is_wp_error($new_image)) {
            error_log('[RNN_EII] media_sideload_image error for image ' . $image_url . ': ' . $new_image->get_error_message());
            continue;
        } else {
            error_log('[RNN_EII] Image successfully sideloaded. New URL: ' . $new_image);
        }
        $new_content = str_replace($image_url, $new_image, $new_content);
        $changed_images++;
    }

    // External URL’ye sahip <img> etrafındaki <a> etiketlerini kaldır.
    $new_content = preg_replace_callback(
        '/<a\b[^>]*>\s*(<img\b[^>]+src=["\']([^"\']*' . preg_quote($external_url, '/') . '[^"\']*)["\'][^>]*>)\s*<\/a>/i',
        function($matches) {
            return $matches[1]; // Sadece <img> etiketini döndür.
        },
        $new_content
    );

    if ($changed_images > 0) {
        $updated = wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $new_content,
        ));
        if (is_wp_error($updated)) {
            error_log('[RNN_EII] wp_update_post error: ' . $updated->get_error_message());
            wp_send_json_error(array('message' => __('Gönderi güncellenirken hata oluştu.', 'rnn-external-image-importer')));
        } else {
            error_log('[RNN_EII] Post updated successfully. Changed images: ' . $changed_images);
        }
    } else {
        error_log('[RNN_EII] Hiçbir görsel güncellenemedi.');
    }

    // Log kaydı oluştur: Orijinal içeriği sakla (geri alma için)
    $log_entry = array(
        'id'               => time() . rand(1000, 9999),
        'post_id'          => $post_id,
        'post_url'         => get_permalink($post_id),
        'changed_images'   => $changed_images,
        'status'           => __('Tamamlandı', 'rnn-external-image-importer'),
        'time'             => current_time('mysql'),
        'detail'           => sprintf(__('İçerik #%d için %d adet harici görsel yerelleştirildi, URL güncellendi ve bağlantılar kaldırıldı.', 'rnn-external-image-importer'), $post_id, $changed_images),
        'original_content' => $original_content
    );

    $logs = get_option('rnn_eii_logs', array());
    $logs[] = $log_entry;
    update_option('rnn_eii_logs', $logs);

    $undo_logs = get_option('rnn_eii_undo_logs', array());
    $undo_logs[] = $log_entry;
    update_option('rnn_eii_undo_logs', $undo_logs);

    wp_send_json_success($log_entry);
}
add_action('wp_ajax_rnn_eii_start_operation', 'rnn_eii_ajax_start_operation');


/**
 * Geri Alma İşlemi:
 * Seçilen log kaydına göre orijinal içeriği geri yükler.
 */
function rnn_eii_ajax_undo_operation() {
    check_ajax_referer('rnn_eii_nonce', 'nonce');

    $log_id = isset($_POST['log_id']) ? sanitize_text_field($_POST['log_id']) : '';
    if (empty($log_id)) {
        wp_send_json_error(array('message' => __('Geçersiz log ID.', 'rnn-external-image-importer')));
    }

    $undo_logs = get_option('rnn_eii_undo_logs', array());
    $found = false;
    $target_log = null;
    foreach ($undo_logs as $key => $log) {
        if ($log['id'] == $log_id) {
            $target_log = $log;
            unset($undo_logs[$key]);
            update_option('rnn_eii_undo_logs', $undo_logs);
            $found = true;
            break;
        }
    }
    if (!$found) {
        wp_send_json_error(array('message' => __('Log kaydı bulunamadı.', 'rnn-external-image-importer')));
    }

    $post_id = $target_log['post_id'];
    $original_content = isset($target_log['original_content']) ? $target_log['original_content'] : '';
    if (empty($original_content)) {
        wp_send_json_error(array('message' => __('Geri yüklenecek orijinal içerik bulunamadı.', 'rnn-external-image-importer')));
    }

    $updated = wp_update_post(array(
        'ID'           => $post_id,
        'post_content' => $original_content,
    ));
    if (is_wp_error($updated)) {
        wp_send_json_error(array('message' => __('Gönderi geri yüklenirken hata oluştu.', 'rnn-external-image-importer')));
    }

    wp_send_json_success(array(
        'message' => __('İşlem geri alındı.', 'rnn-external-image-importer'),
        'status'  => __('Geri alındı', 'rnn-external-image-importer')
    ));
}
add_action('wp_ajax_rnn_eii_undo_operation', 'rnn_eii_ajax_undo_operation');
?>