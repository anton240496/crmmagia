<?php
/**
 * JPG Functions for CRM - ZIP Only Version
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_generate_jpg_file', 'generate_jpg_file_function');

function generate_jpg_file_function()
{
    global $wpdb;

    // Добавляем стартовое логирование
    error_log("🎯 JPG FUNCTION STARTED");

    if (isset($_POST['test'])) {
        wp_send_json_success('✅ JPG обработчик работает!');
    }

  

    $pdf_filepath = '';
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $dialog_id = isset($_POST['dialog_id']) ? intval($_POST['dialog_id']) : 0;

    try {
        // Получаем данные
        if (!isset($_POST['pdf_url']) || !isset($_POST['pdf_filename'])) {
            throw new Exception('Не хватает данных PDF');
        }

        $pdf_url = esc_url_raw($_POST['pdf_url']);
        $pdf_filename = sanitize_text_field($_POST['pdf_filename']);

          $pdf_filename = wp_unslash($pdf_filename);

        // 🔥 ПОЛУЧАЕМ ДАННЫЕ ДЛЯ СИСТЕМЫ ПАПОК
        $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
        $folder_name = generate_folder_name($lead_data);

        error_log("📁 JPG: Используем папку: " . $folder_name);

        // Пути к файлам
        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';
        $ot_menya_dir = $crm_dir . '/от_меня';
        $lead_folder = $ot_menya_dir . '/' . $folder_name;

        // Создаем папки если нет
        if (!file_exists($lead_folder)) {
            if (!wp_mkdir_p($lead_folder)) {
                throw new Exception('Не удалось создать папку заявки: ' . $folder_name);
            }
        }

        // 🔥 ПРАВИЛЬНЫЙ ПУТЬ К PDF - ДОБАВЛЯЕМ РАСШИРЕНИЕ .pdf
        $pdf_filepath = $lead_folder . '/' . $pdf_filename;

        // Если в имени файла нет .pdf - добавляем его
        if (!str_ends_with($pdf_filename, '.pdf')) {
            $pdf_filename .= '.pdf';
            $pdf_filepath = $lead_folder . '/' . $pdf_filename;
        }

        error_log("📄 JPG: Корректный путь к PDF: " . $pdf_filepath);

        // Альтернативный способ поиска если файл не найден
        if (!file_exists($pdf_filepath)) {
            error_log("🔍 PDF не найден по основному пути, пробуем альтернативный...");
            $pdf_filepath_from_url = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url);

            // Также проверяем расширение в URL пути
            if (!str_ends_with($pdf_filepath_from_url, '.pdf')) {
                $pdf_filepath_from_url .= '.pdf';
            }

            if (file_exists($pdf_filepath_from_url)) {
                $pdf_filepath = $pdf_filepath_from_url;
                error_log("✅ PDF найден через URL: " . $pdf_filepath);
            } else {
                error_log("❌ PDF не найден ни по одному пути");
                throw new Exception('PDF файл не найден: ' . $pdf_filename . ' в папке: ' . $lead_folder);
            }
        }
        error_log("📄 JPG: Найден PDF файл: " . $pdf_filepath);

        // Проверяем Imagick
        if (!extension_loaded('imagick')) {
            throw new Exception('Библиотека Imagick не установлена');
        }

        // Загружаем PDF и определяем количество страниц
        $imagick = new Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($pdf_filepath);

        $total_pages = $imagick->getNumberImages();
        error_log("📄 PDF содержит {$total_pages} страниц");

        if ($total_pages === 0) {
            throw new Exception('PDF файл не содержит страниц');
        }

        $jpg_files = [];

        // 🔥 СОЗДАЕМ ПАПКУ ДЛЯ ВРЕМЕННЫХ JPG ВНУТРИ ПАПКИ ЗАЯВКИ
        $temp_jpg_dir = $lead_folder . '/temp_jpg_' . time();
        if (!file_exists($temp_jpg_dir)) {
            wp_mkdir_p($temp_jpg_dir);
        }

        // Конвертируем каждую страницу в JPG
        foreach ($imagick as $page_number => $page) {
            $page_jpg_filename = 'page_' . ($page_number + 1) . '.jpg';
            $page_jpg_filepath = $temp_jpg_dir . '/' . $page_jpg_filename;

            $page->setImageFormat('jpg');
            $page->setImageCompressionQuality(90);
            $page->writeImage($page_jpg_filepath);

            $jpg_files[] = [
                'path' => $page_jpg_filepath,
                'name' => $page_jpg_filename,
                'page' => $page_number + 1
            ];

            error_log("✅ Создана страница " . ($page_number + 1));
        }

        $imagick->clear();
        $imagick->destroy();

        // 📦 СОЗДАЕМ ZIP АРХИВ В ПАПКЕ ЗАЯВКИ
        $zip_filename = '';
        $zip_url = '';

        if (extension_loaded('zip')) {
            // 🔥 СОЗДАЕМ ZIP С ТЕМ ЖЕ ИМЕНЕМ ЧТО И PDF
            $zip_filename = str_replace('.pdf', '.zip', $pdf_filename);
            $zip_filepath = $lead_folder . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_filepath, ZipArchive::CREATE) === TRUE) {
                foreach ($jpg_files as $jpg_file) {
                    $zip->addFile($jpg_file['path'], $jpg_file['name']);
                }
                $zip->close();

                // 🔥 Удаляем временную папку с JPG после создания ZIP
                if (file_exists($temp_jpg_dir)) {
                    foreach ($jpg_files as $jpg_file) {
                        if (file_exists($jpg_file['path'])) {
                            unlink($jpg_file['path']);
                        }
                    }
                    rmdir($temp_jpg_dir);
                }

                $zip_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $zip_filename;
                error_log("✅ Создан ZIP архив: {$zip_filename} с {$total_pages} страницами в папке {$folder_name}");

                // 🗑️ УДАЛЯЕМ PDF ТОЛЬКО ПОСЛЕ УСПЕШНОГО СОЗДАНИЯ JPG
                // 🔥 УДАЛЯЕМ ТОЛЬКО PDF С _ В КОНЦЕ ИМЕНИ (СПЕЦИАЛЬНЫЕ ДЛЯ JPG)
                if (file_exists($pdf_filepath) && strpos($pdf_filename, '_.pdf') !== false) {
                    // Если PDF имеет _ в конце имени - удаляем его (специальный для JPG)
                    unlink($pdf_filepath);
                    error_log("🗑️ Удален специальный PDF для JPG: " . $pdf_filename);
                } elseif (file_exists($pdf_filepath)) {
                    // Обычный PDF - оставляем
                    error_log("📄 Обычный PDF файл сохранен: " . $pdf_filename);
                }



                // 💾 СОХРАНЯЕМ В БАЗУ ДАННЫХ
                if ($message_id > 0) {
                    $files_table = $wpdb->prefix . 'crm_message_files';

                    $result = $wpdb->insert(
                        $files_table,
                        array(
                            'message_id' => $message_id,
                            'file_url' => $zip_url,
                            'file_name' => $zip_filename,
                            'file_type' => 'zip',
                            'file_size' => filesize($zip_filepath),
                            'direction' => 'outgoing',
                            'attached_at' => current_time('mysql')
                        ),
                        array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
                    );

                    if ($result) {
                        error_log("✅ JPG файл сохранен в БД - {$zip_filename} для сообщения {$message_id}");
                    } else {
                        error_log("❌ Ошибка сохранения JPG в БД: " . $wpdb->last_error);
                    }
                }

            } else {
                throw new Exception('Не удалось создать ZIP архив');
            }
        } else {
            throw new Exception('Библиотека ZIP не доступна');
        }

        // 📋 ВОЗВРАЩАЕМ ZIP АРХИВ
        wp_send_json_success([
            'message' => "Создан ZIP архив с {$total_pages} страницами" . ($message_id > 0 ? ' и прикреплен к сообщению' : ''),
            'file_url' => $zip_url,
            'file_name' => $zip_filename,
            'total_pages' => $total_pages,
            'message_id' => $message_id,
            'folder_name' => $folder_name,
            'created_at' => date('d.m.Y H:i')
        ]);

    } catch (Exception $e) {
        // 🔥 УДАЛЯЕМ ТОЛЬКО PDF С _ ПРИ ОШИБКЕ
        if (!empty($pdf_filepath) && file_exists($pdf_filepath) && strpos($pdf_filename, '_.pdf') !== false) {
            unlink($pdf_filepath);
            error_log("🗑️ Удален специальный PDF после ошибки JPG: " . $pdf_filename);
        } elseif (!empty($pdf_filepath) && file_exists($pdf_filepath)) {
            error_log("📄 Обычный PDF файл остается после ошибки: " . $pdf_filename);
        }

        // Удаляем временные файлы если они создались
        if (isset($temp_jpg_dir) && file_exists($temp_jpg_dir)) {
            foreach (glob($temp_jpg_dir . '/*.jpg') as $jpg_file) {
                unlink($jpg_file);
            }
            if (is_dir($temp_jpg_dir)) {
                rmdir($temp_jpg_dir);
            }
        }

        error_log("❌ JPG Generation Error: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}