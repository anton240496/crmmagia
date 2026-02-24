<?php
defined('ABSPATH') or die('No direct access allowed!');
// Загрузка стилей и скриптов
add_action('wp_enqueue_scripts', function () {
    global $is_crm_plugin_page;

    if (empty($is_crm_plugin_page)) {
        return;
    }

    if (get_query_var('crm_custom_page')) {
        wp_enqueue_style('reset', plugin_dir_url(__FILE__) . 'assets/css/reset.css');
        wp_enqueue_style('crm-edit', plugin_dir_url(__FILE__) . 'assets/css/crm-editor.css');
        wp_enqueue_style('crm-documents', plugin_dir_url(__FILE__) . 'assets/css/crm-documents.css');
        wp_enqueue_style('crm', plugin_dir_url(__FILE__) . 'assets/css/crm.css');
        wp_enqueue_style('crm-kp_style', home_url('wp-content/uploads/crm_files/shablon/assets/css/style_kp.css'));


        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js');

        wp_enqueue_script('jquery');



        wp_enqueue_script('crm', plugin_dir_url(__FILE__) . 'assets/js/crm.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm_doc', plugin_dir_url(__FILE__) . 'assets/js/crm_doc.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm_files', plugin_dir_url(__FILE__) . 'assets/js/crm_files_dialog.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-dail', plugin_dir_url(__FILE__) . 'assets/js/crm-dialog.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm_del', plugin_dir_url(__FILE__) . 'assets/js/crm_del.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-history', plugin_dir_url(__FILE__) . 'assets/js/crm-history.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-editor_file', plugin_dir_url(__FILE__) . 'assets/js/crm_save_file.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-edit', plugin_dir_url(__FILE__) . 'assets/js/crm-editor.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-table', plugin_dir_url(__FILE__) . 'assets/js/crm-table.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-two', plugin_dir_url(__FILE__) . 'assets/js/crm-tabletwo.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-more', plugin_dir_url(__FILE__) . 'assets/js/crm-tablemore.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-kupon', plugin_dir_url(__FILE__) . 'assets/js/crm_kupon.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-kalk', plugin_dir_url(__FILE__) . 'assets/js/crm-kalk.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-dop', plugin_dir_url(__FILE__) . 'assets/js/crm-dop.js', array('jquery'), '1.0.0', true);
    }

    if (get_query_var('crm_settings_page')) {
        wp_enqueue_style('setting', plugin_dir_url(__FILE__) . 'assets/css/crm_setting.css');
        wp_enqueue_style('crm-edit', plugin_dir_url(__FILE__) . 'assets/css/crm-editor.css');
        wp_enqueue_style('crm-documents', plugin_dir_url(__FILE__) . 'assets/css/crm-documents.css');
        wp_enqueue_style('crm-kp_style', home_url('wp-content/uploads/crm_files/shablon/assets/css/style_kp.css'));



        // Добавьте другие стили если нужны
        wp_enqueue_style('reset', plugin_dir_url(__FILE__) . 'assets/css/reset.css');

        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js');

        wp_enqueue_script('jquery');

        wp_enqueue_script('setting', plugin_dir_url(__FILE__) . 'assets/js/crm_setting.js', array('jquery'), '1.0.0', true);


        wp_enqueue_script('crm-table', plugin_dir_url(__FILE__) . 'assets/js/crm-table.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-two', plugin_dir_url(__FILE__) . 'assets/js/crm-tabletwo.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-more', plugin_dir_url(__FILE__) . 'assets/js/crm-tablemore.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-edit', plugin_dir_url(__FILE__) . 'assets/js/crm-editor.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-kupon', plugin_dir_url(__FILE__) . 'assets/js/crm_kupon.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('crm-kalk', plugin_dir_url(__FILE__) . 'assets/js/crm-kalk.js', array('jquery'), '1.0.0', true);

    }


    wp_localize_script('crm', 'crm_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('crm_ajax_nonce')
    ));

});

// Поддержка тем
add_theme_support('custom-logo');
add_theme_support('post-thumbnails');
add_theme_support('title-tag');


// Добавляем AJAX обработчик


function get_dialog_item_html_ajax()
{
    // Получаем данные
    $lead_id = intval($_POST['lead_id'] ?? 0);
    $dialog_id = intval($_POST['dialog_id'] ?? 0);
    $dialog_name = sanitize_text_field($_POST['dialog_name'] ?? '');
    $dialog_email = sanitize_text_field($_POST['dialog_email'] ?? '');
    $dialog_created_at = sanitize_text_field($_POST['dialog_created_at'] ?? '');
    $is_active = ($_POST['is_active'] ?? '') === 'true';

    $dialog_name = wp_unslash($dialog_name);
    $dialog_created_at = wp_unslash($dialog_created_at);


    // ДЕБАГ
    error_log("CRM AJAX: lead_id=$lead_id, dialog_id=$dialog_id, dialog_name=$dialog_name");

    if (!$lead_id || !$dialog_id || empty($dialog_name)) {
        wp_send_json_error('Отсутствуют обязательные данные');
    }

    // Получаем email заявки
    $lead_email = get_post_meta($lead_id, 'email', true);
    $display_email = !empty($dialog_email) ? $dialog_email : (!empty($lead_email) ? $lead_email : 'Email не указан');

    // Подключаем HTML шаблон из файла
    ob_start();
    include plugin_dir_path(__FILE__) . 'crm_panel_dial.php';
    $html = ob_get_clean();

    wp_send_json_success($html);
}
add_action('wp_ajax_get_dialog_item_html', 'get_dialog_item_html_ajax');
add_action('wp_ajax_nopriv_get_dialog_item_html', 'get_dialog_item_html_ajax');

function get_lightbox_table_ajax()
{
    // Подключаем файл и получаем его вывод
    ob_start();
    include plugin_dir_path(__FILE__) . 'crm-table.php';
    $table_html = ob_get_clean();

    wp_send_json_success($table_html);
}
add_action('wp_ajax_get_lightbox_table', 'get_lightbox_table_ajax');
add_action('wp_ajax_nopriv_get_lightbox_table', 'get_lightbox_table_ajax');

function get_lightbox_table_two_columns_ajax()
{
    // Подключаем файл и получаем его вывод
    ob_start();
    // include plugin_dir_path(__FILE__) . 'crm-table_two.php';
    include plugin_dir_path(__FILE__) . 'crm-table_two.php';
    $table_html = ob_get_clean();

    wp_send_json_success($table_html);
}
add_action('wp_ajax_get_lightbox_table_two_columns', 'get_lightbox_table_two_columns_ajax');
add_action('wp_ajax_nopriv_get_lightbox_table_two_columns', 'get_lightbox_table_two_columns_ajax');


// Добавьте в functions.php
function get_dialog_template_ajax()
{


    // Остальной код без изменений...
    $lead_id = sanitize_text_field($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    $dialog_name = sanitize_text_field($_POST['dialog_name']);
    $dialog_email = sanitize_email($_POST['dialog_email']);
    $dialog_created_at = sanitize_text_field($_POST['dialog_created_at']);

    $dialog_name = wp_unslash($dialog_name);
    $dialog_created_at = wp_unslash($dialog_created_at);

    ob_start();
    include plugin_dir_path(__FILE__) . 'crm-dialog.php';
    $dialog_html = ob_get_clean();

    wp_send_json_success($dialog_html);
}
add_action('wp_ajax_get_dialog_template', 'get_dialog_template_ajax');
add_action('wp_ajax_nopriv_get_dialog_template', 'get_dialog_template_ajax');


function get_lightbox_table_more_columns_ajax()
{
    // Подключаем файл и получаем его вывод
    ob_start();
    include plugin_dir_path(__FILE__) . 'crm-table_more.php';
    $table_html = ob_get_clean();

    wp_send_json_success($table_html);
}
add_action('wp_ajax_get_lightbox_table_more_columns', 'get_lightbox_table_more_columns_ajax');
add_action('wp_ajax_nopriv_get_lightbox_table_more_columns', 'get_lightbox_table_more_columns_ajax');


function get_editor_template_ajax()
{
    // Отключаем все выводы
    ob_clean();
    
    // Получаем параметры из AJAX запроса
    $dialog_id = intval($_POST['dialog_id'] ?? 0);
    $is_crm_settings = isset($_POST['is_crm_settings']) && $_POST['is_crm_settings'] === '1';
    
    // Если это запрос из настроек CRM, устанавливаем константу
    if ($is_crm_settings) {
        define('IS_CRM_SETTINGS', true);
    }
    
    // ВАЖНО: Нужно определить $EMAIL_CONFIG перед использованием
    // Если $EMAIL_CONFIG не определен, получаем его из базы или используем заглушку
    if (!isset($GLOBALS['EMAIL_CONFIG'])) {
        // Получаем email из базы как fallback
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_email_accounts';
        $email = $wpdb->get_var("SELECT email FROM {$table_name} ORDER BY id ASC LIMIT 1");
        
        // Создаем временный конфиг
        $GLOBALS['EMAIL_CONFIG'] = [
            'accounts' => [
                $email ?: 'example@domain.com' => []
            ]
        ];
    }
    
    // Подключаем ваш файл шаблона
    $editor_path = plugin_dir_path(__FILE__) . 'crm-editor.php';
    
    if (!file_exists($editor_path)) {
        wp_send_json_error('Файл шаблона не найден');
        exit;
    }
    
    // Буферизуем вывод
    ob_start();
    include $editor_path;
    $editor_template = ob_get_clean();
    
    // Отправляем успешный ответ
    wp_send_json_success($editor_template);
    
    exit;
}
add_action('wp_ajax_get_editor_template', 'get_editor_template_ajax');
add_action('wp_ajax_nopriv_get_editor_template', 'get_editor_template_ajax');

// Подключаем CRM панель диалогов



// Подключаем CRM функции
require_once plugin_dir_path(__FILE__) . 'functions-crm.php';




