console.log('RNN External Image Importer Admin script loaded.');

jQuery(document).ready(function($) {
    console.log('Admin script ready.');

    // Tarama: Seçilen kategorideki gönderileri listele
    $('#rnn-eii-start-scan').on('click', function(e) {
        e.preventDefault();
        console.log('Tarama Başlat butonuna tıklandı.');
        var category_id = $('select[name="rnn_eii_scan_category"]').val();
        console.log('Seçilen kategori: ' + category_id);

        $.ajax({
            url: rnn_eii_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rnn_eii_scan_posts',
                nonce: rnn_eii_ajax_obj.nonce,
                category_id: category_id
            },
            success: function(response) {
                console.log('Tarama AJAX başarılı:', response);
                var tbody = $('#rnn-eii-scan-results tbody');
                tbody.empty();

                if (response.success && response.data && response.data.length > 0) {
                    $.each(response.data, function(index, item) {
                        var no = index + 1;
                        var buttonHtml = '';
                        if (item.processed) {
                            // İşlenmiş gönderilerde "Geri Al" butonu (log id ile)
                            buttonHtml = '<button class="button rnn-eii-undo-operation" data-post-id="' + item.post_id + '" data-log-id="' + item.log_id + '">Geri Al</button>';
                        } else {
                            // İşlenmemiş gönderilerde "İşlemi Başlat" butonu
                            buttonHtml = '<button class="button rnn-eii-start-operation" data-post-id="' + item.post_id + '">' + rnn_eii_ajax_obj.start_operation_text + '</button>';
                        }
                        var row = '<tr data-post-id="' + item.post_id + '">';
                        row += '<td>' + no + '</td>';
                        row += '<td>' + item.post_id + '</td>';
                        row += '<td>' + item.category_name + '</td>';
                        row += '<td><a href="' + item.post_url + '" target="_blank">' + item.post_url + ' <span class="new-tab-icon" title="Yeni sekmede açılır">&#x2197;</span></a></td>';
                        row += '<td>' + item.external_images_count + '</td>';
                        row += '<td class="operation-status">' + item.status + '</td>';
                        row += '<td>' + buttonHtml + '</td>';
                        row += '</tr>';
                        tbody.append(row);
                    });
                } else {
                    tbody.append('<tr><td colspan="7">Seçilen kategoride taranacak uygun içerik bulunamadı.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Tarama AJAX hatası:', error);
                alert('Tarama işlemi sırasında hata: ' + error);
            }
        });
    });

    // "İşlemi Başlat" butonuna tıklanınca
    $('body').on('click', '.rnn-eii-start-operation', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        console.log('İşlemi Başlat butonuna tıklandı, post_id:', postId);
        var button = $(this);
        button.prop('disabled', true);

        $.ajax({
            url: rnn_eii_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rnn_eii_start_operation',
                nonce: rnn_eii_ajax_obj.nonce,
                post_id: postId
            },
            success: function(response) {
                console.log('İşlem AJAX başarılı:', response);
                if (response.success) {
                    button.closest('tr').find('.operation-status').text(response.data.status);
                    // Buton metnini "Geri Al" olarak değiştir, sınıfı değiştir ve log id ata
                    button.text('Geri Al');
                    button.removeClass('rnn-eii-start-operation').addClass('rnn-eii-undo-operation');
                    console.log('Log ID from response:', response.data.id);
                    button.data('log-id', response.data.id);
                } else {
                    alert('İşlem hatası: ' + response.data.message);
                }
                button.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('İşlem AJAX hatası:', error);
                alert('İşlem sırasında hata: ' + error);
                button.prop('disabled', false);
            }
        });
    });

    // "Geri Al" butonuna tıklanınca
    $('body').on('click', '.rnn-eii-undo-operation', function(e) {
        e.preventDefault();
        var button = $(this);
        var logId = button.data('log-id');
        console.log('Geri Al butonuna tıklandı, log_id:', logId);
        button.prop('disabled', true);

        $.ajax({
            url: rnn_eii_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rnn_eii_undo_operation',
                nonce: rnn_eii_ajax_obj.nonce,
                log_id: logId
            },
            success: function(response) {
                console.log('Geri alma AJAX başarılı:', response);
                if (response.success) {
                    button.closest('tr').find('.operation-status').text(response.data.message);
                    button.text(rnn_eii_ajax_obj.start_operation_text);
                    button.removeClass('rnn-eii-undo-operation').addClass('rnn-eii-start-operation');
                    button.removeData('log-id');
                } else {
                    alert('Geri alma hatası: ' + response.data.message);
                }
                button.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('Geri alma AJAX hatası:', error);
                alert('Geri alma sırasında hata: ' + error);
                button.prop('disabled', false);
            }
        });
    });
});