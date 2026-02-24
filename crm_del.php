<?php
// ==================== CRM DELETE FUNCTIONS ====================

/**
 * Главная функция удаления диалога
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_delete_dialog', 'handle_delete_dialog');
function handle_delete_dialog()
{
 

    // Проверка прав пользователя (опционально)
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $dialog_id = intval($_POST['dialog_id']);

    if (empty($dialog_id)) {
        wp_send_json_error('ID диалога не указан');
    }

    error_log('CRM Delete: Starting deletion for dialog ID: ' . $dialog_id);

    try {
        // Получаем данные диалога
        $dialog_data = get_dialog_data($dialog_id);

        if (!$dialog_data) {
            wp_send_json_error('Диалог не найден');
        }

        // Удаляем связанные данные
        $results = array(
            'message_relations' => delete_message_files_relations($dialog_id),
            'messages' => delete_dialog_messages($dialog_id),
            'files_records' => delete_dialog_files_records($dialog_id),
            'additional_emails' => delete_dialog_additional_emails($dialog_id),
            'folder' => delete_dialog_folder($dialog_data),
            'dialog' => delete_dialog_record($dialog_id)
        );

        error_log('CRM Delete: Successfully deleted dialog ID: ' . $dialog_id);

        wp_send_json_success(array(
            'message' => 'Диалог и все связанные данные успешно удалены',
            'deleted_items' => $results
        ));

    } catch (Exception $e) {
        error_log('CRM Delete Error: ' . $e->getMessage());
        wp_send_json_error('Ошибка при удалении: ' . $e->getMessage());
    }
}

/**
 * Получить данные диалога для построения пути
 */
function get_dialog_data($dialog_id)
{
    global $wpdb;

    error_log('🔍 CRM Delete Debug: Getting data for dialog ID: ' . $dialog_id);

    $dialog = $wpdb->get_row($wpdb->prepare(
        "SELECT d.id, d.lead_id, d.name as dialog_name, 
                l.name as client_name,  
                l.name_zayv as lead_name, 
                l.email as lead_email
         FROM {$wpdb->prefix}crm_dialogs d
         LEFT JOIN {$wpdb->prefix}crm_leads l ON d.lead_id = l.id
         WHERE d.id = %d",
        $dialog_id
    ));

    // ⭐ ДОБАВЬ ДЕБАГ ДАННЫХ
    error_log('🔍 CRM Delete Debug: Raw dialog data: ' . print_r($dialog, true));

    if (!$dialog) {
        error_log('❌ CRM Delete Error: Dialog not found in database');
        return false;
    }

    // ⭐ ПРОВЕРЬ НА ПУСТЫЕ ЗНАЧЕНИЯ
    $lead_name = !empty($dialog->lead_name) ? $dialog->lead_name : 'no_lead_name';
    $client_name = !empty($dialog->client_name) ? $dialog->client_name : 'no_client_name';
    $dialog_name = !empty($dialog->dialog_name) ? $dialog->dialog_name : 'no_dialog_name';

    error_log('🔍 CRM Delete Debug: Names - lead: ' . $lead_name . ', client: ' . $client_name . ', dialog: ' . $dialog_name);

    // ⭐ ФОРМИРУЕМ ИМЯ ПАПКИ С ЗАЩИТОЙ ОТ ПУСТЫХ ЗНАЧЕНИЙ
    $dialog->folder_name = sprintf(
        '%d_%s_%s_%s',
        $dialog->lead_id,
        sanitize_file_name(str_replace(' ', '-', $lead_name)),
        sanitize_file_name(str_replace(' ', '-', $client_name)),
        sanitize_file_name(str_replace(' ', '-', $dialog_name))
    );

    error_log('✅ CRM Delete Debug: Final folder name: ' . $dialog->folder_name);
    return $dialog;
}

/**
 * Удалить дополнительные email диалога
 */
function delete_dialog_additional_emails($dialog_id)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_emails',
        array('dialog_id' => $dialog_id),
        array('%d')
    );

    error_log('CRM Delete: Removed ' . $result . ' additional emails for dialog: ' . $dialog_id);
    return $result;
}

/**
 * Удалить все сообщения диалога
 */
function delete_dialog_messages($dialog_id)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_messages',
        array('dialog_id' => $dialog_id),
        array('%d')
    );

    error_log('CRM Delete: Removed ' . $result . ' messages for dialog: ' . $dialog_id);
    return $result;
}

/**
 * Удалить записи о файлах диалога
 */
function delete_dialog_files_records($dialog_id)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_files',
        array('dialog_id' => $dialog_id),
        array('%d')
    );

    error_log('CRM Delete: Removed ' . $result . ' file records for dialog: ' . $dialog_id);
    return $result;
}


function delete_message_files_relations($dialog_id)
{
    global $wpdb;

    // Сначала посчитаем сколько связей будет удалено
    $count_before = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_message_files mf
         INNER JOIN {$wpdb->prefix}crm_messages m ON mf.message_id = m.id
         WHERE m.dialog_id = %d",
        $dialog_id
    ));

    error_log("🔍 CRM Delete: Found $count_before message-file relations for dialog: $dialog_id");

    // Удаляем связи через подзапрос к сообщениям этого диалога
    $result = $wpdb->query($wpdb->prepare(
        "DELETE mf FROM {$wpdb->prefix}crm_message_files mf
         INNER JOIN {$wpdb->prefix}crm_messages m ON mf.message_id = m.id
         WHERE m.dialog_id = %d",
        $dialog_id
    ));

    // Проверим сколько осталось после удаления
    $count_after = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_message_files mf
         INNER JOIN {$wpdb->prefix}crm_messages m ON mf.message_id = m.id
         WHERE m.dialog_id = %d",
        $dialog_id
    ));

    error_log("🔍 CRM Delete: After deletion - $count_after relations remaining for dialog: $dialog_id");
    error_log("🔍 CRM Delete: Query result: $result, Affected rows: " . $wpdb->rows_affected);

    if ($result === false) {
        $error = $wpdb->last_error;
        error_log("❌ CRM Delete: Error deleting message-file relations: $error");
        return "error: $error";
    }

    return "deleted: $count_before relations";
}


function delete_dialog_folder($dialog_data)
{
    $base_path = WP_CONTENT_DIR . '/uploads/crm_files/от_меня/';
    $folder_path = $base_path . $dialog_data->folder_name;

    error_log('🔍 CRM Delete: Looking for folder: ' . $folder_path);

    // ТОЛЬКО если папка существует И её название точно совпадает
    if (!file_exists($folder_path) || !is_dir($folder_path)) {
        error_log('❌ CRM Delete: Dialog folder does not exist');
        
        // ⚠️ НЕ ИЩЕМ другие папки! Это опасно!
        error_log('ℹ️ CRM Delete: This dialog has no folder. Nothing to delete.');
        return 'no_folder_exists';
    }

    // Дополнительная проверка на всякий случай
    $found_folders = find_folders_by_lead_id($dialog_data->lead_id, $base_path);
    $current_folder_name = basename($folder_path);
    
    if (!in_array($current_folder_name, $found_folders)) {
        error_log('🚨 SECURITY ALERT: Trying to delete unrelated folder!');
        return 'security_error';
    }

    $result = delete_directory($folder_path);
    
    if ($result) {
        error_log('✅ CRM Delete: Successfully deleted ONLY its own folder');
        return 'folder_deleted';
    }
    
    return 'delete_failed';
}

function find_folders_by_lead_id($lead_id, $base_path)
{
    $folders = array();

    if (!is_dir($base_path)) {
        error_log('❌ CRM Delete: Base path does not exist: ' . $base_path);
        return $folders;
    }

    $items = scandir($base_path);

    foreach ($items as $item) {
        if ($item == '.' || $item == '..')
            continue;

        $full_path = $base_path . $item;

        if (is_dir($full_path) && strpos($item, (string) $lead_id . '_') === 0) {
            $folders[] = $item;
        }
    }

    return $folders;
}

/**
 * Рекурсивное удаление директории
 */
function delete_directory($dir)
{
    if (!file_exists($dir)) {
        error_log('❌ Delete Directory: Path does not exist - ' . $dir);
        return true;
    }

    if (!is_dir($dir)) {
        error_log('🔍 Delete Directory: Not a directory, deleting file - ' . $dir);
        return unlink($dir);
    }

    error_log('🔍 Delete Directory: Scanning directory - ' . $dir);
    $files = scandir($dir);

    foreach ($files as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $item_path = $dir . DIRECTORY_SEPARATOR . $item;
        error_log('🔍 Delete Directory: Processing - ' . $item_path);

        if (is_dir($item_path)) {
            error_log('📁 Delete Directory: Recursing into - ' . $item_path);
            if (!delete_directory($item_path)) {
                error_log('❌ Delete Directory: Failed to delete subdirectory - ' . $item_path);
                return false;
            }
        } else {
            error_log('📄 Delete Directory: Deleting file - ' . $item_path);
            if (!unlink($item_path)) {
                error_log('❌ Delete Directory: Failed to delete file - ' . $item_path);
                return false;
            }
        }
    }

    error_log('🔍 Delete Directory: Removing directory - ' . $dir);
    return rmdir($dir);
}

/**
 * Удалить запись диалога
 */
function delete_dialog_record($dialog_id)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_dialogs',
        array('id' => $dialog_id),
        array('%d')
    );

    if ($result) {
        error_log('CRM Delete: Removed dialog record: ' . $dialog_id);
        return 'dialog_deleted';
    } else {
        error_log('CRM Delete: Failed to remove dialog record: ' . $dialog_id);
        return 'dialog_delete_failed';
    }
}

/**
 * Проверка существования диалога (для валидации)
 */
function verify_dialog_exists($dialog_id)
{
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
        $dialog_id
    ));

    return $exists > 0;
}

/**
 * Получить статистику перед удалением (для подтверждения)
 */
/**
 * Получить статистику перед удалением (для подтверждения)
 */
function get_dialog_stats($dialog_id)
{
    global $wpdb;

    $stats = array(
        'messages_count' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crm_messages WHERE dialog_id = %d",
            $dialog_id
        )),
        'files_count' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crm_files WHERE dialog_id = %d",
            $dialog_id
        )),
        'emails_count' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crm_emails WHERE dialog_id = %d",
            $dialog_id
        )),
        'folder_exists' => false
    );

    // Проверяем существование папки
    $dialog_data = get_dialog_data($dialog_id);
    if ($dialog_data) {
        $folder_path = WP_CONTENT_DIR . '/uploads/crm_files/от_меня/' . $dialog_data->folder_name;
        $stats['folder_exists'] = file_exists($folder_path) && is_dir($folder_path);
    }

    return $stats;
}



// ==================== CRM DELETE LEAD FUNCTIONS ====================

/**
 * Главная функция удаления заявки
 */
add_action('wp_ajax_delete_lead', 'handle_delete_lead');
function handle_delete_lead()
{
 

    // Проверка прав пользователя
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $lead_id = intval($_POST['lead_id']);

    if (empty($lead_id)) {
        wp_send_json_error('ID заявки не указан');
    }

    error_log('CRM Delete Lead: Starting deletion for lead ID: ' . $lead_id);

    try {
        // Получаем данные заявки для логирования
        $lead_data = get_lead_data($lead_id);

        if (!$lead_data) {
            wp_send_json_error('Заявка не найдена');
        }

        // Удаляем все связанные данные
        $results = array(
            'dialogs' => delete_lead_dialogs($lead_id),
            'documents' => delete_lead_documents($lead_id),
            'lead' => delete_lead_record($lead_id)
        );

        error_log('CRM Delete Lead: Successfully deleted lead ID: ' . $lead_id);

        wp_send_json_success(array(
            'message' => 'Заявка и все связанные данные успешно удалены',
            'deleted_items' => $results,
            'lead_id' => $lead_id
        ));

    } catch (Exception $e) {
        error_log('CRM Delete Lead Error: ' . $e->getMessage());
        wp_send_json_error('Ошибка при удалении заявки: ' . $e->getMessage());
    }
}

/**
 * Получить данные заявки
 */
function get_lead_data($lead_id)
{
    global $wpdb;

    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, name_zayv, email, phone, status 
         FROM {$wpdb->prefix}crm_leads 
         WHERE id = %d",
        $lead_id
    ));

    if (!$lead) {
        error_log('CRM Delete Lead Error: Lead not found - ' . $lead_id);
        return false;
    }

    error_log('CRM Delete Lead: Found lead - ' . $lead->name_zayv);
    return $lead;
}

/**
 * Удалить все диалоги заявки и их содержимое
 */
/**
 * Удалить все диалоги заявки и их содержимое
 */
function delete_lead_dialogs($lead_id)
{
    global $wpdb;

    // Проверяем существование таблицы crm_message_files
    $message_files_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}crm_message_files'");
    if (!$message_files_table_exists) {
        error_log("❌ CRM Delete Lead: Table {$wpdb->prefix}crm_message_files does not exist");
    } else {
        error_log("✅ CRM Delete Lead: Table {$wpdb->prefix}crm_message_files exists");
    }

    // Получаем все диалоги заявки
    $dialogs = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}crm_dialogs WHERE lead_id = %d",
        $lead_id
    ));

    $deleted_count = 0;
    $folder_results = array();

    if ($dialogs) {
        foreach ($dialogs as $dialog) {
            error_log('CRM Delete Lead: Processing dialog ID: ' . $dialog->id);

            // Удаляем все данные диалога
            $relations_deleted = delete_message_files_relations($dialog->id);
            $messages_deleted = delete_dialog_messages($dialog->id);         
            $files_deleted = delete_dialog_files_records($dialog->id);
            $emails_deleted = delete_dialog_additional_emails($dialog->id);

            // Удаляем физические файлы диалога
            $dialog_data = get_dialog_data($dialog->id);
            if ($dialog_data) {
                $folder_result = delete_dialog_folder($dialog_data);
                $folder_results[$dialog->id] = $folder_result;
            }

            // Удаляем сам диалог
            $dialog_deleted = $wpdb->delete(
                $wpdb->prefix . 'crm_dialogs',
                array('id' => $dialog->id),
                array('%d')
            );

            if ($dialog_deleted) {
                $deleted_count++;
            }

            error_log('CRM Delete Lead: Deleted dialog ' . $dialog->id .
                ' - messages: ' . $messages_deleted .
                ', files: ' . $files_deleted .
                ', relations: ' . $relations_deleted .
                ', emails: ' . $emails_deleted);
        }
    }

    return array(
        'dialogs_count' => count($dialogs),
        'deleted_dialogs' => $deleted_count,
        'folder_results' => $folder_results
    );
}

/**
 * Удалить документы заявки
 */
function delete_lead_documents($lead_id)
{
    global $wpdb;

    // Проверяем существование таблицы документов
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}crm_doc'");

    if (!$table_exists) {
        error_log('CRM Delete Lead: Documents table does not exist');
        return 'table_not_found';
    }

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_doc',
        array('lead_id' => $lead_id),
        array('%d')
    );

    error_log('CRM Delete Lead: Removed ' . $result . ' documents for lead: ' . $lead_id);
    return $result;
}

/**
 * Удалить запись заявки
 */
function delete_lead_record($lead_id)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'crm_leads',
        array('id' => $lead_id),
        array('%d')
    );

    if ($result) {
        error_log('CRM Delete Lead: Removed lead record: ' . $lead_id);
        return 'lead_deleted';
    } else {
        error_log('CRM Delete Lead: Failed to remove lead record: ' . $lead_id);
        error_log('CRM Delete Lead Error: ' . $wpdb->last_error);
        return 'lead_delete_failed';
    }
}

/**
 * Проверка существования заявки
 */
function verify_lead_exists($lead_id)
{
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}crm_leads WHERE id = %d",
        $lead_id
    ));

    return $exists > 0;
}
?>