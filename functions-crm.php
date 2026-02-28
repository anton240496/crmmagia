<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * CRM System Functions
 * Все функции для работы самописной CRM системы
 */

// pro

add_action('wp_ajax_crm_activate_license', function () {
    error_log('CRM ACTIVATE: ' . print_r($_POST, true));
    if (ob_get_level()) {
        ob_clean();
    }

    // ========== ПОЛУЧАЕМ ID ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ ==========
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('Пользователь не авторизован');
        wp_die();
    }

    // Получаем email и ключ
    $email = sanitize_email($_POST['email'] ?? '');
    $license_key = sanitize_text_field($_POST['license_key'] ?? '');

    if (empty($email)) {
        wp_send_json_error('Введите ваш email');
        wp_die();
    }

    if (!is_email($email)) {
        wp_send_json_error('Введите корректный email');
        wp_die();
    }

    if (empty($license_key)) {
        wp_send_json_error('Введите лицензионный ключ');
        wp_die();
    }

    $api_domain = 'https://magtexnology.com/';

    if (!filter_var($api_domain, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Неверная конфигурация API-сервера');
        wp_die();
    }

    // ========== ПРОВЕРЯЕМ НА СЕРВЕРЕ ==========
    $api_url = $api_domain . '/?crm_verify=' . urlencode($license_key) . '&email=' . urlencode($email);
    $response = wp_remote_get($api_url, [
        'timeout' => 10,
        'sslverify' => false
    ]);

    $used_endpoint = 'new';

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        $api_url_old = $api_domain . '/?pms_verify=' . urlencode($license_key) . '&email=' . urlencode($email);
        $response = wp_remote_get($api_url_old, [
            'timeout' => 10,
            'sslverify' => false
        ]);
        $used_endpoint = 'old';
    }

    if (is_wp_error($response)) {
        wp_send_json_error('Ошибка подключения к серверу лицензий');
        wp_die();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // ========== ОБРАБАТЫВАЕМ ОТВЕТ ==========
    $activation_successful = false;
    $plan_name = 'unknown';
    $expires_date = '';

    if (isset($data['success']) && $data['success'] === true) {
        if (isset($data['data']['active']) && $data['data']['active'] === true) {
            $verified_email = $data['data']['email'] ?? '';

            if (empty($verified_email) || $verified_email === $email) {
                $activation_successful = true;
                $plan_name = sanitize_text_field($data['data']['plan'] ?? 'unknown');
                $expires_date = sanitize_text_field($data['data']['expires'] ?? '');
            } else {
                wp_send_json_error('Лицензия не найдена проверьте mail и ключ и попробуйте еще раз');
                wp_die();
            }
        } elseif (isset($data['data']['plan_name'])) {
            wp_send_json_error('Устаревший формат ключа, обратитесь в поддержку');
            wp_die();
        }
    }

    // ========== СОХРАНЯЕМ РЕЗУЛЬТАТ В USER_META ==========
    if ($activation_successful) {
        // ✅ ПРАВИЛЬНО: передаём ID пользователя первым параметром
        update_user_meta($current_user_id, 'crm_license_key', $license_key);
        update_user_meta($current_user_id, 'crm_license_email', $email);
        update_user_meta($current_user_id, 'crm_license_status', 'active');
        update_user_meta($current_user_id, 'crm_license_plan', $plan_name);
        update_user_meta($current_user_id, 'crm_license_expires', $expires_date);
        update_user_meta($current_user_id, 'crm_license_endpoint_type', $used_endpoint);

        wp_send_json_success([
            'message' => 'Подписка успешно активирована!',
            'plan' => $plan_name,
            'email' => $email
        ]);
    } else {
        // ✅ ПРАВИЛЬНО: удаляем user_meta, а не options
  
       update_user_meta($current_user_id, 'crm_license_status', 'expired');
  

        $error_message = $data['data'] ?? ($data['message'] ?? 'Неизвестная ошибка сервера');
        wp_send_json_error('Ошибка активации: ' . $error_message);
    }

    wp_die();
});



// ====================  ФУНКЦИЯ ПРОВЕРКИ ====================

function my_plugin_check_license_status()
{

    $current_user_id = get_current_user_id();
    if (!$current_user_id)
        return false;
    // 1. Получаем ключ и жестко прописанный домен API
    $license_key = get_user_meta($current_user_id, 'crm_license_key', true);
    $license_email = get_user_meta($current_user_id, 'crm_license_email', true);

    // Домен теперь берется из кода, а не из БД
    $api_domain = 'https://magtexnology.com'; // ← ТОТ ЖЕ САМЫЙ, ЧТО В ФУНКЦИИ АКТИВАЦИИ!

    if (empty($license_key) || empty($license_email)) {
        update_user_meta($current_user_id, 'crm_license_status', 'inactive');
        return false;
    }

    // 2. Проверяем на сервере (остальной код остается без изменений)
    $api_url = $api_domain . '/?crm_verify=' . urlencode($license_key) . '&email=' . urlencode($license_email);
    $response = wp_remote_get($api_url, ['timeout' => 5]);
    $used_new_endpoint = true;

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        // Добавляем email и в старый endpoint!
        $api_url_old = $api_domain . '/?pms_verify=' . urlencode($license_key) . '&email=' . urlencode($license_email);
        $response = wp_remote_get($api_url_old, ['timeout' => 3]);
        $used_new_endpoint = false;
    }

    // if (!is_wp_error($response)) {
    //     $data = json_decode(wp_remote_retrieve_body($response), true);

    //     if ($data['success'] === true) {
    //         update_option('crm_pro_license_status', 'active');
    //         // Важно: получаем и сохраняем ТИП подписки
    //         $plan_name = sanitize_text_field($data['data']['plan_name'] ?? 'unknown');
    //         update_option('crm_pro_license_plan', $plan_name, false);

    //         // Сохраняем дату истечения
    //         $expires_date = sanitize_text_field($data['data']['expires'] ?? '');
    //         update_option('crm_pro_license_expires', $expires_date, false);
    //         return true;
    //     } else {
    //         update_option('crm_pro_license_status', 'expired');
    //         return false;
    //     }
    // }

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // ========== ИСПРАВЛЕННАЯ ПРОВЕРКА ОБОИХ ФОРМАТОВ ==========
        $is_success = false;
        $plan_name = '';
        $expires_date = '';

        // 1. НОВЫЙ ФОРМАТ: success:true, data:{active:true, plan:...}
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['active']) && $data['data']['active'] === true) {
            $is_success = true;
            $plan_name = sanitize_text_field($data['data']['plan'] ?? 'unknown');
            $expires_date = sanitize_text_field($data['data']['expires'] ?? '');
        }
        // 2. СТАРЫЙ ФОРМАТ (для обратной совместимости): success:true, data:{plan_name:...}
        elseif (isset($data['success']) && $data['success'] === true && isset($data['data']['plan_name'])) {
            $is_success = true;
            $plan_name = sanitize_text_field($data['data']['plan_name'] ?? 'unknown');
            $expires_date = sanitize_text_field($data['data']['expires'] ?? '');
        }
        // 3. УСТАРЕВШИЙ ФОРМАТ (на всякий случай): {active: true, plan: ...}
        elseif (isset($data['active']) && $data['active'] === true) {
            $is_success = true;
            $plan_name = sanitize_text_field($data['plan'] ?? 'unknown');
            $expires_date = sanitize_text_field($data['expires'] ?? '');
        }

        if ($is_success) {
            update_user_meta($current_user_id, 'crm_license_status', 'active');
            update_user_meta($current_user_id, 'crm_license_plan', $plan_name);
            update_user_meta($current_user_id, 'crm_license_expires', $expires_date);
            update_user_meta($current_user_id, 'crm_license_endpoint_type', $used_new_endpoint ? 'new' : 'old');
            return true;
        } else {
            update_user_meta($current_user_id, 'crm_license_status', '');
            return false;
        }
    }
    // Если ошибка сети - проверяем локальную дату и тип подписки
    $status = get_user_meta($current_user_id, 'crm_license_status', true);
    $plan = get_user_meta($current_user_id, 'crm_license_plan', true);
    $expires_date = get_user_meta($current_user_id, 'crm_license_expires', true);
    if ($status === 'active') {
        // Для PRO проверяем дату, для VIP всегда активно
        if (strtoupper($plan) === 'PRO' && !empty($expires_date)) {
            if (current_time('timestamp') > strtotime($expires_date)) {
                update_user_meta($current_user_id, 'crm_license_status', '');
                return false;
            }
        }
        return true;
    }

    return false;
}


// ==================== ПРОВЕРКА ПРИ КАЖДОЙ ЗАГРУЗКЕ СТРАНИЦЫ ====================
add_action('init', function () {
    // ВРЕМЕННО КОММЕНТИРУЕМ проверку на каждом init
    my_plugin_check_license_status();

    // Вместо этого проверяем лицензию только на страницах CRM
    if (is_admin() || (isset($_GET['page']) && $_GET['page'] === 'crm_page')) {
        // И только если давно не проверяли (например, раз в 12 часов)
        $last_check = get_option('crm_last_check', 0);
        // if (current_time('timestamp') - $last_check > 12 * HOUR_IN_SECONDS) {
        my_plugin_check_license_status();
        update_user_meta('crm_last_check', current_time('timestamp'), false);
        // }
    }
});

// ==================== КРОН НА СЛУЧАЙ ЕСЛИ ПОЛЬЗОВАТЕЛЬ НЕ ЗАХОДИТ НА САЙТ ====================
add_action('crm_daily_license_check', function () {
    // Принудительно сбрасываем время последней проверки
    // чтобы при следующем заходе на сайт обязательно проверили сервер
    update_user_meta('crm_last_check', 0, false);
});

// Функция для планирования события (вызовите её при активации плагина)
function crm_schedule_daily_check()
{
    if (!wp_next_scheduled('crm_daily_license_check')) {
        wp_schedule_event(time(), 'daily', 'crm_daily_license_check');
    }
}
register_activation_hook(__FILE__, 'crm_schedule_daily_check');

// AJAX обработчик для проверки лицензии
add_action('wp_ajax_crm_check_license_ajax', function () {
    // Проверяем лицензию
    $is_active = my_plugin_check_license_status();

    // Получаем сохраненный домен
    // $api_domain = get_option('crm_license_domain', '');
    // if (empty($api_domain)) {

    //     $api_domain = 'https://magtexnology.com';
    // }

    $api_domain = 'https://magtexnology.com'; // ← ТОТ ЖЕ ДОМЕН!
    $pay_url = $api_domain . '/crmmagia-pay/#pay';

    wp_send_json_success([
        'active' => $is_active,
        'pay_url' => $pay_url
    ]);

    wp_die();
});
// временно не факт что хук правильный
add_filter('pms_payment_metadata', function ($metadata, $payment_data, $user_id) {
    // 1. ГЕНЕРИРУЕМ УНИКАЛЬНЫЙ КЛЮЧ (например, 'crm_ab12CD34ef56GH78')
    $license_key = 'crm_' . wp_generate_password(16, false);

    // 2. ОПРЕДЕЛЯЕМ ТАРИФ (PRO или VIP) по ID плана из данных платежа
    $plan_name = 'unknown';
    if ($payment_data['subscription_plan_id'] == 18) {
        $plan_name = 'PRO';
    } elseif ($payment_data['subscription_plan_id'] == 19) {
        $plan_name = 'VIP';
    }

    // 3. ПЕРЕДАЁМ ЭТИ ДАННЫЕ В МЕТАДАННЫЕ ПЛАТЕЖА
    $metadata['license_key'] = $license_key; // Ключ для CRM
    $metadata['plan'] = $plan_name;          // Тип тарифа

    return $metadata; // ЮKassa получит этот обновлённый массив
}, 10, 3);

// ВРЕМЕННЫЙ КОД ДЛЯ ОТЛАДКИ - удалить после проверки
// ВРЕМЕННЫЙ КОД ДЛЯ ОТЛАДКИ - удалить после проверки
// add_action('init', function () {
//     if (current_user_can('administrator') && isset($_GET['debug_pms_hook'])) {

//         echo '<h2>Тест pms_payment_metadata хука</h2>';

//         // Тест для тарифа 18 (PRO)
//         $test_metadata = array();
//         $test_payment_data = array('subscription_plan_id' => 18);
//         $test_user_id = 1;

//         $result = apply_filters('pms_payment_metadata', $test_metadata, $test_payment_data, $test_user_id);

//         echo '<h3>Тариф ID 18 (PRO):</h3>';
//         echo '<pre>';
//         print_r($result);
//         echo '</pre>';

//         // Тест для тарифа 19 (VIP)
//         $test_metadata = array();
//         $test_payment_data = array('subscription_plan_id' => 19);
//         $test_user_id = 1;

//         $result = apply_filters('pms_payment_metadata', $test_metadata, $test_payment_data, $test_user_id);

//         echo '<h3>Тариф ID 19 (VIP):</h3>';
//         echo '<pre>';
//         print_r($result);
//         echo '</pre>';

//         // Тест для неизвестного тарифа
//         $test_metadata = array();
//         $test_payment_data = array('subscription_plan_id' => 999);
//         $test_user_id = 1;

//         $result = apply_filters('pms_payment_metadata', $test_metadata, $test_payment_data, $test_user_id);

//         echo '<h3>Тариф ID 999 (unknown):</h3>';
//         echo '<pre>';
//         print_r($result);
//         echo '</pre>';

//         wp_die();
//     }
// });
// pro

$EMAIL_CONFIG = array(
    'host' => '',
    'accounts' => array(), // Теперь будет заполняться из базы данных
);

global $EMAIL_CONFIG, $wpdb;

// Получаем sender_email ИЗ БАЗЫ ДАННЫХ для этого диалога
$sender_email_from_db = $wpdb->get_var($wpdb->prepare(
    "SELECT sender_email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
    $dialog_id
));




function get_crm_email_accounts()
{
    global $wpdb;

    error_log("🔍 CRM: get_crm_email_accounts() called");

    // Создание таблицы для email аккаунтов
    $table_name = $wpdb->prefix . 'crm_email_accounts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        host VARCHAR(100),
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // ПРОВЕРЯЕМ, ЕСТЬ ЛИ ЗАПИСИ В ТАБЛИЦЕ
    $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // ЕСЛИ ТАБЛИЦА ПУСТАЯ - СОЗДАЕМ ПЕРВУЮ СТРОКУ
    if ($row_count == 0) {
        error_log("🔍 CRM: Таблица пустая, создаем первую строку...");

        $wpdb->insert(
            $table_name,
            array(
                'email' => '',
                'password' => '',
                'host' => '',
                'active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        error_log("🔍 CRM: Создана первая строка с ID: " . $wpdb->insert_id);
    }

    // Получаем все аккаунты из базы данных
    $accounts_from_db = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id");

    error_log("🔍 CRM: Found " . count($accounts_from_db) . " email accounts in database");

    // 🔧 ИСПРАВЛЕНИЕ: Объявляем переменную перед использованием
    $host_from_db = '';
    if (!empty($accounts_from_db[0])) {
        $host_from_db = $accounts_from_db[0]->host;
    }
    error_log("🔍 CRM: Host from database: " . $host_from_db);

    // Формируем массив accounts для совместимости
    $accounts_array = array();
    foreach ($accounts_from_db as $account) {
        $accounts_array[$account->email] = $account->password;
        error_log("📧 CRM: Account - " . $account->email . " (password length: " . strlen($account->password) . ")");
    }

    // Возвращаем конфигурацию с аккаунтами из базы данных
    $config = array(
        'host' => $host_from_db ?: '',
        'username' => !empty($accounts_from_db[0]) ? $accounts_from_db[0]->email : '',
        'password' => !empty($accounts_from_db[0]) ? $accounts_from_db[0]->password : '',
        'from_email' => !empty($accounts_from_db[0]) ? $accounts_from_db[0]->email : '',
        'accounts' => $accounts_array,
        'from_name' => $wpdb->get_var("
    SELECT 
        CASE 
            WHEN active = 0 THEN '' 
            ELSE CONCAT(
                UPPER(SUBSTRING(name, 1, 1)), 
                SUBSTRING(name, 2)
            )
        END
    FROM {$wpdb->prefix}crm_shabl_mes 
    LIMIT 1
")
    );

    error_log("🔍 CRM: Returning email config: " . print_r($config, true));

    return $config;
}

// Обработчик AJAX для сохранения названия агентства
// Регистрируем AJAX обработчик
add_action('wp_ajax_save_agency_data', 'save_agency_data_handler');

function save_agency_data_handler()
{
    error_log('🔧 CRM: save_agency_data_handler called');

    if (!current_user_can('manage_options')) {
        error_log('🔧 CRM: User not authorized');
        wp_die('Insufficient permissions');
    }

    $agency_name = isset($_POST['agency_name']) ? sanitize_text_field($_POST['agency_name']) : '';
    $agency_name = wp_unslash($agency_name);

    $agency_podval = isset($_POST['agency_podval']) ? sanitize_text_field($_POST['agency_podval']) : '';
    $agency_podval = wp_unslash($agency_podval);

    $agency_color = isset($_POST['agency_color']) ? sanitize_text_field($_POST['agency_color']) : '';
    $agency_color = wp_unslash($agency_color);

    error_log('🔧 CRM: Received agency_name: ' . $agency_name);
    error_log('🔧 CRM: Received agency_podval: ' . $agency_podval);

    if (empty($agency_name)) {
        error_log('🔧 CRM: Empty agency name');
        wp_send_json_error('Название не может быть пустым');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_mes';

    // Проверяем есть ли запись
    $existing = $wpdb->get_var("SELECT id FROM $table_name LIMIT 1");
    error_log('🔧 CRM: Existing record ID: ' . $existing);

    if ($existing) {
        error_log('🔧 CRM: Updating existing record');
        $result = $wpdb->update(
            $table_name,
            array(
                'name' => $agency_name,
                'podval' => $agency_podval,
                'color' => $agency_color
            ),
            array('id' => $existing),
            array('%s', '%s', '%s'), // Оба поля строковые
            array('%d')
        );
    } else {
        error_log('🔧 CRM: Inserting new record');
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $agency_name,
                'podval' => $agency_podval,
                'color' => $agency_color
            ),
            array('%s', '%s', '%s')
        );
    }

    error_log('🔧 CRM: Database result: ' . ($result !== false ? 'success' : 'failure'));
    if ($result !== false) {
        wp_send_json_success('Данные сохранены');
    } else {
        error_log('🔧 CRM: Database error: ' . $wpdb->last_error);
        wp_send_json_error('Ошибка базы данных: ' . $wpdb->last_error);
    }
}



// AJAX обработчик для сохранения контактов
add_action('wp_ajax_save_crm_contact', 'save_crm_contact_callback');
add_action('wp_ajax_nopriv_save_crm_contact', 'save_crm_contact_callback');

function save_crm_contact_callback()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_kp';

    $type = sanitize_text_field($_POST['type']); // 'phone' или 'email'
    $value = sanitize_text_field($_POST['value']);

    // Определяем поле в базе
    $field = ($type == 'phone') ? 'telefon_sait_shortcode' : 'mail_sait_shortcode';

    // Сохраняем в базу
    $result = $wpdb->update(
        $table_name,
        array($field => $value),
        array('id' => 1)
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'Сохранено',
            'value' => $value,
            'type' => $type
        ));
    } else {
        wp_send_json_error('Ошибка сохранения');
    }

    wp_die();
}
// для менеджера
// Обработчик AJAX для обновления имени
add_action('wp_ajax_update_name_in_db', 'update_name_in_db');
add_action('wp_ajax_nopriv_update_name_in_db', 'update_name_in_db'); // если нужно для неавторизованных

function update_name_in_db()
{

    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_kp';

    $new_name = sanitize_text_field($_POST['new_name']);

    $new_name = wp_unslash($new_name);

    // Обновляем запись (предполагаем, что есть хотя бы одна запись)
    $result = $wpdb->update(
        $table_name,
        array('name_men' => $new_name),
        array('id' => 1), // или как у вас определяется ID
        array('%s'), // формат значения
        array('%d')  // формат WHERE
    );

    if ($result !== false) {
        wp_send_json_success('Имя успешно обновлено');
    } else {
        wp_send_json_error('Ошибка при обновлении');
    }
}

// Обработчик AJAX для обновления телефона
add_action('wp_ajax_update_tel_in_db', 'update_tel_in_db');
add_action('wp_ajax_nopriv_update_tel_in_db', 'update_tel_in_db');

function update_tel_in_db()
{
    // Для отладки
    error_log('AJAX вызван: update_tel_in_db');

    if (!isset($_POST['new_tel'])) {
        wp_send_json_error('No telephone provided');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_kp';
    $new_tel = sanitize_text_field($_POST['new_tel']);

    // Получаем ID первой записи
    $id = $wpdb->get_var("SELECT id FROM {$table_name} ORDER BY id ASC LIMIT 1");

    if (!$id) {
        // Если записей нет, создаем новую
        $result = $wpdb->insert(
            $table_name,
            array('tel_men' => $new_tel),
            array('%s')
        );
        if ($result) {
            wp_send_json_success('Телефон создан');
        } else {
            wp_send_json_error('Ошибка при создании записи');
        }
    } else {
        // Обновляем существующую
        $result = $wpdb->update(
            $table_name,
            array('tel_men' => $new_tel),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success('Телефон обновлен');
        } else {
            wp_send_json_error('Ошибка базы данных при обновлении');
        }
    }
}


// cтили пожключения медиабилиотке wp
// Для админки
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_media();
});

add_action('wp_ajax_crm_save_background', 'crm_handle_background_save');

function crm_handle_background_save()
{
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Доступ запрещен');
    }

    $image_id = intval($_POST['image_id']);
    $source_path = get_attached_file($image_id);

    if (!$source_path) {
        wp_send_json_error('Файл не найден');
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    wp_mkdir_p($target_dir);

    $ext = pathinfo($source_path, PATHINFO_EXTENSION);

    // ===== УДАЛЯЕМ СТАРЫЙ kp_prev.* =====
    $old_files = glob($target_dir . 'kp_prev.*');
    foreach ($old_files as $old_file) {
        if (is_file($old_file)) {
            unlink($old_file);
        }
    }

    // Новый файл для предпросмотра
    $preview_file = $target_dir . 'kp_prev.' . $ext;

    // ===== ОБРАБОТКА КАРТИНКИ =====
    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source_path);
            $save_function = 'imagejpeg';
            $quality = 90;
            break;
        case 'png':
            $image = imagecreatefrompng($source_path);
            $save_function = 'imagepng';
            $quality = 9;
            break;
        case 'gif':
            $image = imagecreatefromgif($source_path);
            $save_function = 'imagegif';
            $quality = null;
            break;
        default:
            wp_send_json_error('Неподдерживаемый формат');
    }

    if (!$image)
        wp_send_json_error('Не удалось загрузить изображение');





    // Сохраняем
    if ($save_function == 'imagejpeg') {
        imagejpeg($image, $preview_file, $quality);
    } elseif ($save_function == 'imagepng') {
        imagepng($image, $preview_file, $quality);
    } elseif ($save_function == 'imagegif') {
        imagegif($image, $preview_file);
    }

    imagedestroy($image);

    // URL с timestamp против кеша
    $timestamp = time();
    $preview_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/kp_prev.' . $ext . '?v=' . $timestamp;

    wp_send_json_success([
        'message' => 'Картинка обновлена для предпросмотра',
        'url' => $preview_url
    ]);
}

add_action('wp_ajax_apply_php_shadow', 'apply_shadow_to_preview_handler');

function apply_shadow_to_preview_handler()
{
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Доступ запрещен');
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';

    // Ищем файл kp_prev.* (предпросмотр)
    $preview_files = glob($target_dir . 'kp_prev.*');

    if (empty($preview_files)) {
        wp_send_json_error('Сначало смените фон кнопка "Смена фона"');
    }

    $preview_path = $preview_files[0];
    $ext = strtolower(pathinfo($preview_path, PATHINFO_EXTENSION));

    // Загружаем изображение
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($preview_path);
            $save_function = 'imagejpeg';
            $quality = 90;
            break;
        case 'png':
            $image = imagecreatefrompng($preview_path);
            $save_function = 'imagepng';
            $quality = 9;
            break;
        case 'gif':
            $image = imagecreatefromgif($preview_path);
            $save_function = 'imagegif';
            $quality = null;
            break;
        default:
            wp_send_json_error('Неподдерживаемый формат');
    }

    if (!$image) {
        wp_send_json_error('Не удалось загрузить изображение');
    }

    // ===== ПРИМЕНЯЕМ ЗАТЕМНЕНИЕ =====
    imagefilter($image, IMG_FILTER_BRIGHTNESS, -90);

    // Сохраняем с затемнением
    if ($save_function == 'imagejpeg') {
        imagejpeg($image, $preview_path, $quality);
    } elseif ($save_function == 'imagepng') {
        imagepng($image, $preview_path, $quality);
    } elseif ($save_function == 'imagegif') {
        imagegif($image, $preview_path);
    }

    imagedestroy($image);

    // Обновленный URL
    $timestamp = time();
    $preview_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/kp_prev.' . $ext . '?v=' . $timestamp;

    wp_send_json_success([
        'message' => 'Тень применена к предпросмотру',
        'url' => $preview_url
    ]);
}

add_action('wp_ajax_crm_save_logo', 'crm_handle_logo_save');

function crm_handle_logo_save()
{
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Доступ запрещен');
    }

    $image_id = intval($_POST['image_id']);
    $source_path = get_attached_file($image_id);

    if (!$source_path) {
        wp_send_json_error('Файл не найден');
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    wp_mkdir_p($target_dir);

    $ext = pathinfo($source_path, PATHINFO_EXTENSION);

    // Удаляем старый logokp_prev.*
    $old_files = glob($target_dir . 'logokp_prev.*');
    foreach ($old_files as $old_file) {
        if (is_file($old_file))
            unlink($old_file);
    }

    // Сохраняем новый логотип
    $logo_file = $target_dir . 'logokp_prev.' . $ext;

    // Копируем (без обработки, если нужна прозрачность PNG)
    if (!copy($source_path, $logo_file)) {
        wp_send_json_error('Ошибка копирования');
    }

    // URL с timestamp
    $timestamp = time();
    $logo_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/logokp_prev.' . $ext . '?v=' . $timestamp;

    wp_send_json_success([
        'message' => 'Логотип обновлен для предпросмотра',
        'url' => $logo_url
    ]);
}

add_action('wp_ajax_crm_save_avatar', 'crm_handle_avatar_save');

function crm_handle_avatar_save()
{
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Доступ запрещен');
    }

    $image_id = intval($_POST['image_id']);
    $source_path = get_attached_file($image_id);

    if (!$source_path) {
        wp_send_json_error('Файл не найден');
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    wp_mkdir_p($target_dir);

    $ext = pathinfo($source_path, PATHINFO_EXTENSION);

    // Удаляем старый avatarkp_prev.*
    $old_files = glob($target_dir . 'avatarkp_prev.*');
    foreach ($old_files as $old_file) {
        if (is_file($old_file))
            unlink($old_file);
    }

    // Сохраняем новую аватарку
    $avatar_file = $target_dir . 'avatarkp_prev.' . $ext;

    // Копируем
    if (!copy($source_path, $avatar_file)) {
        wp_send_json_error('Ошибка копирования');
    }

    // URL с timestamp
    $timestamp = time();
    $avatar_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/avatarkp_prev.' . $ext . '?v=' . $timestamp;

    wp_send_json_success([
        'message' => 'Аватарка обновлена',
        'url' => $avatar_url
    ]);
}



add_action('wp_ajax_crm_get_current_icon', 'crm_get_current_icon');

add_action('wp_ajax_crm_save_icon', 'crm_handle_icon_save');

function crm_handle_icon_save()
{
    // Включаем логирование ошибок
    error_log('=== crm_handle_icon_save вызван ===');
    error_log('$_POST: ' . print_r($_POST, true));

    if (!current_user_can('upload_files')) {
        error_log('Ошибка: Доступ запрещен');
        wp_send_json_error('Доступ запрещен');
    }

    $image_id = intval($_POST['image_id']);
    error_log('Получен image_id: ' . $image_id);

    if (empty($image_id)) {
        error_log('Ошибка: image_id пустой');
        wp_send_json_error('Не передан ID изображения');
    }

    $source_path = get_attached_file($image_id);
    error_log('Путь к исходному файлу: ' . $source_path);

    if (!$source_path || !file_exists($source_path)) {
        error_log('Ошибка: Файл не найден по пути: ' . $source_path);
        wp_send_json_error('Файл не найден');
    }

    $upload_dir = wp_upload_dir();
    error_log('Директория загрузок: ' . print_r($upload_dir, true));

    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    error_log('Целевая директория: ' . $target_dir);

    // Проверяем права на запись
    if (!is_writable($upload_dir['basedir'])) {
        error_log('Ошибка: Директория uploads не доступна для записи');
        wp_send_json_error('Нет прав на запись');
    }

    wp_mkdir_p($target_dir);

    if (!is_dir($target_dir)) {
        error_log('Ошибка: Не удалось создать директорию: ' . $target_dir);
        wp_send_json_error('Не удалось создать директорию');
    }

    if (!is_writable($target_dir)) {
        error_log('Ошибка: Директория ' . $target_dir . ' не доступна для записи');
        wp_send_json_error('Нет прав на запись в директорию');
    }

    $ext = pathinfo($source_path, PATHINFO_EXTENSION);
    error_log('Расширение файла: ' . $ext);

    // 🔥 УДАЛЯЕМ СТАРЫЕ zak_prev ФАЙЛЫ
    $old_icons = glob($target_dir . 'zak_prev.*');
    error_log('Найдено старых файлов: ' . count($old_icons));

    foreach ($old_icons as $old_icon) {
        if (is_file($old_icon)) {
            error_log('Удаляем старый файл: ' . $old_icon);
            unlink($old_icon);
        }
    }

    // 🔥 КОПИРУЕМ ФАЙЛ
    $icon_file = $target_dir . 'zak_prev.' . $ext;
    error_log('Путь нового файла: ' . $icon_file);

    // Проверяем, можно ли скопировать
    if (!is_readable($source_path)) {
        error_log('Ошибка: Исходный файл не читается');
        wp_send_json_error('Исходный файл не доступен для чтения');
    }

    if (copy($source_path, $icon_file)) {
        error_log('✅ Файл успешно скопирован');
        error_log('Размер нового файла: ' . filesize($icon_file) . ' байт');

        // 🔥 URL для возврата
        $icon_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/zak_prev.' . $ext . '?v=' . time();
        error_log('URL для возврата: ' . $icon_url);

        wp_send_json_success([
            'message' => 'Иконка закладки сохранена',
            'url' => $icon_url,
            'file' => 'zak_prev.' . $ext
        ]);
    } else {
        error_log('❌ Не удалось скопировать файл');
        error_log('Ошибка копирования: ' . error_get_last()['message']);
        wp_send_json_error('Не удалось скопировать файл');
    }
}

// 🔥 Получаем текущую иконку (если есть)
function crm_get_current_icon()
{
    $upload_dir = wp_upload_dir();
    $icon_path = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/zak_prev.*';

    $icon_files = glob($icon_path);

    if (!empty($icon_files)) {
        $icon_file = $icon_files[0];
        $ext = pathinfo($icon_file, PATHINFO_EXTENSION);
        $icon_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/zak_prev.' . $ext . '?v=' . filemtime($icon_file);

        wp_send_json_success([
            'url' => $icon_url,
            'exists' => true
        ]);
    } else {
        // 🔥 Если нет zak_prev - возвращаем дефолтную
        $default_url = plugin_dir_url(__FILE__) . 'assets/img/zakladka.png';
        wp_send_json_success([
            'url' => $default_url,
            'exists' => false,
            'is_default' => true
        ]);
    }
}


// 🔥 Инициализация сессии для всех пользователей
add_action('init', function () {
    if (!session_id() && !headers_sent()) {
        return;
    }
});

// 🔥 Функция для обновления CSS переменной
function update_zakladka_css_variable($url = '')
{
    if (empty($url)) {
        // Проверяем сессию
        if (isset($_SESSION['crm_icon_preview'])) {
            $url = $_SESSION['crm_icon_preview'];
        } else {
            // Дефолтная иконка
            $url = plugin_dir_url(__FILE__) . 'assets/img/zakladka.png';
        }
    }

    // Добавляем inline CSS с переменной
    echo "<style id='zakladka-preview-style'>
    :root {
        --zakladka-image: url('" . esc_url($url) . "');
    }
    </style>";
}

// 🔥 Добавляем CSS на все страницы администратора
add_action('admin_head', function () {
    update_zakladka_css_variable();
});

// 🔥 Или на фронтенд страницы где нужна закладка
add_action('wp_head', function () {
    if (is_page('ваша-страница-с-закладкой')) { // укажите нужную страницу
        update_zakladka_css_variable();
    }
});


add_action('init', 'delete_zak_prev_on_load');

function delete_zak_prev_on_load()
{
    // Проверяем, что это не AJAX запрос и не админка
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }



    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';

    $old_zak = glob($target_dir . 'zak_prev.*');

    // 🔥 УДАЛЯЕМ ФАЙЛ zak_prev.* БЕЗ ПЕРЕИМЕНОВАНИЯ
    if (!empty($old_zak)) {
        foreach ($old_zak as $zak_file) {
            if (file_exists($zak_file) && is_file($zak_file)) {
                if (unlink($zak_file)) {
                    error_log("🗑️ Файл zak_prev удален: " . basename($zak_file));
                } else {
                    error_log("❌ Не удалось удалить файл: " . basename($zak_file));
                }
            }
        }
    }

}

add_action('wp_ajax_update_kp', 'update_kp_handler');

function update_kp_handler()
{
    if (!is_user_logged_in()) {
        wp_die('Требуется авторизация');
    }



    $background_path = isset($_POST['background_path']) ? sanitize_text_field($_POST['background_path']) : '';
    $logo_path = isset($_POST['logo_path']) ? sanitize_text_field($_POST['logo_path']) : '';
    $avatar_path = isset($_POST['avatar_path']) ? sanitize_text_field($_POST['avatar_path']) : '';
    $zakladka_path = isset($_POST['zakladka_path']) ? sanitize_text_field($_POST['zakladka_path']) : '';
    $glav_color = isset($_POST['glav_color']) ? sanitize_hex_color($_POST['glav_color']) : '';
    $two_color = isset($_POST['two_color']) ? sanitize_hex_color($_POST['two_color']) : '';
    $zakladka_color = isset($_POST['zakladka_color']) ? sanitize_hex_color($_POST['zakladka_color']) : '';

    // ДОБАВЛЯЕМ: 6 НОВЫХ ПАРАМЕТРОВ ДЛЯ ТАБЛИЦЫ-КАЛЬКУЛЯТОРА
    $calc_name_bord = isset($_POST['calc_name_bord']) ? sanitize_hex_color($_POST['calc_name_bord']) : '';
    $kp_calc_name_text = isset($_POST['kp_calc_name_text']) ? sanitize_hex_color($_POST['kp_calc_name_text']) : '';
    $calc_name_sht_bac = isset($_POST['calc_name_sht_bac']) ? sanitize_hex_color($_POST['calc_name_sht_bac']) : '';
    $calc_name_sht_text = isset($_POST['calc_name_sht_text']) ? sanitize_hex_color($_POST['calc_name_sht_text']) : '';
    $calc_name_sht_ysl_bac = isset($_POST['calc_name_sht_ysl_bac']) ? sanitize_hex_color($_POST['calc_name_sht_ysl_bac']) : '';
    $calc_name_sht_ysl_text = isset($_POST['calc_name_sht_ysl_text']) ? sanitize_hex_color($_POST['calc_name_sht_ysl_text']) : '';
    // КОНЕЦ ДОБАВЛЕНИЯ НОВЫХ ПАРАМЕТРОВ

    $results = array();
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    $css_file = $upload_dir['basedir'] . '/crm_files/shablon/assets/css/style_kp.css';

    // ===== 1. ЧИТАЕМ CSS ФАЙЛ (если существует) =====
    $css_content = '';
    if (file_exists($css_file)) {
        $css_content = file_get_contents($css_file);
    }


    // 1. ФОН
    if (!empty($background_path)) {
        $bg_files = glob($target_dir . 'kp_prev.*');
        if (!empty($bg_files)) {
            $bg_file = $bg_files[0];
            $ext = pathinfo($bg_file, PATHINFO_EXTENSION);
            $old_bg = glob($target_dir . 'kp.*');
            foreach ($old_bg as $file)
                unlink($file);
            $new_bg = $target_dir . 'kp.' . $ext;
            if (rename($bg_file, $new_bg)) {
                $results['background_image'] = 'wp-content/uploads/crm_files/shablon/assets/img/kp.' . $ext;
            }
        }
    }

    // 2. ЛОГОТИП
    if (!empty($logo_path)) {
        $logo_files = glob($target_dir . 'logokp_prev.*');
        if (!empty($logo_files)) {
            $logo_file = $logo_files[0];
            $ext = pathinfo($logo_file, PATHINFO_EXTENSION);
            $old_logo = glob($target_dir . 'logokp.*');
            foreach ($old_logo as $file)
                unlink($file);
            $new_logo = $target_dir . 'logokp.' . $ext;
            if (rename($logo_file, $new_logo)) {
                $results['logo'] = 'wp-content/uploads/crm_files/shablon/assets/img/logokp.' . $ext;
            }
        }
    }

    // 3. АВАТАРКА
    if (!empty($avatar_path)) {
        $avatar_files = glob($target_dir . 'avatarkp_prev.*');
        if (!empty($avatar_files)) {
            $avatar_file = $avatar_files[0];
            $ext = pathinfo($avatar_file, PATHINFO_EXTENSION);
            $old_avatar = glob($target_dir . 'avatarkp.*');
            foreach ($old_avatar as $file)
                unlink($file);
            $new_avatar = $target_dir . 'avatarkp.' . $ext;
            if (rename($avatar_file, $new_avatar)) {
                $results['avatar'] = 'wp-content/uploads/crm_files/shablon/assets/img/avatarkp.' . $ext;
            }
        }
    }

    // 🔥 4. ЗАКЛАДКА - ПЕРЕМЕСТИТЕ ЭТОТ КОД СЮДА (сразу после аватарки)
    // 🔥 4. ЗАКЛАДКА - ИСПРАВЛЕННАЯ ЛОГИКА =====
// ✅ ИСПРАВЛЕНО: Удаляем только если передали реальный путь к файлу
// 🔥 ИСПРАВЛЕННЫЙ КОД ДЛЯ ЗАКЛАДКИ (замените блок с 56 строки)
// ===== 4. ЗАКЛАДКА =====
    if (!empty($zakladka_path)) {
        // Получаем абсолютный путь к файлу
        $abs_zakladka_path = ABSPATH . $zakladka_path;

        // Проверяем, что файл существует по абсолютному пути
        if (file_exists($abs_zakladka_path)) {
            $filename = basename($zakladka_path); // zak_prev.png
            $zak_file = $abs_zakladka_path; // Полный путь к файлу

            if (strpos($filename, 'zak_prev') === 0) {
                $ext = pathinfo($zak_file, PATHINFO_EXTENSION);

                // Удаляем старый файл zak.*
                $old_zak = glob($target_dir . 'zak.*');
                foreach ($old_zak as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        error_log("🗑️ Удален старый файл zak: " . basename($file));
                    }
                }

                // Переименовываем zak_prev в zak
                $new_zak = $target_dir . 'zak.' . $ext;

                // Используем полный путь к старому файлу
                if (rename($zak_file, $new_zak)) {
                    error_log("✅ Переименован файл: " . basename($zak_file) . " → zak." . $ext);
                    $results['zakladka_updated'] = true;
                    $zak_updated = true;

                    // Удаляем правило с цветом закладки из CSS
                    $css_content = preg_replace('/\.zakladka::before\s*\{[^}]*\}/', '', $css_content);
                    $css_content = preg_replace('/\.zakladka_red::before\s*\{[^}]*\}/', '', $css_content);
                } else {
                    error_log("❌ Ошибка переименования файла: " . $zak_file . " → " . $new_zak);
                    error_log("Ошибка: " . error_get_last()['message']);
                }
            } else {
                error_log("ℹ️ Файл закладки не начинается с 'zak_prev': " . $filename);
            }
        } else {
            error_log("⚠️ Файл не найден по пути: " . $abs_zakladka_path);
            error_log("Zakladka path получен: " . $zakladka_path);
            error_log("Target dir: " . $target_dir);
        }
    }

    // 5.2. Если есть ЦВЕТ закладки (и он не белый)
    else if (!empty($zakladka_color) && $zakladka_color !== '#ffffff' && $zakladka_color !== '#fff') {
        error_log("🔥 Устанавливаем цвет закладки: " . $zakladka_color);

        // УДАЛЯЕМ ФАЙЛ ZAK.* (если он есть)
        $old_zak = glob($target_dir . 'zak.*');
        foreach ($old_zak as $file) {
            if (is_file($file)) {
                unlink($file);
                error_log("🗑️ Удален файл zak: " . basename($file));
            }
        }

        // Удаляем ВСЕ правила закладок
        $css_content = preg_replace('/\.zakladka::before\s*\{[^}]*\}/', '', $css_content);
        $css_content = preg_replace('/\.zakladka_red::before\s*\{[^}]*\}/', '', $css_content);

        // Добавляем новое правило с ЦВЕТОМ закладки
        $zak_color_css = "\n\n.zakladka::before {\n";
        $zak_color_css .= "    content: '';\n";
        $zak_color_css .= "    background-color: {$zakladka_color} !important;\n";
        $zak_color_css .= "    background-image: none !important;\n";
        $zak_color_css .= "    border-radius: 50% !important;\n";
        $zak_color_css .= "}\n";

        $css_content .= $zak_color_css;
        $results['zakladka_color'] = $zakladka_color;
        $results['zak_file_deleted'] = true;
        $zak_updated = true;
    }
    // 5.3. Если цвет закладки белый (или прозрачный) - восстанавливаем дефолт
    else if (!empty($zakladka_color) && ($zakladka_color === '#ffffff' || $zakladka_color === '#fff' || $zakladka_color === 'transparent')) {
        $css_content = preg_replace('/\.zakladka::before\s*\{[^}]*\}/', '', $css_content);
        $results['zakladka_color_reset'] = true;
        $zak_updated = true;
    }




    // 6. ОСНОВНОЙ ЦВЕТ ТЕКСТА (надёжная перезапись)
    $zak_files = glob($target_dir . 'zak.*');
    if (!empty($zak_files)) {
        $zak_file = $zak_files[0];
        $ext = pathinfo($zak_file, PATHINFO_EXTENSION);
        $absolute_url = home_url('/wp-content/uploads/crm_files/shablon/assets/img/zak.' . $ext);
        $absolute_url_with_version = $absolute_url . '?v=' . time();

        // Удаляем ВСЕ правила закладок
        $css_content = preg_replace('/\.zakladka::before\s*\{[^}]*\}/', '', $css_content);
        $css_content = preg_replace('/\.zakladka_red::before\s*\{[^}]*\}/', '', $css_content);

        // Добавляем правило с картинкой
        $zak_css = "\n\n.zakladka_red::before {\n";
        $zak_css .= "    content: '';\n";
        $zak_css .= "    background-image: url('" . esc_url($absolute_url_with_version) . "') !important;\n";
        $zak_css .= "}\n";

        $css_content .= $zak_css;
        $results['zakladka_image'] = true;
        $zak_updated = true;
    }

    // ===== 7. ОБРАБОТКА ЦВЕТОВ ТЕКСТА (ОТДЕЛЬНО ОТ БЛОКА glav_color) =====
    // 7.1. Основной цвет текста (glav_color) - БЕЗ ПЕРЕЗАПИСИ CSS_CONTENT!
    if (!empty($glav_color)) {
        // Удаляем все старые правила .glav_color
        $css_content = preg_replace('/\.glav_color\s*\{[^}]*\}/', '', $css_content);

        // Добавляем новое правило
        $new_css = "\n.glav_color {\n    color: {$glav_color} !important;\n}";
        $css_content .= $new_css;

        $results['glav_color'] = $glav_color;
    }

    // 7.2. Второй цвет текста (two_color)
    if (!empty($two_color)) {
        // Удаляем все старые правила .two_color
        $css_content = preg_replace('/\.two_color\s*\{[^}]*\}/', '', $css_content);

        // Добавляем новое правило
        $new_css = "\n.two_color {\n    color: {$two_color} !important;\n}";
        $css_content .= $new_css;

        $results['two_color'] = $two_color;
    }

    // ДОБАВЛЯЕМ: ОБРАБОТКА 6 НОВЫХ ЦВЕТОВ ДЛЯ ТАБЛИЦЫ-КАЛЬКУЛЯТОРА
// 7.3. Граница заголовков таблицы (calc_name_bord)
    if (!empty($calc_name_bord)) {
        // Удаляем старые правила для table_cont_red границы
        $css_content = preg_replace('/\.table_cont_red\s*\{[^}]*border[^}]*\}/', '', $css_content);

        // Добавляем новое правило для границы
        $new_css = "\n.table_cont_red {\n    border: 1px solid {$calc_name_bord} !important;\n}";
        $css_content .= $new_css;

        $results['calc_name_bord'] = $calc_name_bord;
    }

    // 7.4. Текст заголовков таблицы (kp_calc_name_text)
    if (!empty($kp_calc_name_text)) {
        // Удаляем старые правила для table_cont_red цвета текста
        $css_content = preg_replace('/\.table_cont_red\s*\{[^}]*color[^}]*\}/', '', $css_content);

        // Добавляем новое правило для цвета текста
        $new_css = "\n.table_cont_red {\n    color: {$kp_calc_name_text} !important;\n}";
        $css_content .= $new_css;

        $results['kp_calc_name_text'] = $kp_calc_name_text;
    }

    // 7.5. Фон "штуки и итоги" (calc_name_sht_bac)
    if (!empty($calc_name_sht_bac)) {
        // Удаляем старые правила для shtit_red фона
        $css_content = preg_replace('/\.shtit_red\s*\{[^}]*background[^}]*\}/', '', $css_content);

        // Добавляем новое правило для фона
        $new_css = "\n.shtit_red {\n    background: {$calc_name_sht_bac} !important;\n}";
        $css_content .= $new_css;

        $results['calc_name_sht_bac'] = $calc_name_sht_bac;
    }

    // 7.6. Текст "штуки и итоги" (calc_name_sht_text)
    if (!empty($calc_name_sht_text)) {
        // Удаляем старые правила для .shtit_red .table_info
        $css_content = preg_replace('/\.shtit_red \.table_info\s*\{[^}]*\}/', '', $css_content);

        // Добавляем новое правило для текста
        $new_css = "\n.shtit_red .table_info {\n    color: {$calc_name_sht_text} !important;\n}";
        $css_content .= $new_css;

        $results['calc_name_sht_text'] = $calc_name_sht_text;
    }

    // 7.7. Фон "услуги и ндс" (calc_name_sht_ysl_bac)
    if (!empty($calc_name_sht_ysl_bac)) {
        // Удаляем старые правила для yslnds_red фона
        $css_content = preg_replace('/\.yslnds_red\s*\{[^}]*background[^}]*\}/', '', $css_content);

        // Добавляем новое правило для фона
        $new_css = "\n.yslnds_red {\n    background: {$calc_name_sht_ysl_bac} !important;\n}";
        $css_content .= $new_css;

        $results['calc_name_sht_ysl_bac'] = $calc_name_sht_ysl_bac;
    }

    // 7.8. Текст "услуги и ндс" (calc_name_sht_ysl_text)
    if (!empty($calc_name_sht_ysl_text)) {
        // Удаляем старые правила для .yslnds_red .table_info
        $css_content = preg_replace('/\.yslnds_red \.table_info\s*\{[^}]*\}/', '', $css_content);

        // Добавляем новое правило для текста
        $new_css = "\n.yslnds_red .table_info {\n    color: {$calc_name_sht_ysl_text} !important;\n}";
        $css_content .= $new_css;

        $results['calc_name_sht_ysl_text'] = $calc_name_sht_ysl_text;
    }
    // КОНЕЦ ДОБАВЛЕНИЯ ОБРАБОТКИ НОВЫХ ЦВЕТОВ

    // ===== 8. ВАЖНО: СОХРАНЕНИЕ CSS ФАЙЛА ПРИ ЛЮБЫХ ИЗМЕНЕНИЯХ =====
    // Чистим CSS
    $css_content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $css_content);
    $css_content = trim($css_content);

    // 🔥 СОХРАНЯЕМ CSS ЕСЛИ БЫЛИ ИЗМЕНЕНИЯ:
    // - цвет закладки
    // - файл закладки  
    // - glav_color
    // - two_color
    if (
        !empty($zakladka_color) || !empty($zak_files) || !empty($glav_color) || !empty($two_color) ||
        !empty($calc_name_bord) || !empty($kp_calc_name_text) || !empty($calc_name_sht_bac) ||
        !empty($calc_name_sht_text) || !empty($calc_name_sht_ysl_bac) || !empty($calc_name_sht_ysl_text) ||
        $zak_updated
    ) {
        if (file_put_contents($css_file, $css_content) !== false) {
            $results['css_updated'] = true;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_kp';
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE id = 1");

    // ДОБАВЛЯЕМ В БД ТОЛЬКО ФОН, ЛОГО И АВАТАР
    if (!empty($results)) {
        // Создаем массив ТОЛЬКО для изображений
        $db_data = [];

        // Берем только фон, лого и аватар из $results
        $image_fields = ['background_image', 'logo', 'avatar'];
        foreach ($image_fields as $field) {
            if (isset($results[$field])) {
                $db_data[$field] = $results[$field];
            }
        }

        // 🔥 НЕ добавляем цвета в БД! Они уже в CSS файле

        if (!empty($db_data)) {
            if ($exists > 0) {
                error_log("📦 Данные для БД (только изображения): " . print_r($db_data, true));
                $wpdb->update($table_name, $db_data, array('id' => 1));
                error_log("💾 Выполнен запрос UPDATE в БД");
            } else {
                $db_data['id'] = 1;
                $wpdb->insert($table_name, $db_data);
            }
        } else {
            error_log("ℹ️ Нет данных изображений для сохранения в БД");
        }
    }


    clearstatcache();

    wp_send_json_success([
        'message' => 'Изображения и цвет сохранены',
        'results' => $results,
        'timestamp' => time(),
        'css_version' => $_POST['css_version'] // Возвращаем ту же версию
    ]);
}
// ===== ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ДЛЯ ОБРАБОТКИ ОДНОГО ИЗОБРАЖЕНИЯ =====

function process_image($image_type, $file_prefix, $db_field, $image_path)
{
    $result = array('success' => false, 'path' => '');

    // Если путь пустой - сразу выходим
    if (empty($image_path)) {
        return $result;
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';

    // ===== 1. ИЩЕМ ФАЙЛ С _prev В ИМЕНИ =====
    // Ищем файл который нужно переименовать (например: kp_prev.jpg, logokp_prev.jpg, avatarkp_prev.jpg)
    $prev_files = glob($target_dir . $file_prefix . '_prev.*');

    if (empty($prev_files)) {
        // Если нет файла с _prev, проверяем путь из запроса
        $filename = basename($image_path);

        // Проверяем, может файл уже в папке
        if (file_exists($target_dir . $filename)) {
            $prev_files = array($target_dir . $filename);
        } else {
            // Проверяем полный путь
            $full_path = ABSPATH . ltrim($image_path, '/');
            if (file_exists($full_path)) {
                $prev_files = array($full_path);
            }
        }
    }

    // ===== 2. ЕСЛИ НАШЛИ ФАЙЛ - ПЕРЕИМЕНОВЫВАЕМ =====
    if (!empty($prev_files)) {
        $current_file = $prev_files[0];
        $ext = pathinfo($current_file, PATHINFO_EXTENSION);
        $new_filename = $file_prefix . '.' . $ext;
        $new_file = $target_dir . $new_filename;

        error_log("Найден файл для обработки: " . basename($current_file));
        error_log("Будет переименован в: " . $new_filename);

        // ===== 3. УДАЛЯЕМ СТАРУЮ ВЕРСИЮ (ЕСЛИ ЕСТЬ) =====
        // Удаляем только файл с тем же именем (kp.jpg, logokp.jpg, avatarkp.jpg)
        if (file_exists($new_file)) {
            unlink($new_file);
            error_log("Удален старый файл: " . $new_filename);
        }

        // ===== 4. ПЕРЕИМЕНОВЫВАЕМ =====
        if (rename($current_file, $new_file)) {
            $result['success'] = true;
            $result['path'] = 'wp-content/uploads/crm_files/shablon/assets/img/' . $new_filename;
            error_log("Успешно переименован: " . basename($current_file) . " -> " . $new_filename);
        } else {
            error_log("Ошибка переименования файла");
        }
    } else {
        error_log("Не найден файл для префикса: " . $file_prefix);
    }

    return $result;
}


// ИЛИ для фронтенда
add_action('wp_enqueue_scripts', function () {
    if (is_user_logged_in()) {
        wp_enqueue_media();
    }
});


// 🔧 AJAX ОБРАБОТЧИКИ ДЛЯ CRM

// 1. Сохранение всех почт
// 🔧 СОХРАНЕНИЕ ФОРМЫ EMAIL (AJAX версия)
add_action('wp_ajax_save_all_emails', 'handle_save_all_emails_ajax');
add_action('wp_ajax_nopriv_save_all_emails', 'handle_save_all_emails_ajax');

function handle_save_all_emails_ajax()
{
    global $wpdb;

    // Проверяем, что это AJAX запрос
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        wp_send_json_error('Invalid request');
        return;
    }

    $table_name = $wpdb->prefix . 'crm_email_accounts';

    // Создаем таблицу если её нет
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        host VARCHAR(100),
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // 🔧 ПОЛУЧАЕМ ТЕКУЩЕЕ СОСТОЯНИЕ ACTIVE ИЗ БАЗЫ
    $active_status = $wpdb->get_var("SELECT active FROM $table_name LIMIT 1");
    if ($active_status === null) {
        $active_status = 1; // Значение по умолчанию если записей нет
    }

    // 🔧 ИСПРАВЛЕНИЕ: БЕРЕМ ХОСТ ИЗ БАЗЫ
    $main_host = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1") ?: '';

    // Сохраняем текущий хост до очистки таблицы
    $current_host_before_truncate = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1") ?: '';
    $main_host = $current_host_before_truncate;

    // Очищаем таблицу перед сохранением новых данных (как было раньше)
    $wpdb->query("TRUNCATE TABLE $table_name");

    $saved_count = 0;

    // Проверяем наличие данных
    if (isset($_POST['email']) && is_array($_POST['email'])) {
        // Сохраняем каждый email и пароль в базу данных
        foreach ($_POST['email'] as $index => $email) {
            $password = $_POST['password'][$index] ?? '';

            if (!empty($email) && !empty($password)) {
                // 🔧 ИСПРАВЛЕНИЕ: Если active = 1, используем хост из верхнего поля
                if ($active_status == 1) {
                    $individual_host = $main_host;
                } else {
                    // Если active = 0, используем индивидуальный хост из формы
                    $individual_host = !empty($_POST['host'][$index]) ? sanitize_text_field($_POST['host'][$index]) : '';
                }

                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'email' => sanitize_email($email),
                        'password' => sanitize_text_field($password),
                        'host' => $individual_host,
                        'active' => $active_status
                    ),
                    array('%s', '%s', '%s', '%d')
                );

                if ($result) {
                    $saved_count++;
                }

                error_log("🔍 CRM: Saved email $email with active=$active_status, host=$individual_host");
            }
        }
    }

    if ($saved_count > 0) {
        wp_send_json_success("Почта и пароль успешно добавлены/изменены! Сохранено записей: $saved_count");
    } else {
        wp_send_json_error("Ошибка: не удалось сохранить данные");
    }
}

// 2. Удаление почты
add_action('wp_ajax_delete_email_account', 'handle_delete_email_ajax');
function handle_delete_email_ajax()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_email_accounts';

    $email = sanitize_email($_POST['email'] ?? '');

    if (!is_email($email)) {
        wp_send_json_error('Некорректный email');
        return;
    }

    // Проверяем сколько почт осталось
    $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($total_emails <= 1) {
        wp_send_json_error('Нельзя удалить последнюю почту!');
        return;
    }

    $result = $wpdb->delete(
        $table_name,
        ['email' => $email],
        ['%s']
    );

    if ($result) {
        wp_send_json_success("Почта $email удалена");
    } else {
        wp_send_json_error('Ошибка удаления');
    }
}

// 3. Обновление статуса active (этот обработчик уже должен быть)
add_action('wp_ajax_update_active_status', 'handle_update_active_status_ajax');
function handle_update_active_status_ajax()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_email_accounts';

    $active_status = intval($_POST['active'] ?? 1);

    // Если включен общий хост
    if ($active_status == 1) {
        // 1. Получаем хост из первой записи
        $first_host = $wpdb->get_var(
            "SELECT host FROM $table_name ORDER BY id LIMIT 1"
        );

        if ($first_host) {
            // 2. Копируем его во все записи
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_name SET host = %s, active = %d",
                    $first_host,
                    $active_status
                )
            );
        }
    } else {
        // Если выключен - только обновляем active
        $result = $wpdb->query(
            $wpdb->prepare("UPDATE $table_name SET active = %d", $active_status)
        );
    }

    wp_send_json_success(['message' => 'Настройка сохранена']);
}


add_action('wp_ajax_update_active_status', 'update_active_status_handler');
function update_active_status_handler()
{
    global $wpdb;

    $active_status = isset($_POST['active']) ? intval($_POST['active']) : 0;
    $table_name = $wpdb->prefix . 'crm_email_accounts';

    // Если включаем режим "один хост у всех почт"
    if ($active_status == 1) {
        // Получаем хост первой почты
        $first_account = $wpdb->get_row("SELECT host FROM $table_name ORDER BY id LIMIT 1");
        $common_host = $first_account ? $first_account->host : '';

        // Обновляем хост у всех почт
        $result = $wpdb->query(
            $wpdb->prepare("UPDATE $table_name SET active = %d, host = %s", $active_status, $common_host)
        );
    } else {
        // Просто обновляем active
        $result = $wpdb->query(
            $wpdb->prepare("UPDATE $table_name SET active = %d", $active_status)
        );
    }

    if ($result !== false) {
        wp_send_json_success(['message' => 'Настройки сохранены']);
    } else {
        wp_send_json_error(['message' => 'Ошибка сохранения']);
    }
}

add_action('wp_ajax_update_shablon_active', 'update_shablon_active_handler');
function update_shablon_active_handler()
{
    // Проверка прав
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No permissions');
    }

    $active = isset($_POST['active']) ? intval($_POST['active']) : 0;

    // 🔥 ДОБАВИМ ОТЛАДКУ
    error_log('🔧 CRM: update_shablon_active called with active=' . $active);

    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_mes';

    // Обновляем активность
    $existing = $wpdb->get_var("SELECT id FROM $table_name LIMIT 1");
    error_log('🔧 CRM: Existing record ID: ' . $existing);

    if ($existing) {
        error_log('🔧 CRM: Updating record ID ' . $existing . ' to active=' . $active);
        $result = $wpdb->update(
            $table_name,
            array('active' => $active),
            array('id' => $existing),
            array('%d'),
            array('%d')
        );
    } else {
        error_log('🔧 CRM: Inserting new record with active=' . $active);
        $result = $wpdb->insert(
            $table_name,
            array('active' => $active),
            array('%d')
        );
    }

    error_log('🔧 CRM: Database result: ' . ($result !== false ? 'success' : 'failure'));

    if ($result !== false) {
        wp_send_json_success('Status updated');
    } else {
        error_log('🔧 CRM: Database error: ' . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
}

add_option('crm_messages_history_enabled', 'true'); // По умолчанию включено


add_action('wp_ajax_debug_messages_table', 'handle_debug_messages_table');
function handle_debug_messages_table()
{
    global $wpdb;

    $dialog_id = intval($_POST['dialog_id']);
    $table_name = $wpdb->prefix . 'crm_messages';

    // Проверяем существование таблицы
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if (!$table_exists) {
        wp_send_json_error('Таблица не существует');
        return;
    }

    // Получаем информацию о столбцах
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $column_names = array();
    foreach ($columns as $column) {
        $column_names[] = $column->Field;
    }

    // Считаем общее количество сообщений
    $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Получаем сообщения для диалога
    $dialog_messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE dialog_id = %d ORDER BY sent_at DESC",
        $dialog_id
    ));

    wp_send_json_success(array(
        'table_name' => $table_name,
        'table_exists' => $table_exists,
        'columns' => $column_names,
        'total_messages' => $total_messages,
        'dialog_messages' => $dialog_messages,
        'dialog_messages_count' => count($dialog_messages)
    ));
}

// Проверка всех таблиц CRM
add_action('wp_ajax_debug_crm_tables', 'handle_debug_crm_tables');
function handle_debug_crm_tables()
{
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'crm_leads',
        $wpdb->prefix . 'crm_dialogs',
        $wpdb->prefix . 'crm_messages',
        $wpdb->prefix . 'crm_files',
        $wpdb->prefix . 'crm_message_files',
        $wpdb->prefix . 'crm_emails'
    );

    $results = array();

    foreach ($tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
        } else {
            $count = 0;
            $columns = array();
        }

        $results[$table] = array(
            'exists' => $exists,
            'count' => $count,
            'columns' => $columns
        );
    }

    wp_send_json_success($results);
}



// ✅ ФУНКЦИЯ ДЛЯ ПРОВЕРКИ ТАБЛИЦЫ ФАЙЛОВ
add_action('wp_ajax_check_files_table', 'handle_check_files_table');
function handle_check_files_table()
{
    global $wpdb;

    $table_message_files = $wpdb->prefix . 'crm_message_files';

    // Проверяем существование таблицы
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_message_files'") === $table_message_files;

    if (!$table_exists) {
        wp_send_json_error('Таблица crm_message_files не существует');
        return;
    }

    // Получаем информацию о столбцах
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_message_files");
    $column_names = array();
    foreach ($columns as $column) {
        $column_names[] = $column->Field;
    }

    // Считаем общее количество файлов
    $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $table_message_files");

    // Получаем несколько последних файлов для примера
    $recent_files = $wpdb->get_results("
        SELECT mf.*, m.dialog_id, m.message 
        FROM $table_message_files mf
        LEFT JOIN {$wpdb->prefix}crm_messages m ON mf.message_id = m.id
        ORDER BY mf.attached_at DESC 
        LIMIT 10
    ");

    wp_send_json_success(array(
        'table_name' => $table_message_files,
        'table_exists' => $table_exists,
        'columns' => $column_names,
        'total_files' => $total_files,
        'recent_files' => $recent_files
    ));
}

add_action('wp_ajax_diagnose_email_system', 'handle_diagnose_email_system');
add_action('wp_ajax_nopriv_diagnose_email_system', 'handle_diagnose_email_system');
function handle_diagnose_email_system()
{


    $current_email = get_last_cf7_to_email_enhanced();
    $transient_email = get_transient('cf7_last_to_email');
    $option_email = get_option('cf7_last_used_email');

    // Получаем все формы CF7
    $forms = WPCF7_ContactForm::find();
    $forms_info = [];

    foreach ($forms as $form) {
        $mail_settings = $form->prop('mail');
        $to_field = $mail_settings['recipient'] ?? 'Не указан';

        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $to_field, $matches);
        $extracted_email = !empty($matches[0]) ? $matches[0][0] : 'Не найден';

        $forms_info[] = [
            'title' => $form->title(),
            'to_field' => $to_field,
            'extracted_email' => $extracted_email
        ];
    }

    wp_send_json_success([
        'current_sender' => $current_email,
        'transient_email' => $transient_email,
        'option_email' => $option_email,
        'admin_email' => get_option('admin_email'),
        'cf7_forms' => $forms_info,
        'used_in_function' => 'get_last_cf7_to_email_enhanced()'
    ]);
}
function get_cf7_to_email_enhanced($contact_form = null)
{
    $to_email = '';

    if ($contact_form) {
        $mail = $contact_form->prop('mail');

        // Получаем поле "To" из настроек почты CF7
        if (isset($mail['recipient']) && !empty($mail['recipient'])) {
            $to_field = $mail['recipient'];

            // Извлекаем email из поля "To" (может быть несколько через запятую)
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $to_field, $matches);

            if (!empty($matches[0])) {
                // Берем первый email из списка
                $to_email = trim($matches[0][0]);
                error_log('CRM: Extracted TO email: ' . $to_email . ' from: ' . $to_field);
            } else {
                // Альтернативный метод: ищем email в квадратных скобках (shortcode)
                if (preg_match('/\[.*?\]/', $to_field, $shortcode_matches)) {
                    $shortcode = $shortcode_matches[0];
                    error_log('CRM: Found shortcode in TO field: ' . $shortcode);

                    // Если это shortcode, пробуем получить значение из submission
                    $submission = WPCF7_Submission::get_instance();
                    if ($submission) {
                        $data = $submission->get_posted_data();
                        $field_name = trim($shortcode, '[]');
                        if (isset($data[$field_name]) && is_email($data[$field_name])) {
                            $to_email = sanitize_email($data[$field_name]);
                            error_log('CRM: Got email from shortcode field: ' . $to_email);
                        }
                    }
                }
            }
        }
    }

    return $to_email;
}

// Обновляем функцию сохранения email
add_action('wpcf7_mail_sent', 'save_cf7_to_email_enhanced');
function save_cf7_to_email_enhanced($contact_form)
{
    $to_email = get_cf7_to_email_enhanced($contact_form);

    if ($to_email && is_email($to_email)) {
        // Сохраняем в transient
        set_transient('cf7_last_to_email', $to_email, 3600); // Храним 1 час
        error_log('CRM: ✅ Saved CF7 TO email: ' . $to_email);

        // Также сохраняем в option для надежности
        update_option('cf7_last_used_email', $to_email);
    } else {
        error_log('CRM: WARNING: Could not extract valid TO email from CF7 form');

        // Логируем настройки формы для отладки
        $mail = $contact_form->prop('mail');
        error_log('CRM: CF7 mail settings - To: ' . ($mail['recipient'] ?? 'NOT SET'));
    }
}

// Улучшенная функция получения последнего email
function get_last_cf7_to_email_enhanced()
{
    // Пробуем получить из transient
    $last_email = get_transient('cf7_last_to_email');

    // Если нет в transient, пробуем из option
    if (!$last_email || !is_email($last_email)) {
        $last_email = get_option('cf7_last_used_email');
    }

    // Если все еще нет, пробуем получить из последней формы CF7
    if (!$last_email || !is_email($last_email)) {
        $forms = WPCF7_ContactForm::find();
        if (!empty($forms)) {
            foreach ($forms as $form) {
                $last_email = get_cf7_to_email_enhanced($form);
                if ($last_email && is_email($last_email)) {
                    break;
                }
            }
        }
    }

    // Фолбэк на административный email
    if (!$last_email || !is_email($last_email)) {
        $last_email = get_option('admin_email');
        error_log('CRM: Using admin email as final fallback: ' . $last_email);
    }

    error_log('CRM: Final TO email for sending: ' . $last_email);

    return $last_email;
}
function get_cf7_to_email($contact_form = null)
{
    $to_email = '';

    if ($contact_form) {
        $mail = $contact_form->prop('mail');

        // Получаем поле "To" из настроек почты CF7
        if (isset($mail['recipient']) && !empty($mail['recipient'])) {
            $to_field = $mail['recipient'];

            // Извлекаем email из поля "To" (может быть несколько через запятую)
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $to_field, $matches);

            if (!empty($matches[0])) {
                // Берем первый email из списка
                $to_email = trim($matches[0][0]);
                error_log('CRM: Extracted TO email: ' . $to_email . ' from: ' . $to_field);
            }
        }
    }

    return $to_email;
}

// Сохраняем email из поля "To" при отправке формы CF7
add_action('wpcf7_mail_sent', 'save_cf7_to_email');
function save_cf7_to_email($contact_form)
{
    $to_email = get_cf7_to_email($contact_form);

    if ($to_email) {
        // Сохраняем в transient
        set_transient('cf7_last_to_email', $to_email, 3600); // Храним 1 час
        error_log('CRM: Saved CF7 TO email: ' . $to_email);
    } else {
        error_log('CRM: WARNING: Could not extract TO email from CF7 form');

        // Логируем настройки формы для отладки
        $mail = $contact_form->prop('mail');
        error_log('CRM: CF7 mail settings - To: ' . ($mail['recipient'] ?? 'NOT SET'));
    }
}

// Функция для получения последнего email из поля "To" в CF7
function get_last_cf7_to_email()
{
    $last_email = get_transient('cf7_last_to_email');

    error_log('CRM Debug: Last CF7 TO email from transient: ' . ($last_email ?: 'NOT FOUND'));

    if (!$last_email) {
        // Если нет в transient, пробуем получить из последней формы
        $forms = WPCF7_ContactForm::find();
        error_log('CRM Debug: Found ' . count($forms) . ' CF7 forms');

        if (!empty($forms)) {
            $last_form = $forms[0];
            $last_email = get_cf7_to_email($last_form);
            error_log('CRM Debug: TO email from first form: ' . ($last_email ?: 'NOT FOUND'));
        }
    }

    $final_email = $last_email ?: get_option('admin_email');
    error_log('CRM Debug: Final TO email for sending: ' . $final_email);

    return $final_email;
}
add_action('wp_enqueue_scripts', 'register_crm_scripts');
function register_crm_scripts()
{
    // Подключаем скрипт CRM только на странице CRM

    if (is_page_template('crm.php')) {
        wp_enqueue_script('crm', plugin_dir_path(__FILE__) . 'assets/js/crm.js', array('jquery'), '1.0.0', true);

        // Передаем переменные в скрипт
        wp_localize_script('crm', 'crm_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('crm_nonce')
        ));
    }
}

// Создание всех таблиц CRM
function create_all_crm_tables()
{
    global $wpdb;

    // Названия таблиц
    $table_leads = $wpdb->prefix . 'crm_leads';
    $table_dialogs = $wpdb->prefix . 'crm_dialogs';
    $table_messages = $wpdb->prefix . 'crm_messages';
    $table_files = $wpdb->prefix . 'crm_files';
    $table_message_files = $wpdb->prefix . 'crm_message_files';
    $table_doc = $wpdb->prefix . 'crm_doc';
    $table_emails = $wpdb->prefix . 'crm_emails';

    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // 1. Таблица заявок
    $sql_leads = "CREATE TABLE IF NOT EXISTS $table_leads (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name_zayv varchar(100) NOT NULL DEFAULT '',
    name varchar(100) NOT NULL,
    phone varchar(20) NOT NULL,
    email varchar(100) DEFAULT '',
    page_url varchar(255) DEFAULT '',
    status varchar(20) DEFAULT 'xolod',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

    // global $EMAIL_CONFIG;
    // $default_sender = array_keys($EMAIL_CONFIG['accounts'])[0];

    $sql_dialogs = "CREATE TABLE IF NOT EXISTS $table_dialogs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    lead_id mediumint(9) NOT NULL,
    name varchar(255) NOT NULL,
    email varchar(255) DEFAULT '',
    sender_email varchar(255) DEFAULT '$default_sender', 
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY lead_id (lead_id)
) $charset_collate;";

    // 3. Таблица сообщений (ОБНОВЛЕННАЯ СТРУКТУРА)
    $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    dialog_id mediumint(9) NOT NULL,
    message text NOT NULL,
    sender_email varchar(255) NOT NULL,
    subject varchar(255) DEFAULT '',
    email varchar(100) NOT NULL,
    direction enum('incoming','outgoing') DEFAULT 'outgoing',
    sent_at datetime DEFAULT CURRENT_TIMESTAMP,
    message_hash varchar(32) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    attachments text DEFAULT NULL,
    PRIMARY KEY (id),
    KEY dialog_id (dialog_id),
    KEY direction (direction),
    UNIQUE KEY message_hash (message_hash)
) $charset_collate;";

    // 4. Таблица для файлов
    $sql_files = "CREATE TABLE IF NOT EXISTS $table_files (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        dialog_id mediumint(9) NOT NULL,
        file_name varchar(255) NOT NULL,
        file_path varchar(500) NOT NULL,
        file_url varchar(500) NOT NULL,
        
        pdf boolean DEFAULT FALSE,
        jpg boolean DEFAULT FALSE,
        html boolean DEFAULT FALSE,
        
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // 5. Таблица для связи сообщений и файлов
    $sql_message_files = "CREATE TABLE IF NOT EXISTS $table_message_files (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    message_id mediumint(9) NOT NULL,
    file_url varchar(500) NOT NULL,
    file_name varchar(255) NOT NULL,
    file_type varchar(50) DEFAULT '',          
    file_size int(11) DEFAULT 0,                
    direction enum('incoming','outgoing') DEFAULT 'outgoing', 
    attached_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY message_id (message_id),                
    KEY direction (direction)                  
) $charset_collate;";


    $sql_doc = "CREATE TABLE IF NOT EXISTS $table_doc(
    id mediumint(9) NOT NULL AUTO_INCREMENT,
     lead_id mediumint(9) NOT NULL,
     recipient varchar(255) NOT NULL,
     chet varchar(255) NOT NULL,
     bankrec varchar(255) NOT NULL,
     bik varchar(255) NOT NULL,
     korchet varchar(255) NOT NULL,
     inn varchar(255) NOT NULL,
     kpp varchar(255) NOT NULL,
     okpo varchar(255) NOT NULL,
     ogrn varchar(255) NOT NULL,
     swift varchar(255) NOT NULL,
     addrbank varchar(255) NOT NULL,
     addroffice varchar(255) NOT NULL,
    PRIMARY KEY (id),
    KEY lead_id (lead_id)
) $charset_collate;";

    // таблиц нескольких почт для диалогов
    $sql_emails = "CREATE TABLE IF NOT EXISTS $table_emails(
    id INT AUTO_INCREMENT PRIMARY KEY,  
    dialog_id INT,
    email VARCHAR(255),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    INDEX dialog_id_idx (dialog_id)
)$charset_collate;";





    // Создаем все таблицы
    dbDelta($sql_leads);
    dbDelta($sql_dialogs);
    dbDelta($sql_messages);
    dbDelta($sql_files);
    dbDelta($sql_message_files);
    dbDelta($sql_doc);
    dbDelta($sql_emails);


    // Проверяем ошибки
    if ($wpdb->last_error) {
        error_log('Ошибка создания таблиц CRM: ' . $wpdb->last_error);
        return false;
    }

    error_log('CRM Tables created successfully');
    return true;
}
add_action('after_setup_theme', 'create_all_crm_tables');

// Функция для проверки и добавления отсутствующих столбцов
function check_and_fix_crm_tables()
{
    global $wpdb;

    // Проверяем наличие столбца name_zayv в таблице crm_leads
    $leads_table = $wpdb->prefix . 'crm_leads';
    $leads_columns = $wpdb->get_results("SHOW COLUMNS FROM $leads_table");
    $leads_has_name_zayv = false;

    foreach ($leads_columns as $column) {
        if ($column->Field == 'name_zayv') {
            $leads_has_name_zayv = true;
            break;
        }
    }

    if (!$leads_has_name_zayv) {
        $wpdb->query("ALTER TABLE $leads_table ADD name_zayv varchar(100) NOT NULL DEFAULT '' AFTER id");
        error_log('CRM: Added name_zayv column to crm_leads table');
    }

    // Проверяем наличие столбца email в таблице crm_leads
    $leads_has_email = false;
    foreach ($leads_columns as $column) {
        if ($column->Field == 'email') {
            $leads_has_email = true;
            break;
        }
    }

    if (!$leads_has_email) {
        $wpdb->query("ALTER TABLE $leads_table ADD email varchar(100) DEFAULT '' AFTER phone");
        error_log('CRM: Added email column to crm_leads table');
    }

    // Проверяем наличие столбца message в таблице crm_messages
    $messages_table = $wpdb->prefix . 'crm_messages';
    $messages_columns = $wpdb->get_results("SHOW COLUMNS FROM $messages_table");
    $messages_has_message = false;

    foreach ($messages_columns as $column) {
        if ($column->Field == 'message') {
            $messages_has_message = true;
            break;
        }
    }

    if (!$messages_has_message) {
        $wpdb->query("ALTER TABLE $messages_table ADD message text NOT NULL AFTER dialog_id");
        error_log('CRM: Added message column to crm_messages table');
    }

    // ДОБАВЛЯЕМ ПРОВЕРКУ СТОЛБЦА EMAIL В ТАБЛИЦЕ DIALOGS
    $dialogs_table = $wpdb->prefix . 'crm_dialogs';
    $dialogs_columns = $wpdb->get_results("SHOW COLUMNS FROM $dialogs_table");
    $dialogs_has_email = false;

    foreach ($dialogs_columns as $column) {
        if ($column->Field == 'email') {
            $dialogs_has_email = true;
            break;
        }
    }

    if (!$dialogs_has_email) {
        $wpdb->query("ALTER TABLE $dialogs_table ADD email varchar(255) DEFAULT '' AFTER name");
        error_log('CRM: Added email column to crm_dialogs table');
    }


}

// Проверяем и исправляем таблицы при загрузке CRM
add_action('wp_loaded', 'verify_crm_tables_on_load');
function verify_crm_tables_on_load()
{
    if (is_page_template('crm.php')) {
        check_and_fix_crm_tables();
    }
}


// ручное созжание
// Обработчик создания ручной заявки
add_action('wp_ajax_create_manual_lead', 'handle_create_manual_lead');
function handle_create_manual_lead()
{


    global $wpdb;

    // Получаем и валидируем данные
    $zayv_name = sanitize_text_field($_POST['zayv_name']);
    $client_name = sanitize_text_field($_POST['client_name']);
    $client_phone = sanitize_text_field($_POST['client_phone']);

    $zayv_name = wp_unslash($zayv_name);
    $client_name = wp_unslash($client_name);


    // Проверяем что все поля заполнены
    if (empty($zayv_name) || empty($client_name) || empty($client_phone)) {
        wp_send_json_error('Все поля обязательны для заполнения');
    }

    // ⚠️ ЗАПРЕЩАЕМ "Не указан" как имя заявки
    if (mb_strtolower(trim($zayv_name)) === 'не указан') {
        wp_send_json_error('Нельзя использовать "Не указан" как имя заявки');
    }

    // Проверяем уникальность имени заявки
    $existing_zayv = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads WHERE name_zayv = %s",
        $zayv_name
    ));

    if ($existing_zayv > 0) {
        wp_send_json_error('Имя заявки уже существует');
    }

    // Сохраняем заявку в БД
    $result = $wpdb->insert(
        $wpdb->prefix . 'crm_leads',
        array(
            'name_zayv' => $zayv_name,
            'name' => $client_name,
            'phone' => $client_phone,
            'email' => '',
            'page_url' => 'Ручное создание',
            'status' => 'xolod',
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result === false) {
        error_log('Ошибка создания ручной заявки: ' . $wpdb->last_error);
        wp_send_json_error('Ошибка базы данных при создании заявки');
    }

    $lead_id = $wpdb->insert_id;

    // Создаем запись в таблице документов (как в старой логике)
    create_doc_for_lead($lead_id);

    // Логируем успешное создание
    error_log('Ручная заявка создана: ID ' . $lead_id . ', Имя: ' . $zayv_name);

    wp_send_json_success(array(
        'lead_id' => $lead_id,
        'message' => 'Заявка успешно создана'
    ));
}
// создание через сайт

// Обновляем старую функцию чтобы name_zayv не был пустым
// Оставляем старую логику CF7 без изменений
add_action('wpcf7_mail_sent', 'save_cf7_to_crm');
function save_cf7_to_crm($contact_form)
{
    global $wpdb;

    $submission = WPCF7_Submission::get_instance();

    if ($submission) {
        $data = $submission->get_posted_data();

        // Автоматический поиск имени и телефона
        $name = find_field_by_keywords($data, ['name', 'имя', 'fio', 'фио', 'fullname', 'вашеимя']);
        $phone = find_field_by_keywords($data, ['phone', 'tel', 'телефон', 'тел', 'phone-number', 'телефон']);

        // Если не нашли через ключевые слова, используем первый попавшийся текст или телефон
        if (empty($name)) {
            $name = find_name_in_data($data);
        }

        if (empty($phone)) {
            $phone = find_phone_in_data($data);
        }

        // Получаем URL страницы
        $page_url = $_SERVER['HTTP_REFERER'] ?? '';

        // Сохраняем в базу данных
        $result = $wpdb->insert(
            $wpdb->prefix . 'crm_leads',
            array(
                'name_zayv' => '',
                'name' => $name ?: 'Не указано',
                'phone' => $phone ?: 'Не указано',
                'email' => '', // Оставляем пустым как в оригинале
                'page_url' => $page_url,
                'status' => 'xolod',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // После сохранения заявки создаем запись в crm_doc
        if ($result !== false) {
            $lead_id = $wpdb->insert_id;
            create_doc_for_lead($lead_id);
            error_log('Заявка сохранена в CRM с ID: ' . $lead_id . ', Имя: ' . ($name ?: 'не указано') . ', Телефон: ' . ($phone ?: 'не указан'));
        } else {
            error_log('Ошибка сохранения заявки в CRM: ' . $wpdb->last_error);
        }
    }
}

// Вспомогательная функция для поиска поля по ключевым словам
function find_field_by_keywords($data, $keywords)
{
    foreach ($data as $key => $value) {
        if (!is_string($value) || empty($value)) {
            continue;
        }

        $lower_key = strtolower($key);
        foreach ($keywords as $keyword) {
            if (strpos($lower_key, $keyword) !== false) {
                return sanitize_text_field($value);
            }
        }
    }
    return '';
}

// Резервные функции для поиска
function find_name_in_data($data)
{
    foreach ($data as $key => $value) {
        if (!is_string($value) || empty($value)) {
            continue;
        }

        // Проверяем, похоже ли значение на имя (содержит буквы и пробелы)
        if (preg_match('/^[a-zA-Zа-яА-ЯёЁ\s]+$/u', $value) && strlen($value) > 2) {
            return sanitize_text_field($value);
        }
    }
    return '';
}

function find_phone_in_data($data)
{
    foreach ($data as $key => $value) {
        if (!is_string($value) || empty($value)) {
            continue;
        }

        // Проверяем, похоже ли значение на телефон (содержит цифры, скобки, плюсы, дефисы)
        $clean_value = preg_replace('/[^\d+]/', '', $value);
        if (preg_match('/^[\d+\-\s\(\)]+$/', $value) && strlen($clean_value) >= 5) {
            return sanitize_text_field($value);
        }
    }
    return '';
}
// Функция для создания записи в crm_doc для заявки
function create_doc_for_lead($lead_id)
{
    global $wpdb;

    $table_doc = $wpdb->prefix . 'crm_doc';

    // Проверяем, нет ли уже записи для этого lead_id
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_doc WHERE lead_id = %d",
        $lead_id
    ));

    if ($existing == 0) {
        // Создаем пустую запись с документами
        $result = $wpdb->insert(
            $table_doc,
            array(
                'lead_id' => $lead_id,
                'recipient' => '',
                'chet' => '',
                'bankrec' => '',
                'bik' => '',
                'korchet' => '',
                'inn' => '',
                'kpp' => '',
                'okpo' => '',
                'ogrn' => '',
                'swift' => '',
                'addrbank' => '',
                'addroffice' => ''
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result !== false) {
            error_log('Создана запись в crm_doc для заявки ID: ' . $lead_id);
        } else {
            error_log('Ошибка создания записи в crm_doc: ' . $wpdb->last_error);
        }
    }
}

// Функция для получения времени последней проверки
function get_last_check_time()
{
    return get_option('last_leads_check', 0);
}

// Функция для обновления времени проверки
function update_last_check_time()
{
    update_option('last_leads_check', current_time('timestamp'));
}

// AJAX для получения количества новых заявок
add_action('wp_ajax_get_new_leads_count', 'get_new_leads_count');
add_action('wp_ajax_nopriv_get_new_leads_count', 'get_new_leads_count');

function get_new_leads_count()
{
    global $wpdb;

    $page_load_time = isset($_POST['page_load_time']) ? intval($_POST['page_load_time']) : 0;

    if ($page_load_time > 0) {
        $mysql_time = date('Y-m-d H:i:s', $page_load_time);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads WHERE created_at > %s",
            $mysql_time
        ));

        wp_send_json_success(array('count' => $count ?: 0));
    } else {
        wp_send_json_success(array('count' => 0));
    }
}

// Передаем время сервера в JavaScript
add_action('wp_head', 'add_server_time');
function add_server_time()
{
    if (!is_admin()) {
        $server_time = current_time('timestamp');
        echo '<script type="text/javascript">';
        echo 'var serverLoadTime = ' . $server_time . ';';
        echo '</script>';
    }
}

// Добавляем скрипт в футер
add_action('wp_footer', 'add_leads_counter_script');
function add_leads_counter_script()
{
    if (!is_admin()) {
        $server_time = current_time('timestamp'); // UTC+3
        $formatted_time = current_time('d.m.Y H:i:s');
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                // Используем время СЕРВЕРА, а не клиента
                // var pageLoadTime = typeof serverLoadTime !== 'undefined' ? serverLoadTime : Math.floor(Date.now() / 1000);

                // console.log('Время сервера при загрузке: ' + new Date(pageLoadTime * 1000).toLocaleString());

                var pageLoadTime = <?php echo $server_time; ?>;

                console.log('Время сервера (WP): <?php echo $formatted_time; ?>');
                console.log('WP timestamp: ' + pageLoadTime);

                $('.header__zayv span').text('0');

                // Сразу показываем 0
                $('.header__zayv span').text('0');

                function updateCounter() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_new_leads_count',
                            page_load_time: pageLoadTime
                        },
                        success: function (response) {
                            if (response.success) {
                                var count = response.data.count;
                                $('.header__zayv span').text(count);
                                console.log('Новых заявок: ' + count);
                            }
                        }
                    });
                }

                $('.header__zayv').on('click', function (e) {
                    e.preventDefault();
                    location.reload();
                });

                // Первая проверка через 2 секунды
                setTimeout(updateCounter, 2000);
                setInterval(updateCounter, 30000);
            });
        </script>
        <?php
    }
}



// ==================== ОБРАБОТЧИКИ ДЛЯ ИМЕНИ ЗАЯВКИ ====================

// ✅ ДОБАВИТЬ этот обработчик
add_action('wp_ajax_get_lead_data', 'handle_get_lead_data');
function handle_get_lead_data()
{


    global $wpdb;

    $lead_id = intval($_POST['lead_id']);

    if (!$lead_id) {
        wp_send_json_error('Не указан ID заявки');
    }

    // Получаем данные заявки из БД
    $lead_data = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, name_zayv, phone, email, status, created_at 
         FROM {$wpdb->prefix}crm_leads 
         WHERE id = %d",
        $lead_id
    ));

    if (!$lead_data) {
        wp_send_json_error('Заявка не найдена');
    }

    wp_send_json_success(array(
        'id' => $lead_data->id,
        'name' => $lead_data->name,
        'name_zayv' => $lead_data->name_zayv,
        'phone' => $lead_data->phone,
        'email' => $lead_data->email,
        'status' => $lead_data->status,
        'created_at' => $lead_data->created_at
    ));
}

// Проверка уникальности имени заявки
add_action('wp_ajax_check_zayv_name_unique', 'handle_check_zayv_name_unique');
function handle_check_zayv_name_unique()
{
    if (!wp_verify_nonce($_POST['nonce'], 'crm_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }

    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $name_zayv = sanitize_text_field($_POST['name_zayv']);

    $name_zayv = wp_unslash($name_zayv);

    // ⚠️ ЗАПРЕЩАЕМ ИСПОЛЬЗОВАТЬ "Не указан" КАК ИМЯ ЗАЯВКИ
    if (mb_strtolower(trim($name_zayv)) === 'не указан') {
        wp_send_json_success(array(
            'unique' => false,
            'message' => 'Нельзя использовать "Не указан" как имя заявки'
        ));
    }

    if (empty($name_zayv)) {
        wp_send_json_success(array('unique' => true));
    }

    // Проверяем существование имени заявки у других заявок
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads 
         WHERE name_zayv = %s AND id != %d",
        $name_zayv,
        $lead_id
    ));

    wp_send_json_success(array(
        'unique' => $existing == 0,
        'message' => $existing > 0 ? 'Имя заявки уже существует' : ''
    ));
}

// Обновление имени заявки
add_action('wp_ajax_update_zayv_name', 'handle_update_zayv_name');
function handle_update_zayv_name()
{



    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $name_zayv = sanitize_text_field($_POST['name_zayv']);

    $name_zayv = wp_unslash($name_zayv);



    // ⚠️ ЗАПРЕЩАЕМ СОХРАНЕНИЕ "Не указан" В БАЗУ
    if (mb_strtolower(trim($name_zayv)) === 'не указан') {
        wp_send_json_error('Нельзя использовать "Не указан" как имя заявки');
    }

    // Проверяем уникальность если имя не пустое
    if (!empty($name_zayv)) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads 
             WHERE name_zayv = %s AND id != %d",
            $name_zayv,
            $lead_id
        ));

        if ($existing > 0) {
            wp_send_json_error('Имя заявки уже существует');
        }
    }

    $result = $wpdb->update(
        "{$wpdb->prefix}crm_leads",
        array('name_zayv' => $name_zayv),
        array('id' => $lead_id),
        array('%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success('Имя заявки успешно обновлено');
    } else {
        wp_send_json_error('Ошибка базы данных при обновлении имени заявки');
    }
}

// ==================== ОБРАБОТЧИКИ AJAX ДЛЯ РАБОТЫ С EMAIL ====================

add_action('wp_ajax_update_lead_email', 'handle_update_lead_email');
function handle_update_lead_email()
{
    if (!isset($_POST['lead_id']) || !isset($_POST['email'])) {
        wp_send_json_error('Missing required parameters');
    }

    $lead_id = intval($_POST['lead_id']);
    $email = sanitize_email($_POST['email']);

    global $wpdb;

    $result = $wpdb->update(
        "{$wpdb->prefix}crm_leads",
        array('email' => $email),
        array('id' => $lead_id),
        array('%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success('Email заявки успешно обновлен');
    } else {
        wp_send_json_error('Ошибка базы данных при обновлении email');
    }
}

// Обработчик для обновления email диалога
add_action('wp_ajax_update_dialog_email', 'handle_update_dialog_email');
function handle_update_dialog_email()
{
    if (!isset($_POST['lead_id']) || !isset($_POST['dialog_id']) || !isset($_POST['email'])) {
        wp_send_json_error('Missing required parameters');
    }

    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    $email = sanitize_email($_POST['email']);

    global $wpdb;

    // 🔧 ПРОВЕРКА ДУБЛИКАТОВ ДЛЯ ОСНОВНОЙ ПОЧТЫ

    if ($email) { // если email не пустой
        // Проверяем дубликаты в дополнительных email
        $existing_email = $wpdb->get_var($wpdb->prepare(
            "SELECT email FROM {$wpdb->prefix}crm_emails 
             WHERE dialog_id = %d AND email = %s",
            $dialog_id,
            $email
        ));

        if ($existing_email) {
            wp_send_json_error('Этот email уже используется как дополнительный');
        }
    }

    // Обновляем основную почту
    $result = $wpdb->update(
        "{$wpdb->prefix}crm_dialogs",
        array('email' => $email),
        array('id' => $dialog_id, 'lead_id' => $lead_id),
        array('%s'),
        array('%d', '%d')
    );

    if ($result !== false) {
        wp_send_json_success('Email диалога успешно обновлен');
    } else {
        wp_send_json_error('Ошибка базы данных при обновлении email диалога: ' . $wpdb->last_error);
    }
}
// последующие почты
add_action('wp_ajax_save_dialog_additional_email', 'handle_save_dialog_additional_email');
function handle_save_dialog_additional_email()
{
    if (!isset($_POST['dialog_id']) || !isset($_POST['email'])) {
        wp_send_json_error('Missing required parameters');
    }

    $dialog_id = intval($_POST['dialog_id']);
    $email = sanitize_email($_POST['email']);

    global $wpdb;

    if (!is_email($email)) {
        wp_send_json_error('Некорректный email адрес');
    }

    // 🔧 ПРОВЕРКА ДУБЛИКАТОВ

    // 1. Проверяем основную почту диалога
    $main_email = $wpdb->get_var($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
        $dialog_id
    ));

    if ($main_email === $email) {
        wp_send_json_error('Этот email уже используется как основной для диалога');
    }

    // 2. Проверяем дубликаты в дополнительных email
    $existing_email = $wpdb->get_var($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}crm_emails 
         WHERE dialog_id = %d AND email = %s",
        $dialog_id,
        $email
    ));

    if ($existing_email) {
        wp_send_json_error('Этот email уже добавлен как дополнительный');
    }

    // Сохраняем email
    $result = $wpdb->insert(
        "{$wpdb->prefix}crm_emails",
        array(
            'dialog_id' => $dialog_id,
            'email' => $email,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s')
    );

    if ($result !== false) {
        $email_id = $wpdb->insert_id;
        wp_send_json_success('Дополнительный email успешно сохранен', array('email_id' => $email_id));
    } else {
        wp_send_json_error('Ошибка базы данных при сохранении email: ' . $wpdb->last_error);
    }
}

// Добавь эту функцию для получения дополнительных email
function get_dialog_additional_emails($dialog_id)
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}crm_emails 
         WHERE dialog_id = %d 
         ORDER BY created_at ASC",
        $dialog_id
    ));
}

function update_dialog_sender_email_handler()
{
    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    $sender_email = sanitize_email($_POST['sender_email']);

    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'crm_dialogs',
        array('sender_email' => $sender_email),
        array('id' => $dialog_id, 'lead_id' => $lead_id),
        array('%s'),
        array('%d', '%d')
    );

    if ($result !== false) {
        wp_send_json_success('Email отправителя обновлен');
    } else {
        wp_send_json_error('Ошибка обновления email отправителя');
    }
}
add_action('wp_ajax_update_dialog_sender_email', 'update_dialog_sender_email_handler');

add_action('wp_ajax_update_dialog_additional_email', 'handle_update_dialog_additional_email');
function handle_update_dialog_additional_email()
{
    if (!isset($_POST['email_id']) || !isset($_POST['email'])) {
        wp_send_json_error('Missing required parameters');
    }

    $email_id = intval($_POST['email_id']);
    $email = sanitize_email($_POST['email']);

    global $wpdb;

    if (!is_email($email)) {
        wp_send_json_error('Некорректный email адрес');
    }

    // 🔧 ПРОВЕРКА ДУБЛИКАТОВ ПРИ ОБНОВЛЕНИИ

    // Получаем dialog_id для этого email
    $current_email = $wpdb->get_row($wpdb->prepare(
        "SELECT dialog_id, email FROM {$wpdb->prefix}crm_emails WHERE id = %d",
        $email_id
    ));

    if (!$current_email) {
        wp_send_json_error('Email не найден');
    }

    $dialog_id = $current_email->dialog_id;

    // 1. Проверяем основную почту диалога
    $main_email = $wpdb->get_var($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
        $dialog_id
    ));

    if ($main_email === $email) {
        wp_send_json_error('Этот email уже используется как основной для диалога');
    }

    // 2. Проверяем дубликаты в дополнительных email (исключая текущий)
    $existing_email = $wpdb->get_var($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}crm_emails 
         WHERE dialog_id = %d AND email = %s AND id != %d",
        $dialog_id,
        $email,
        $email_id
    ));

    if ($existing_email) {
        wp_send_json_error('Этот email уже добавлен как дополнительный');
    }

    // Обновляем email
    $result = $wpdb->update(
        "{$wpdb->prefix}crm_emails",
        array('email' => $email),
        array('id' => $email_id),
        array('%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success('Email успешно обновлен');
    } else {
        wp_send_json_error('Ошибка базы данных при обновлении email: ' . $wpdb->last_error);
    }
}

add_action('wp_ajax_delete_dialog_additional_email', 'handle_delete_dialog_additional_email');
function handle_delete_dialog_additional_email()
{
    if (!isset($_POST['email_id'])) {
        wp_send_json_error('Missing required parameters');
    }

    $email_id = intval($_POST['email_id']);

    global $wpdb;

    // Удаляем email из таблицы
    $result = $wpdb->delete(
        "{$wpdb->prefix}crm_emails",
        array('id' => $email_id),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success('Email успешно удален');
    } else {
        wp_send_json_error('Ошибка базы данных при удалении email: ' . $wpdb->last_error);
    }
}

// ==================== AJAX ОБРАБОТЧИКИ ДЛЯ ДИАЛОГОВ ====================

// Создание нового диалога
add_action('wp_ajax_create_dialog', 'handle_create_dialog');
function handle_create_dialog()
{

    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $dialog_name = sanitize_text_field($_POST['dialog_name']);

    $dialog_name = wp_unslash($dialog_name);

    // Детальное логирование получаемых данных
    error_log('CRM Debug: Attempting to create dialog. Lead ID: ' . $lead_id . ', Dialog Name: ' . $dialog_name);

    if (empty($dialog_name)) {
        error_log('CRM Error: Empty dialog name');
        wp_send_json_error('Введите название диалога');
    }

    // Проверяем существование заявки
    $lead_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads WHERE id = %d",
        $lead_id
    ));

    if (!$lead_exists) {
        error_log('CRM Error: Lead not found. Lead ID: ' . $lead_id);
        wp_send_json_error('Заявка не найдена');
    }

    $table_dialogs = $wpdb->prefix . 'crm_dialogs';

    // Проверяем существование таблицы диалогов
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_dialogs
    ));

    if (!$table_exists) {
        error_log('CRM Error: Table does not exist: ' . $table_dialogs);
        create_all_crm_tables();
        wp_send_json_error('Таблица диалогов не существует. Попробуйте снова.');
    }

    // Проверяем наличие столбца name
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_dialogs LIKE 'name'");
    if (!$column_exists) {
        error_log('CRM Error: Column "name" does not exist in table: ' . $table_dialogs);
        check_and_fix_crm_tables();
        wp_send_json_error('Ошибка структуры таблицы. Попробуйте снова.');
    }

    // ⭐ ПРОВЕРКА УНИКАЛЬНОСТИ: нет ли уже диалога с таким названием для этой заявки
    $existing_dialog = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_dialogs WHERE lead_id = %d AND name = %s",
        $lead_id,
        $dialog_name
    ));

    if ($existing_dialog > 0) {
        error_log('CRM Error: Dialog already exists. Lead ID: ' . $lead_id . ', Name: ' . $dialog_name);
        wp_send_json_error('Диалог с названием "' . $dialog_name . '" уже существует в этой заявке');
    }

    // Получаем email заявки для установки по умолчанию
    $lead_email = '';
    // ✅ ОБНОВИТЬ этот запрос в функции handle_create_dialog
    $lead_data = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, name_zayv, phone, email, status, created_at 
     FROM {$wpdb->prefix}crm_leads 
     WHERE id = %d",
        $lead_id
    ));

    if ($lead_data && !empty($lead_data->email)) {
        $lead_email = $lead_data->email;
    }

    // Создаем диалог С email
    $result = $wpdb->insert(
        $table_dialogs,
        array(
            'lead_id' => $lead_id,
            'name' => $dialog_name,
            'email' => $lead_email,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s')
    );

    if ($result === false) {
        $last_error = $wpdb->last_error;
        error_log('CRM Database Error: ' . $last_error);
        error_log('CRM Query: ' . $wpdb->last_query);
        wp_send_json_error('Ошибка базы данных: ' . $last_error);
    } else {
        $dialog_id = $wpdb->insert_id;
        error_log('CRM Success: Dialog created with ID: ' . $dialog_id . ' для заявки: ' . $lead_id);

        // Получаем данные созданного диалога
        $new_dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_dialogs WHERE id = %d",
            $dialog_id
        ));

        wp_send_json_success(array(
            'dialog_id' => $dialog_id,
            'dialog' => $new_dialog,
            'message' => 'Диалог успешно создан'
        ));
    }
}

// ⭐ ДОБАВЛЯЕМ ПРОВЕРКУ УНИКАЛЬНОСТИ В ОБРАБОТЧИК ОБНОВЛЕНИЯ
add_action('wp_ajax_update_any_field', 'handle_update_any_field');
function handle_update_any_field()
{
    $table = sanitize_text_field($_POST['table']);
    $id = intval($_POST['id']);
    $field_type = sanitize_text_field($_POST['field_type']);
    $field_value = sanitize_text_field($_POST['field_value']);


    $table = wp_unslash($table);
    $field_type = wp_unslash($field_type);
    $field_value = wp_unslash($field_value);


    global $wpdb;

    // ⭐ ПРОВЕРКА УНИКАЛЬНОСТИ ТОЛЬКО ДЛЯ НАЗВАНИЯ ДИАЛОГА
    if ($table === 'dialogs' && $field_type === 'name') {
        // Получаем lead_id текущего диалога
        $current_lead_id = $wpdb->get_var($wpdb->prepare(
            "SELECT lead_id FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
            $id
        ));

        // Проверяем, нет ли другого диалога с таким же названием в этой заявке
        $existing_dialog = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}crm_dialogs 
             WHERE lead_id = %d AND name = %s AND id != %d",
            $current_lead_id,
            $field_value,
            $id
        ));

        if ($existing_dialog) {
            wp_send_json_error('Диалог с названием "' . $field_value . '" уже существует в этой заявке');
            return;
        }
    }

    // Остальной код обновления...
    $table_field_map = [
        'dialogs' => [
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email'
        ],
        'leads' => [
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email'
        ],
        'doc' => [
            'recipient' => 'recipient',
            'chet' => 'chet',
            'bankrec' => 'bankrec',
            'bik' => 'bik',
            'korchet' => 'korchet',
            'inn' => 'inn',
            'kpp' => 'kpp',
            'okpo' => 'okpo',
            'ogrn' => 'ogrn',
            'swift' => 'swift',
            'addrbank' => 'addrbank',
            'addroffice' => 'addroffice',
        ]

    ];

    // Проверяем существование таблицы и поля
    if (!isset($table_field_map[$table])) {
        wp_send_json_error('Неизвестная таблица: ' . $table);
    }

    if (!isset($table_field_map[$table][$field_type])) {
        wp_send_json_error('Неизвестный тип поля: ' . $field_type);
    }

    $field_name = $table_field_map[$table][$field_type];
    $table_name = "{$wpdb->prefix}crm_{$table}";

    $result = $wpdb->update(
        $table_name,
        [$field_name => $field_value],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success('Поле обновлено');
    } else {
        wp_send_json_error('Ошибка базы данных: ' . $wpdb->last_error);
    }
}



// Обработчик для получения шаблона диалога (ДОБАВЛЕН)
add_action('wp_ajax_get_dialog_template', 'handle_get_dialog_template');
function handle_get_dialog_template()
{


    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    $dialog_name = sanitize_text_field($_POST['dialog_name']);
    $dialog_email = sanitize_email($_POST['dialog_email']);
    $dialog_created_at = sanitize_text_field($_POST['dialog_created_at']);


    $dialog_name = wp_unslash($dialog_name);
    $dialog_created_at = wp_unslash($dialog_created_at);


    if (!$lead_id || !$dialog_id) {
        wp_send_json_error('Неверные параметры');
    }

    // Получаем данные диалога с email
    $table_name = $wpdb->prefix . 'crm_dialogs';
    $dialog = $wpdb->get_row($wpdb->prepare("
        SELECT id, name, email, created_at 
        FROM $table_name 
        WHERE id = %d AND lead_id = %d
    ", $dialog_id, $lead_id));

    if (!$dialog) {
        wp_send_json_error('Диалог не найден');
    }

    // Используем переданные данные или данные из БД
    $current_dialog_name = $dialog_name ?: $dialog->name;
    $current_dialog_email = $dialog_email ?: $dialog->email;
    $current_dialog_created_at = $dialog_created_at ?: $dialog->created_at;

    // Генерируем HTML для секции сообщений
    $html = generate_dialog_message_section($lead_id, $dialog_id, $current_dialog_name, $current_dialog_email, $current_dialog_created_at);

    wp_send_json_success($html);
}




// Получение списка диалогов для заявки
add_action('wp_ajax_get_dialogs', 'handle_get_dialogs');
function handle_get_dialogs()
{


    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $table_dialogs = $wpdb->prefix . 'crm_dialogs';

    // Проверяем существование таблицы
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_dialogs
    ));

    if (!$table_exists) {
        wp_send_json_success(array());
    }

    // ВЫБИРАЕМ email ИЗ ТАБЛИЦЫ
    $dialogs = $wpdb->get_results($wpdb->prepare(
        "SELECT id, lead_id, name, email, created_at FROM $table_dialogs WHERE lead_id = %d ORDER BY created_at DESC",
        $lead_id
    ));

    wp_send_json_success($dialogs);
}
function get_our_company_email()
{
    // 1. Пробуем из настроек CRM
    $crm_email = get_option('crm_company_email');
    if ($crm_email && is_email($crm_email)) {
        return $crm_email;
    }

    // 2. Пробуем из CF7 (текущий метод)
    $cf7_email = get_last_cf7_to_email_enhanced();
    if ($cf7_email && is_email($cf7_email)) {
        return $cf7_email;
    }

    // 3. Админ email WordPress
    $admin_email = get_option('admin_email');
    if ($admin_email && is_email($admin_email)) {
        return $admin_email;
    }

    global $EMAIL_CONFIG;

    // Получаем выбранную почту
    $selected_email = $_POST['selected_email'] ?? array_keys($EMAIL_CONFIG['accounts'])[0];

    return $selected_email; // ⬅️
}

// Упрощенная отправка сообщения БЕЗ генерации файлов (для тестирования)

// ✅ ИСПРАВЛЕННАЯ ФУНКЦИЯ ОТПРАВКИ СООБЩЕНИЙ

// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ С ДИАГНОСТИКОЙ
add_action('wp_ajax_send_crm_message', 'handle_send_crm_message');
add_action('wp_ajax_nopriv_send_crm_message', 'handle_send_crm_message');
function handle_send_crm_message()
{
    error_log("🎯🎯🎯 ОСНОВНАЯ ФУНКЦИЯ ВЫЗВАНА: " . date('Y-m-d H:i:s'));
    error_log("📨 POST данные: " . print_r($_POST, true));

    global $wpdb;
    global $EMAIL_CONFIG;




    // Получаем данные
    $dialog_id = intval($_POST['dialog_id'] ?? 0);
    $message_text = sanitize_textarea_field($_POST['message_text'] ?? '');
    $recipient_email = sanitize_email($_POST['recipient_email'] ?? '');
    global $EMAIL_CONFIG;

    $selected_email = $_POST['selected_email'] ?? array_keys($EMAIL_CONFIG['accounts'])[0];
    $sender_email = sanitize_email($selected_email);

    // ✅ ИСПРАВЛЕНО: убрано дублирование и "Сообщение из CRM"
    // $subject = sanitize_text_field($_POST['subject'] ?? '');
    $subject = generate_message_subject($dialog_id);

    if (empty($subject)) {
        $subject = generate_message_subject($dialog_id);
    }
    // ✅ УДАЛЕНО: фолбэк на "Сообщение из CRM"

    $message_hash = md5($message_text . time() . $dialog_id);

    error_log("🔍 Обработанные данные:");
    error_log("   - dialog_id: " . $dialog_id);
    error_log("   - sender_email: " . $sender_email);
    error_log("   - subject: " . $subject);
    error_log("   - message_hash: " . $message_hash);

    // Валидация
    if (empty($dialog_id) || empty($message_text) || empty($recipient_email)) {
        wp_send_json_error('Заполните все поля');
    }

    // Сохраняем в БД
    // В функции handle_send_crm_message():
    $result = $wpdb->insert(
        $wpdb->prefix . 'crm_messages',
        array(
            'dialog_id' => $dialog_id,
            'message' => $message_text,

            // ✅ ИСПРАВЛЕНО: правильные email поля
            'sender_email' => $sender_email,    // ОТ нас
            'email' => $recipient_email,        // КЛИЕНТУ

            'subject' => $subject,
            'direction' => 'outgoing',
            'message_hash' => $message_hash,
            'attachments' => '',
            'sent_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if (!$result) {
        error_log('❌ Ошибка БД: ' . $wpdb->last_error);
        wp_send_json_error('Ошибка сохранения: ' . $wpdb->last_error);
    }

    $message_id = $wpdb->insert_id;
    error_log("✅ Сообщение сохранено в БД с ID: " . $message_id);

    // Проверяем что сохранилось
    $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_messages WHERE id = %d", $message_id));
    if ($saved) {
        error_log("🔍 Проверка сохраненных данных:");
        error_log("   - sender_email: " . $saved->sender_email);
        error_log("   - subject: " . $saved->subject);
        error_log("   - message_hash: " . $saved->message_hash);
    }

    // Отправляем email
    // После сохранения сообщения в БД
    $email_result = send_email_with_attachments_handler($recipient_email, $message_text, array(), $sender_email, $dialog_id);


    wp_send_json_success(array(
        'message_id' => $message_id,
        'email_sent' => !empty($email_result['success']),
        'message' => 'Сообщение отправлено и сохранено',
        'debug' => array(
            'saved_sender_email' => $saved->sender_email ?? '',
            'saved_subject' => $saved->subject ?? '',
            'saved_message_hash' => $saved->message_hash ?? ''
        )
    ));
}

// ✅ ФУНКЦИЯ ДЛЯ АВТОМАТИЧЕСКОЙ ГЕНЕРАЦИИ ТЕМЫ

function generate_message_subject($dialog_id)
{
    global $wpdb;

    error_log("🎯 Генерация темы для диалога: " . $dialog_id);

    // Получаем полную информацию о диалоге и заявке
    $dialog_data = $wpdb->get_row($wpdb->prepare(
        "SELECT
            -- l.id, 
            d.name as dialog_name,
            d.lead_id,
            l.name_zayv, 
            l.name as client_name
         FROM {$wpdb->prefix}crm_dialogs d 
         LEFT JOIN {$wpdb->prefix}crm_leads l ON d.lead_id = l.id 
         WHERE d.id = %d",
        $dialog_id
    ));

    error_log("🔍 Данные из БД:");
    error_log("   - name_zayv: " . ($dialog_data->name_zayv ?? 'НЕТ'));
    error_log("   - client_name: " . ($dialog_data->client_name ?? 'НЕТ'));
    error_log("   - dialog_name: " . ($dialog_data->dialog_name ?? 'НЕТ'));

    if ($dialog_data) {
        $subject_parts = [];



        // 1. Имя заявки (простая проверка на пустоту)
        if (!empty(trim($dialog_data->name_zayv))) {
            $subject_parts[] = trim($dialog_data->name_zayv);
        }

        // 2. Имя клиента (простая проверка на пустоту) 
        if (!empty(trim($dialog_data->client_name))) {
            $subject_parts[] = trim($dialog_data->client_name);
        }

        // 3. Название диалога (простая проверка на пустоту)
        if (!empty(trim($dialog_data->dialog_name))) {
            $subject_parts[] = trim($dialog_data->dialog_name);
        }

        error_log("📝 Части темы: " . implode(', ', $subject_parts));

        if (!empty($subject_parts)) {
            $final_subject = implode('; ', $subject_parts);
            error_log("🎯 Финальная тема: " . $final_subject);
            return $final_subject;
        }
    }

    return 'Переписка по заявке ' . $dialog_id;
}


// ✅ ИСПРАВЛЕННАЯ ФУНКЦИЯ ПОЛУЧЕНИЯ СООБЩЕНИЙ С ФАЙЛАМИ
add_action('wp_ajax_get_dialog_messages', 'handle_get_dialog_messages');
// 🔧 ИСПРАВЛЕННАЯ ФУНКЦИЯ ЗАГРУЗКИ ДИАЛОГА

function handle_get_dialog_messages()
{
    if (!isset($_POST['dialog_id']) || empty($_POST['dialog_id'])) {
        wp_send_json_error('Не указан ID диалога');
    }

    global $wpdb;
    $dialog_id = intval($_POST['dialog_id']);

    // 🔥 ДИАГНОСТИКА: Какие данные приходят
    error_log("🎯 DEBUG handle_get_dialog_messages:");
    error_log("   - POST dialog_id: " . $dialog_id);
    error_log("   - POST data: " . print_r($_POST, true));

    //  ДОБАВЛЯЕМ ПРОВЕРКУ РЕЖИМА ФАЙЛОВ
    $files_enabled = isset($_POST['files_enabled']) ? $_POST['files_enabled'] === '1' : true;

    error_log("🔍 ЗАГРУЗКА ДИАЛОГА: $dialog_id, режим файлов: " . ($files_enabled ? 'ВКЛ' : 'ВЫКЛ'));

    $table_messages = $wpdb->prefix . 'crm_messages';
    $table_message_files = $wpdb->prefix . 'crm_message_files';

    try {
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
            m.email,
            GROUP_CONCAT(mf.id) as file_ids,
            GROUP_CONCAT(mf.file_name) as file_names,
            GROUP_CONCAT(mf.file_type) as file_types,
            GROUP_CONCAT(mf.file_size) as file_sizes
     FROM $table_messages m
     LEFT JOIN $table_message_files mf ON m.id = mf.message_id
     WHERE m.dialog_id = %d 
     GROUP BY m.id
     ORDER BY m.sent_at DESC",
            $dialog_id
        ));

        error_log("📊 Загружено из БД: " . count($messages) . " сообщений");

        $formatted_messages = array();

        foreach ($messages as $message) {
            // 🔥 ДИАГНОСТИКА КАЖДОГО СООБЩЕНИЯ
            error_log("📨 DEBUG Сообщение:");
            error_log("   - ID: " . $message->id);
            error_log("   - lead_id: " . ($message->lead_id ?? 'НЕТ'));
            error_log("   - dialog_id: " . $message->dialog_id);
            error_log("   - direction: " . $message->direction);
            error_log("   - file_ids: " . ($message->file_ids ?? 'НЕТ'));

            $attachments = array();
            $has_files_removed = false;

            //  ВСЕГДА ОБРАБАТЫВАЕМ ФАЙЛЫ (ДАЖЕ КОГДА РЕЖИМ ВЫКЛЮЧЕН)
            if (!empty($message->file_ids)) {
                $file_ids = explode(',', $message->file_ids);
                $file_names = explode(',', $message->file_names);
                $file_types = explode(',', $message->file_types);
                $file_sizes = explode(',', $message->file_sizes);

                for ($i = 0; $i < count($file_ids); $i++) {
                    if (!empty($file_ids[$i]) && !empty($file_names[$i])) {
                        // 🔥 ФОРМИРУЕМ ПРАВИЛЬНЫЙ ПУТЬ К ФАЙЛУ С УЧЕТОМ ПАПКИ ДИАЛОГА
                        $file_url = get_file_url_with_folder(
                            $file_names[$i],
                            $message->lead_id, // Используем lead_id из сообщения
                            $dialog_id
                        );

                        $attachments[] = array(
                            'id' => $file_ids[$i],
                            'file_name' => $file_names[$i],
                            'file_type' => $file_types[$i] ?? '',
                            'file_size' => $file_sizes[$i] ?? 0,
                            'file_url' => $file_url // 🔥 ДОБАВЛЯЕМ ПРАВИЛЬНЫЙ URL
                        );
                    }
                }

                error_log("📎 Сообщение {$message->id}: найдено " . count($attachments) . " файлов");
            }

            $formatted_messages[] = array(
                'id' => $message->id,
                'dialog_id' => $message->dialog_id,
                'message' => $message->message,
                'email' => $message->email,
                'sender_email' => $message->sender_email,
                'direction' => $message->direction,
                'sent_at' => $message->sent_at,
                'subject' => $message->subject,
                'lead_id' => $message->lead_id, // 🔥 УЖЕ ЕСТЬ В БАЗЕ
                'attachments' => $attachments // 🔥 ТЕПЕРЬ С ПРАВИЛЬНЫМИ ПУТЯМИ
            );
        }

        wp_send_json_success($formatted_messages);

    } catch (Exception $e) {
        error_log("❌ Ошибка загрузки сообщений: " . $e->getMessage());
        wp_send_json_error('Ошибка базы данных: ' . $e->getMessage());
    }
}

// 🔥 ФУНКЦИЯ ДЛЯ ФОРМИРОВАНИЯ ПРАВИЛЬНОГО ПУТИ К ФАЙЛУ

function get_file_url_with_folder($fileName, $leadId, $dialogId)
{
    $upload_dir = wp_upload_dir();

    error_log("🔍 DEBUG get_file_url_with_folder:");
    error_log("   - fileName: " . $fileName);
    error_log("   - leadId: " . $leadId);
    error_log("   - dialogId: " . $dialogId);

    // 🔥 ЕСЛИ leadId ПУСТОЙ - ПОЛУЧАЕМ ИЗ ДИАЛОГА
    if (empty($leadId) || $leadId == 0) {
        global $wpdb;
        $dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT lead_id FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
            $dialogId
        ));

        if ($dialog && !empty($dialog->lead_id)) {
            $leadId = $dialog->lead_id;
            error_log("   - leadId из диалога: " . $leadId);
        } else {
            error_log("   - ❌ leadId не найден даже в диалоге!");
            // 🔥 РЕЗЕРВНЫЙ ВАРИАНТ - используем только dialogId
            $folder_name = 'диалог_' . $dialogId;
            $file_path = '/crm_files/от_меня/' . $folder_name . '/' . $fileName;
            $full_url = $upload_dir['baseurl'] . $file_path;
            error_log("   - Резервный URL: " . $full_url);
            return $full_url;
        }
    }

    // Получаем данные для имени папки (используем существующую функцию)
    $lead_data = get_lead_data_for_folder($leadId, $dialogId);
    error_log("   - lead_data: " . print_r($lead_data, true));

    $folder_name = generate_folder_name($lead_data);
    error_log("   - folder_name: " . $folder_name);

    // Формируем правильный путь
    $file_path = '/crm_files/от_меня/' . $folder_name . '/' . $fileName;
    $full_url = $upload_dir['baseurl'] . $file_path;

    error_log("   - full_url: " . $full_url);

    return $full_url;
}




// Обновление статуса заявки
add_action('wp_ajax_update_lead_status', 'handle_update_lead_status');
function handle_update_lead_status()
{


    global $wpdb;

    $lead_id = intval($_POST['lead_id']);
    $status = sanitize_text_field($_POST['status']);

    // Проверяем допустимые статусы
    $allowed_statuses = array('xolod', 'sozvon', 'otpr', 'tepl', 'gorak');
    if (!in_array($status, $allowed_statuses)) {
        wp_send_json_error('Недопустимый статус');
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'crm_leads',
        array('status' => $status),
        array('id' => $lead_id),
        array('%s'),
        array('%d')
    );

    if ($result !== false) {
        // Возвращаем обновленную статистику
        $stats = get_crm_stats();
        wp_send_json_success(array(
            'message' => 'Статус обновлен',
            'stats' => $stats
        ));
    } else {
        error_log('Ошибка обновления статуса: ' . $wpdb->last_error);
        wp_send_json_error('Ошибка обновления статуса');
    }
}

// ==================== ГЕНЕРАЦИЯ JPG ФАЙЛОВ ====================

// Генерация JPG в формате A4
require_once plugin_dir_path(__FILE__) . 'func_jpg.php';

// ==================== ГЕНЕРАЦИЯ PDF ФАЙЛОВ ====================

require_once plugin_dir_path(__FILE__) . 'func_pdf.php';


// ==================== ГЕНЕРАЦИЯ html ФАЙЛОВ ====================

require_once plugin_dir_path(__FILE__) . 'func_html.php';

// ==================== удаление диалога со всем его содержимым ====================

require_once plugin_dir_path(__FILE__) . 'crm_del.php';

// ==================== получение файлов ====================

require_once plugin_dir_path(__FILE__) . 'crm_files.php';

// ==================== ТЕСТИРОВАНИЕ И ДИАГНОСТИКА ====================
// Функция для тестирования работы CRM
add_shortcode('test_crm_system', 'test_crm_system_handler');
function test_crm_system_handler()
{
    $output = '<h3>Тестирование CRM системы</h3>';

    // Проверяем таблицы
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'crm_leads',
        $wpdb->prefix . 'crm_dialogs',
        $wpdb->prefix . 'crm_messages',
        $wpdb->prefix . 'crm_files',
        $wpdb->prefix . 'crm_message_files'
    ];

    $output .= '<h4>Проверка таблиц:</h4>';
    foreach ($tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $status = $exists ? '✅ Существует' : '❌ Отсутствует';
        $output .= "<p>$table: $status</p>";
    }

    // Проверяем заявки
    $leads_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads");
    $output .= "<h4>Статистика:</h4>";
    $output .= "<p>Заявок в базе: $leads_count</p>";

    // Тест генерации файлов
    $output .= '<h4>Тест генерации файлов:</h4>';

    $test_content = "Тестовое сообщение на кириллице:\nПривет мир!\nТест работы системы.";

    $pdf_url = generate_pdf_from_html($test_content, 'test');
    $jpg_url = generate_jpg_from_content($test_content, 'test');

    if ($pdf_url) {
        $output .= "<p>PDF: <a href='$pdf_url' target='_blank'>$pdf_url</a> ✅</p>";
    } else {
        $output .= "<p>PDF: Ошибка генерации ❌</p>";
    }

    if ($jpg_url) {
        $output .= "<p>JPG: <a href='$jpg_url' target='_blank'>$jpg_url</a> ✅</p>";
    } else {
        $output .= "<p>JPG: Ошибка генерации ❌</p>";
    }

    return $output;
}

// Шорткод для тестирования JPG системы
add_shortcode('test_jpg_system', 'test_jpg_system_handler');
function test_jpg_system_handler()
{
    $output = '<h3>Тестирование системы JPG</h3>';

    // Проверяем GD библиотеку
    $gd_available = extension_loaded('gd') && function_exists('imagecreate');
    $output .= '<h4>Проверка GD библиотеки:</h4>';
    $output .= '<p>Доступна: ' . ($gd_available ? '✅ Да' : '❌ Нет') . '</p>';

    if ($gd_available) {
        $gd_info = gd_info();
        $output .= '<p>Версия: ' . $gd_info['GD Version'] . '</p>';
        $output .= '<p>Поддержка JPG: ' . ($gd_info['JPEG Support'] ? '✅ Да' : '❌ Нет') . '</p>';
    }

    // Тест генерации JPG
    $output .= '<h4>Тест генерации JPG:</h4>';

    $test_content = "Тестовое сообщение для JPG:\n\n";
    $test_content .= "Привет мир! Это тест кириллицы.\n";
    $test_content .= "Проверка работы CRM системы.\n\n";
    $test_content .= "Содержание тестового сообщения должно отображаться корректно в созданном JPG файле.";

    $jpg_url = generate_jpg_from_content($test_content, 'test_' . time(), 'Тест JPG генерации');

    if ($jpg_url) {
        $output .= "<p>JPG файл создан: <a href='$jpg_url' target='_blank'>Открыть JPG</a> ✅</p>";
        $output .= "<p><small>Ссылка: $jpg_url</small></p>";
        $output .= '<img src="' . $jpg_url . '" style="max-width: 400px; border: 1px solid #ccc; margin: 10px 0;" alt="Тестовое JPG изображение">';
    } else {
        $output .= "<p>Ошибка создания JPG файла ❌</p>";
    }

    return $output;
}

// Диагностика AJAX
add_action('wp_ajax_test_ajax', 'test_ajax_handler');
function test_ajax_handler()
{
    if (!wp_verify_nonce($_POST['nonce'], 'crm_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }

    wp_send_json_success(array(
        'message' => 'AJAX работает корректно',
        'timestamp' => current_time('mysql'),
        'server' => $_SERVER['SERVER_SOFTWARE']
    ));
}

// ==================== ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ ====================

// Временная функция для отладки - пересоздание таблиц
add_action('admin_init', 'crm_debug_recreate_tables');
function crm_debug_recreate_tables()
{
    if (isset($_GET['debug_crm_tables']) && current_user_can('manage_options')) {
        error_log('CRM: Manual table recreation triggered');

        global $wpdb;
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');

        // Удаляем все таблицы CRM
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}crm_leads");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}crm_dialogs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}crm_messages");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}crm_files");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}crm_message_files");

        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');

        $result = create_all_crm_tables();
        if ($result) {
            error_log('CRM: Tables recreated successfully');
            echo '<div class="notice notice-success"><p>CRM таблицы пересозданы успешно</p></div>';
        } else {
            error_log('CRM: Table recreation failed');
            echo '<div class="notice notice-error"><p>Ошибка пересоздания CRM таблиц</p></div>';
        }

        // Также проверяем и добавляем отсутствующие столбцы
        check_and_fix_crm_tables();
    }
}

// Получение статистики по заявкам
function get_crm_stats()
{
    global $wpdb;

    $table_leads = $wpdb->prefix . 'crm_leads';

    $stats = array(
        'total' => 0,
        'xolod' => 0,
        'sozvon' => 0,
        'otpr' => 0,
        'tepl' => 0,
        'gorak' => 0
    );

    // Проверяем существование таблицы
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_leads'");
    if (!$table_exists) {
        return $stats;
    }

    // Общее количество заявок
    $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads");

    // Заявки по статусам
    $status_counts = $wpdb->get_results("
        SELECT status, COUNT(*) as count 
        FROM $table_leads 
        GROUP BY status
    ");

    foreach ($status_counts as $status_count) {
        if (isset($stats[$status_count->status])) {
            $stats[$status_count->status] = $status_count->count;
        }
    }

    return $stats;
}

// Новая функция для возврата статистики через AJAX
function handle_get_crm_stats()
{
    $stats = get_crm_stats();
    wp_send_json_success($stats);
}
add_action('wp_ajax_get_crm_stats', 'handle_get_crm_stats');

// Диагностика email настроек
add_action('wp_ajax_test_email_settings', 'test_email_settings_handler');


// Проверка email настроек WordPress
add_action('admin_init', 'check_wordpress_email_settings');
function check_wordpress_email_settings()
{
    if (isset($_GET['page']) && $_GET['page'] === 'crm_page') { // Замените на slug вашей CRM страницы
        error_log('CRM: WordPress Admin Email: ' . get_option('admin_email'));
        error_log('CRM: WordPress URL: ' . get_site_url());

        // Проверяем, настроена ли отправка писем в WordPress
        $mail_url = get_option('mailserver_url');
        $mail_port = get_option('mailserver_port');

        error_log('CRM: Mail Server: ' . $mail_url . ':' . $mail_port);

        if (empty($mail_url)) {
            error_log('CRM: Using default PHP mail() function');
        } else {
            error_log('CRM: Using SMTP server');
        }
    }
}


add_action('wp_ajax_generate_pdf_file', 'generate_pdf_file_ajax_handler');
function generate_pdf_file_ajax_handler()
{
    error_log("🎯 === PDF AJAX HANDLER STARTED ===");
    error_log("📨 RAW POST DATA: " . file_get_contents('php://input'));
    error_log("📨 POST ARRAY: " . print_r($_POST, true));
    error_log("🔍 SERVER DATA:");
    error_log("   - HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'not set'));
    error_log("   - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

    // Проверяем nonce
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'crm_nonce');
    error_log("🔐 NONCE CHECK: " . ($nonce_valid ? 'VALID' : 'INVALID'));

    if (!$nonce_valid) {
        error_log("❌ NONCE ERROR: " . ($_POST['nonce'] ?? 'not set'));
        wp_send_json_error('Ошибка безопасности: неверный nonce');
    }

    try {
        error_log("🎯 generate_pdf_file ВЫЗВАНА");

        global $wpdb;

        $lead_id = intval($_POST['lead_id']);
        $file_content = $_POST['file_content'];
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $dialog_id = isset($_POST['dialog_id']) ? intval($_POST['dialog_id']) : 0;

        error_log("🔍 Параметры:");
        error_log("   - lead_id: " . $lead_id);
        error_log("   - dialog_id: " . $dialog_id);
        error_log("   - message_id: " . $message_id);
        error_log("   - file_content длина: " . strlen($file_content));

        if (empty($file_content) || $file_content === '<br>') {
            throw new Exception('Введите текст для файла');
        }



        // 🔥 Генерируем PDF с системой папок
        $pdf_url = generate_pdf_with_folders($file_content, $lead_id, $dialog_id, 'Коммерческое предложение');

        if (!$pdf_url) {
            throw new Exception('Не удалось сгенерировать PDF файл');
        }

        error_log("✅ PDF сгенерирован: " . $pdf_url);


        wp_send_json_success(array(
            'file_url' => $pdf_url,
            'file_name' => basename($pdf_url),
            // 'message_id' => $message_id,
            'message' => 'PDF файл успешно создан'
        ));

    } catch (Exception $e) {
        error_log("❌ Ошибка в generate_pdf_file_ajax_handler: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

// 🔥 НОВАЯ ФУНКЦИЯ ДЛЯ СИСТЕМЫ ПАПОК:
function generate_pdf_with_folders($html_content, $lead_id, $dialog_id, $title = 'Коммерческое предложение')
{
    // Получаем реальные данные заявки с учетом конкретного диалога
    $lead_data = get_lead_data_for_folder($lead_id, $dialog_id); // 🔥 ПЕРЕДАЕМ DIALOG_ID

    // Генерируем правильное имя папки
    $folder_name = generate_folder_name($lead_data);

    // Используем ваш существующий код
    return generate_pdf_from_html_with_folders($html_content, $lead_id, $folder_name, $title);
}

//  функция для работы с папками
function generate_pdf_from_html_with_folders($html_content, $lead_id, $folder_name, $title = 'Сообщение из CRM')
{
    global $ENABLE_HEADER;

    try {
        $dompdf_loaded = load_dompdf();
        if (!$dompdf_loaded) {
            throw new Exception('Не удалось загрузить библиотеку DomPDF');
        }

        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        // СОЗДАЕМ ПАПКУ от_меня
        $ot_menya_dir = $crm_dir . '/от_меня';
        if (!file_exists($ot_menya_dir)) {
            if (!wp_mkdir_p($ot_menya_dir)) {
                throw new Exception('Не удалось создать папку "от_меня"');
            }
        }

        // СОЗДАЕМ ПАПКУ ЗАЯВКИ с правильным именем
        $lead_folder = $ot_menya_dir . '/' . $folder_name;
        if (!file_exists($lead_folder)) {
            if (!wp_mkdir_p($lead_folder)) {
                throw new Exception('Не удалось создать папку заявки: ' . $folder_name);
            }
        }

        $filename = 'Коммерческое_предложение_' . $lead_id . '_' . time() . '.pdf';
        $filepath = $lead_folder . '/' . $filename;

        // Остальной код создания PDF...
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'Unbounded');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', get_template_directory());

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');

        $prepared_html = prepare_dark_html_for_dompdf($html_content, $title);
        $dompdf->loadHtml($prepared_html);
        $dompdf->render();

        // Сохраняем PDF
        $pdf_output = $dompdf->output();
        if (file_put_contents($filepath, $pdf_output) === false) {
            throw new Exception('Не удалось сохранить PDF файл');
        }

        if (!file_exists($filepath)) {
            throw new Exception('PDF файл не был создан');
        }

        error_log('CRM PDF created with folders: ' . $filename);
        return $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $filename;

    } catch (Exception $e) {
        error_log('CRM PDF with folders Error: ' . $e->getMessage());
        return generate_html_fallback($html_content, $lead_id, $title);
    }
}

add_action('wp_ajax_send_message_with_files', 'send_message_with_files_ajax_handler');
function send_message_with_files_ajax_handler()
{
    global $wpdb;

    // Включаем максимальную отладку
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);

    error_log("🎯 ========== CRM: send_message_with_files_ajax_handler START ==========");
    error_log("🔍 CRM: PHP Memory: " . memory_get_usage() . ", Peak: " . memory_get_peak_usage());

    try {
        // ========== ПРОВЕРКА ВХОДНЫХ ДАННЫХ ==========
        error_log("🔍 CRM: Step 1 - Validating input data");

        if (!isset($_POST['dialog_id'])) {
            error_log('❌ CRM: dialog_id is missing in POST data');
            wp_send_json_error('Dialog ID is required');
        }

        $dialog_id = intval($_POST['dialog_id']);
        error_log("🔍 CRM: dialog_id received: " . $dialog_id);

        if ($dialog_id <= 0) {
            error_log('❌ CRM: Invalid dialog_id: ' . $dialog_id);
            wp_send_json_error('Invalid dialog ID');
        }

        $message_text = isset($_POST['message_text']) ? sanitize_textarea_field($_POST['message_text']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';

        $message_text = wp_unslash($message_text);
        error_log("🔍 CRM: Basic data - message_text length: " . strlen($message_text) . ", subject: " . $subject);


        // ========== ОБРАБОТКА EMAIL АДРЕСОВ ==========
        error_log("🔍 CRM: Step 2 - Processing email addresses");
        $recipient_emails = array();

        if (isset($_POST['recipient_emails'])) {
            error_log("🔍 CRM: recipient_emails exists in POST");

            // ВАЖНО: jQuery может отправлять массивы как recipient_emails[] 
            // Проверяем оба варианта
            if (isset($_POST['recipient_emails']) && is_array($_POST['recipient_emails'])) {
                // Вариант 1: recipient_emails как массив
                $email_array = $_POST['recipient_emails'];
            } elseif (isset($_POST['recipient_emails[]']) && is_array($_POST['recipient_emails[]'])) {
                // Вариант 2: recipient_emails[] как массив (jQuery так делает)
                $email_array = $_POST['recipient_emails[]'];
            } else {
                // Вариант 3: это строка с одним email
                $email_array = array($_POST['recipient_emails']);
            }

            error_log("🔍 CRM: Email array: " . print_r($email_array, true));

            foreach ($email_array as $email) {
                $clean_email = sanitize_email(trim($email));
                if (is_email($clean_email)) {
                    $recipient_emails[] = $clean_email;
                    error_log("✅ CRM: Valid email: " . $clean_email);
                }
            }
        }

        error_log("🔍 CRM: Validated emails count: " . count($recipient_emails));

        if (empty($recipient_emails)) {
            error_log('❌ CRM: No valid email addresses');
            wp_send_json_error('No valid email addresses provided');
        }

        // ========== ОБРАБОТКА ВЛОЖЕНИЙ ==========
        error_log("🔍 CRM: Step 3 - Processing attachments");
        $attachments = array();

        if (isset($_POST['attachments'])) {
            error_log("🔍 CRM: attachments received, type: " . gettype($_POST['attachments']));

            if (is_array($_POST['attachments'])) {
                foreach ($_POST['attachments'] as $index => $attachment) {
                    if (is_array($attachment) && isset($attachment['url']) && isset($attachment['name'])) {
                        $clean_attachment = array(
                            'url' => esc_url_raw($attachment['url']),
                            'name' => sanitize_text_field($attachment['name'])
                        );
                        $attachments[] = $clean_attachment;
                        error_log("✅ CRM: Valid attachment #$index: " . $clean_attachment['name']);
                    }
                }
            }
        }

        error_log("🔍 CRM: Valid attachments count: " . count($attachments));



        // ========== ПОЛУЧЕНИЕ КОНФИГУРАЦИИ EMAIL ==========
        error_log("🔍 CRM: Step 4 - Getting email configuration");

        $EMAIL_CONFIG = get_crm_email_accounts();
        error_log("🔍 CRM: Email config received: " . print_r($EMAIL_CONFIG, true));

        if (empty($EMAIL_CONFIG['accounts'])) {
            error_log('❌ CRM: No email accounts in configuration');
            wp_send_json_error('No email accounts configured in system');
        }

        // ========== ПОЛУЧЕНИЕ SENDER_EMAIL ИЗ БАЗЫ ==========
        error_log("🔍 CRM: Step 5 - Getting sender email from database");

        $sender_email_from_db = $wpdb->get_var($wpdb->prepare(
            "SELECT sender_email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
            $dialog_id
        ));

        error_log("🔍 CRM: Sender email from DB: " . ($sender_email_from_db ?: 'NOT FOUND'));

        $sender_email = ($sender_email_from_db && is_email($sender_email_from_db))
            ? $sender_email_from_db
            : array_keys($EMAIL_CONFIG['accounts'])[0];

        error_log("🔍 CRM: Final sender email: " . $sender_email);

        // ========== ПРОВЕРКА СУЩЕСТВОВАНИЯ ТАБЛИЦ ==========
        error_log("🔍 CRM: Step 6 - Checking database tables");

        $messages_table = $wpdb->prefix . 'crm_messages';
        $files_table = $wpdb->prefix . 'crm_message_files';

        $messages_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$messages_table'") == $messages_table;
        $files_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$files_table'") == $files_table;

        error_log("🔍 CRM: Tables check - messages: " . ($messages_table_exists ? 'EXISTS' : 'MISSING') .
            ", files: " . ($files_table_exists ? 'EXISTS' : 'MISSING'));

        if (!$messages_table_exists) {
            error_log('❌ CRM: Messages table does not exist');
            wp_send_json_error('Database table for messages not found');
        }

        // ========== СОХРАНЕНИЕ СООБЩЕНИЙ И ОТПРАВКА EMAIL ==========
        error_log("🔍 CRM: Step 7 - Saving messages and sending emails");

        $saved_messages = array();
        $email_results = array();
        $success_count = 0;

        foreach ($recipient_emails as $index => $single_email) {
            error_log("🔍 CRM: Processing recipient #$index: " . $single_email);

            try {
                // Сохраняем сообщение в базу
                $message_hash = md5($message_text . $single_email . microtime(true));

                error_log("🔍 CRM: Inserting message into database for: " . $single_email);

                $insert_data = array(
                    'dialog_id' => $dialog_id,
                    'message' => $message_text,
                    'sender_email' => $sender_email,
                    'email' => $single_email,
                    'subject' => $subject,
                    'direction' => 'outgoing',
                    'message_hash' => $message_hash,
                    'attachments' => '',
                    'sent_at' => current_time('mysql'),
                    'created_at' => current_time('mysql')
                );

                error_log("🔍 CRM: Insert data: " . print_r($insert_data, true));

                $result = $wpdb->insert(
                    $messages_table,
                    $insert_data,
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );

                if ($result === false) {
                    $db_error = $wpdb->last_error ?: 'Unknown database error';
                    error_log('❌ CRM: Database insert failed for ' . $single_email . ': ' . $db_error);
                    $email_results[$single_email] = array('success' => false, 'error' => 'Database error: ' . $db_error);
                    continue;
                }

                $message_id = $wpdb->insert_id;
                $saved_messages[$single_email] = $message_id;

                error_log("✅ CRM: Message saved with ID: " . $message_id . " for: " . $single_email);

                // Сохраняем файлы если есть
                if (!empty($attachments) && $files_table_exists) {
                    error_log("🔍 CRM: Saving " . count($attachments) . " attachments for message ID: " . $message_id);

                    foreach ($attachments as $attachment_index => $attachment) {
                        $file_result = $wpdb->insert(
                            $files_table,
                            array(
                                'message_id' => $message_id,
                                'file_url' => $attachment['url'],
                                'file_name' => $attachment['name'],
                                'attached_at' => current_time('mysql')
                            ),
                            array('%d', '%s', '%s', '%s')
                        );

                        if ($file_result) {
                            error_log("✅ CRM: Attachment saved: " . $attachment['name']);
                        } else {
                            error_log("❌ CRM: Attachment save failed: " . $attachment['name'] . " - " . $wpdb->last_error);
                        }
                    }
                }

                // Отправляем email
                error_log("🔍 CRM: Sending email to: " . $single_email);

                $email_result = send_email_with_attachments_handler(
                    $single_email,
                    $message_text,
                    $attachments,
                    $sender_email,
                    $dialog_id
                );

                error_log("🔍 CRM: Email send result for " . $single_email . ": " . print_r($email_result, true));

                $email_results[$single_email] = array(
                    'success' => $email_result['success'] ?? false,
                    'message_id' => $message_id,
                    'sender_email' => $email_result['sender_email'] ?? $sender_email,
                    'error' => $email_result['error'] ?? ''
                );

                if ($email_result['success'] ?? false) {
                    $success_count++;
                    error_log("✅ CRM: Email sent successfully to: " . $single_email);
                } else {
                    error_log("❌ CRM: Email failed for: " . $single_email . " - " . ($email_result['error'] ?? 'Unknown error'));
                }

            } catch (Exception $e) {
                error_log('❌ CRM: Exception processing email ' . $single_email . ': ' . $e->getMessage());
                $email_results[$single_email] = array('success' => false, 'error' => 'Processing error: ' . $e->getMessage());
            }
        }

        // ========== ФОРМИРОВАНИЕ ОТВЕТА ==========
        error_log("🔍 CRM: Step 8 - Preparing response");

        $total_count = count($recipient_emails);
        $email_sent = $success_count > 0;

        error_log("📊 CRM: Final statistics - success: $success_count, total: $total_count, email_sent: " . ($email_sent ? 'YES' : 'NO'));

        $response_data = array(
            'message_ids' => $saved_messages,
            'email_sent' => $email_sent,
            'sent_count' => $success_count,
            'total_count' => $total_count,
            'results' => $email_results,
            'message' => 'Processed ' . $total_count . ' email(s), successfully sent: ' . $success_count
        );

        error_log("✅ CRM: Sending success response: " . print_r($response_data, true));

        wp_send_json_success($response_data);

    } catch (Exception $e) {
        error_log('❌ ========== CRM: TOP LEVEL EXCEPTION ==========');
        error_log('❌ CRM: Exception: ' . $e->getMessage());
        error_log('❌ CRM: Stack trace: ' . $e->getTraceAsString());
        error_log('❌ CRM: File: ' . $e->getFile() . ':' . $e->getLine());

        wp_send_json_error('Server error: ' . $e->getMessage());
    }

    error_log("🔚 ========== CRM: send_message_with_files_ajax_handler END ==========");
}



function send_email_with_attachments_handler($to, $message, $attachments = array(), $sender_email = null, $dialog_id = null)
{
    // 🔧 ПОЛУЧАЕМ КОНФИГУРАЦИЮ ИЗ БАЗЫ ДАННЫХ
    $EMAIL_CONFIG = get_crm_email_accounts();

    // Генерируем тему письма ДО всего
    if ($dialog_id) {
        $subject = generate_message_subject($dialog_id);
    } else {
        $subject = 'Переписка';
    }

    // 🔧 ПРОВЕРЯЕМ ИСПОЛЬЗОВАНИЕ ШАБЛОНА ПИСЬМА
    global $wpdb;
    $shablon_table = $wpdb->prefix . 'crm_shabl_mes';
    $use_template = $wpdb->get_var("SELECT active FROM $shablon_table LIMIT 1");

    // Если active = 0, НЕ используем шаблон письма
    if ($use_template === '0') {
        error_log("🔧 CRM: Шаблон письма отключен (active=0), используем простой текст");
        $email_message = nl2br(htmlspecialchars($message));
    } else {
        error_log("🔧 CRM: Используем HTML шаблон письма (active=1)");

        // 🔧 ИСПРАВЛЕНИЕ: правильный путь к CSS файлу
        $css_path = plugin_dir_path(__FILE__) . 'assets/css/crm_message.css';
        $css_content = '';

        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            error_log("✅ CRM: CSS file loaded: $css_path");
        } else {
            error_log("⚠️ CRM: CSS file not found: $css_path");
        }

        // 🔧 ИСПРАВЛЕНИЕ: убрано дублирование тега <style>
        include 'crm_shablon_mes.php';
        $email_message = generate_email_template($subject, $message, $css_content, $attachments);
    }
    // Проверяем, есть ли вообще почты в базе
    if (empty($EMAIL_CONFIG['accounts'])) {
        error_log("❌ CRM: No email accounts found in database");
        return array(
            'success' => false,
            'sender_email' => '',
            'error' => 'No email accounts configured. Please add email accounts in CRM settings.'
        );
    }

    // ЕСЛИ есть sender_email из базы - используем его, иначе первую почту
    $available_emails = array_keys($EMAIL_CONFIG['accounts']);

    // Если sender_email не передан или не существует, используем первую доступную
    if (empty($sender_email) || !in_array($sender_email, $available_emails)) {
        $selected_email = $available_emails[0];
        error_log("🔧 CRM: Using default email: $selected_email");
    } else {
        $selected_email = $sender_email;
    }

    // Берем пароль для выбранной почты
    $selected_password = $EMAIL_CONFIG['accounts'][$selected_email];

    // Проверяем валидность получателя
    if (!is_email($to)) {
        error_log("❌ CRM: Invalid recipient email: $to");
        return array(
            'success' => false,
            'sender_email' => $selected_email,
            'error' => 'Invalid recipient email address'
        );
    }

    // 🔧 ПОЛУЧАЕМ SMTP ХОСТ ДЛЯ ВЫБРАННОЙ ПОЧТЫ
    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_email_accounts';

    // Получаем хост для конкретного email
    $smtp_host_from_db = $wpdb->get_var($wpdb->prepare(
        "SELECT host FROM $table_name WHERE email = %s",
        $selected_email
    ));

    error_log("🔧 CRM: SMTP host for $selected_email: " . $smtp_host_from_db);

    $smtp_config = array(
        'host' => $smtp_host_from_db,
        'username' => $selected_email,
        'password' => $selected_password,
        'from_email' => $selected_email,
        'from_name' => $EMAIL_CONFIG['from_name']
    );

    // Генерируем тему письма
    if ($dialog_id) {
        $subject = generate_message_subject($dialog_id);
    } else {
        $subject = 'Переписка';
    }

    error_log("🎯 CRM: Starting email send to: $to");
    error_log("🔧 CRM: Using email: $selected_email");
    error_log("🔧 CRM: Subject: $subject");
    error_log("📎 CRM: Attachments count: " . count($attachments));
    error_log("🔧 CRM: Use template: " . ($use_template === '0' ? 'NO' : 'YES'));

    // 🔧 ВРЕМЕННО ОТКЛЮЧАЕМ ВСЕ SMTP ПЛАГИНЫ
    add_filter('pre_option_mailjet_enabled', '__return_false');
    add_filter('pre_option_wp_mail_smtp_active', '__return_false');
    add_filter('pre_option_smtp_enabled', '__return_false');
    add_filter('pre_option_swpsmtp_enabled', '__return_false');

    // Переменная для хранения ошибки SMTP
    $smtp_error = '';

    // 🔧 ПРИМЕНЯЕМ НАШИ SMTP НАСТРОЙКИ
    add_action('phpmailer_init', function ($phpmailer) use ($smtp_config, &$smtp_error) {
        error_log("🔧 CRM: Applying custom SMTP settings for: " . $smtp_config['username']);

        try {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_config['host'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_config['username'];
            $phpmailer->Password = $smtp_config['password'];
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->Port = 587;
            $phpmailer->From = $smtp_config['from_email'];
            $phpmailer->FromName = $smtp_config['from_name'];
            $phpmailer->Sender = $smtp_config['from_email'];
            $phpmailer->Timeout = 15;

            // Настройка дебага
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function ($str, $level) use (&$smtp_error) {
                error_log("📧 SMTP Debug: $str");
                $smtp_error .= $str . "\n";
            };

            $phpmailer->SMTPDebug = 4; // Максимальный уровень дебага
            $phpmailer->Debugoutput = function ($str, $level) use (&$smtp_error) {
                error_log("📧 SMTP Level $level: $str");
                $smtp_error .= "Level $level: $str\n";

                // Специфичные проверки для 2FA
                if (strpos($str, 'Application-specific password required') !== false) {
                    error_log("🎯 ДИАГНОСТИКА: ТРЕБУЕТСЯ ПАРОЛЬ ПРИЛОЖЕНИЯ (2FA)");
                }
                if (strpos($str, 'two-factor') !== false) {
                    error_log("🎯 ДИАГНОСТИКА: ОБНАРУЖЕНА ДВУХФАКТОРНАЯ АУТЕНТИФИКАЦИЯ");
                }
                if (strpos($str, '235') !== false && strpos($str, 'Authentication successful') !== false) {
                    error_log("🎯 ДИАГНОСТИКА: АУТЕНТИФИКАЦИЯ УСПЕШНА");
                }
                if (strpos($str, '535') !== false && strpos($str, 'BadCredentials') !== false) {
                    error_log("🎯 ДИАГНОСТИКА: НЕВЕРНЫЕ УЧЕТНЫЕ ДАННЫЕ - ВОЗМОЖНО НУЖЕН ПАРОЛЬ ПРИЛОЖЕНИЯ");
                }
            };

        } catch (Exception $e) {
            error_log("❌ CRM: PHPMailer init error: " . $e->getMessage());
            $smtp_error = $e->getMessage();
        }
    });

    // 🔧 ИСПРАВЛЕННАЯ ЧАСТЬ: ПОДГОТОВКА ФИЗИЧЕСКИХ ВЛОЖЕНИЙ ДЛЯ ОТПРАВКИ
    $email_attachments = array();
    if (!empty($attachments)) {
        $upload_dir = wp_upload_dir();

        foreach ($attachments as $attachment) {
            if (isset($attachment['url'])) {
                $file_url = $attachment['url'];

                // 🔥 ОСНОВНОЕ ИСПРАВЛЕНИЕ: правильное преобразование URL в путь
                $file_path = $upload_dir['basedir'] . str_replace($upload_dir['baseurl'], '', $file_url);

                // Альтернативный способ если выше не сработал
                if (!file_exists($file_path)) {
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                }

                // Еще одна проверка - декодируем URL если нужно
                if (!file_exists($file_path)) {
                    $decoded_path = urldecode($file_path);
                    if (file_exists($decoded_path)) {
                        $file_path = $decoded_path;
                    }
                }

                if (file_exists($file_path)) {
                    $email_attachments[] = $file_path;
                    error_log("✅ CRM: Добавлено вложение: " . basename($file_path));
                } else {
                    error_log("❌ CRM: Файл не найден: " . $file_path);
                    error_log("❌ CRM: URL файла: " . $file_url);
                    error_log("❌ CRM: BaseURL: " . $upload_dir['baseurl']);
                    error_log("❌ CRM: BaseDir: " . $upload_dir['basedir']);
                }
            }
        }
    }

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $smtp_config['from_name'] . ' <' . $smtp_config['from_email'] . '>'
    );

    // 🔧 ОТПРАВЛЯЕМ EMAIL
    error_log("📤 CRM: Отправка email to: $to, subject: $subject");
    error_log("📎 CRM: Физических вложений для отправки: " . count($email_attachments));

    $result = wp_mail($to, $subject, $email_message, $headers, $email_attachments);

    // 🔧 ВОССТАНАВЛИВАЕМ ПЛАГИНЫ
    remove_all_filters('pre_option_mailjet_enabled');
    remove_all_filters('pre_option_wp_mail_smtp_active');
    remove_all_filters('pre_option_smtp_enabled');
    remove_all_filters('pre_option_swpsmtp_enabled');
    remove_all_actions('phpmailer_init');

    if ($result) {
        error_log("✅ CRM: Email sent successfully from: {$smtp_config['from_email']} to: $to");
        error_log("✅ CRM: " . ($use_template === '0' ? 'Простой текст отправлен' : 'HTML письмо отправлено') . " с " . count($attachments) . " вложениями");
        error_log("✅ CRM: Физически прикреплено файлов: " . count($email_attachments));
    } else {
        error_log("❌ CRM: Email failed from: {$smtp_config['from_email']} to: $to");
        error_log("❌ CRM: SMTP errors: " . $smtp_error);

        // Дополнительная диагностика при ошибке
        global $phpmailer;
        if (isset($phpmailer)) {
            error_log("❌ CRM: PHPMailer Error: " . $phpmailer->ErrorInfo);
        }
    }

    // ✅ ВОЗВРАЩАЕМ РЕЗУЛЬТАТ ОТПРАВКИ
    return array(
        'success' => $result,
        'sender_email' => $selected_email, // Используем выбранный email
        'error' => $result ? '' : ($smtp_error ?: 'Unknown error occurred')
    );
}

// add_action( 'phpmailer_init', 'fix_smtp_certificate_issue' );
// function fix_smtp_certificate_issue( $phpmailer ) {
//     // Отключаем проверку SSL-сертификата
//     $phpmailer->SMTPOptions = array(
//         'ssl' => array(
//             'verify_peer'       => false,
//             'verify_peer_name'  => false,
//             'allow_self_signed' => true,
//         ),
//     );
// }

// 🔧 НОВАЯ ФУНКЦИЯ: Получение CSS для email
function get_css_for_email()
{
    // 🔧 ИСПОЛЬЗУЕМ ТЕ ЖЕ CSS ФАЙЛЫ ЧТО И В PDF
    $css_content = '';

    // Подключаем crm-documents.css
    $css_documents_path = plugin_dir_path(__FILE__) . 'assets/css/crm-documents.css';
    if (file_exists($css_documents_path)) {
        $css_content .= file_get_contents($css_documents_path);
        error_log('✅ CRM Email: CSS documents loaded');
    } else {
        error_log('❌ CRM Email: CSS documents file not found at ' . $css_documents_path);
    }

    // Подключаем crm-tex.css
    $css_tex_path = plugin_dir_path(__FILE__) . 'assets/css/crm-tex.css';
    if (file_exists($css_tex_path)) {
        $css_content .= file_get_contents($css_tex_path);
        error_log('✅ CRM Email: CSS tex loaded');
    } else {
        error_log('❌ CRM Email: CSS tex file not found at ' . $css_tex_path);
    }

    // 🔧 ОПТИМИЗИРУЕМ CSS ДЛЯ EMAIL
    $css_content = optimize_css_for_email($css_content);

    return $css_content;
}



// 🔧 Функция добавления префиксов
function add_prefix_to_classes($html_content)
{
    $classes_mapping = [
        'file-content-editor' => 'kp-file-content-editor',
        'wap' => 'kp-wap',
        'container' => 'kp-container',
        'document-header' => 'kp-document-header',
        'document-subtitle' => 'kp-document-subtitle',
        'address' => 'kp-address',
        'address_item' => 'kp-address_item',
        'address_info' => 'kp-address_info',
        'p' => 'kp-p',
        'table-container' => 'kp-table-container',
        'textcols_one' => 'kp-textcols_one',
        'pdf-table' => 'kp-pdf-table',
        'zakladka' => 'kp-zakladka',
        'textcols-row' => 'kp-textcols-row',
        'textcols-item' => 'kp-textcols-item',
        'name' => 'kp-name',
        'data' => 'kp-data',
        'text' => 'kp-text',
        'texnik' => 'kp-texnik',
        'osnova' => 'kp-osnova',
        'footer_doc' => 'kp-footer_doc',
        'footer_row' => 'kp-footer_row',
    ];

    foreach ($classes_mapping as $old_class => $new_class) {
        $html_content = str_replace(
            ["class=\"$old_class\"", "class='$old_class'", "class=\"$old_class "],
            ["class=\"$new_class\"", "class='$new_class'", "class=\"$new_class "],
            $html_content
        );
    }

    return $html_content;
}

// 🔧 Функция конвертации классов в inline-стили
function convert_classes_to_inline_styles($html_content)
{
    $styles_mapping = [
        'kp-file-content-editor' => 'background:url() no-repeat center center; background-size:cover; min-height:600px; padding:20px;',
        'kp-wap' => 'max-width:800px; margin:0 auto; background:rgba(255,255,255,0.95); padding:30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);',
        'kp-document-header' => 'text-align:center; margin-bottom:30px;',
        'kp-document-subtitle' => 'font-size:24px; color:#2c3e50; margin:20px 0; font-weight:bold;',
        'kp-address' => 'display:flex; justify-content:center; flex-wrap:wrap; gap:20px; margin:20px 0;',
        'kp-address_item' => 'text-align:center;',
        'kp-address_info' => 'color:#7f8c8d; margin:5px 0;',
        'kp-p' => 'min-height:200px; padding:20px; margin:20px 0;',
    ];

    foreach ($styles_mapping as $class => $style) {
        $pattern = '/class="[^"]*' . preg_quote($class) . '[^"]*"/';
        $html_content = preg_replace_callback($pattern, function ($matches) use ($style) {
            // Добавляем style атрибут если его нет
            if (strpos($matches[0], 'style=') === false) {
                return $matches[0] . ' style="' . $style . '"';
            }
            return $matches[0];
        }, $html_content);
    }

    return $html_content;
}

// 🔧 НОВАЯ ФУНКЦИЯ: Очистка HTML от артефактов почтовых клиентов


function prepare_html_for_email($html_content, $title = 'Сообщение из CRM')
{
    // Очищаем HTML контент
    $html_content = stripslashes($html_content);
    $html_content = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');

    // Очищаем от артефактов почтовых клиентов
    $html_content = clean_email_html($html_content);

    // 🔥 ПОЛУЧАЕМ ВСЕ СТИЛИ ИЗ ВАШИХ CSS ФАЙЛОВ
    $css_content = get_all_css_from_files();

    // Создаем HTML с ВСЕМИ стилями
    $full_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
        <style>
            /* ВСЕ СТИЛИ ИЗ CRM CSS ФАЙЛОВ */
            " . $css_content . "
        </style>
    </head>
    <body>
        {$html_content}
    </body>
    </html>
    ";

    return $full_html;
}

function get_all_css_from_files()
{
    $css_content = '';

    // Берем ВСЕ стили из crm-documents.css
    $css_documents_path = plugin_dir_path(__FILE__) . 'assets/css/crm-documents.css';
    if (file_exists($css_documents_path)) {
        $css_content .= file_get_contents($css_documents_path);
        error_log('✅ Загружены стили из crm-documents.css');
    }

    // Берем ВСЕ стили из crm-tex.css
    $css_tex_path = plugin_dir_path(__FILE__) . 'assets/css/crm-tex.css';
    if (file_exists($css_tex_path)) {
        $css_content .= file_get_contents($css_tex_path);
        error_log('✅ Загружены стили из crm-tex.css');
    }

    // Оптимизируем CSS для email (убираем только то, что точно не работает)
    $css_content = optimize_css_for_email($css_content);

    return $css_content;
}

function optimize_css_for_email($css)
{
    // Убираем ТОЛЬКО то, что точно не работает в почтовых клиентах
    $unsupported = [
        '/position:\s*fixed[^;]*;/i',
        '/transform:\s*[^;]+;/i',
        '/transition:\s*[^;]+;/i',
        '/animation:\s*[^;]+;/i',
        '/@keyframes[^{]+\{[^}]+\}/s',
    ];

    $css = preg_replace($unsupported, '', $css);

    return $css;
}

function clean_email_html($html_content)
{
    // ТОЛЬКО очистка от мусора Mail.ru, НИЧЕГО не добавляем
    $html_content = preg_replace('/_mr_css_attr/', '', $html_content);
    $html_content = str_replace('\"', '"', $html_content);
    $html_content = str_replace('\&quot;', '"', $html_content);
    $html_content = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $html_content);
    $html_content = preg_replace('/<script[^>]*>.*?<\/script>/s', '', $html_content);
    $html_content = preg_replace('/<div id="style_[^"]*">/', '', $html_content);
    $html_content = preg_replace('/<div id="style_[^"]*_BODY">/', '', $html_content);
    $html_content = preg_replace('/\s?cl-[a-z0-9]+\s?/', ' ', $html_content);
    $html_content = str_replace('</div></div>', '</div>', $html_content);

    return trim($html_content);
}

// 🔧 ОБНОВЛЕННАЯ ФУНКЦИЯ: Оптимизация CSS для email


function get_email_attachments_info($imap_connection, $email_number)
{
    error_log("📝 get_email_attachments_info: только читаем информацию о вложениях");

    $attachments = array();
    $structure = imap_fetchstructure($imap_connection, $email_number);

    if (!empty($structure->parts)) {
        foreach ($structure->parts as $part_num => $part) {
            $part_id = $part_num + 1;

            $is_attachment = false;
            $filename = '';

            // Проверяем параметры filename
            if ($part->ifdparameters) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $param->value;
                        $is_attachment = true;
                        break;
                    }
                }
            }

            if (!$filename && $part->ifparameters) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                        $is_attachment = true;
                        break;
                    }
                }
            }

            if ($is_attachment && $filename) {
                $filename = decode_email_subject($filename);
                error_log("📎 Найден файл (только информация): " . $filename);

                $attachments[] = array(
                    'file_name' => $filename,
                    'file_type' => $part->subtype,
                    'file_size' => 0,
                    'file_url' => '',
                    'source' => 'info_only'
                );
            }
        }
    }

    return $attachments;
}

// ✅  ФУНКЦИЯ ДЛЯ ИЗВЛЕЧЕНИЯ ВЛОЖЕНИЙ ИЗ ПИСЕМ MAIL.RU


function parse_incoming_emails($client_email, $dialog_id = null)
{
    global $wpdb;

    error_log("🔍 Парсер для: $client_email" . ($dialog_id ? ", диалог: $dialog_id" : ""));

    // ⭐ УМНАЯ ЛОГИКА: если указан dialog_id - фильтруем по диалогу
    if ($dialog_id) {
        $last_message_date = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(sent_at) FROM {$wpdb->prefix}crm_messages 
            WHERE dialog_id = %d AND sender_email = %s AND direction = 'incoming'
        ", $dialog_id, $client_email));

        if ($last_message_date) {
            $since_date = date('d-M-Y', strtotime($last_message_date));
            error_log("📅 Проверяем письма НОВЕЕ: $since_date (последнее в диалоге: $last_message_date)");
        } else {
            $since_date = date('d-M-Y', strtotime("-7 days"));
            error_log("📅 Первая проверка диалога, берем 7 дней: $since_date");
        }
    } else {
        $since_date = date('d-M-Y', strtotime("-7 days"));
        error_log("📅 Общая проверка: последние 7 дней ($since_date)");
    }



    global $EMAIL_CONFIG;

    // Получаем выбранную почту (из формы или по умолчанию)
    $selected_email = $_POST['selected_email'] ?? array_keys($EMAIL_CONFIG['accounts'])[0];

    // Берем пароль для выбранной почты
    $selected_password = $EMAIL_CONFIG['accounts'][$selected_email];

    // Для IMAP
    $imap_host = $EMAIL_CONFIG['host'];
    $imap_port = 993;
    $username = $selected_email;           // ⬅️ выбранная почта
    $password = $selected_password;




    $mailbox = "{{$imap_host}:{$imap_port}/imap/ssl}INBOX";
    $imap_connection = imap_open($mailbox, $username, $password);

    if (!$imap_connection) {
        error_log("❌ Парсер: не удалось подключиться к IMAP");
        return array();
    }

    $search_criteria = 'SINCE "' . $since_date . '" FROM "' . $client_email . '"';
    $emails = imap_search($imap_connection, $search_criteria);

    $replies = array();

    if ($emails) {
        error_log("✅ Парсер: найдено " . count($emails) . " писем за период");

        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($imap_connection, $email_number, 0);

            $original_subject = $overview[0]->subject ?? '';
            error_log("📧 ОРИГИНАЛЬНАЯ тема: " . $original_subject);

            $clean_subject = decode_email_subject($original_subject);

            // Извлекаем реальный email отправителя
            $from_header = $overview[0]->from ?? '';
            preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $from_header, $matches);
            $actual_from_email = $matches[0] ?? '';

            error_log("🔍 Проверяем письмо от: $actual_from_email (ожидаем: $client_email)");

            // Фильтруем по email
            if ($actual_from_email !== $client_email) {
                error_log("⏩ Пропускаем чужое письмо: $actual_from_email");
                continue;
            }

            // Пропускаем старые письма если указан dialog_id
            if ($dialog_id && isset($last_message_date)) {
                $message_date = process_email_date($overview[0]->date ?? '');
                if ($message_date <= $last_message_date) {
                    error_log("⏩ Пропускаем старое письмо: $actual_from_email");
                    continue;
                }
            }

            error_log("✅ Обрабатываем письмо от: $actual_from_email");

            // Проверка соответствия темы диалогу
            if ($dialog_id) {
                $dialog_subject = get_dialog_subject($dialog_id);

                $clean_dialog_subject = preg_replace('/^(Re:|Fwd:|Ответ:)\s*/i', '', $dialog_subject);
                $clean_email_subject = preg_replace('/^(Re:|Fwd:|Ответ:)\s*/i', '', $clean_subject);

                $clean_dialog_subject = trim($clean_dialog_subject);
                $clean_email_subject = trim($clean_email_subject);

                error_log("🔍 Сравнение тем для диалога $dialog_id:");
                error_log("   - Тема диалога: '$clean_dialog_subject'");
                error_log("   - Тема письма: '$clean_email_subject'");

                if ($clean_dialog_subject !== $clean_email_subject) {
                    error_log("⏩ Пропускаем письмо - тема не соответствует диалогу");
                    continue;
                }

                error_log("✅ Тема письма соответствует диалогу");
            }

            // Находим dialog_id для письма
            $current_dialog_id = $dialog_id;

            if (!$current_dialog_id) {
                $found_dialog_id = find_dialog_for_email($clean_subject, $actual_from_email);
                if ($found_dialog_id) {
                    $current_dialog_id = $found_dialog_id;
                    error_log("✅ Найден диалог по теме: $current_dialog_id");
                } else {
                    error_log("❌ Не найден диалог для письма, пропускаем");
                    continue;
                }
            }

            //  ИЗВЛЕЧЕНИЕ ТЕКСТА ПИСЬМА
            $structure = imap_fetchstructure($imap_connection, $email_number);
            $html_body = '';
            $text_body = '';

            // ⭐ ИСПОЛЬЗУЕМ ВАШУ ФУНКЦИЮ ДЛЯ ИЗВЛЕЧЕНИЯ ВЛОЖЕНИЙ
            // ⭐ ПРОВЕРЯЕМ НАСТРОЙКУ ФАЙЛОВ ПЕРЕД ИЗВЛЕЧЕНИЕМ ВЛОЖЕНИЙ
            $files_enabled_option = get_option("crm_files_enabled_{$current_dialog_id}", '0');
            $files_enabled = ($files_enabled_option === '1');

            if ($files_enabled) {
                $attachments = extract_email_attachments($imap_connection, $email_number, $current_dialog_id);
                error_log("📎 Режим файлов ВКЛЮЧЕН, извлекаем " . count($attachments) . " вложений");
            } else {
                $attachments = get_email_attachments_info($imap_connection, $email_number);
                error_log("📝 Режим файлов ОТКЛЮЧЕН, только читаем информацию о " . count($attachments) . " вложениях");
            }

            // 🔧 ЕСЛИ ЕСТЬ ВЛОЖЕНИЯ - ИСПОЛЬЗУЕМ РЕКУРСИВНЫЙ МЕТОД
            if (!empty($attachments)) {
                error_log("🎯 Письмо с вложениями, используем рекурсивный парсинг");

                // Рекурсивная функция для обработки multipart писем
                function parse_multipart_email($imap_connection, $email_number, $part, $part_number = '')
                {
                    $data = array(
                        'html' => '',
                        'text' => '',
                        'attachments' => array()
                    );

                    // Если это multipart, обрабатываем все части
                    if (isset($part->parts)) {
                        $part_count = count($part->parts);
                        for ($i = 0; $i < $part_count; $i++) {
                            $prefix = $part_number ? $part_number . '.' : '';
                            $sub_data = parse_multipart_email($imap_connection, $email_number, $part->parts[$i], $prefix . ($i + 1));

                            $data['html'] .= $sub_data['html'];
                            $data['text'] .= $sub_data['text'];
                        }
                    } else {
                        // Обрабатываем отдельную часть
                        $body = imap_fetchbody($imap_connection, $email_number, $part_number);

                        // Декодируем содержимое
                        if (isset($part->encoding)) {
                            switch ($part->encoding) {
                                case 3: // BASE64
                                    $body = imap_base64($body);
                                    break;
                                case 4: // QUOTED-PRINTABLE
                                    $body = imap_qprint($body);
                                    break;
                                case 1: // 8BIT
                                case 2: // BINARY
                                default:
                                    // Оставляем как есть
                                    break;
                            }
                        }

                        // Определяем тип содержимого
                        $type = $part->type;
                        $subtype = isset($part->subtype) ? strtoupper($part->subtype) : '';

                        // Текстовые части
                        if ($type == 0) {
                            if ($subtype == 'HTML') {
                                $data['html'] = $body;
                                error_log("🎯 Найдена HTML часть в multipart");
                            } elseif ($subtype == 'PLAIN') {
                                $data['text'] = $body;
                                error_log("🎯 Найдена TEXT часть в multipart");
                            }
                        }
                    }

                    return $data;
                }

                // Парсим структуру письма с вложениями
                $email_data = parse_multipart_email($imap_connection, $email_number, $structure);

                // Обрабатываем HTML тело если есть
                if (!empty($email_data['html'])) {
                    $html_body = imap_utf8($email_data['html']);
                    $html_body = extract_and_save_images($html_body, $email_number, '1');
                    error_log("📝 HTML из multipart: " . substr(strip_tags($html_body), 0, 100));
                }

                // Обрабатываем текстовое тело если есть
                if (!empty($email_data['text'])) {
                    $text_body = imap_utf8($email_data['text']);
                    error_log("📝 TEXT из multipart: " . substr($text_body, 0, 100));
                }

            } else {
                // 🔧 ЕСЛИ НЕТ ВЛОЖЕНИЙ - ИСПОЛЬЗУЕМ СТАРЫЙ МЕТОД
                error_log("📝 Письмо без вложений, используем старый метод");

                if (!empty($structure->parts)) {
                    foreach ($structure->parts as $part_num => $part) {
                        $part_number = $part_num + 1;

                        // Обрабатываем HTML часть
                        if ($part->type == 2 && $part->subtype == 'HTML') {
                            $html_body = imap_fetchbody($imap_connection, $email_number, $part_number);

                            // Декодируем в зависимости от кодировки
                            if (isset($part->encoding)) {
                                switch ($part->encoding) {
                                    case 3: // BASE64
                                        $html_body = imap_base64($html_body);
                                        break;
                                    case 4: // QUOTED-PRINTABLE
                                        $html_body = imap_qprint($html_body);
                                        break;
                                    case 0: // 7BIT
                                    case 1: // 8BIT
                                    default:
                                        // Оставляем как есть
                                        break;
                                }
                            }

                            $html_body = imap_utf8($html_body);
                            $html_body = extract_and_save_images($html_body, $email_number, $part_number);
                        }

                        // Обрабатываем TEXT часть
                        if ($part->type == 0 && $part->subtype == 'PLAIN') {
                            $text_body = imap_fetchbody($imap_connection, $email_number, $part_number);

                            if (isset($part->encoding)) {
                                switch ($part->encoding) {
                                    case 3: // BASE64
                                        $text_body = imap_base64($text_body);
                                        break;
                                    case 4: // QUOTED-PRINTABLE
                                        $text_body = imap_qprint($text_body);
                                        break;
                                }
                            }
                            $text_body = imap_utf8($text_body);
                        }
                    }
                } else {
                    //  ПРОСТОЕ ПИСЬМО (одна часть) - ДОБАВЛЯЕМ СЮДА
                    $body = imap_body($imap_connection, $email_number);

                    // Ваш новый код с декодированием
                    $structure = imap_fetchstructure($imap_connection, $email_number);
                    $encoding = $structure->encoding ?? 0;

                    switch ($encoding) {
                        case 3: // BASE64
                            $body = imap_base64($body);
                            error_log("🔧 Декодировано BASE64 для простого письма");
                            break;
                        case 4: // QUOTED-PRINTABLE
                            $body = imap_qprint($body);
                            break;
                    }

                    $text_body = imap_utf8($body);
                }
            }

            // Выбираем тело письма (предпочтительно HTML)
            $clean_body = !empty($html_body) ? $html_body : $text_body;

            error_log("🔍 Текст после извлечения: " . substr(strip_tags($clean_body), 0, 200));
            $clean_body = remove_obvious_quotations($clean_body);
            error_log("🔍 Текст ПОСЛЕ remove_obvious_quotations: " . substr(strip_tags($clean_body), 0, 200));

            // 🔧 ОСОБАЯ ОБРАБОТКА ТОЛЬКО ДЛЯ ВХОДЯЩИХ ПИСЕМ С ВЛОЖЕНИЯМИ
            if (!empty($attachments) && !empty($html_body)) {
                error_log("🎯 Начинаем извлечение текста для письма с вложениями");

                // Функция для извлечения основного текста из HTML
                function extract_main_text_from_html($html)
                {
                    if (empty($html))
                        return $html;

                    error_log("🔍 HTML для извлечения: " . substr(strip_tags($html), 0, 200));

                    // Пытаемся найти основной текст в блоках Mail.ru
                    if (preg_match('/<div[^>]*class="[^"]*cl-[a-z0-9]+[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
                        $inner_html = $matches[1];

                        //  ИЗВЛЕКАЕМ ТОЛЬКО ПЕРВЫЙ ТЕКСТ ДО ПЕРВОГО БЛОКА ЦИТАТЫ
                        // Ищем где начинается цитата или следующее сообщение
                        if (preg_match('/^(.*?)(<blockquote|<div[^>]*class="[^"]*mail-quote|<div[^>]*data-signature-widget)/is', $inner_html, $text_match)) {
                            $main_text = $text_match[1];
                        } else {
                            $main_text = $inner_html;
                        }

                        $text = trim(strip_tags($main_text));
                        error_log("🎯 Извлечен только новый текст: " . substr($text, 0, 100));

                        if (!empty($text)) {
                            return $main_text; // Возвращаем HTML только нового текста
                        }
                    }

                    // Если не нашли - возвращаем чистый текст
                    $clean_text = strip_tags($html);
                    error_log("🎯 Возвращаем чистый текст: " . substr($clean_text, 0, 100));
                    return $clean_text;
                }

                $clean_body = extract_main_text_from_html($clean_body);
                error_log("🎯 Для письма с вложениями извлечен основной текст");
            }

            // После извлечения вложений добавьте:
            error_log("======= ОТЛАДКА ВЛОЖЕНИЙ В parse_incoming_emails() =======");
            error_log("📧 Письмо: " . ($clean_subject ?? 'без темы'));
            error_log("📎 Количество вложений: " . count($attachments));

            foreach ($attachments as $index => $attachment) {
                error_log("   Вложение $index:");
                error_log("   - file_name: " . $attachment['file_name']);
                error_log("   - file_url: " . ($attachment['file_url'] ?? 'NULL'));
                error_log("   - file_type: " . ($attachment['file_type'] ?? 'NULL'));
                error_log("   - file_size: " . ($attachment['file_size'] ?? '0'));
            }
            error_log("=====================================================");

            // Проверяем окончательный текст
            $final_text = trim(strip_tags($clean_body));
            error_log("🔍 ФИНАЛЬНЫЙ текст: '" . $final_text . "'");
            error_log("🔍 Длина финального текста: " . strlen($final_text));

            // Если текст пустой, ставим заглушку
            if (empty($final_text)) {
                $clean_body = '<p>Письмо без текста (только вложения)</p>';
                error_log("📝 Письмо без текста, установлена заглушка");
            } else {
                $text_preview = substr($final_text, 0, 100);
                error_log("📝 Текст письма извлечен: " . $text_preview . "...");
            }

            if (!empty($attachments)) {
                error_log("📎 Найдено вложений: " . count($attachments));
                foreach ($attachments as $attachment) {
                    error_log("   - " . $attachment['file_name'] . " (" . $attachment['file_type'] . ")");
                }
            }

            //  СОХРАНЯЕМ СООБЩЕНИЕ В БАЗУ ДАННЫХ И ПОЛУЧАЕМ message_id
            $message_data = array(
                'dialog_id' => $current_dialog_id,
                'sender_email' => $actual_from_email,
                'message' => $clean_body,
                'direction' => 'incoming',
                'sent_at' => process_email_date($overview[0]->date ?? ''),
                'subject' => $clean_subject,
                'original_subject' => $original_subject,
                'message_id_header' => $overview[0]->message_id ?? '',
                'has_images' => contains_images($clean_body) ? 1 : 0
            );

            // Сохраняем сообщение в БД
            $wpdb->insert("{$wpdb->prefix}crm_messages", $message_data);
            $message_id = $wpdb->insert_id;

            if ($message_id) {
                error_log("✅ Сообщение сохранено в БД с ID: $message_id");

                //  СОХРАНЯЕМ ВЛОЖЕНИЯ В БАЗУ ДАННЫХ

                //  СОХРАНЯЕМ ВЛОЖЕНИЯ В БАЗУ ДАННЫХ ТОЛЬКО ЕСЛИ ФАЙЛЫ ВКЛЮЧЕНЫ
                $files_enabled_option = get_option("crm_files_enabled_{$current_dialog_id}", '0');
                $files_enabled = ($files_enabled_option === '1');

                if ($files_enabled && !empty($attachments)) {
                    error_log("📎 Режим файлов ВКЛЮЧЕН, сохраняем " . count($attachments) . " вложений в БД");

                    foreach ($attachments as $attachment) {
                        $attachment_data = array(
                            'message_id' => $message_id,
                            'file_name' => $attachment['file_name'],
                            'file_url' => $attachment['file_url'] ?? '',
                            'file_type' => $attachment['file_type'] ?? '',
                            'file_size' => $attachment['file_size'] ?? 0,
                            'created_at' => current_time('mysql')
                        );

                        $wpdb->insert("{$wpdb->prefix}crm_attachments", $attachment_data);
                        error_log("✅ Вложение сохранено в БД: " . $attachment['file_name']);
                    }
                } else {
                    error_log("📝 Режим файлов ОТКЛЮЧЕН, НЕ сохраняем вложения в БД");
                    // Вложения остаются в $attachments для отображения, но не сохраняются в БД
                }
            } else {
                error_log("❌ Ошибка сохранения сообщения в БД");
            }

            // Сохраняем информацию о письме для возврата
            $replies[] = array(
                'id' => 'email_' . $overview[0]->uid,
                'message' => $clean_body,
                'email' => $actual_from_email,
                'sent_at' => process_email_date($overview[0]->date ?? ''),
                'direction' => 'incoming',
                'subject' => $clean_subject,
                'original_subject' => $original_subject,
                'message_id' => $overview[0]->message_id ?? '',
                'has_images' => contains_images($clean_body),
                'dialog_id' => $current_dialog_id,
                'attachments' => $attachments,
                'db_message_id' => $message_id // Добавляем ID из БД
            );

            error_log("✅ Добавлено письмо: $clean_subject от $actual_from_email для диалога $current_dialog_id, ID в БД: $message_id");
        }
    } else {
        error_log("📭 Парсер: письма не найдены" . ($dialog_id ? " для диалога $dialog_id" : ""));
    }

    imap_close($imap_connection);

    error_log("📨 Итог парсера: " . count($replies) . " писем от $client_email");
    return $replies;
}





// ✅ ИСПРАВЛЕННАЯ ФУНКЦИЯ СОХРАНЕНИЯ НАСТРОЕК

add_action('wp_ajax_update_file_setting', function () {
    if (!wp_verify_nonce($_POST['nonce'], 'crm_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }

    $dialog_id = intval($_POST['dialog_id']);
    $files_enabled = $_POST['files_enabled'] === '1' ? '1' : '0';

    error_log("💾 Сохраняем настройку для диалога {$dialog_id}: '{$files_enabled}'");

    // Сохраняем настройку
    $result = update_option("crm_files_enabled_{$dialog_id}", $files_enabled);

    // Проверяем что сохранилось
    $saved_value = get_option("crm_files_enabled_{$dialog_id}");
    error_log("💾 Проверка сохранения: ожидали '{$files_enabled}', получили '{$saved_value}'");

    wp_send_json_success([
        'files_enabled' => $files_enabled === '1',
        'saved_value' => $saved_value,
        'message' => $files_enabled === '1' ? 'Файлы включены' : 'Файлы отключены'
    ]);
});

// ✅ ДОБАВЬТЕ В functions.php
add_action('wp_ajax_get_file_setting', function () {
    if (!wp_verify_nonce($_POST['nonce'], 'crm_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }

    $dialog_id = sanitize_text_field($_POST['dialog_id']);

    $files_enabled = get_option("crm_files_enabled_{$dialog_id}", '1') === '1';

    error_log("🔍 Запрос настроек для диалога {$dialog_id}: " . ($files_enabled ? 'ВКЛ' : 'ВЫКЛ'));

    wp_send_json_success([
        'files_enabled' => $files_enabled,
        'dialog_id' => $dialog_id
    ]);
});



//  ФУНКЦИЯ СОХРАНЕНИЯ ФАЙЛА НА СЕРВЕР

function extract_email_attachments($imap_connection, $email_number, $dialog_id)
{
    error_log("🎯 ФУНКЦИЯ extract_email_attachments() ВЫЗВАНА!");

    //  ПРОВЕРЯЕМ НАСТРОЙКУ ФАЙЛОВ ПЕРЕД ИЗВЛЕЧЕНИЕМ
    $files_enabled_option = get_option("crm_files_enabled_{$dialog_id}", '0');
    $files_enabled = ($files_enabled_option === '1');

    if (!$files_enabled) {
        error_log("📝 extract_email_attachments: ФАЙЛЫ ОТКЛЮЧЕНЫ, пропускаем сохранение");
        return array(); // Возвращаем пустой массив
    }

    $attachments = array();
    $structure = imap_fetchstructure($imap_connection, $email_number);

    // 📁 ИЩЕМ ПРИКРЕПЛЕННЫЕ ФАЙЛЫ В СТРУКТУРЕ
    if (!empty($structure->parts)) {
        foreach ($structure->parts as $part_num => $part) {
            $part_id = $part_num + 1;

            $is_attachment = false;
            $filename = '';

            // Проверяем параметры filename
            if ($part->ifdparameters) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $param->value;
                        $is_attachment = true;
                        break;
                    }
                }
            }

            if (!$filename && $part->ifparameters) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                        $is_attachment = true;
                        break;
                    }
                }
            }

            //  ЕСЛИ НАШЛИ ПРИКРЕПЛЕННЫЙ ФАЙЛ
            if ($is_attachment && $filename) {
                $filename = decode_email_subject($filename);

                error_log("✅ НАЙДЕН ПРИКРЕПЛЕННЫЙ ФАЙЛ: " . $filename);

                //  ИЗВЛЕКАЕМ И СОХРАНЯЕМ ФАЙЛ В CRM-EMAILS
                $file_path = save_attachment_to_server($imap_connection, $email_number, $part, $part_id, $filename, $dialog_id);

                if ($file_path) {
                    $upload_dir = wp_upload_dir();
                    $real_file_path = $upload_dir['basedir'] . $file_path;
                    $real_file_size = filesize($real_file_path);
                    $formatted_size = format_file_size($real_file_size);
                    $file_icon = get_file_icon($filename);

                    //  ФОРМИРУЕМ ПОЛНЫЙ URL ДЛЯ СКАЧИВАНИЯ
                    $web_url = $upload_dir['baseurl'] . $file_path;

                    $html_block = '
                    <div class="attach-list" style="border:1px solid #ddd; border-radius:8px; padding:15px; margin:10px 0; background:#f8f9fa;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:40px; height:40px; background:' . $file_icon['color'] . '; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                <span style="color:white; font-weight:bold;">' . $file_icon['ext'] . '</span>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:500; color:#333;">' . htmlspecialchars($filename) . '</div>
                                <div style="font-size:12px; color:#666;">' . $formatted_size . ' · ' . strtoupper($file_icon['ext']) . ' документ</div>
                            </div>
                            <a href="' . $web_url . '" target="_blank" style="background:#005ff9; color:white; padding:8px 16px; border-radius:4px; text-decoration:none; font-size:14px;">
                                Скачать
                            </a>
                        </div>
                    </div>';

                    $attachments[] = array(
                        'file_name' => $filename,
                        'file_type' => $part->subtype,
                        'file_size' => $real_file_size,
                        'file_url' => $file_path, // Относительный путь для БД
                        'file_path' => $real_file_path, // Полный путь на сервере
                        'web_url' => $web_url, // Полный URL для скачивания
                        'html_block' => $html_block,
                        'email_part' => $part_id,
                        'source' => 'server_file'
                    );
                } else {
                    error_log("❌ Ошибка сохранения файла: " . $filename);

                    $simple_html = '<div style="border:1px solid #ccc; padding:10px; margin:5px 0; background:#f9f9f9;">📎 ' . htmlspecialchars($filename) . ' (ошибка загрузки)</div>';

                    $attachments[] = array(
                        'file_name' => $filename,
                        'file_type' => $part->subtype,
                        'file_size' => 0,
                        'file_url' => '',
                        'file_path' => '',
                        'web_url' => '',
                        'html_block' => $simple_html,
                        'email_part' => $part_id,
                        'source' => 'attachment_error'
                    );
                }
            }
        }
    }

    error_log("📎 ИТОГО вложений: " . count($attachments));
    return $attachments;
}
function save_attachment_to_server($imap_connection, $email_number, $part, $part_id, $filename, $dialog_id)
{
    // Извлекаем бинарные данные файла
    $file_data = imap_fetchbody($imap_connection, $email_number, $part_id);

    // Декодируем в зависимости от кодировки
    if ($part->encoding == 3) { // BASE64
        $file_data = base64_decode($file_data);
    } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
        $file_data = quoted_printable_decode($file_data);
    }

    // Проверяем что данные не пустые
    if (empty($file_data)) {
        error_log("❌ Пустые данные файла: " . $filename);
        return false;
    }

    //  ИСПОЛЬЗУЕМ ЕДИНУЮ ПАПКУ CRM-EMAILS ДЛЯ ВСЕХ ФАЙЛОВ
    $upload_dir = wp_upload_dir();
    $crm_uploads = $upload_dir['basedir'] . '/crm-emails';

    // Создаем папку crm-emails если не существует
    if (!file_exists($crm_uploads)) {
        wp_mkdir_p($crm_uploads);
    }

    // Создаем подпапку для диалога
    $dialog_folder = $crm_uploads . '/dialog-' . $dialog_id;
    if (!file_exists($dialog_folder)) {
        wp_mkdir_p($dialog_folder);
    }

    // Генерируем безопасное имя файла
    $safe_filename = sanitize_file_name($filename);

    // Добавляем timestamp для уникальности
    $file_info = pathinfo($safe_filename);
    $unique_filename = $file_info['filename'] . '_' . time() . '.' . ($file_info['extension'] ?? 'bin');

    $file_path = $dialog_folder . '/' . $unique_filename;

    //  ДОБАВЬТЕ ПОДРОБНОЕ ЛОГИРОВАНИЕ
    error_log("📁 СОХРАНЕНИЕ ФАЙЛА В CRM-EMAILS:");
    error_log("   - Исходное имя: " . $filename);
    error_log("   - Безопасное имя: " . $unique_filename);
    error_log("   - Полный путь на сервере: " . $file_path);
    error_log("   - Папка CRM: " . $crm_uploads);
    error_log("   - Папка диалога: " . $dialog_folder);
    error_log("   - Размер данных: " . strlen($file_data) . " байт");

    // Сохраняем файл
    if (file_put_contents($file_path, $file_data)) {
        $file_size = filesize($file_path);

        // Формируем URL через WordPress (относительный от baseurl)
        $file_url = $upload_dir['baseurl'] . '/crm-emails/dialog-' . $dialog_id . '/' . $unique_filename;

        error_log("✅ Файл успешно сохранен в crm-emails:");
        error_log("   - Серверный путь: " . $file_path);
        error_log("   - Веб URL: " . $file_url);
        error_log("   - Размер файла: " . $file_size . " байт");

        // Возвращаем относительный путь для использования в HTML
        return '/crm-emails/dialog-' . $dialog_id . '/' . $unique_filename;
    } else {
        error_log("❌ Ошибка сохранения файла: " . $file_path);
        return false;
    }
}

//  ФУНКЦИЯ ФОРМАТИРОВАНИЯ РАЗМЕРА ФАЙЛА
function format_file_size($bytes)
{
    if ($bytes == 0)
        return '0 Б';
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

//  ФУНКЦИЯ ДЛЯ ОПРЕДЕЛЕНИЯ ИКОНКИ ФАЙЛА
function get_file_icon($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'pdf' => ['color' => '#f96657', 'ext' => 'PDF'],
        'doc' => ['color' => '#2b579a', 'ext' => 'DOC'],
        'docx' => ['color' => '#2b579a', 'ext' => 'DOC'],
        'xls' => ['color' => '#217346', 'ext' => 'XLS'],
        'xlsx' => ['color' => '#217346', 'ext' => 'XLS'],
        'zip' => ['color' => '#8052a3', 'ext' => 'ZIP'],
        'rar' => ['color' => '#8052a3', 'ext' => 'RAR'],
        'jpg' => ['color' => '#dba617', 'ext' => 'IMG'],
        'jpeg' => ['color' => '#dba617', 'ext' => 'IMG'],
        'png' => ['color' => '#dba617', 'ext' => 'IMG'],
        'default' => ['color' => '#6c757d', 'ext' => 'FILE']
    ];

    return $icons[$ext] ?? $icons['default'];
}

function get_complete_html($imap_connection, $email_number, $structure, $part_number = '')
{
    $html_parts = [];

    if (isset($structure->parts)) {
        // Обрабатываем все части multipart
        foreach ($structure->parts as $index => $part) {
            $subpart_number = $part_number ? $part_number . '.' . ($index + 1) : ($index + 1);

            // Получаем данные части
            $part_data = imap_fetchbody($imap_connection, $email_number, $subpart_number);

            // Декодируем в зависимости от кодировки
            if ($part->encoding == 3) { // BASE64
                $part_data = base64_decode($part_data);
            } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                $part_data = quoted_printable_decode($part_data);
            }

            // Если это HTML или текст, добавляем
            if ($part->type == 0 && in_array(strtoupper($part->subtype), ['HTML', 'PLAIN'])) {
                $html_parts[] = $part_data;
            }

            // Рекурсивно обрабатываем вложенные части
            if (isset($part->parts)) {
                $nested_html = get_complete_html($imap_connection, $email_number, $part, $subpart_number);
                if ($nested_html) {
                    $html_parts[] = $nested_html;
                }
            }
        }
    } else {
        // Обрабатываем одиночную часть
        $part_data = imap_fetchbody($imap_connection, $email_number, $part_number ?: '1');

        if ($structure->encoding == 3) {
            $part_data = base64_decode($part_data);
        } elseif ($structure->encoding == 4) {
            $part_data = quoted_printable_decode($part_data);
        }

        if ($structure->type == 0 && in_array(strtoupper($structure->subtype), ['HTML', 'PLAIN'])) {
            $html_parts[] = $part_data;
        }
    }

    return implode('', $html_parts);
}

//  РЕКУРСИВНАЯ ФУНКЦИЯ ДЛЯ ОБРАБОТКИ MULTIPART СТРУКТУРЫ
function process_part($imap_connection, $email_number, $part, &$attachments, $dialog_id, $part_number = '')
{
    // Если это multipart, обрабатываем каждую часть рекурсивно
    if (isset($part->parts)) {
        foreach ($part->parts as $index => $subpart) {
            $subpart_number = $part_number ? $part_number . '.' . ($index + 1) : ($index + 1);
            process_part($imap_connection, $email_number, $subpart, $attachments, $dialog_id, $subpart_number);
        }
    } else {
        //  ПРОВЕРЯЕМ ЯВЛЯЕТСЯ ЛИ ЧАСТЬ ПРИКРЕПЛЕННЫМ ФАЙЛОМ
        $is_attachment = false;
        $filename = '';

        // Проверяем Content-Disposition: attachment
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) == 'filename') {
                    $filename = $param->value;
                    $is_attachment = true;
                    break;
                }
            }
        }

        // Проверяем параметры name в Content-Type
        if (!$filename && $part->ifparameters) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) == 'name') {
                    $filename = $param->value;
                    $is_attachment = true;
                    break;
                }
            }
        }

        //  ЕСЛИ НАШЛИ ПРИКРЕПЛЕННЫЙ ФАЙЛ
        if ($is_attachment && $filename) {
            $filename = decode_email_subject($filename);

            error_log("✅ НАЙДЕН ПРИКРЕПЛЕННЫЙ ФАЙЛ: " . $filename . " (часть: $part_number)");

            //  ИЗВЛЕКАЕМ БИНАРНЫЕ ДАННЫЕ ФАЙЛА
            $file_data = imap_fetchbody($imap_connection, $email_number, $part_number);

            // Декодируем в зависимости от кодировки
            if ($part->encoding == 3) { // BASE64
                $file_data = base64_decode($file_data);
            } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                $file_data = quoted_printable_decode($file_data);
            }

            //  СОХРАНЯЕМ ФАЙЛ НА СЕРВЕР
            $file_path = save_attachment_to_server($filename, $file_data, $dialog_id);

            if ($file_path) {
                error_log("✅ Файл сохранен: " . $file_path);

                //  СОЗДАЕМ HTML БЛОК С ССЫЛКОЙ НА ФАЙЛ
                $html_with_link = '<a href="' . $file_path . '" target="_blank" style="border:1px solid #ccc; padding:10px; margin:5px 0; background:#f9f9f9; display:block;">📎 ' . htmlspecialchars($filename) . '</a>';

                $attachments[] = array(
                    'file_name' => $filename,
                    'file_type' => $part->subtype,
                    'file_size' => strlen($file_data),
                    'file_url' => $html_with_link,
                    'email_part' => $part_number,
                    'source' => 'multipart_attachment'
                );
            } else {
                error_log("❌ Ошибка сохранения файла: " . $filename);

                // Если не удалось сохранить, создаем простой HTML
                $simple_html = '<div style="border:1px solid #ccc; padding:10px; margin:5px 0; background:#f9f9f9;">📎 ' . htmlspecialchars($filename) . ' (ошибка загрузки)</div>';

                $attachments[] = array(
                    'file_name' => $filename,
                    'file_type' => $part->subtype,
                    'file_size' => 0,
                    'file_url' => $simple_html,
                    'email_part' => $part_number,
                    'source' => 'attachment_error'
                );
            }
        }
    }
}



//  ФУНКЦИЯ ПОИСКА ССЫЛКИ ДЛЯ КОНКРЕТНОГО ФАЙЛА
function find_download_url_for_file($download_urls, $filename)
{
    foreach ($download_urls as $url) {
        // Декодируем URL для поиска
        $decoded_url = urldecode($url);
        // Ищем название файла в декодированном URL
        if (strpos($decoded_url, $filename) !== false) {
            return $url; // Возвращаем оригинальный URL
        }
    }
    return null;
}
//  ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ДЛЯ ОПРЕДЕЛЕНИЯ РАСШИРЕНИЯ ФАЙЛА
function get_file_extension($filename)
{
    $parts = explode('.', $filename);
    return strtolower(end($parts));
}

//  ФУНКЦИЯ ПОИСКА HTML БЛОКА ДЛЯ КОНКРЕТНОГО ФАЙЛА
function find_html_block_for_file($html_blocks, $filename)
{
    foreach ($html_blocks as $html_block) {
        // Ищем название файла в HTML блоке
        if (strpos($html_block, $filename) !== false) {
            return $html_block;
        }
    }
    return null;
}

//  ФУНКЦИЯ ДЛЯ СОЗДАНИЯ HTML БЛОКА ДЛЯ ФАЙЛА
function create_html_block_for_file($filename, $filetype)
{
    $file_icon = get_file_icon_by_type($filetype);

    $html = '
    <div class="crm-file-attachment" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9; max-width: 300px;">
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <div style="font-size: 24px; margin-right: 10px;">' . $file_icon . '</div>
            <div>
                <div style="font-weight: bold; font-size: 14px;">' . htmlspecialchars($filename) . '</div>
                <div style="font-size: 12px; color: #666;">' . strtoupper($filetype) . ' файл</div>
            </div>
        </div>
        <div style="background: #e9ecef; padding: 8px 12px; border-radius: 4px; font-size: 12px; color: #495057;">
            📎 Прикрепленный файл из письма
        </div>
    </div>';

    return $html;
}

//  ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ИКОНКИ ПО ТИПУ ФАЙЛА
function get_file_icon_by_type($filetype)
{
    $filetype = strtolower($filetype);

    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'zip' => '📦',
        'rar' => '📦',
        '7z' => '📦',
        'txt' => '📄',
        'default' => '📎'
    ];

    return $icons[$filetype] ?? $icons['default'];
}
function save_client_replies_to_db($client_email, $replies, $dialog_id = null)
{
    global $wpdb;

    error_log("💾 СОХРАНЕНИЕ ВХОДЯЩИХ СООБЩЕНИЙ В БД");
    error_log("📧 Клиент: $client_email");
    error_log("📨 Сообщений для обработки: " . count($replies));
    error_log("💬 Диалог: " . ($dialog_id ?? 'не указан'));

    $saved_count = 0;
    // 🔧 ПОДКЛЮЧАЕМ КОНФИГ
    global $EMAIL_CONFIG;


    $our_email = array_keys($EMAIL_CONFIG['accounts'])[0];

    foreach ($replies as $index => $reply) {
        error_log("\n--- Обработка сообщения $index ---");

        // ⭐ ПРИОРИТЕТ: используем dialog_id из письма, потом параметр функции
        $current_dialog_id = $reply['dialog_id'] ?? $dialog_id;

        if (!$current_dialog_id) {
            error_log("❌ ПРОПУСК: не указан dialog_id");
            continue;
        }

        error_log("🔍 Диалог ID: $current_dialog_id");

        // Декодируем тему
        $original_subject = $reply['original_subject'] ?? '';
        $decoded_subject = decode_email_subject($original_subject);
        $subject = empty($decoded_subject) ? 'Письмо от клиента' : $decoded_subject;

        $message_content = $reply['message'] ?? '';
        $sent_at = $reply['sent_at'] ?? '';

        error_log("📝 Тема: $subject");
        error_log("📅 Дата: $sent_at");
        error_log("📎 Вложений: " . count($reply['attachments'] ?? []));

        // Отладка вложений перед сохранением
        if (!empty($reply['attachments'])) {
            foreach ($reply['attachments'] as $attach_index => $attachment) {
                error_log("   📎 Вложение $attach_index:");
                error_log("      - Имя: " . $attachment['file_name']);
                error_log("      - URL: " . ($attachment['file_url'] ?? 'НЕТ ССЫЛКИ'));
                error_log("      - Тип: " . ($attachment['file_type'] ?? 'не указан'));
                error_log("      - Размер: " . ($attachment['file_size'] ?? 0));
            }
        }

        // Хеш для проверки дубликатов
        $message_hash_full = md5($message_content . $original_subject . $sent_at . $client_email);

        // Проверяем существование сообщения
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}crm_messages 
            WHERE message_hash = %s OR (
                message = %s AND sender_email = %s AND sent_at = %s
            )
        ", $message_hash_full, $message_content, $client_email, $sent_at));

        if ($existing) {
            error_log("⏩ ПРОПУСК: дубликат сообщения для диалога $current_dialog_id");
            continue;
        }

        // Сохраняем основное сообщение
        $message_data = array(
            'dialog_id' => $current_dialog_id,
            'message' => $message_content,
            'email' => $our_email,
            'sender_email' => $client_email,
            'subject' => $subject,
            'sent_at' => $sent_at,
            'direction' => 'incoming',
            'message_hash' => $message_hash_full,
            'attachments' => '',
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert("{$wpdb->prefix}crm_messages", $message_data);

        if (!$result) {
            error_log("❌ ОШИБКА сохранения сообщения: " . $wpdb->last_error);
            continue;
        }

        $message_id = $wpdb->insert_id;
        error_log("✅ Сообщение сохранено с ID: $message_id");

        // Сохраняем вложения
        if (!empty($reply['attachments'])) {
            $attachments_saved = 0;

            foreach ($reply['attachments'] as $attachment) {
                $file_data = array(
                    'message_id' => $message_id,
                    'file_url' => $attachment['file_url'] ?? '',
                    'file_name' => $attachment['file_name'],
                    'file_type' => $attachment['file_type'] ?? pathinfo($attachment['file_name'], PATHINFO_EXTENSION),
                    'file_size' => $attachment['file_size'] ?? 0,
                    'direction' => 'incoming',
                    'attached_at' => current_time('mysql')
                );

                $file_result = $wpdb->insert($wpdb->prefix . 'crm_message_files', $file_data);

                if ($file_result) {
                    $attachments_saved++;
                    error_log("   ✅ Вложение сохранено: " . $attachment['file_name']);

                    // Проверяем что ссылка действительно сохранилась
                    if (!empty($attachment['file_url'])) {
                        $saved_url = $wpdb->get_var($wpdb->prepare(
                            "SELECT file_url FROM {$wpdb->prefix}crm_message_files WHERE id = %d",
                            $wpdb->insert_id
                        ));
                        error_log("   🔗 Ссылка в БД: " . ($saved_url ?: 'ПУСТО'));
                    }
                } else {
                    error_log("   ❌ Ошибка сохранения вложения '" . $attachment['file_name'] . "': " . $wpdb->last_error);
                }
            }

            error_log("📎 Итог вложений: $attachments_saved/" . count($reply['attachments']) . " сохранено");
        } else {
            error_log("📭 Нет вложений для сохранения");
        }

        $saved_count++;
        error_log("✅ УСПЕХ: сообщение $index сохранено в диалог $current_dialog_id");
    }

    // Финальный отчет
    error_log("\n🎯 ИТОГ СОХРАНЕНИЯ:");
    error_log("   - Обработано сообщений: " . count($replies));
    error_log("   - Сохранено новых: $saved_count");
    error_log("   - Пропущено (дубликаты): " . (count($replies) - $saved_count));

    return $saved_count;
}

// ✅ ФУНКЦИЯ ДЛЯ ИЗВЛЕЧЕНИЯ ИНФОРМАЦИИ ИЗ HTML БЛОКА
function extract_file_info_from_html_block($html_block)
{
    $file_info = [];

    // 1. Извлекаем название файла
    preg_match('/<small[^>]*>(.*?)<\/small>/is', $html_block, $name_matches);
    if (!empty($name_matches[1])) {
        $file_info['file_name'] = trim(strip_tags($name_matches[1]));
    }

    // 2. Извлекаем preview URL
    preg_match('/background-image:\s*url\([^"]*"([^"]+)"[^"]*\)/i', $html_block, $preview_matches);
    if (!empty($preview_matches[1])) {
        $file_info['preview_url'] = $preview_matches[1];
    }

    // 3. Определяем тип файла по названию
    if (!empty($file_info['file_name'])) {
        $file_info['file_type'] = pathinfo($file_info['file_name'], PATHINFO_EXTENSION);
    }

    return !empty($file_info['file_name']) ? $file_info : null;
}


//для нужной темы
function get_dialog_subject($dialog_id)
{
    global $wpdb;

    // Пробуем получить тему из последнего исходящего сообщения
    $dialog_subject = $wpdb->get_var($wpdb->prepare("
        SELECT subject FROM {$wpdb->prefix}crm_messages 
        WHERE dialog_id = %d AND direction = 'outgoing' 
        ORDER BY sent_at DESC LIMIT 1
    ", $dialog_id));

    // Если нет исходящих - берем тему из любого сообщения диалога
    if (empty($dialog_subject)) {
        $dialog_subject = $wpdb->get_var($wpdb->prepare("
            SELECT subject FROM {$wpdb->prefix}crm_messages 
            WHERE dialog_id = %d 
            ORDER BY sent_at DESC LIMIT 1
        ", $dialog_id));
    }

    // Если все еще нет темы - берем название диалога
    if (empty($dialog_subject)) {
        $dialog_data = $wpdb->get_row($wpdb->prepare("
            SELECT name FROM {$wpdb->prefix}crm_dialogs WHERE id = %d
        ", $dialog_id));
        $dialog_subject = $dialog_data->name ?? 'Переписка с клиентом';
    }

    error_log("🎯 Тема диалога $dialog_id: $dialog_subject");
    return $dialog_subject;
}
// 🔧 ПАРСЕР ПОЧТЫ - глобальная функция// ✅ ОСНОВНАЯ ФУНКЦИЯ ПАРСЕРА ПОЧТЫ
// 





add_action('wp_ajax_download_incoming_attachment', 'handle_download_incoming_attachment');
function handle_download_incoming_attachment()
{
    global $wpdb;

    error_log("👁️ AJAX: download_incoming_attachment вызван");

    // Проверяем nonce (для GET или POST)
    $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'crm_nonce')) {
        wp_die('Security check failed');
    }

    $message_id = intval($_POST['message_id'] ?? $_GET['message_id'] ?? 0);

    //  ГЛАВНОЕ ИСПРАВЛЕНИЕ: Правильно декодируем кириллические названия
    $file_name = $_POST['file_name'] ?? $_GET['file_name'] ?? '';
    $file_name = urldecode(sanitize_text_field($file_name));

    $file_name = wp_unslash($file_name);



    error_log("🔍 Ищем файл для сообщения: $message_id, файл: '$file_name'");

    if (!$message_id || !$file_name) {
        wp_send_json_error('Не указаны message_id или file_name');
    }

    // 1. Сначала ищем файл по точному совпадению
    $file_record = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}crm_message_files 
        WHERE message_id = %d AND file_name = %s
    ", $message_id, $file_name));

    //  2. Если не нашли - пробуем найти по LIKE (для старых сообщений с проблемами кодировки)
    if (!$file_record) {
        error_log("🔍 Файл не найден по точному совпадению, пробуем LIKE поиск");
        $file_record = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}crm_message_files 
            WHERE message_id = %d AND file_name LIKE %s
        ", $message_id, '%' . $wpdb->esc_like($file_name) . '%'));
    }

    //  3. Если все еще не нашли - логируем для отладки
    if (!$file_record) {
        error_log("❌ Файл '$file_name' не найден в БД для сообщения $message_id");

        // Логируем все файлы этого сообщения для отладки
        $all_files = $wpdb->get_results($wpdb->prepare("
            SELECT file_name FROM {$wpdb->prefix}crm_message_files 
            WHERE message_id = %d
        ", $message_id));

        error_log("📋 Все файлы сообщения $message_id:");
        foreach ($all_files as $file) {
            error_log("   - '" . $file->file_name . "'");
        }

        wp_send_json_error('Файл не найден в базе данных');
    }

    error_log("✅ Файл найден в БД: " . $file_record->file_url);

    $file_url = $file_record->file_url;

    // Преобразуем относительный путь в абсолютный
    if (strpos($file_url, '/') === 0) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . $file_url;
        error_log("🔍 Относительный путь, преобразуем в: " . $file_path);
    } else {
        $file_path = $file_url;
    }

    error_log("🔍 Полный путь к файлу: " . $file_path);

    if (!file_exists($file_path)) {
        error_log("❌ Файл не существует на сервере: " . $file_path);

        //  Пробуем альтернативные пути
        $upload_dir = wp_upload_dir();

        // Вариант 1: В папке crm_files
        $alternative_path1 = $upload_dir['basedir'] . '/crm_files/' . $file_record->file_name;
        error_log("🔍 Пробуем альтернативный путь 1: " . $alternative_path1);

        // Вариант 2: Декодированное название файла
        $decoded_file_name = urldecode($file_record->file_name);
        $alternative_path2 = $upload_dir['basedir'] . '/crm_files/' . $decoded_file_name;
        error_log("🔍 Пробуем альтернативный путь 2: " . $alternative_path2);

        if (file_exists($alternative_path1)) {
            $file_path = $alternative_path1;
            error_log("✅ Файл найден по альтернативному пути 1");
        } elseif (file_exists($alternative_path2)) {
            $file_path = $alternative_path2;
            error_log("✅ Файл найден по альтернативному пути 2");
        } else {
            wp_send_json_error('Файл не существует на сервере');
        }
    }

    error_log("✅ Файл существует, начинаем отдачу для ПРОСМОТРА");

    // Определяем MIME тип
    $mime_type = mime_content_type($file_path);
    if (!$mime_type) {
        $mime_type = 'application/octet-stream';
    }

    // Всегда режим просмотра (inline)
    error_log("👁️ Режим ПРОСМОТРА, MIME: " . $mime_type);
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($file_record->file_name) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    readfile($file_path);
    exit;
}

add_action('wp_ajax_download_incoming_attachment', 'handle_download_incoming_attachment');

// ⭐ ДОБАВЛЯЕМ ФУНКЦИЮ ПОИСКА ДИАЛОГА (в конец файла)
function find_dialog_for_email($email_subject, $client_email)
{
    global $wpdb;

    $clean_subject = preg_replace('/^(Re:|Fwd:|Ответ:)\s*/i', '', $email_subject);
    $clean_subject = trim($clean_subject);

    error_log("🔍 Поиск диалога для: '$clean_subject', email: $client_email");

    // ✅ ВАРИАНТ 1: Ищем по названию диалога (поле `name` в таблице `crm_dialogs`)
    $dialog = $wpdb->get_var($wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}crm_dialogs 
        LIMIT 1
    ", $clean_subject, $client_email));

    if ($dialog) {
        error_log("✅ Найден диалог по названию: $clean_subject");
        return $dialog;
    }

    // ✅ ВАРИАНТ 2: Ищем по теме сообщений (поле `subject` в таблице `crm_messages`)
    $dialog = $wpdb->get_var($wpdb->prepare("
        SELECT DISTINCT dialog_id 
        FROM {$wpdb->prefix}crm_messages 
        WHERE subject = %s 
        LIMIT 1
    ", $clean_subject, $client_email));

    if ($dialog) {
        error_log("✅ Найден диалог по теме сообщений: $clean_subject");
        return $dialog;
    }

    error_log("❌ Не найден диалог для: '$clean_subject'");
    return null;
}

// Добавляем только УМНУЮ ФИЛЬТРАЦИЮ по теме в существующий парсер

function contains_images($html_body)
{
    // Проверяем теги img (включая внешние ссылки)
    if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $html_body)) {
        return true;
    }

    // Проверяем base64 картинки
    if (strpos($html_body, 'data:image/') !== false) {
        return true;
    }

    // Проверяем внешние картинки (как в вашем примере)
    if (preg_match('/src="[^"]*\.(jpg|jpeg|png|gif|webp)/i', $html_body)) {
        return true;
    }

    return false;
}


// ✅ ФУНКЦИЯ ИЗВЛЕЧЕНИЯ И СОХРАНЕНИЯ КАРТИНОК
function extract_and_save_images($html_body, $email_number, $part_number)
{
    // 1. Сначала обрабатываем base64 картинки
    preg_match_all('/src="data:image\/([^;]+);base64,([^"]+)"/', $html_body, $base64_matches);

    if (!empty($base64_matches[0])) {
        error_log("🖼️ Найдено base64 картинок: " . count($base64_matches[0]));
        $html_body = process_base64_images($html_body, $base64_matches, $email_number, $part_number);
    }

    // 2. Теперь обрабатываем внешние картинки (как в вашем примере)
    preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/', $html_body, $external_matches);

    if (!empty($external_matches[1])) {
        error_log("🌐 Найдено внешних картинок: " . count($external_matches[1]));
        $html_body = process_external_images($html_body, $external_matches, $email_number, $part_number);
    }

    return $html_body;
}

function process_external_images($html_body, $matches, $email_number, $part_number)
{
    $upload_dir = wp_upload_dir();
    $crm_uploads = $upload_dir['basedir'] . '/crm-emails';

    if (!file_exists($crm_uploads)) {
        wp_mkdir_p($crm_uploads);
    }

    foreach ($matches[0] as $index => $full_match) {
        $image_url = $matches[1][$index];

        // Пропускаем уже обработанные base64 картинки
        if (strpos($image_url, 'data:image/') === 0) {
            continue;
        }

        // Добавляем протокол если нужно
        if (strpos($image_url, '//') === 0) {
            $image_url = 'https:' . $image_url;
        }

        // Скачиваем картинку
        $image_data = download_external_image($image_url);

        if ($image_data) {
            // Определяем расширение
            $extension = 'jpg';
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)/i', $image_url, $ext_matches)) {
                $extension = strtolower($ext_matches[1]);
            }

            $filename = 'external_' . $email_number . '_' . $part_number . '_img_' . $index . '.' . $extension;
            $filepath = $crm_uploads . '/' . $filename;

            // Сохраняем файл
            if (file_put_contents($filepath, $image_data)) {
                $file_url = $upload_dir['baseurl'] . '/crm-emails/' . $filename;

                // Заменяем внешний URL на локальный в HTML
                $html_body = str_replace(
                    $full_match,
                    '<img src="' . $file_url . '" style="max-width: 100%; height: auto;">',
                    $html_body
                );

                error_log("✅ Сохранена внешняя картинка: " . $filename);
            }
        }
    }

    return $html_body;
}

function download_external_image($url)
{
    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'redirection' => 5,
    ));

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        return wp_remote_retrieve_body($response);
    }

    error_log("❌ Не удалось скачать картинку: " . $url);
    return false;
}
// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

// 1. ФУНКЦИЯ ДЕКОДИРОВАНИЯ ТЕМЫ

function decode_email_subject($subject)
{
    if (empty($subject)) {
        return 'Без темы';
    }

    // ⭐ ПРОСТОЙ И НАДЕЖНЫЙ ВАРИАНТ - используем imap_utf8
    $decoded = imap_utf8($subject);

    // Если imap_utf8 не сработал, пробуем ручное декодирование
    if ($decoded === false || $decoded === $subject) {
        // Убираем лишние пробелы между MIME частями
        $subject = preg_replace('/\?=\s+=\?/', '?==?', $subject);

        // Декодируем MIME encoded words
        $decoded = preg_replace_callback(
            '/=\?([^?]+)\?([BQ])\?([^?]*)\?=/i',
            function ($matches) {
                $charset = $matches[1];
                $encoding = $matches[2];
                $text = $matches[3];

                if ($encoding == 'B') {
                    $decoded_text = base64_decode($text);
                } elseif ($encoding == 'Q') {
                    $decoded_text = quoted_printable_decode(str_replace('_', ' ', $text));
                }

                // Конвертируем кодировку если нужно
                if (function_exists('mb_convert_encoding') && $charset != 'UTF-8') {
                    $decoded_text = mb_convert_encoding($decoded_text, 'UTF-8', $charset);
                }

                return $decoded_text;
            },
            $subject
        );
    }

    // Финальная очистка
    $decoded = trim($decoded);
    $decoded = preg_replace('/[^\x20-\x7E\x{0410}-\x{044F}\x{0401}\x{0451}]/u', '', $decoded); // Убираем мусор

    return empty($decoded) ? 'Письмо от клиента' : $decoded;
}

function process_email_date($original_date)
{
    if (!$original_date) {
        return current_time('mysql');
    }

    try {
        $client_date = DateTime::createFromFormat(DateTime::RFC2822, $original_date);

        if ($client_date) {
            $moscow_tz = new DateTimeZone('Europe/Moscow');
            $client_date->setTimezone($moscow_tz);
            return $client_date->format('Y-m-d H:i:s');
        } else {
            // Резервный метод
            $timestamp = strtotime($original_date);
            $moscow_timestamp = $timestamp + (3 * 60 * 60);
            return date('Y-m-d H:i:s', $moscow_timestamp);
        }
    } catch (Exception $e) {
        error_log("⚠️ Ошибка обработки даты: " . $e->getMessage());
        return current_time('mysql');
    }
}
// 2. ФУНКЦИЯ ИЗВЛЕЧЕНИЯ ТОЛЬКО ТЕЛА ПИСЬМА
function extract_new_email_content($body)
{
    if (empty($body)) {
        return '';
    }

    // Убираем СТАРЫЕ цитаты (письма на которые отвечают), но сохраняем ВСЁ текущее письмо
    $patterns = [
        '/\nOn.*wrote:\n.*$/s',           // On Mon, Dec 1, 2025 at 10:00 AM, user@example.com wrote:
        '/\n-----*Original Message-----*\n.*$/s', // -----Original Message-----
        '/\nFrom:.*\nSent:.*\nTo:.*\nSubject:.*$/s', // Старые заголовки
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            $new_content = substr($body, 0, $matches[0][1]);
            if (strlen(trim($new_content)) > 10) {
                return trim($new_content);
            }
        }
    }

    // Если не нашли старых цитат - возвращаем всё письмо
    return trim($body);
}
// 3. ФУНКЦИЯ УДАЛЕНИЯ ОЧЕВИДНЫХ ЦИТАТ
function remove_obvious_quotations($body)
{
    $lines = explode("\n", $body);
    $clean_lines = [];
    $in_quote = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Начало цитаты старого письма
        if (
            preg_match('/^>+/', $trimmed) ||
            preg_match('/^On.*wrote:/', $trimmed) ||
            preg_match('/^-----*Original Message-----*$/', $trimmed)
        ) {
            $in_quote = true;
            continue;
        }

        // Пропускаем только явные заголовки старых писем
        if (
            $in_quote && (
                preg_match('/^From:/', $trimmed) ||
                preg_match('/^Sent:/', $trimmed) ||
                preg_match('/^To:/', $trimmed) ||
                preg_match('/^Subject:/', $trimmed)
            )
        ) {
            continue;
        }

        // Если это обычный текст или картинка - сохраняем
        $clean_lines[] = $line;
        $in_quote = false;
    }

    return implode("\n", $clean_lines);
}

// ✅ ФУНКЦИЯ СОХРАНЕНИЯ ПИСЕМ В БАЗУ ДАННЫХ

function find_dialog_by_subject($client_email, $subject)
{
    global $wpdb;

    if (empty($subject))
        return null;

    // Ищем диалог, где есть сообщения с похожей темой
    $dialog = $wpdb->get_row($wpdb->prepare("
        SELECT DISTINCT dialog_id 
        FROM {$wpdb->prefix}crm_messages 
        WHERE sender_email = %s 
        AND subject LIKE %s
        ORDER BY sent_at DESC 
        LIMIT 1
    ", $client_email, '%' . $wpdb->esc_like($subject) . '%'));

    return $dialog ? $dialog->dialog_id : null;
}


// 🔧  ФУНКЦИЯ: Проверяет и сохраняет письма в БД
function check_and_save_client_replies($dialog_id, $client_email)
{
    global $wpdb;

    $replies = get_client_email_replies($client_email);

    foreach ($replies as $reply) {
        // Создаем уникальный хеш для предотвращения дублирования
        $message_hash = md5($reply['message'] . $reply['date'] . $reply['subject']);

        // Проверяем, нет ли уже такого сообщения в БД
        // ✅ ПРАВИЛЬНО:
        $existing = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$wpdb->prefix}crm_messages  // ← $wpdb
    WHERE dialog_id = %d AND message_hash = %s
", $dialog_id, $message_hash));

        if (!$existing) {
            // Сохраняем новое входящее сообщение в БД
            $wpdb->insert(
                "{$wpdb->prefix}crm_messages",
                array(
                    'dialog_id' => $dialog_id,
                    'message' => $reply['message'],
                    'email' => $client_email,
                    'sent_at' => $reply['date'],
                    'direction' => 'incoming',
                    'message_hash' => $message_hash,
                    'attachments' => $reply['attachments'] ? json_encode($reply['attachments']) : '',
                    'created_at' => current_time('mysql')
                )
            );

            error_log("✅ Сохранено новое входящее сообщение в диалог $dialog_id от $client_email");
        }
    }
}

// 🔧 ОБНОВИТЕ структуру таблицы crm_messages (добавьте поле message_hash)
function update_crm_messages_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'crm_messages';

    // Проверяем существует ли поле message_hash
    $column_exists = $wpdb->get_var("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_NAME = '$table_name' 
        AND COLUMN_NAME = 'message_hash'
    ");

    if (!$column_exists) {
        $wpdb->query("
            ALTER TABLE $table_name 
            ADD COLUMN message_hash VARCHAR(32) DEFAULT NULL,
            ADD UNIQUE INDEX unique_message_hash (message_hash)
        ");
        error_log("✅ Добавлено поле message_hash в таблицу crm_messages");
    }
}
add_action('init', 'update_crm_messages_table');
function get_client_email_replies($client_email)
{
    $replies = array();

    try {
        // Настройки почты для Reg.ru
        global $EMAIL_CONFIG;


        $email = array_keys($EMAIL_CONFIG['accounts'])[0];
        $password = $EMAIL_CONFIG['accounts'][$email];

        $parser = new CRM_Email_Parser($email, $password);

        if ($parser->connect()) {
            $client_replies = $parser->getClientReplies($client_email, 30);

            foreach ($client_replies as $reply) {
                $replies[] = array(
                    'id' => 'email_' . $reply['id'],
                    'message' => $reply['body'],
                    'email' => $client_email,
                    'sent_at' => $reply['date'],
                    'direction' => 'incoming',
                    'attachments' => $reply['attachments'],
                    'subject' => $reply['subject']
                );
            }
        }
    } catch (Exception $e) {
        error_log('CRM Email Parser Error: ' . $e->getMessage());
    }

    return $replies;
}

// ✅ ДИАГНОСТИКА SMTP
function crm_phpmailer_debug($phpmailer)
{
    error_log("CRM PHPMailer Debug:");
    error_log("From: " . $phpmailer->From);
    error_log("FromName: " . $phpmailer->FromName);
    error_log("Subject: " . $phpmailer->Subject);
    error_log("Body length: " . strlen($phpmailer->Body));
    error_log("Is SMTP: " . ($phpmailer->isSMTP() ? 'Yes' : 'No'));
    error_log("Host: " . $phpmailer->Host);
    error_log("Port: " . $phpmailer->Port);

    // Включаем подробное логирование
    $phpmailer->SMTPDebug = 2;
    $phpmailer->Debugoutput = function ($str, $level) {
        error_log("CRM SMTP Debug: $str");
    };
}



// Запустите в консоли: checkToEmail()
// AJAX обработчик для получения email из поля "To"
add_action('wp_ajax_get_to_email', 'handle_get_to_email');
add_action('wp_ajax_nopriv_get_to_email', 'handle_get_to_email'); // Добавляем для публичного доступ
function handle_get_to_email()
{
    $to_email = get_last_cf7_to_email();

    wp_send_json_success(array(
        'to_email' => $to_email,
        'admin_email' => get_option('admin_email'),
        'message' => 'Email из поля "To" в Contact Form 7'
    ));
}

add_action('wp_ajax_get_cf7_forms_email', 'handle_get_cf7_forms_email');
add_action('wp_ajax_nopriv_get_cf7_forms_email', 'handle_get_cf7_forms_email');
function handle_get_cf7_forms_email()
{


    $forms = WPCF7_ContactForm::find();
    $forms_data = array();

    foreach ($forms as $form) {
        $mail_settings = $form->prop('mail');
        $to_email = $mail_settings['recipient'] ?? 'Не указан';

        // Извлекаем email из поля To
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $to_email, $matches);
        $extracted_email = !empty($matches[0]) ? $matches[0][0] : 'Не найден';

        $forms_data[] = array(
            'id' => $form->id(),
            'title' => $form->title(),
            'to_field' => $to_email,
            'extracted_email' => $extracted_email
        );
    }

    wp_send_json_success($forms_data);
}

// Диагностика шрифтов для JPG
add_shortcode('test_jpg_fonts', 'test_jpg_fonts_handler');
function test_jpg_fonts_handler()
{
    $output = '<h3>Диагностика шрифтов для JPG</h3>';

    // Проверяем GD
    $gd_available = extension_loaded('gd') && function_exists('imagecreate');
    $output .= '<h4>GD библиотека:</h4>';
    $output .= '<p>Доступна: ' . ($gd_available ? '✅ Да' : '❌ Нет') . '</p>';

    if ($gd_available) {
        $gd_info = gd_info();
        $output .= '<p>Версия: ' . $gd_info['GD Version'] . '</p>';
        $output .= '<p>Поддержка JPG: ' . ($gd_info['JPEG Support'] ? '✅ Да' : '❌ Нет') . '</p>';
        $output .= '<p>Поддержка TTF: ' . ($gd_info['FreeType Support'] ? '✅ Да' : '❌ Нет') . '</p>';
    }

    // Проверяем шрифты
    $font_regular = plugin_dir_path(__FILE__) . 'fonts/DejaVuSans.ttf';
    $font_bold = plugin_dir_path(__FILE__) . 'fonts/DejaVuSans-Bold.ttf';

    $output .= '<h4>Шрифты:</h4>';
    $output .= '<p>DejaVuSans.ttf: ' . (file_exists($font_regular) ? '✅ Найден' : '❌ Не найден') . '</p>';
    $output .= '<p>DejaVuSans-Bold.ttf: ' . (file_exists($font_bold) ? '✅ Найден' : '❌ Не найден') . '</p>';

    if (file_exists($font_regular)) {
        $output .= '<p>Путь: ' . $font_regular . '</p>';
    }

    // Тест генерации с кириллицей
    $output .= '<h4>Тест кириллицы:</h4>';

    $test_content = "Тестовое сообщение на кириллице:\n\n";
    $test_content .= "Привет мир! Это проверка работы кириллических символов.\n";
    $test_content .= "Съешь же ещё этих мягких французских булок, да выпей чаю.\n";
    $test_content .= "АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ\n";
    $test_content .= "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";

    if (file_exists($font_regular)) {
        $jpg_url = generate_jpg_from_content($test_content, 'font_test_' . time(), 'Тест кириллицы в JPG');
    } else {
        $jpg_url = generate_jpg_simple($test_content, 'font_test_' . time(), 'Test Latin Only');
    }

    if ($jpg_url) {
        $output .= "<p>JPG файл создан: <a href='$jpg_url' target='_blank'>Открыть JPG</a> ✅</p>";
        $output .= '<img src="' . $jpg_url . '" style="max-width: 400px; border: 1px solid #ccc; margin: 10px 0;" alt="Тестовое JPG изображение">';
    } else {
        $output .= "<p>Ошибка создания JPG файла ❌</p>";
    }

    return $output;
}

// ✅ ДОБАВИТЬ ЭТУ ФУНКЦИЮ ДЛЯ JPG СТИЛЕЙ
function get_crm_jpg_styles()
{
    $css_path = get_stylesheet_directory() . '/assets/css/crm-documents.css';

    if (file_exists($css_path)) {
        $css_content = file_get_contents($css_path);

        // Парсим CSS для извлечения стилей, которые можно использовать в JPG
        return parse_css_for_jpg($css_content);
    }

    // Стили по умолчанию если файл не найден
    return array(
        'title_font_size' => 48,
        'title_color' => array(52, 152, 219), // Синий
        'content_font_size' => 22,
        'content_color' => array(0, 0, 0), // Черный
        'subtitle_color' => array(100, 100, 100), // Серый
        'line_height' => 36,
        'margin' => 100
    );
}

// ✅ ФУНКЦИЯ ПАРСИНГА CSS ДЛЯ JPG
function parse_css_for_jpg($css_content)
{
    $styles = array(
        'title_font_size' => 48,
        'title_color' => array(52, 152, 219),
        'content_font_size' => 22,
        'content_color' => array(0, 0, 0),
        'subtitle_color' => array(100, 100, 100),
        'line_height' => 36,
        'margin' => 100
    );

    // Парсим CSS правила
    preg_match_all('/([^{]+)\s*\{([^}]+)\}/', $css_content, $matches, PREG_SET_ORDER);

    foreach ($matches as $rule) {
        $selector = trim($rule[1]);
        $properties = $rule[2];

        // Для заголовка документа
        if (strpos($selector, '.document-title') !== false) {
            if (preg_match('/font-size:\s*(\d+)px/', $properties, $font_match)) {
                $styles['title_font_size'] = intval($font_match[1]) * 2; // Увеличиваем для JPG
            }
            if (preg_match('/color:\s*#([0-9a-f]{3,6})/i', $properties, $color_match)) {
                $styles['title_color'] = hex_to_rgb($color_match[1]);
            }
        }

        // Для основного контента
        if (strpos($selector, '.document-content') !== false || strpos($selector, 'body') !== false) {
            if (preg_match('/font-size:\s*(\d+)px/', $properties, $font_match)) {
                $styles['content_font_size'] = intval($font_match[1]) * 1.8;
            }
            if (preg_match('/line-height:\s*([\d.]+)/', $properties, $line_match)) {
                $styles['line_height'] = intval($line_match[1] * $styles['content_font_size']);
            }
        }

        // Для подзаголовков
        if (strpos($selector, '.document-subtitle') !== false) {
            if (preg_match('/color:\s*#([0-9a-f]{3,6})/i', $properties, $color_match)) {
                $styles['subtitle_color'] = hex_to_rgb($color_match[1]);
            }
        }
    }

    return $styles;
}

// ✅ ФУНКЦИЯ КОНВЕРТАЦИИ HEX В RGB
function hex_to_rgb($hex)
{
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }

    return array($r, $g, $b);
}

add_action('wp_ajax_check_new_emails', 'handle_check_new_emails');
add_action('wp_ajax_nopriv_check_new_emails', 'handle_check_new_emails');
function handle_check_new_emails()
{
    if (!isset($_POST['dialog_id']) || empty($_POST['dialog_id'])) {
        wp_send_json_error('Не указан ID диалога');
    }

    global $wpdb;
    $dialog_id = intval($_POST['dialog_id']);

    // Получаем email диалога
    $dialog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}crm_dialogs 
        WHERE id = %d
    ", $dialog_id));

    if (!$dialog || empty($dialog->email)) {
        wp_send_json_error('Диалог не найден или email не указан');
    }

    // Проверяем новые письма
    $client_replies = parse_incoming_emails($dialog->email, $dialog_id);
    $new_messages_count = 0;

    if (!empty($client_replies)) {
        $new_messages_count = save_client_replies_to_db($dialog->email, $client_replies, $dialog_id);
        error_log("CRM: Найдено и сохранено новых писем: " . $new_messages_count);
    }

    wp_send_json_success(array(
        'new_messages_count' => $new_messages_count,
        'checked_at' => current_time('mysql'),
        'client_email' => $dialog->email
    ));
}


add_action('wp_head', 'add_ajaxurl');
function add_ajaxurl()
{
    ?>
    <script type="text/javascript">
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    <?php
}


// создание папки ддля файла
function ensureFolderExists($folderPath)
{
    $fullPath = __DIR__ . '/crm_files/' . $folderPath;

    if (!file_exists($fullPath)) {
        mkdir($fullPath, 0777, true); // true создает все вложенные папки
        return true; // Папка создана
    }

    return false; // Папка уже существует
}

// В функции загрузки файла
function uploadFileToStructuredFolder($file, $leadId, $leadName, $clientName, $dialogName)
{
    // Очищаем названия от запрещенных символов
    $cleanLeadName = preg_replace('/[<>:"\/\\|?*]/', '_', $leadName);
    $cleanClientName = preg_replace('/[<>:"\/\\|?*]/', '_', $clientName);
    $cleanDialogName = preg_replace('/[<>:"\/\\|?*]/', '_', $dialogName);

    // Формируем путь к папке
    $folderPath = "от_меня/{$leadId}_{$cleanLeadName}_{$cleanClientName}_{$cleanDialogName}/";

    // Создаем папку если ее нет
    ensureFolderExists($folderPath);

    // Полный путь к файлу
    $fullPath = $folderPath . $file['name'];

    // Загружаем файл
    if (move_uploaded_file($file['tmp_name'], __DIR__ . '/crm_files/' . $fullPath)) {
        return $fullPath; // Возвращаем путь для сохранения в БД
    }

    return false;
}


// Обработчик загрузки файлов
add_action('wp_ajax_upload_crm_file', 'handle_crm_file_upload');
add_action('wp_ajax_nopriv_upload_crm_file', 'handle_crm_file_upload');

function handle_crm_file_upload()
{


    // Проверка прав пользователя (опционально)
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Недостаточно прав');
    }

    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);

    error_log("📤 DEBUG: Загрузка файла для lead_id: {$lead_id}, dialog_id: {$dialog_id}");

    if (!empty($_FILES['crm_file'])) {
        $file = $_FILES['crm_file'];

        // Проверка типа файла
        $file_type = wp_check_filetype($file['name']);
        if (!$file_type['type']) {
            wp_send_json_error('Тип файла не поддерживается: ' . $file['name']);
        }

        // Проверка размера файла (30MB)
        if ($file['size'] > 30 * 1024 * 1024) {
            wp_send_json_error('Файл слишком большой (макс. 30MB)');
        }

        // 🔥 СОЗДАЕМ СИСТЕМУ ПАПОК
        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        // Создаем папку от_меня если нет
        $ot_menya_dir = $crm_dir . '/от_меня';
        if (!file_exists($ot_menya_dir)) {
            if (!wp_mkdir_p($ot_menya_dir)) {
                wp_send_json_error('Не удалось создать папку "от_меня"');
            }
        }

        // Получаем данные для имени папки
        $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
        $folder_name = generate_folder_name($lead_data);

        // Создаем папку заявки если нет
        $lead_folder = $ot_menya_dir . '/' . $folder_name;
        if (!file_exists($lead_folder)) {
            if (!wp_mkdir_p($lead_folder)) {
                wp_send_json_error('Не удалось создать папку заявки: ' . $folder_name);
            }
        }

        error_log("📁 DEBUG: Загружаем файл в папку: {$folder_name}");

        // 🔥 ЗАГРУЖАЕМ ФАЙЛ В ПАПКУ ЗАЯВКИ
        $file_name = sanitize_file_name($file['name']);
        $file_path = $lead_folder . '/' . $file_name;

        // Проверяем, не существует ли уже файл с таким именем
        $counter = 1;
        $original_name = pathinfo($file_name, PATHINFO_FILENAME);
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);

        while (file_exists($file_path)) {
            $file_name = $original_name . '_' . $counter . '.' . $extension;
            $file_path = $lead_folder . '/' . $file_name;
            $counter++;
        }

        // Перемещаем загруженный файл в нужную папку
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error('Не удалось сохранить файл');
        }

        // Формируем URL файла
        $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $file_name;

        error_log("✅ DEBUG: Файл загружен: {$file_url}");

        // Сохраняем информацию о файле в мета-поля лида
        $file_data = array(
            'url' => $file_url,
            'name' => $file_name,
            'original_name' => $file['name'],
            'type' => $file_type['ext'],
            'size' => $file['size'],
            'uploaded_at' => current_time('mysql'),
            'folder' => $folder_name
        );

        // Добавляем файл к мета-полям лида
        $existing_files = get_post_meta($lead_id, '_crm_dialog_files', true);
        if (empty($existing_files)) {
            $existing_files = array();
        }

        $existing_files[] = $file_data;
        update_post_meta($lead_id, '_crm_dialog_files', $existing_files);

        // Возвращаем данные файла
        wp_send_json_success(array(
            'file_url' => $file_url,
            'file_name' => $file_name,
            'original_name' => $file['name'],
            'file_type' => $file_type['ext'],
            'file_size' => size_format($file['size']),
            'folder_name' => $folder_name,
            'message' => 'Файл успешно загружен'
        ));
    }

    wp_send_json_error('Файл не получен');
}


add_filter('upload_mimes', 'add_custom_mime_types');
function add_custom_mime_types($mimes)
{
    $mimes['pdf'] = 'application/pdf';
    $mimes['doc'] = 'application/msword';
    $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $mimes['xls'] = 'application/vnd.ms-excel';
    $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $mimes['zip'] = 'application/zip';
    $mimes['rar'] = 'application/x-rar-compressed';
    // Добавьте другие нужные типы файлов
    return $mimes;
}


// Обработчик переименования файлов
add_action('wp_ajax_rename_crm_file', 'handle_rename_crm_file');

function handle_rename_crm_file()
{
    try {
        error_log("🔄 DEBUG: Переименование файла");



        // Проверка прав пользователя
        if (!current_user_can('edit_posts')) {
            throw new Exception('Недостаточно прав для переименования файлов');
        }

        $old_file_url = esc_url_raw($_POST['old_file_url']);
        $old_file_name = sanitize_file_name($_POST['old_file_name']);
        $new_file_name = sanitize_file_name($_POST['new_file_name']);

        error_log("📁 DEBUG: Параметры переименования:");
        error_log("   - Старый URL: " . $old_file_url);
        error_log("   - Старое имя: " . $old_file_name);
        error_log("   - Новое имя: " . $new_file_name);

        if (empty($old_file_url) || empty($old_file_name) || empty($new_file_name)) {
            throw new Exception('Не все параметры переданы');
        }

        // Получаем пути к файлам
        $upload_dir = wp_upload_dir();
        $base_upload_path = $upload_dir['basedir'];
        $base_upload_url = $upload_dir['baseurl'];

        // Преобразуем URL в путь
        $old_file_path = str_replace($base_upload_url, $base_upload_path, $old_file_url);

        // Проверяем существование исходного файла
        if (!file_exists($old_file_path)) {
            throw new Exception('Файл не найден: ' . $old_file_path);
        }

        // Сохраняем расширение файла
        $file_extension = pathinfo($old_file_name, PATHINFO_EXTENSION);
        $new_file_name_with_ext = $new_file_name . '.' . $file_extension;

        // Получаем путь к новой папке (та же папка что и у старого файла)
        $file_directory = dirname($old_file_path);
        $new_file_path = $file_directory . '/' . $new_file_name_with_ext;

        // 🔥 ПРОВЕРЯЕМ СУЩЕСТВОВАНИЕ ФАЙЛА С ТАКИМ ИМЕНЕМ
        if (file_exists($new_file_path)) {
            throw new Exception('Файл с именем "' . $new_file_name_with_ext . '" уже существует в этом диалоге');
        }

        // Переименовываем файл
        if (!rename($old_file_path, $new_file_path)) {
            throw new Exception('Не удалось переименовать файл');
        }

        // Формируем новый URL
        $new_file_url = str_replace($base_upload_path, $base_upload_url, $new_file_path);

        error_log("✅ DEBUG: Файл переименован:");
        error_log("   - Старый путь: " . $old_file_path);
        error_log("   - Новый путь: " . $new_file_path);
        error_log("   - Новый URL: " . $new_file_url);

        // TODO: Обновить запись в базе данных если файл привязан к сообщению

        wp_send_json_success([
            'new_file_name' => $new_file_name_with_ext,
            'new_file_url' => $new_file_url,
            'message' => 'Файл успешно переименован'
        ]);

    } catch (Exception $e) {
        error_log("❌ DEBUG: Ошибка переименования файла: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}


