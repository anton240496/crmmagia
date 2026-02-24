<?php

if (!defined('ABSPATH')) {
    exit;
}

global $is_crm_plugin_page;
$is_crm_plugin_page = true;


/**
 * Template Name: CRM система
 */

// Проверяем авторизацию пользователя
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Получаем заявки из базы
global $wpdb;
$leads = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}crm_leads 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRMMAGIA - Система управления</title>
    <?php wp_head(); ?>
    <!-- Отладка шаблона: Имя файла: <?php echo __FILE__; ?> -->
</head>

<body class=" customize-support">
    <div class="wrap">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">

                <header class="page-header">
                    <div class="header_wap">
                        <h1 class="page-title">CRM система - Заявки с сайта</h1>
                        <div class="header_func">
                            <button class="header_dop header__zayv">
                                обновить <span>0</span> новые заявки с сайта
                            </button>
                            <div>
                                <div class="setting_block">
                                    <a class="set_btn" href="<?= home_url('/crm_settings/') ?>">
                                        <img class="crm_sett_img"
                                            src="<?php echo plugin_dir_url(__FILE__) . 'assets/img/settings.svg'; ?>"
                                            alt="Settings">
                                        Настройки</a>
                                    <?php
                                    // Проверяем, есть ли в таблице wp_crm_email_accounts заполненные данные
                                    global $wpdb;
                                    $email_table = $wpdb->prefix . 'crm_email_accounts';

                                    // Проверяем, есть ли хотя бы одна запись, где заполнены email, password и host
                                    $email_data = $wpdb->get_row($wpdb->prepare(
                                        "SELECT COUNT(*) as count FROM $email_table 
                                            WHERE email IS NOT NULL 
                                            AND email != '' 
                                            AND password IS NOT NULL 
                                            AND password != '' 
                                            AND host IS NOT NULL 
                                            AND host != ''"
                                    ));

                                    $show_email_notice = false; // По умолчанию скрываем
                                    
                                    // Проверяем результат
                                    if ($email_data && $email_data->count == 0) {
                                        // Нет заполненных записей - показываем уведомление
                                        $show_email_notice = true;
                                    }

                                    // Также проверяем, существует ли таблица вообще
                                    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_table)) == $email_table;
                                    if (!$table_exists) {
                                        // Таблицы не существует - тоже показываем уведомление
                                        $show_email_notice = true;
                                    }
                                    ?>

                                    <div class="vnimanie">
                                        <?php if ($show_email_notice): ?>
                                            <a href="<?= home_url('/crm_settings/') ?>#mail_link"
                                                class="vnim_block vnim_mail" target="_blank">
                                                <div class="vnim_kratko">
                                                    <span class="exclamation">!</span>
                                                </div>
                                                <div class="vnim_podr">
                                                    1.Заполните: почту, пароль и хост SMTP
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                        <?php
                                        // Проверяем данные для второго пункта (шаблон письма)
                                        global $wpdb;
                                        $shablon_table = $wpdb->prefix . 'crm_shabl_mes';

                                        $show_shablon_notice = false; // По умолчанию скрываем
                                        
                                        // Проверяем существование таблицы
                                        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $shablon_table)) == $shablon_table;

                                        if ($table_exists) {
                                            // Проверяем два условия:
                                            // 1. Есть ли запись с name = "Агентство" (без учета регистра)
                                            // 2. Есть ли записи с пустым name
                                            $check_condition = $wpdb->get_var($wpdb->prepare(
                                                "SELECT COUNT(*) FROM $shablon_table 
                                                    WHERE LOWER(name) = %s 
                                                    OR name IS NULL 
                                                    OR name = ''",
                                                'агентство'
                                            ));

                                            // Если есть записи, соответствующие условию - показываем уведомление (display flex)
                                            if ($check_condition && $check_condition > 0) {
                                                $show_shablon_notice = true;
                                            }
                                        } else {
                                            // Таблицы не существует - тоже показываем уведомление
                                            $show_shablon_notice = true;
                                        }
                                        ?>
                                        <?php if ($show_shablon_notice): ?>
                                            <a href="<?= home_url('/crm_settings/') ?>#shablon_link"
                                                class="vnim_block vnim_shablon" target="_blank">
                                                <div class="vnim_kratko">
                                                    <span class="exclamation">!</span>
                                                </div>
                                                <div class="vnim_podr">
                                                    2. настройте шаблон письма

                                                </div>
                                            </a>
                                        <?php endif; ?>
                                        <?php
                                        // Проверяем данные для третьего пункта (шаблон КП)
                                        global $wpdb;
                                        $kp_table = $wpdb->prefix . 'crm_shabl_kp';

                                        $show_kp_notice = false; // По умолчанию скрываем
                                        
                                        // Проверяем существование таблицы
                                        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $kp_table)) == $kp_table;

                                        if ($table_exists) {
                                            // Проверяем условия:
                                            // 1. telefon_sait_shortcode пустой
                                            // 2. mail_sait_shortcode пустой  
                                            // 3. name_men = "Имя менеджера"
                                            // 4. tel_men = "+7(999)999-99-99"
                                            $kp_data = $wpdb->get_row(
                                                "SELECT COUNT(*) as count FROM $kp_table 
                                                    WHERE (telefon_sait_shortcode IS NULL OR telefon_sait_shortcode = '')
                                                       OR (mail_sait_shortcode IS NULL OR mail_sait_shortcode = '')
                                                       OR name_men = 'Имя менеджера'
                                                       OR tel_men = '+7(999)999-99-99'"
                                            );

                                            // Если есть записи, соответствующие условиям - показываем уведомление
                                            if ($kp_data && $kp_data->count > 0) {
                                                $show_kp_notice = true;
                                            }
                                        } else {
                                            // Таблицы не существует - показываем уведомление
                                            $show_kp_notice = true;
                                        }
                                        ?>
                                        <?php
                                        // Проверяем две вещи:
                                        // 1. Нужно ли показывать уведомление о шаблоне КП
                                        // 2. Активна ли PRO/VIP лицензия
                                        $show_block = $show_kp_notice && my_plugin_check_license_status();
                                        ?>

                                        <?php if ($show_block): ?>
                                            <a href="<?= home_url('/crm_settings/') ?>#kp_link" class="vnim_block vnim_kp"
                                                target="_blank">
                                                <div class="vnim_kratko">
                                                    <span class="exclamation">!</span>
                                                </div>
                                                <div class="vnim_podr">
                                                    3. Настройте шаблон КП
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>



                        </div>


                    </div>


        </div>
    </div>
    </div>
    <?php
    $stats = get_crm_stats();
    ?>

    <div class="crm-stats">
        <div class="stat-card status-all" data-filter="all">
            <span class="stat-number"><?php echo $stats['total']; ?></span>
            <span class="stat-label">Всего заявок</span>
        </div>
        <div class="stat-card status-xolod" data-filter="xolod">
            <span class="stat-number"><?php echo $stats['xolod']; ?></span>
            <span class="stat-label">Холодный</span>
        </div>
        <div class="stat-card status-sozvon" data-filter="sozvon">
            <span class="stat-number"><?php echo $stats['sozvon']; ?></span>
            <span class="stat-label">Созвонились</span>
        </div>
        <div class="stat-card status-otpr" data-filter="otpr">
            <span class="stat-number"><?php echo $stats['otpr']; ?></span>
            <span class="stat-label">Отправили КП</span>
        </div>
        <div class="stat-card status-tepl" data-filter="tepl">
            <span class="stat-number"><?php echo $stats['tepl']; ?></span>
            <span class="stat-label">Теплый</span>
        </div>
        <div class="stat-card status-gorak" data-filter="gorak">
            <span class="stat-number"><?php echo $stats['gorak']; ?></span>
            <span class="stat-label">Горячий</span>
        </div>
    </div>
    </header>
    <div class="create_zayv_container">
        <form action="" class="create_zayv_form" id="create_zayv_form">
            <div class="create_zayv_item">
                <label for="zayv_name" class="create_zayv_label">Имя заявки*</label>
                <input class="create_zayv_input" type="text" id="zayv_name" name="zayv_name" required
                    placeholder="Введите уникальное имя заявки">
            </div>
            <div class="create_zayv_item">
                <label for="client_name" class="create_zayv_label">Имя клиента*</label>
                <input class="create_zayv_input" type="text" id="client_name" name="client_name" required
                    placeholder="Введите имя клиента">
            </div>
            <div class="create_zayv_item">
                <label for="client_phone" class="create_zayv_label">Телефон*</label>
                <input class="create_zayv_input" type="text" id="client_phone" name="client_phone" required
                    placeholder="+7 (___) ___-__-__">
            </div>

            <button type="submit" class="create_zayv">Создать заявку</button>
        </form>
        <div id="create_zayv_message" style="display:none;"></div>
    </div>
    <div class="crm-container">
        <div class="crm-table wp-list-table widefat fixed striped">
            <?php if (empty($leads)): ?>
                <div>
                    <div class="no-leads">Заявок пока нет</div>
                </div>
            <?php else: ?>
                <?php foreach ($leads as $lead): ?>
                    <div class="lead_wap_content">
                        <div class="lead-row status-<?php echo $lead->status; ?>" data-lead-id="<?php echo $lead->id; ?>">


                            <div class="lead-name-zayv">
                                <button class="zayv_del" data-lead-id="<?php echo $lead->id; ?>">удалить заявку</button>

                                <div class="lead-name_ihey">
                                    <div class="zayv_stold_name">имя заявки</div>
                                    <div class="name-zayv-edit-container" data-lead-id="<?php echo $lead->id; ?>">
                                        <div class="name-zayv-display" style="display: flex; align-items: center; gap: 5px;">
                                            <?php if (!empty($lead->name_zayv)): ?>
                                                <span class="name-zayv-text"><?php echo esc_html($lead->name_zayv); ?></span>
                                            <?php else: ?>
                                                <span class="no-name-zayv" style="color: #dc3232; font-style: italic;">Не
                                                    указан</span>
                                                <button type="button" class="edit-name-zayv-btn" style="
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            font-size: 12px;
            padding: 2px;
        " title="Редактировать имя заявки">
                                                    ✏️
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="name-zayv-edit" style="display: none;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <input type="text" class="name-zayv-input"
                                                    value="<?php echo esc_attr($lead->name_zayv); ?>"
                                                    placeholder="Введите имя заявки"
                                                    style="flex: 1; padding: 2px 5px; font-size: 13px;">
                                                <button type="button"
                                                    class="save-name-zayv-btn button button-small button-primary"
                                                    style="padding: 2px 8px; font-size: 12px;">
                                                    ✓
                                                </button>
                                                <button type="button" class="cancel-name-zayv-btn button button-small"
                                                    style="padding: 2px 8px; font-size: 12px;">
                                                    ✕
                                                </button>
                                            </div>
                                            <div class="name-zayv-status" style="font-size: 11px; margin-top: 2px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lead-name_ihey">
                                <div class="zayv_stold_name">имя клиента</div>
                                <div class="field-container" data-table="leads" data-lead-id="<?php echo $lead->id; ?>"
                                    data-field-type="name">

                                    <span class="field-text"><?php echo esc_html($lead->name); ?></span>

                                </div>
                            </div>
                            <div class="lead-name_ihey">

                                <div class="zayv_stold_name">Телефон</div>

                                <div class="lead-phone field-container" data-table="leads"
                                    data-lead-id="<?php echo $lead->id; ?>" data-field-type="phone">
                                    <a class="field-text phone-link" href="tel:<?php echo esc_attr($lead->phone); ?>"
                                        data-original-phone="<?php echo esc_attr($lead->phone); ?>">

                                    </a>

                                    <button type="button" class="edit-field-btn" title="Редактировать Телефон">
                                        ✏️
                                    </button>
                                </div>
                            </div>

                            <div class="lead-name_ihey data_zayv">

                                <div class="lead-date">
                                    <?php echo date('d.m.Y H:i', strtotime($lead->created_at)); ?>
                                </div>
                            </div>
                            <div class="lead-item" data-lead-id="<?php echo $lead->id; ?>">

                                <div class="lead-name_ihey">
                                    <div class="zayv_stold_name">Статус</div>
                                    <div class="lead-status">
                                        <select class="status-select" data-lead-id="<?php echo $lead->id; ?>">
                                            <option value="xolod" <?php selected($lead->status, 'xolod'); ?>>Холодный
                                            </option>
                                            <option value="sozvon" <?php selected($lead->status, 'sozvon'); ?>>
                                                Созвонились</option>
                                            <option value="otpr" <?php selected($lead->status, 'otpr'); ?>>Отправили КП
                                            </option>
                                            <option value="tepl" <?php selected($lead->status, 'tepl'); ?>>Теплый
                                            </option>
                                            <option value="gorak" <?php selected($lead->status, 'gorak'); ?>>Горячий
                                            </option>
                                        </select>
                                    </div>
                                </div>

                            </div>


                            <div class="lead-name_ihey ihey_spisok">
                                <button class="obnova_doc_stat">обновить</button>
                                <div class="zayv_stold_name ihey_spisok_name">
                                    <div>Документы</div>
                                    <div class="doc_spisok_itog">
                                        <?php
                                        global $wpdb;
                                        $doc_data = $wpdb->get_row($wpdb->prepare(
                                            "SELECT * FROM {$wpdb->prefix}crm_doc WHERE lead_id = %d",
                                            $lead->id
                                        ));

                                        // Проверяем, все ли поля заполнены
                                        $all_fields_filled = true;
                                        if ($doc_data) {
                                            $required_fields = [
                                                'recipient',
                                                'chet',
                                                'bankrec',
                                                'bik',
                                                'korchet',
                                                'inn',
                                                'kpp',
                                                'okpo',
                                                'ogrn',
                                                'swift',
                                                'addrbank',
                                                'addroffice'
                                            ];

                                            foreach ($required_fields as $field) {
                                                if (empty($doc_data->$field)) {
                                                    $all_fields_filled = false;
                                                    break;
                                                }
                                            }
                                        } else {
                                            $all_fields_filled = false;
                                        }

                                        // Выводим иконку в зависимости от результата проверки
                                        if ($all_fields_filled) {
                                            echo '✅'; // Все поля заполнены
                                        } else {
                                            echo '❌'; // Есть незаполненные поля
                                        }
                                        ?>
                                    </div>

                                    <div class="arrow">∨</div>
                                </div>
                                <ul class="doc_spisok">
                                    <?php
                                    global $wpdb;
                                    $doc_data = $wpdb->get_row($wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}crm_doc WHERE lead_id = %d",
                                        $lead->id
                                    ));
                                    ?>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Получатель:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="recipient">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->recipient) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->recipient)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->recipient); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Номер счёта: </h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="chet">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->chet) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->chet)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->chet); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Банк получателя: </h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="bankrec">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->bankrec) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->bankrec)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->bankrec); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">БИК:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="bik">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->bik) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->bik)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->bik); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Корр. счёт:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="korchet">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->korchet) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->korchet)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->korchet); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">ИНН:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="inn">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->inn) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->inn)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->inn); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">КПП: </h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="kpp">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->kpp) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->kpp)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->kpp); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">ОКПО:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="okpo">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->okpo) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->okpo)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->okpo); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">ОГРНИП: </h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="ogrn">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->ogrn) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->ogrn)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->ogrn); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">SWIFT-код: </h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="swift">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->swift) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->swift)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->swift); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Почтовый адрес банка:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="addrbank">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->addrbank) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->addrbank)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->addrbank); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>

                                    <li class="doc_spisok_item">
                                        <h4 class="doc_spisok_name">Почтовый адрес доп.офиса:</h4>
                                        <div class="field-container" data-table="doc"
                                            data-lead-id="<?php echo $doc_data->id; ?>" data-field-type="addroffice">
                                            <p
                                                class="doc_spisok_text <?php echo !empty($doc_data->addroffice) ? 'filled-data' : 'empty-data'; ?>">
                                                <?php if (!empty($doc_data->addroffice)): ?>
                                                    ✅ <span class="field-text"><?php echo esc_html($doc_data->addroffice); ?></span>
                                                <?php else: ?>
                                                    ❌ <span class="field-text">Не указан</span>
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="edit-field-btn" title="Редактировать">
                                                ✏️
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="lead-actions">
                                <button type="button" class="button button-primary toggle-dialog"
                                    data-lead-id="<?php echo $lead->id; ?>" aria-expanded="false"
                                    aria-controls="dialog-panel-<?php echo $lead->id; ?>">
                                    <span class="dialog-text">Диалог</span>
                                    <span class="dashicons dashicons-arrow-down"></span>
                                </button>
                            </div>
                        </div>

                        <div class="dialog-row" id="dialog-row-<?php echo $lead->id; ?>" aria-hidden="true">
                            <div class="dialog-cell">
                                <div class="dialog-panel" id="dialog-panel-<?php echo $lead->id; ?>" aria-hidden="true">

                                    <!-- СЦЕНАРИЙ 1: Нет диалогов -->
                                    <div id="scenario1-<?php echo $lead->id; ?>" class="scenario-panel">
                                        <div class="no-dialogs-message">
                                            <h4>📝 У этого клиента пока нет диалогов</h4>
                                            <p>Создайте первый диалог для начала переписки</p>

                                            <div class="create-dialog-form" id="createDialogForm-<?php echo $lead->id; ?>"
                                                style="display: none; margin-top: 15px;">
                                                <input type="text" class="new-dialog-name"
                                                    placeholder="Введите название диалога">
                                                <div style="margin-top: 10px;">
                                                    <button type="button" class="button button-primary confirm-create-dialog"
                                                        data-lead-id="<?php echo $lead->id; ?>">Создать</button>
                                                    <button type="button" class="button cancel-create-dialog"
                                                        data-lead-id="<?php echo $lead->id; ?>">Отмена</button>
                                                </div>
                                            </div>

                                            <button type="button" class="button button-primary create-dialog-btn"
                                                data-lead-id="<?php echo $lead->id; ?>">
                                                + Создать первый диалог
                                            </button>
                                        </div>
                                    </div>

                                    <!-- СЦЕНАРИЙ 2: Есть диалоги -->
                                    <div id="scenario2-<?php echo $lead->id; ?>" class="scenario-panel" style="display: none;">
                                        <div class="dialogs-header">
                                            <h4>💬 Диалоги клиента</h4>
                                            <button type="button" class="button create-dialog-btn"
                                                data-lead-id="<?php echo $lead->id; ?>">
                                                + Новый диалог
                                            </button>
                                        </div>

                                        <div class="create-dialog-form" id="createDialogForm2-<?php echo $lead->id; ?>"
                                            style="display: none; margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                            <input type="text" class="new-dialog-name" placeholder="Введите название диалога"
                                                style="width: 100%; margin-bottom: 10px;">
                                            <div>
                                                <button type="button" class="button button-primary confirm-create-dialog"
                                                    data-lead-id="<?php echo $lead->id; ?>">Создать</button>
                                                <button type="button" class="button cancel-create-dialog"
                                                    data-lead-id="<?php echo $lead->id; ?>">Отмена</button>
                                            </div>
                                        </div>

                                        <!-- СПИСОК ДИАЛОГОВ -->
                                        <div id="dialogsList-<?php echo $lead->id; ?>" class="dialogs-list">
                                            <!-- Диалоги будут загружены через JavaScript -->

                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </div>


    </div>
</body>

<?php

wp_footer();
?>