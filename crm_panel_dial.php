<?php
// crm_panel_dial.php

if (!defined('ABSPATH')) {
    exit;
}

global $EMAIL_CONFIG, $wpdb;

// Получаем sender_email ИЗ БАЗЫ ДАННЫХ для этого диалога
$sender_email_from_db = $wpdb->get_var($wpdb->prepare(
    "SELECT sender_email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
    $dialog_id
));

// Если в БД нет sender_email, используем первую почту из массива
$sender_email = ($sender_email_from_db && $sender_email_from_db != '') ? $sender_email_from_db : array_keys($EMAIL_CONFIG['accounts'])[0];

$all_emails = array_keys($EMAIL_CONFIG['accounts']);
$additional_emails = get_dialog_additional_emails($dialog_id);

?>
<div class="dialog-item <?php echo $is_active ? 'active' : ''; ?>" data-dialog-id="<?php echo $dialog_id; ?>">
    <div class="dialog-item_wrap">
        <!-- НАЗВАНИЕ ДИАЛОГА С РЕДАКТИРОВАНИЕМ -->
        <div class="field-container field-server" data-table="dialogs" data-lead-id="<?php echo $lead_id; ?>"
            data-dialog-id="<?php echo $dialog_id; ?>" data-field-type="name">
            <div style="display: inline-flex; align-items: center; gap: 5px;">
                <span class="field-text"><?php echo esc_html($dialog_name); ?></span>
            </div>
        </div>


        <?php if ($is_active): ?>
            <span class="active-dialog-indicator">ОТКРЫТ</span>
        <?php endif; ?>

        <br>

        <!-- EMAIL ДИАЛОГА С РЕДАКТИРОВАНИЕМ -->
        <div class="decode_email_wap">
            <div class="email_label">Кому</div>
            <div class="dialog-email-container email_glav" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>"
                style="display: flex; align-items: center; gap: 5px; margin: 2px 0;">
                <small> <span class="dialog-email-text"><?php echo esc_html($display_email); ?></span></small>
                <button type="button" class="edit-dialog-email-btn" style="
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            font-size: 10px;
            padding: 1px;
            line-height: 1;
        " title="Редактировать email диалога">
                    ✏️
                </button>
                <button class="email_dobav">+</button>
                <button type="button" class="toggle-emails-btn" style="
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            font-size: 12px;
            padding: 1px;
            line-height: 1;
        " title="Показать все email">
                    ▼
                </button>
            </div>

            <!-- Контейнер для дополнительных email (скрыт по умолчанию) -->
            <div class="additional-emails-container"
                style="display: none; margin-top: 5px; padding: 5px; background: #f5f5f5; border-radius: 3px;">
                <div class="additional-emails-list">
                    <?php if (!empty($additional_emails)): ?>
                        <?php foreach ($additional_emails as $email_item): ?>
                            <div class="dialog-email-container" data-lead-id="<?php echo $lead_id; ?>"
                                data-dialog-id="<?php echo $dialog_id; ?>" data-email-id="<?php echo $email_item->id; ?>"
                                style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                                <small><span
                                        class="dialog-email-text"><?php echo esc_html($email_item->email); ?></span></small>
                                <button type="button" class="edit-dialog-email-btn" style="
                            background: none;
                            border: none;
                            cursor: pointer;
                            opacity: 0.7;
                            font-size: 10px;
                            padding: 1px;
                            line-height: 1;
                        " title="Редактировать email диалога">
                                    ✏️
                                </button>
                                <button class="email_dial_del">-</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Новые email будут добавляться здесь при нажатии на + -->
                </div>
            </div>
        </div>
        <?php
        // Получаем все email аккаунты из базы данных
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_email_accounts';
        $email_accounts_from_db = $wpdb->get_results("SELECT email FROM $table_name ORDER BY id");

        // Формируем массив всех email-адресов
        $all_emails = array();
        foreach ($email_accounts_from_db as $account) {
            $all_emails[] = $account->email;
        }

        // Получаем email отправителя для этого диалога (из вашего существующего кода)
        $sender_email = $wpdb->get_var($wpdb->prepare(
            "SELECT sender_email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
            $dialog_id
        ));
        ?>

        <!-- ВЫБОР ОТПРАВИТЕЛЯ -->
        <div class="whom_cont">
            <div class="email_label">от кого</div>
            <select class="sender-email-select" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>">

                <?php
                // Фильтруем пустые email
                $filtered_emails = array_filter($all_emails, function ($email) {
                    return !empty(trim($email));
                });
                ?>

                <?php if (!empty($filtered_emails)): ?>
                    <?php foreach ($filtered_emails as $email): ?>
                        <option value="<?php echo $email; ?>" <?php echo $sender_email == $email ? 'selected="selected"' : ''; ?>>
                            <?php echo esc_html($email); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled selected style="color: red;">
                        -- заполните почты в настройках --
                    </option>
                <?php endif; ?>
            </select>
        </div>


        <div>🕐
            <?php echo esc_html($dialog_created_at ? date('d.m.Y H:i', strtotime($dialog_created_at)) : 'Дата не указана'); ?>
        </div>
        <div style="margin-top: 5px;">
            <button type="button" class="show-messages-history-btn" data-lead-id="<?php echo $lead_id; ?>"
                data-dialog-id="<?php echo $dialog_id; ?>" style="
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
        " title="Показать историю сообщений"
                onclick="event.stopPropagation(); showMessagesHistory(<?php echo $lead_id; ?>, <?php echo $dialog_id; ?>)">
                📨 История сообщений beta
            </button>
        </div>
        <button class="dialog_del" data-dialog-id="<?php echo $dialog_id; ?>" data-lead-id="<?php echo $lead_id; ?>">
            Удалить диалог
        </button>
    </div>

    <!-- КНОПКИ -->
    <div onclick="openCloseDialog('<?php echo $lead_id; ?>', <?php echo $dialog_id; ?>, event)" class="opendialog">
        открыть диалог
    </div>




</div>