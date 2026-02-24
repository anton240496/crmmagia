<?php
// Endpoint для получения файлов диалога

if (!defined('ABSPATH')) {
    exit;
}

if ($_POST['action'] == 'get_dialog_files' && $_POST['lead_id'] && $_POST['dialog_id']) {

    define('WP_USE_THEMES', false);
    require_once('wp-load.php');

    header('Content-Type: application/json');

    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    $highlight_file = isset($_POST['highlight_file']) ? $_POST['highlight_file'] : '';

    global $wpdb;

    // Получаем данные диалога
    $dialog_data = $wpdb->get_row($wpdb->prepare("
        SELECT l.name_zayv, l.name as client_name, d.name as dialog_name 
        FROM {$wpdb->prefix}crm_leads l 
        JOIN {$wpdb->prefix}crm_dialogs d ON l.id = d.lead_id 
        WHERE l.id = %d AND d.id = %d
    ", $lead_id, $dialog_id), ARRAY_A);

    if ($dialog_data) {
        // ЗАМЕНЯЕМ ПРОБЕЛЫ НА ДЕФИСЫ ВО ВСЕХ НАЗВАНИЯХ
        $name_zayv_clean = str_replace(' ', '-', $dialog_data['name_zayv']);
        $name_zayv_clean = str_replace("'", '', $name_zayv_clean);
        $name_zayv_clean = str_replace('"', '', $name_zayv_clean);

        $client_name_clean = str_replace(' ', '-', $dialog_data['client_name']);
        $client_name_clean = str_replace("'", '', $client_name_clean);
        $client_name_clean = str_replace('"', '', $client_name_clean);

        $dialog_name_clean = str_replace(' ', '-', $dialog_data['dialog_name']);
        $dialog_name_clean = str_replace("'", '', $dialog_name_clean);
        $dialog_name_clean = str_replace('"', '', $dialog_name_clean);

        $upload_dir = wp_upload_dir();
        $folder_path = $upload_dir['basedir'] . '/crm_files/от_меня/' .
            $lead_id . '_' .
            $name_zayv_clean . '_' .
            $client_name_clean . '_' .
            $dialog_name_clean;

        if (is_dir($folder_path)) {
            $files = array_diff(scandir($folder_path), ['.', '..']);
            $file_list = [];

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'html') {
                    $file_path = $folder_path . '/' . $file;
                    $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' .
                        $lead_id . '_' .
                        $name_zayv_clean . '_' .
                        $client_name_clean . '_' .
                        $dialog_name_clean . '/' . rawurlencode($file);

                    $should_highlight = ($file === $highlight_file);

                    $file_list[] = [
                        'name' => $file,
                        'path' => $file_path,
                        'url' => $file_url,
                        'size' => filesize($file_path),
                        'type' => mime_content_type($file_path),
                        'modified_time' => filemtime($file_path),
                        'highlight' => $should_highlight
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'files' => $file_list,
                'highlight_file' => $highlight_file
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Папка не найдена: ' . $folder_path
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Данные диалога не найдены'
        ]);
    }

    exit;
}

// 🔥 НОВЫЙ ЭНДПОИНТ ДЛЯ УДАЛЕНИЯ ФАЙЛОВ
if ($_POST['action'] == 'delete_dialog_file' && $_POST['lead_id'] && $_POST['dialog_id'] && $_POST['file_name']) {

    define('WP_USE_THEMES', false);
    require_once('wp-load.php');

    header('Content-Type: application/json');

    $lead_id = intval($_POST['lead_id']);
    $dialog_id = intval($_POST['dialog_id']);
    
    // 🔥 ОСНОВНОЕ ИСПРАВЛЕНИЕ: Декодируем имя файла
    $file_name = urldecode($_POST['file_name']);
    
    error_log("Файл для удаления (после urldecode): " . $file_name);

    global $wpdb;

    // Получаем данные диалога
    $dialog_data = $wpdb->get_row($wpdb->prepare("
        SELECT l.name_zayv, l.name as client_name, d.name as dialog_name 
        FROM {$wpdb->prefix}crm_leads l 
        JOIN {$wpdb->prefix}crm_dialogs d ON l.id = d.lead_id 
        WHERE l.id = %d AND d.id = %d
    ", $lead_id, $dialog_id), ARRAY_A);

    if ($dialog_data) {
        // Формируем путь к папке
        $name_zayv_clean = str_replace(' ', '-', $dialog_data['name_zayv']);
        $name_zayv_clean = str_replace("'", '', $name_zayv_clean);
        $name_zayv_clean = str_replace('"', '', $name_zayv_clean);

        $client_name_clean = str_replace(' ', '-', $dialog_data['client_name']);
        $client_name_clean = str_replace("'", '', $client_name_clean);
        $client_name_clean = str_replace('"', '', $client_name_clean);

        $dialog_name_clean = str_replace(' ', '-', $dialog_data['dialog_name']);
        $dialog_name_clean = str_replace("'", '', $dialog_name_clean);
        $dialog_name_clean = str_replace('"', '', $dialog_name_clean);

        $upload_dir = wp_upload_dir();
        $folder_path = $upload_dir['basedir'] . '/crm_files/от_меня/' .
            $lead_id . '_' .
            $name_zayv_clean . '_' .
            $client_name_clean . '_' .
            $dialog_name_clean;

        $file_path = $folder_path . '/' . $file_name;

        // Проверяем существует ли файл
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Файл удален',
                    'deleted_file' => $file_name
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Ошибка при удалении файла'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Файл не найден: ' . $file_name,
                'debug_info' => [
                    'requested_file' => $file_name,
                    'folder_path' => $folder_path
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Данные диалога не найдены'
        ]);
    }

    exit;
}