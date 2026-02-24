<?php

if (!defined('ABSPATH')) {
    exit;
}


global $EMAIL_CONFIG, $wpdb;

// 🔴 ПОЛУЧАЕМ dialog_id из AJAX запроса
$dialog_id = $_POST['dialog_id'] ?? $_GET['dialog_id'] ?? 0;

// Получаем sender_email из БАЗЫ ДАННЫХ для этого диалога
$sender_email_from_db = $wpdb->get_var($wpdb->prepare(
    "SELECT sender_email FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
    $dialog_id
));

// ========== ИСПРАВЛЕНИЕ: Логика для настроек CRM ==========
if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS) {
    // Для настроек CRM: берем первую почту из таблицы crm_email_accounts
    $table_name = $wpdb->prefix . 'crm_email_accounts';
    $email = $wpdb->get_var("SELECT email FROM {$table_name} ORDER BY id ASC LIMIT 1");
    $sender_email = $email ?: 'Нет настроенных почт(1 пункт),если впервые настроили обновите страницу';

   
} else {
    // Обычный режим: из БД диалога или конфига
    $sender_email = ($sender_email_from_db && $sender_email_from_db != '')
        ? $sender_email_from_db
        : (array_keys($EMAIL_CONFIG['accounts'])[0] ?? 'example@domain.com');


}
// ==========================================================

// Получаем фон и логотипы
$table_name = $wpdb->prefix . 'crm_shabl_kp';
$row = $wpdb->get_row("SELECT background_image, logo, avatar FROM $table_name LIMIT 1");

// Фон
$background_url = '';
if (!empty($row) && !empty($row->background_image)) {
    $background_url = home_url('/' . ltrim($row->background_image, '/'));
}

// Логотип
$logo_url = '';
if (!empty($row) && !empty($row->logo)) {
    $logo_url = home_url('/' . ltrim($row->logo, '/'));
}

// Аватар
$avatar_url = '';
if (!empty($row) && !empty($row->avatar)) {
    $avatar_url = home_url('/' . ltrim($row->avatar, '/'));
}
?>
<div class="file-content-editor editor_red" style="background: url('<?php echo esc_url($background_url); ?>')"
    contenteditable="true" data-placeholder="Введите текст для файла...">


    <div class="wap">
        <div class="container">
            <div class="document-header ">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Логотип" class="logo_kp">


                <div class="document-subtitle glav_color">
                    <h1> Ваш заголовок, УТП, оффер, скидка, КП</h1>

                </div>
                <div class="document-subtitle2 ">
                    <h2 class="glav_color">Под заголовок 3 строки</h2>

                </div>


                <div class="address">
                    <div class="address_item botoom two_color">
                        <a class="address_info" href="<?php echo esc_url(home_url()); ?>">
                            <?php echo esc_attr($_SERVER['HTTP_HOST']); ?>
                        </a>
                    </div>

                    <?php
                    global $wpdb;
                    $result = $wpdb->get_row("SELECT telefon_sait_shortcode, mail_sait_shortcode FROM {$wpdb->prefix}crm_shabl_kp WHERE id = 1");
                    ?>
                    <div class="address_item botoom glav_color">
                        <p class="address_info">
                            <span
                                class="current-email <?php echo empty($result->mail_sait_shortcode) ? 'no-selected' : ''; ?>">
                                <?php echo $result && !empty($result->mail_sait_shortcode)
                                    ? esc_html($result->mail_sait_shortcode)
                                    : 'выберите контактную почту'; ?>
                            </span>
                        </p>
                    </div>

                    <div class="address_item botoom glav_color">
                        <p class="address_info">
                            <span
                                class="current-phone <?php echo empty($result->telefon_sait_shortcode) ? 'no-selected' : ''; ?>">
                                <?php echo $result && !empty($result->telefon_sait_shortcode)
                                    ? esc_html($result->telefon_sait_shortcode)
                                    : 'выберите контактный телефон'; ?>
                            </span>
                        </p>
                    </div>



                </div>
            </div>
            <div class="p glav_color"></div>

            <div class="footer_doc">
                <div class="footer_row">

                    <img class="avatar" src="<?php echo esc_url($avatar_url); ?>" alt="avatar">

                    <table class="table_avatar">
                        <tr>
                            <td class="avatar_prof  two_color">Менеджер проектов</td>
                        </tr>
                        <tr>
                            <td class="avatar_name avatar_text glav_color">
                                <?php
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'crm_shabl_kp';

                                // Получаем имя и проверяем условие в одном запросе
                                $result = $wpdb->get_row(
                                    $wpdb->prepare("SELECT name_men FROM {$table_name} ORDER BY id ASC LIMIT 1")
                                );

                                $name_display = 'Имя не указано';
                                $no_selected_class = '';

                                if ($result) {
                                    $name_display = esc_html($result->name_men);
                                    if ($result->name_men === 'Имя менеджера' || empty($result->name_men)) {
                                        $no_selected_class = 'no-selected';
                                    }
                                } else {
                                    // Если записи нет вообще
                                    $no_selected_class = 'no-selected';
                                }
                                ?>

                                <p class="name-display <?php echo $no_selected_class; ?>">
                                    <?php echo $name_display; ?>
                                </p>

                                <?php if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS): ?>
                                    <script type="text/javascript">
                                        // Добавляем ajaxurl если его нет
                                        if (typeof ajaxurl === 'undefined') {
                                            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                                        }
                                    </script>

                                    <button class="red_name_men" contenteditable="false">Сменить</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="avatar_mail avatar_text glav_color" id="kp-sender-email">
                                <?php
                                if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS) {
                                    // Берем первую почту из crm_email_accounts с префиксом WordPress
                                    global $wpdb;

                                    $table_name = $wpdb->prefix . 'crm_email_accounts';
                                    $email = $wpdb->get_var(
                                        $wpdb->prepare("SELECT email FROM {$table_name} ORDER BY id ASC LIMIT 1")
                                    );

                                    // Выводим email или заглушку если нет результатов
                                    echo $email ? esc_html($email) : 'Нет настроенных почт (1 пункт), если впервые настроили обновите страницу';
                                } else {
                                    echo esc_html($sender_email);
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="avatar_tel avatar_text">
                                <?php
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'crm_shabl_kp';

                                // Получаем телефон и проверяем условие
                                $result = $wpdb->get_row(
                                    $wpdb->prepare("SELECT tel_men FROM {$table_name} ORDER BY id ASC LIMIT 1")
                                );

                                $tel_display = 'телефон не указан';
                                $tel_value = '';
                                $no_selected_class = '';

                                if ($result) {
                                    $tel_value = $result->tel_men;
                                    $tel_display = esc_html($tel_value);

                                    if ($result->tel_men === '+7(999)999-99-99' || empty($result->tel_men)) {
                                        $no_selected_class = 'no-selected';
                                    }
                                } else {
                                    // Если записи нет вообще
                                    $no_selected_class = 'no-selected';
                                }
                                ?>

                                <!-- Для PDF и обычного просмотра -->
                                <p class="tel-display glav_color <?php echo $no_selected_class; ?>">
                                    <?php echo $tel_display; ?>
                                </p>

                                <?php if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS): ?>
                                    <!-- Для редактирования в CRM -->
                                    <input class="red_input_tel glav_color <?php echo $no_selected_class; ?>" placeholder="+7 (___) ___-__-__" type="text"
                                        value="<?php echo esc_attr($tel_value); ?>">
                                    <button class="red_tel_men" contenteditable="false">Сменить</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="address_item date">
                                    <p class="address_info two_color">Дата: «<?php echo date('d'); ?>»
                                        <?php
                                        setlocale(LC_TIME, 'ru_RU.UTF-8'); // Устанавливаем русскую локаль
                                        echo strftime('%B');
                                        ?> <?php echo date('Y'); ?> г.
                                    </p>
                                </div>
                            </td>
                        </tr>

                    </table>

                </div>
            </div>

        </div>
    </div>
</div>