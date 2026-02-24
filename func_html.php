<?php
/**
 * PDF Functions for CRM with DomPDF
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}
// Функция генерации HTML файла
function generate_html_file($html_content, $lead_id, $dialog_id, $title = 'Документ')
{
    error_log("🟢 DEBUG: Начало генерации HTML файла для lead_id: {$lead_id}, dialog_id: {$dialog_id}");

    try {
        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        error_log("📁 DEBUG: Базовый путь: {$crm_dir}");

        // Создаем папку от_меня
        $ot_menya_dir = $crm_dir . '/от_меня';
        if (!file_exists($ot_menya_dir)) {
            error_log("📁 DEBUG: Создаем папку от_меня: {$ot_menya_dir}");
            if (!wp_mkdir_p($ot_menya_dir)) {
                throw new Exception('Не удалось создать папку "от_меня"');
            }
        } else {
            error_log("📁 DEBUG: Папка от_меня уже существует");
        }

        // Получаем данные заявки для имени папки
        error_log("🔍 DEBUG: Получаем данные заявки");
        $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
        $folder_name = generate_folder_name($lead_data);

        error_log("📁 DEBUG: Имя папки: {$folder_name}");

        $lead_folder = $ot_menya_dir . '/' . $folder_name;

        // Создаем папку заявки
        if (!file_exists($lead_folder)) {
            error_log("📁 DEBUG: Создаем папку заявки: {$lead_folder}");
            if (!wp_mkdir_p($lead_folder)) {
                throw new Exception('Не удалось создать папку заявки: ' . $folder_name);
            }
        } else {
            error_log("📁 DEBUG: Папка заявки уже существует");
        }

        $filename = 'document_' . $lead_id . '_' . $dialog_id . '_' . time() . '.html';
        $filepath = $lead_folder . '/' . $filename;

        error_log("📄 DEBUG: Создаем файл: {$filepath}");

        // Подготавливаем HTML
        $prepared_html = prepare_html_content($html_content, $title);

        // Сохраняем HTML файл
        error_log("💾 DEBUG: Сохраняем файл...");
        $result = file_put_contents($filepath, $prepared_html);

        if ($result === false) {
            throw new Exception('Не удалось сохранить HTML файл');
        }

        error_log("✅ DEBUG: Файл сохранен, размер: " . $result . " байт");

        if (!file_exists($filepath)) {
            throw new Exception('HTML файл не был создан');
        }

        $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $filename;

        error_log('✅ CRM HTML created: ' . $filename);
        error_log('🔗 URL файла: ' . $file_url);

        return [
            'success' => true,
            'file_url' => $file_url,
            'file_path' => $filepath,
            'file_name' => $filename
        ];

    } catch (Exception $e) {
        error_log('❌ CRM HTML Generation Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}





add_action('wp_ajax_generate_html_file', 'handle_generate_html_file');
function handle_generate_html_file()
{
    error_log("🟢 DEBUG: AJAX generate_html_file вызван");

    try {
        $lead_id = intval($_POST['lead_id']);
        $dialog_id = intval($_POST['dialog_id']);
        $file_content = $_POST['file_content'];
        $custom_file_name = sanitize_text_field($_POST['custom_file_name']);


        $custom_file_name = wp_unslash($custom_file_name);

        error_log("📥 DEBUG: Данные получены - lead: {$lead_id}, dialog: {$dialog_id}, custom_name: {$custom_file_name}");

        if (empty($file_content)) {
            wp_send_json_error('Введите текст для документа');
        }

        if (empty($custom_file_name)) {
            wp_send_json_error('Введите имя файла');
        }

        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        // СОЗДАЕМ ПАПКУ от_меня
        $ot_menya_dir = $crm_dir . '/от_меня';
        if (!file_exists($ot_menya_dir)) {
            wp_mkdir_p($ot_menya_dir);
        }

        // Получаем данные для папки
        $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
        $folder_name = generate_folder_name($lead_data);

        $lead_folder = $ot_menya_dir . '/' . $folder_name;

        // СОЗДАЕМ ПАПКУ ЗАЯВКИ
        if (!file_exists($lead_folder)) {
            wp_mkdir_p($lead_folder);
        }

        // 🔥 ИСПОЛЬЗУЕМ ТОЧНОЕ ИМЯ ФАЙЛА БЕЗ TIMESTAMP
        // Если пользователь не указал расширение, добавляем .html
        $safe_file_name = sanitize_file_name($custom_file_name);
        if (!preg_match('/\.html?$/i', $safe_file_name)) {
            $safe_file_name .= '.html';
        }

        $filename = $safe_file_name; // 🔥 БЕЗ TIMESTAMP
        $filepath = $lead_folder . '/' . $filename;

        // 🔥 ПРОВЕРЯЕМ НА КОНФЛИКТ ИМЕН - ЕСЛИ ФАЙЛ УЖЕ СУЩЕСТВУЕТ, ВОЗВРАЩАЕМ ОШИБКУ
        if (file_exists($filepath)) {
            error_log("❌ Файл уже существует: {$filename}");
            wp_send_json_error('Файл с именем "' . $custom_file_name . '" уже существует в этом диалоге. Используйте другое имя.');
        }

        error_log("📁 DEBUG: Создаем файл: {$filename}");

        // Подготавливаем HTML
        $prepared_html = prepare_html_content($file_content, $custom_file_name);

        // Сохраняем HTML файл
        if (file_put_contents($filepath, $prepared_html)) {
            $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $filename;

            error_log("✅ Файл создан: {$filepath}");

            // СОХРАНЯЕМ В БАЗУ ДАННЫХ
            global $wpdb;

            $saved = $wpdb->insert(
                $wpdb->prefix . 'crm_files',
                [
                    'dialog_id' => $dialog_id,
                    'file_name' => $custom_file_name, // Оригинальное имя без изменений
                    'file_path' => $filepath,
                    'file_url' => $file_url,
                    'created_at' => current_time('mysql'),
                    'html' => true,
                    'pdf' => false,
                    'jpg' => false
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
            );

            if ($saved) {
                error_log("✅ Файл сохранен в БД с ID: " . $wpdb->insert_id);

                wp_send_json_success([
                    'message' => 'HTML файл "' . $custom_file_name . '" успешно создан!',
                    'file_url' => $file_url,
                    'file_name' => $custom_file_name,
                    'file_id' => $wpdb->insert_id
                ]);
            } else {
                error_log("❌ Ошибка сохранения в БД: " . $wpdb->last_error);
                wp_send_json_error('Ошибка сохранения в базу данных');
            }

        } else {
            throw new Exception('Не удалось сохранить файл');
        }

    } catch (Exception $e) {
        error_log('❌ Ошибка: ' . $e->getMessage());
        wp_send_json_error('Ошибка: ' . $e->getMessage());
    }
}

// Функция подготовки HTML контента
function prepare_html_content($html_content, $title)
{
    $html_content = stripslashes($html_content);
    $html_content = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');

    // Получаем CSS
    $css_content = '';
    $css_tex = '';

    if (function_exists('get_css_for_pdf')) {
        $css_content = get_css_for_pdf();
    }

    if (function_exists('get_css_tex_pdf')) {
        $css_tex = get_css_tex_pdf();
    }

    $full_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
        <style>
            " . $css_tex . "
            " . $css_content . "
        </style>
    </head>
    <body class='bodym'>
        <div class=\"content-wrapper\">
                {$html_content}
            </div>
        </div>
    </body>
    </html>
    ";

    return $full_html;
}

add_action('wp_ajax_get_files_list', 'handle_get_files_list');
function handle_get_files_list()
{
    $dialog_id = intval($_POST['dialog_id']);

    global $wpdb;
    $files = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}crm_files WHERE dialog_id = %d ORDER BY created_at DESC",
        $dialog_id
    ));

    wp_send_json_success(['files' => $files]);
}


add_action('wp_ajax_check_file_name_exists', 'handle_check_file_name_exists');
function handle_check_file_name_exists()
{
    $file_name = sanitize_text_field($_POST['file_name']);
    $dialog_id = intval($_POST['dialog_id']);
    $lead_id = intval($_POST['lead_id']);

    $file_name = wp_unslash($file_name);

    //  добавляем .html
    if (!preg_match('/\.html?$/i', $file_name)) {
        $file_name .= '.html';
    }

    $safe_file_name = sanitize_file_name($file_name);

    // Получаем данные для пути
    $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
    $folder_name = generate_folder_name($lead_data);

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/crm_files/от_меня/' . $folder_name . '/' . $safe_file_name;

    $exists = file_exists($file_path);

    wp_send_json_success(['exists' => $exists]);
}

// Обработчик AJAX для удаления файла
add_action('wp_ajax_delete_file', 'handle_delete_file');
function handle_delete_file()
{
    error_log("🟢 DEBUG: AJAX delete_file вызван");

    try {
        $file_id = intval($_POST['file_id']);

        if (!$file_id) {
            wp_send_json_error('Не указан ID файла');
        }

        global $wpdb;

        // Получаем информацию о файле
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}crm_files WHERE id = %d",
            $file_id
        ));

        if (!$file) {
            wp_send_json_error('Файл не найден в базе данных');
        }

        error_log("🔍 DEBUG: Удаляем файл ID: {$file_id}");
        error_log("📁 DEBUG: Путь к файлу: {$file->file_path}");

        $success = true;
        $message = '';

        // Удаляем физический файл
        if (!empty($file->file_path)) {
            if (file_exists($file->file_path)) {
                if (unlink($file->file_path)) {
                    error_log("✅ Файл удален из файловой системы: {$file->file_path}");
                    $message .= 'Файл удален из файловой системы. ';
                } else {
                    $success = false;
                    error_log("❌ Не удалось удалить файл: {$file->file_path}");
                    wp_send_json_error('Не удалось удалить файл из файловой системы');
                }
            } else {
                error_log("⚠️ Файл не найден в файловой системе: {$file->file_path}");
                $message .= 'Файл не найден в файловой системе. ';
            }
        }

        // Удаляем запись из базы данных
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'crm_files',
            ['id' => $file_id],
            ['%d']
        );

        if ($deleted) {
            error_log("✅ Запись удалена из базы данных");
            $message .= 'Запись удалена из базы данных.';

            wp_send_json_success([
                'message' => $message,
                'file_id' => $file_id,
                'dialog_id' => $file->dialog_id
            ]);
        } else {
            error_log("❌ Ошибка удаления из БД: " . $wpdb->last_error);
            wp_send_json_error('Не удалось удалить запись из базы данных');
        }

    } catch (Exception $e) {
        error_log('❌ Ошибка удаления файла: ' . $e->getMessage());
        wp_send_json_error('Ошибка: ' . $e->getMessage());
    }
}


// Обработчик замены файла
add_action('wp_ajax_replace_file', 'handle_replace_file');
function handle_replace_file()
{
    error_log("🟢 DEBUG: AJAX replace_file вызван");

    try {
        $file_id = intval($_POST['file_id']);
        $file_content = $_POST['file_content'];
        $custom_file_name = sanitize_text_field($_POST['custom_file_name']);

        $custom_file_name = wp_unslash($custom_file_name);

        error_log("📥 DEBUG: Данные замены - file_id: {$file_id}, custom_name: {$custom_file_name}");

        if (!$file_id) {
            wp_send_json_error('Не указан ID файла');
        }

        global $wpdb;

        // Получаем информацию о файле
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}crm_files WHERE id = %d",
            $file_id
        ));

        if (!$file) {
            wp_send_json_error('Файл не найден в базе данных');
        }

        error_log("🔍 DEBUG: Найден файл в БД: {$file->file_name}, путь: {$file->file_path}");

        // Подготавливаем HTML контент
        $prepared_html = prepare_html_content($file_content, $custom_file_name);

        // Перезаписываем файл
        if (file_put_contents($file->file_path, $prepared_html)) {
            error_log("✅ Файл заменен: {$file->file_path}");

            // 🔥 ОБНОВЛЯЕМ ВРЕМЯ В БАЗЕ ДАННЫХ - используем created_at как ты сказал
            $updated = $wpdb->update(
                $wpdb->prefix . 'crm_files',
                [
                    'file_name' => $custom_file_name,
                    'created_at' => current_time('mysql'), // 🔥 ИСПОЛЬЗУЕМ created_at ДЛЯ ОБНОВЛЕНИЯ
                    'pdf' => false,
                    'jpg' => false
                ],
                ['id' => $file_id],
                ['%s', '%s', '%d', '%d'], // 🔥 ПРАВИЛЬНЫЕ ТИПЫ ДАННЫХ
                ['%d']
            );

            if ($updated === false) {
                error_log("❌ Ошибка обновления БД: " . $wpdb->last_error);
                throw new Exception('Ошибка базы данных: ' . $wpdb->last_error);
            }

            error_log("✅ База данных обновлена");

            wp_send_json_success([
                'message' => 'Файл "' . $custom_file_name . '" успешно заменен!',
                'file_name' => $custom_file_name
            ]);
        } else {
            throw new Exception('Не удалось перезаписать файл');
        }

    } catch (Exception $e) {
        error_log('❌ Ошибка замены файла: ' . $e->getMessage());
        wp_send_json_error('Ошибка: ' . $e->getMessage());
    }
}

// Обработчик переименования файла
add_action('wp_ajax_rename_file', 'handle_rename_file');
function handle_rename_file()
{
    error_log("🟢 DEBUG: AJAX rename_file вызван");

    try {
        $file_id = intval($_POST['file_id']);
        $new_file_name = sanitize_text_field($_POST['new_file_name']);

        $new_file_name = wp_unslash($new_file_name);

        error_log("🔍 DEBUG: Данные - file_id: {$file_id}, new_file_name: {$new_file_name}");

        if (!$file_id || !$new_file_name) {
            wp_send_json_error('Не указаны данные');
        }

        global $wpdb;

        // Получаем информацию о файле
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}crm_files WHERE id = %d",
            $file_id
        ));

        if (!$file) {
            wp_send_json_error('Файл не найден в базе данных');
        }

        error_log("🔍 DEBUG: Текущий файл - имя в БД: {$file->file_name}, путь: {$file->file_path}");

        // Проверяем существует ли исходный файл
        if (!file_exists($file->file_path)) {
            error_log("❌ Исходный файл не существует: {$file->file_path}");
            throw new Exception('Исходный файл не существует');
        }

        // 🔥 ДОБАВЛЯЕМ .html ЕСЛИ НЕТ
        if (!preg_match('/\.html?$/i', $new_file_name)) {
            $new_file_name .= '.html';
        }

        $file_dir = dirname($file->file_path);
        $new_file_path = $file_dir . '/' . $new_file_name;

        error_log("🔍 DEBUG: Переименовываем: {$file->file_path} -> {$new_file_path}");

        // 🔥 ПРОСТО ПЕРЕИМЕНОВЫВАЕМ ФАЙЛ БЕЗ ИЗМЕНЕНИЯ СОДЕРЖИМОГО
        if (rename($file->file_path, $new_file_path)) {
            error_log("✅ Файл переименован в файловой системе");

            // Обновляем URL файла
            $upload_dir = wp_upload_dir();
            $new_file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);

            // Сохраняем в БД имя БЕЗ .html
            $file_name_for_db = pathinfo($new_file_name, PATHINFO_FILENAME);

            // Обновляем запись в базе данных
            $updated = $wpdb->update(
                $wpdb->prefix . 'crm_files',
                [
                    'file_name' => $file_name_for_db,
                    'file_path' => $new_file_path,
                    'file_url' => $new_file_url,
                    'created_at' => current_time('mysql'),

                    'pdf' => false,  // 🔥 СБРАСЫВАЕМ PDF
                    'jpg' => false   // 🔥 СБРАСЫВАЕМ JPG
                ],
                ['id' => $file_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                error_log("❌ Ошибка БД: " . $wpdb->last_error);
                throw new Exception('Ошибка базы данных: ' . $wpdb->last_error);
            } else {
                error_log("✅ Запись обновлена в БД");
                wp_send_json_success([
                    'message' => 'Файл успешно переименован',
                    'file_name' => $file_name_for_db
                ]);
            }
        } else {
            throw new Exception('Не удалось переименовать файл в файловой системе');
        }

    } catch (Exception $e) {
        error_log('❌ Ошибка переименования файла: ' . $e->getMessage());
        wp_send_json_error('Ошибка: ' . $e->getMessage());
    }
}