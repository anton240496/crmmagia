<?php
/**
 * Template for CRM dialog message section
 */

// Этот файл подключается через AJAX, поэтому не нужно проверять ABSPATH

if (!defined('ABSPATH')) {
    exit;
}

$template_url = get_template_directory_uri();
$is_pro_license_active = my_plugin_check_license_status(); // Ваша функция проверки
?>

<div class="message-section">
    <div class="message-form">
        <div class="email-field">
            <label>Email получателя:</label>
            <?php
            // Получаем все email для этого диалога
            $all_emails = array();

            // Добавляем главный email (если есть)
            if (!empty($dialog_email)) {
                $all_emails[] = $dialog_email;
            }

            // Добавляем дополнительные email
            $additional_emails = get_dialog_additional_emails($dialog_id);
            foreach ($additional_emails as $email_item) {
                if (!empty($email_item->email)) {
                    $all_emails[] = $email_item->email;
                }
            }

            // Объединяем все email через запятую
            $recipient_emails = implode(', ', $all_emails);
            ?>

            <input type="text" class="recipient-email vvod" value="<?php echo esc_attr($recipient_emails); ?>"
                placeholder="Введите email через запятую" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>" style="width: 100%; margin-bottom: 5px;">

        </div>

        <textarea class="message-text vvod" placeholder="Введите ваше сообщение..." rows="4"></textarea>

        <div class="attachments-container vvod"
            style="display: block; margin: 10px 0; padding: 10px; border: 1px solid #ddd;border-radius: 5px;">
            <div class="updated_title">
                <h4>Прикрепленные файлы:</h4><button class="updated_mes_files">Посмотреть файлы</button>
            </div>
            <div class="attachments-list " data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>">
                <!-- Файлы будут загружены через JavaScript -->
                <p class="cursive">Загрузка файлов...</p>
            </div>
        </div>

        <div class="action-buttons">
            <button class="button button-primary send-message-with-files-dialog" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>">
                Отправить сообщение с файлами
            </button>
            <button class="button button-primary send_prik_firld" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>" style="display: flex; align-items: center; gap: 5px;">
                📎 Прикрепить файл
            </button>

            <button class="button create-file-btn-dialog button-primary" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>">
                Создать файл
                <?php if (!$is_pro_license_active): ?>
                    <span class="crm_pro">PRO</span>
                <?php endif; ?>
            </button>
        </div>

        <!-- ОКНО СОЗДАНИЯ ФАЙЛА ВНУТРИ ДИАЛОГА -->
        <div class="file-creation-window vvod" id="file-window-<?php echo $lead_id; ?>-<?php echo $dialog_id; ?>"
            style="display: none;">
            <div class="file-window-header">
                <div class="file-generation-buttons">
                    <button type="button" class="button generate-pdf-btn-dialog" data-lead-id="<?php echo $lead_id; ?>"
                        data-dialog-id="<?php echo $dialog_id; ?>">Создать PDF</button>
                    <button type="button" class="button generate-jpg-a4-btn-dialog"
                        data-lead-id="<?php echo $lead_id; ?>" data-dialog-id="<?php echo $dialog_id; ?>">Создать
                        PDF,Jpg</button>
                    <div class="save_file_editor_func">
                        <div class="save_file_editor_top">
                            <button class="save_file_editor_open button">Сохранить</button>
                            <button class="editor_replace_btn disabled button"
                                title="Сначала создайте новый файл">заменить</button>
                        </div>
                        <div class="save_file_editor_interface" style="display: none;">
                            <input type="text" value="" class="file-name-input">
                            <div class="file-name-error"
                                style="display: none; color: red; font-size: 12px; margin-top: 5px;">
                                Файл с таким именем уже существует в этом диалоге
                            </div>
                            <div class="save_file_editor_btns">
                                <button class="editor_new_btn">новый</button>
                                <button class="editor_cancel_btn">отмена</button>
                            </div>
                        </div>
                    </div>
                    <div class="save_file_editor">
                        <p>сохраненные файлы</p>
                        <ul class="save_file_spisok">
                            <?php
                            global $wpdb;

                            // Определяем dialog_id (добавляем проверки как в первом варианте)
                            if (isset($dialog) && is_object($dialog) && isset($dialog->id)) {
                                $current_dialog_id = $dialog->id;
                            } elseif (isset($dialog_id)) {
                                $current_dialog_id = $dialog_id;
                            } else {
                                echo '<li class="save_file_empty">Ошибка: не найден ID диалога</li>';
                                return;
                            }

                            $files = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}crm_files 
             WHERE dialog_id = %d 
             ORDER BY created_at DESC",
                                $current_dialog_id
                            ));
                            if ($files && count($files) > 0) {
                                foreach ($files as $file) {
                                    $file_type = $file->pdf ? 'HTML' : ($file->html ? 'HTML' : 'File');



                                    echo '<li class="save_file_item">';
                                    echo '<div class="file-item-name">';
                                    echo '<span class="file-name-text">' . esc_html($file->file_name) . '</span>';
                                    echo '<div class="file-name-edit" style="display: none; align-items: center; gap: 5px;">';
                                    echo '<input type="text" class="file-name-edit-input" value="' . esc_attr(pathinfo($file->file_name, PATHINFO_FILENAME)) . '" placeholder="Введите имя файла" style="flex: 1; padding: 2px 5px; font-size: 13px;">';
                                    echo '<button type="button" class="file-name-save-btn">&#10004;</button>';
                                    echo '<button type="button" class="file-name-cancel-btn">&#10006;</button>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="file-item-info">';
                                    echo '<span class="file-date">' . date('d.m.Y H:i', strtotime($file->created_at)) . '</span>';
                                    echo '</div>';
                                    echo '<div class="file-actions">';
                                    echo '<button type="button" class="file-download" data-file-url="' . esc_url($file->file_url) . '" data-file-name="' . esc_attr($file->file_name) . '" title="Открыть в редакторе">📥</button>';
                                    echo '<button type="button" class="file_edit_editor" data-file-id="' . esc_attr($file->id) . '" data-file-name="' . esc_attr($file->file_name) . '" title="Изменить файл">✏️</button>';
                                    echo '<button type="button" class="file-delete" data-file-id="' . esc_attr($file->id) . '" data-file-name="' . esc_attr($file->file_name) . '" title="удалить файл">🗑️</button>';
                                    echo '</div>';
                                    echo '</li>';
                                }
                            } else {
                                echo '<li class="save_file_empty">файлов в диалоге нет</li>';
                            }
                            ?>
                        </ul>
                    </div>

                    <div class="redactor_file">
                        <?php
                        // 🔥 ДИНАМИЧЕСКИЙ SQL ЗАПРОС ДЛЯ ОТОБРАЖЕНИЯ ТЕКУЩЕГО ФАЙЛА
                        // По умолчанию показываем "редактируется новый"
                        $current_file_text = 'редактируется новый';

                        // Можно добавить логику для определения текущего файла
                        // Например, если есть переменная с текущим файлом или из сессии
                        if (isset($current_editing_file) && !empty($current_editing_file)) {
                            $current_file_text = 'редактируется ' . esc_html($current_editing_file);
                        }

                        echo $current_file_text;
                        ?>
                    </div>



                    <div class="file-window-header"
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; margin-left: auto;">
                        <button type="button" class="close-file-window-dialog" data-lead-id="<?php echo $lead_id; ?>"
                            data-dialog-id="<?php echo $dialog_id; ?>"
                            style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
                    </div>
                </div>
            </div>
            <div class="file-window-scrollable">

                <?php
                // Подключаем функционал
                $crm_editor_path = plugin_dir_path(__FILE__) . 'crm_kp_func.php';

                $file_exists = file_exists($crm_editor_path);

                echo '<script>';
                echo 'console.log("CRM Editor Path: ' . $crm_editor_path . '");';
                echo 'console.log("File exists: ' . ($file_exists ? 'true' : 'false') . '");';
                echo '</script>';
                if (file_exists($crm_editor_path)) {
                    include $crm_editor_path;
                }

                // Подключаем редактор
                $crm_editor_path = plugin_dir_path(__FILE__) . 'crm-editor.php';

                $file_exists = file_exists($crm_editor_path);

                echo '<script>';
                echo 'console.log("CRM Editor Path: ' . $crm_editor_path . '");';
                echo 'console.log("File exists: ' . ($file_exists ? 'true' : 'false') . '");';
                echo '</script>';
                if (file_exists($crm_editor_path)) {
                    include $crm_editor_path;
                } else {
                    echo '<div class="editor-content" contenteditable="true" style="border: 1px solid #ccc; padding: 15px; min-height: 400px; margin-top: 10px;"></div>';
                }
                ?>
            </div>

        </div>
    </div>
</div>
</div>