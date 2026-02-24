<?php
if (!defined('ABSPATH')) {
    exit;
}

global $is_crm_plugin_page;
$is_crm_plugin_page = true;



// Проверяем авторизацию пользователя
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}

// Подключаем файл с конфигурацией
require_once plugin_dir_path(__FILE__) . 'functions-crm.php';

global $wpdb;




// 🔧 АВТОМАТИЧЕСКОЕ СОЗДАНИЕ ОДНОЙ ПАПКИ ДЛЯ ПЛАГИНА
// Проверяем наличие активной PRO/VIP лицензии
$is_pro_active = my_plugin_check_license_status();

$upload_dir = wp_upload_dir();
$crm_folder = '/crm_files/shablon/assets/img/';
$full_path = $upload_dir['basedir'] . $crm_folder;

// Создаем папки и файлы ТОЛЬКО если есть подписка
if ($is_pro_active) {

    $upload_dir = wp_upload_dir();
    global $wpdb;

    $upload_dir = wp_upload_dir();

    // 1. Путь к ПАПКЕ с CSS
    $css_folder = $upload_dir['basedir'] . '/crm_files/shablon/assets/css/';

    // 2. Путь к ФАЙЛУ CSS
    $css_file = $css_folder . 'style_kp.css';

    // 3. Создаем папку если ее нет
    if (!file_exists($css_folder)) {
        wp_mkdir_p($css_folder);

        // Защита от листинга
        file_put_contents($css_folder . 'index.php', '<?php // Silence is golden');
    }


    // 5. Проверяем для папки img (если нужно)
    $img_folder = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
    if (!file_exists($img_folder)) {
        wp_mkdir_p($img_folder);
        file_put_contents($img_folder . 'index.php', '<?php // Silence is golden');
    }
}

// Если нет подписки - папки не создаются
// Существующие папки остаются (как и с таблицей)

// Сохраняем путь для использования
$GLOBALS['crm_upload_path'] = $full_path;
$GLOBALS['crm_upload_url'] = $upload_dir['baseurl'] . $crm_folder;

// 🔧 ВСЕГДА ПОЛУЧАЕМ СВЕЖУЮ КОНФИГУРАЦИЮ В НАЧАЛЕ
$EMAIL_CONFIG = get_crm_email_accounts();
// 🔧 ОБРАБОТКА ИЗМЕНЕНИЯ СОСТОЯНИЯ ACTIVE
if (isset($_POST['update_active_status'])) {
    $active_status = isset($_POST['host']) ? 1 : 0;

    $table_name = $wpdb->prefix . 'crm_email_accounts';

    // Обновляем active у всех записей
    $result = $wpdb->query(
        $wpdb->prepare("UPDATE $table_name SET active = %d", $active_status)
    );

    if ($result !== false) {
        $status_text = $active_status ? 'включен' : 'выключен';
        $success_message = "Режим 'один хост у всех почт' $status_text";
        error_log("✅ CRM: Active status updated to: $active_status");

        // Обновляем конфигурацию
        $EMAIL_CONFIG = get_crm_email_accounts();
    } else {
        $error_message = "Ошибка при изменении настроек";
    }
}
// 🔧 ОБРАБОТКА ИЗМЕНЕНИЯ ХОСТА - ОТДЕЛЬНАЯ ФОРМА
if (isset($_POST['update_host_action']) && isset($_POST['smtp_host'])) {
    $new_host = sanitize_text_field($_POST['smtp_host']);

    if (!empty($new_host)) {
        $table_name = $wpdb->prefix . 'crm_email_accounts';

        error_log("🔧 CRM: Attempting to update host to: $new_host");

        // Обновляем хост у всех существующих почт
        $result = $wpdb->query(
            $wpdb->prepare("UPDATE $table_name SET host = %s", $new_host)
        );

        if ($result !== false) {
            $success_message = "Хост успешно изменен на: $new_host";
            error_log("✅ CRM: Host updated successfully to: $new_host");

            // 🔧 ОБНОВЛЯЕМ КОНФИГУРАЦИЮ СРАЗУ ПОСЛЕ ИЗМЕНЕНИЯ
            $EMAIL_CONFIG = get_crm_email_accounts();
        } else {
            $error_message = "Ошибка при изменении хоста";
            error_log("❌ CRM: Failed to update host");
        }
    }
}

// 🔧 ОБРАБОТКА УДАЛЕНИЯ ПОЧТЫ
if (isset($_POST['delete_email'])) {
    // ... ваш существующий код удаления ...
}

// СОХРАНЕНИЕ ФОРМЫ EMAIL (ТОЛЬКО ЕСЛИ НЕ ИЗМЕНЕНИЕ ХОСТА)
if (isset($_POST['email']) && is_array($_POST['email']) && !isset($_POST['update_host_action'])) {
    // ... ваш существующий код сохранения email ...
}
// 🔧 ВСЕГДА ПОЛУЧАЕМ СВЕЖУЮ КОНФИГУРАЦИЮ В НАЧАЛЕ
$EMAIL_CONFIG = get_crm_email_accounts();

// 🔧 ОБРАБОТКА ИЗМЕНЕНИЯ ХОСТА - ОТДЕЛЬНАЯ ФОРМА
// СОХРАНЕНИЕ ФОРМЫ EMAIL (ТОЛЬКО ЕСЛИ НЕ ИЗМЕНЕНИЕ ХОСТА)
if (isset($_POST['email']) && is_array($_POST['email']) && !isset($_POST['update_host_action'])) {

    // Создаем таблицу если её нет
    $table_name = $wpdb->prefix . 'crm_email_accounts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
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

    // 🔧 ИСПРАВЛЕНИЕ: ВСЕГДА СОХРАНЯЕМ ТЕКУЩИЙ ХОСТ ИЗ БАЗЫ
    $current_host = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1");
    $host_to_use = $current_host ?: ''; // Если хоста нет, используем пустую строку

    // Очищаем таблицу перед сохранением новых данных
    $wpdb->query("TRUNCATE TABLE $table_name");

    $saved_count = 0;
    // Сохраняем каждый email и пароль в базу данных
    foreach ($_POST['email'] as $index => $email) {
        if (!empty($email) && !empty($_POST['password'][$index])) {
            // 🔧 ИСПРАВЛЕНИЕ: Если active = 1, используем общий хост
            if ($active_status == 1) {
                $individual_host = $host_to_use;
            } else {
                // Если active = 0, используем индивидуальный хост из формы
                $individual_host = !empty($_POST['host'][$index]) ? sanitize_text_field($_POST['host'][$index]) : '';
            }

            $result = $wpdb->insert(
                $table_name,
                array(
                    'email' => sanitize_email($email),
                    'password' => sanitize_text_field($_POST['password'][$index]),
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

    if ($saved_count > 0) {
        $success_message = "Почта и пароль успешно добавлены/изменены! Сохранено записей: $saved_count";
        // 🔧 ОБНОВЛЯЕМ КОНФИГУРАЦИЮ ПОСЛЕ СОХРАНЕНИЯ
        $EMAIL_CONFIG = get_crm_email_accounts();
    } else {
        $error_message = "Ошибка: не удалось сохранить данные";
    }
}

// 🔧 ОБРАБОТКА УДАЛЕНИЯ ПОЧТЫ
if (isset($_POST['delete_email'])) {
    $email_to_delete = sanitize_email($_POST['delete_email']);

    if (is_email($email_to_delete)) {
        $table_name = $wpdb->prefix . 'crm_email_accounts';

        // 🔧 ПРОВЕРЯЕМ СКОЛЬКО ПОЧТ ОСТАЛОСЬ
        $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($total_emails <= 1) {
            $error_message = "Нельзя удалить последнюю почту! Должна остаться хотя бы одна почта.";
        } else {
            $result = $wpdb->delete(
                $table_name,
                array('email' => $email_to_delete),
                array('%s')
            );

            if ($result) {
                $success_message = "Почта $email_to_delete успешно удалена!";
                // 🔧 ОБНОВЛЯЕМ КОНФИГУРАЦИЮ ПОСЛЕ УДАЛЕНИЯ
                $EMAIL_CONFIG = get_crm_email_accounts();
            } else {
                $error_message = "Ошибка при удалении почты $email_to_delete";
            }
        }
    }
}


// СОХРАНЕНИЕ ФОРМЫ EMAIL (ТОЛЬКО ЕСЛИ НЕ ИЗМЕНЕНИЕ ХОСТА)
if (isset($_POST['email']) && is_array($_POST['email']) && !isset($_POST['update_host_action'])) {

    // Создаем таблицу если её нет
    $table_name = $wpdb->prefix . 'crm_email_accounts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
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


    // 🔧 ИСПРАВЛЕНИЕ: ВСЕГДА ИСПОЛЬЗУЕМ ХОСТ ИЗ БАЗЫ ДАННЫХ
    $main_host = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1") ?: '';

    // Но перед этим сохраняем текущий хост до очистки таблицы
    $current_host_before_truncate = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1") ?: '';
    $main_host = $current_host_before_truncate;// Хост из верхнего поля

    // Очищаем таблицу перед сохранением новых данных
    $wpdb->query("TRUNCATE TABLE $table_name");

    $saved_count = 0;
    // Сохраняем каждый email и пароль в базу данных
    foreach ($_POST['email'] as $index => $email) {
        if (!empty($email) && !empty($_POST['password'][$index])) {
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
                    'password' => sanitize_text_field($_POST['password'][$index]),
                    'host' => $individual_host,
                    'active' => $active_status // 🔧 СОХРАНЯЕМ ACTIVE
                ),
                array('%s', '%s', '%s', '%d')
            );
            if ($result) {
                $saved_count++;
            }

            error_log("🔍 CRM: Saved email $email with active=$active_status, host=$individual_host");
        }
    }

    if ($saved_count > 0) {
        $success_message = "Почта и пароль успешно добавлены/изменены! Сохранено записей: $saved_count";
        // 🔧 ОБНОВЛЯЕМ КОНФИГУРАЦИЮ ПОСЛЕ СОХРАНЕНИЯ
        $EMAIL_CONFIG = get_crm_email_accounts();
    } else {
        $error_message = "Ошибка: не удалось сохранить данные";
    }
}

// 🔧 ОБНОВЛЯЕМ ДАННЫЕ ДЛЯ ОТОБРАЖЕНИЯ
$email_accounts = array();
$index = 0;
foreach ($EMAIL_CONFIG['accounts'] as $email => $password) {
    $email_accounts[] = (object) array(
        'id' => $index++,
        'email' => $email,
        'password' => $password
    );
}


define('IS_CRM_SETTINGS', true);

$is_crm_settings = true;



?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRMMAGIA - Настройки</title>
    <?php wp_head(); ?>
    <!-- Отладка шаблона: Имя файла: <?php echo __FILE__; ?> -->
</head>

<body data-is-crm-settings="1">
    <a class="glav_str_btn" href="<?= home_url('/CRMMagia/') ?>">
        Главная страница</a>
    <div class="settings-container">
        <h1>Настройки CRM системы</h1>
        <!-- БЛОК УВЕДОМЛЕНИЙ -->
        <?php if (isset($success_message)): ?>
            <div class="notice notice-success is-dismissible"
                style="margin: 20px 0; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">
                <p style="margin: 0; font-size: 16px; font-weight: bold;">✅ <?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="notice notice-error is-dismissible"
                style="margin: 20px 0; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">
                <p style="margin: 0; font-size: 16px; font-weight: bold;">❌ <?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>
        <div class="set_punkt" id="mail_link">
            <div class="set_head_wap">
                <div class="set_podzag">
                    <h2>1. Логины и пароли</h2>
                    <button type="button" class="dobav_login">добавить</button>
                </div>
                <!-- 🔧 ОТДЕЛЬНАЯ ФОРМА ДЛЯ ИЗМЕНЕНИЯ ХОСТА -->
                <?php
                // Определяем active_status до использования в форме
                global $wpdb;
                $table_name = $wpdb->prefix . 'crm_email_accounts';
                $active_status = $wpdb->get_var("SELECT active FROM $table_name LIMIT 1");
                if ($active_status === null) {
                    $active_status = 1; // Значение по умолчанию
                }
                $is_checked = ($active_status == 1) ? 'checked' : '';
                ?>

                <form class="set_head_from" method="POST" id="host-form">
                    <div class="host_glav"
                        style="<?php echo ($active_status == 0) ? 'display: none;' : 'display: flex;'; ?>">
                        <div class="login_wrap">
                            <label for="smtp_host">хост</label>
                            <input type="text" name="smtp_host" id="smtp_host" placeholder="введите SMTP хост" value="<?php
                            $current_host = $wpdb->get_var("SELECT host FROM $table_name LIMIT 1");
                            echo esc_attr($current_host ?: '');
                            ?>" required>
                        </div>
                        <button type="submit" name="update_host_action" class="update_host">добавить / изменить
                            хост</button>
                    </div>

                    <div class="wrap_heck">
                        <label for="active_checkbox" class="checkbox-label">
                            <input type="checkbox" name="host" id="active_checkbox" <?php echo $is_checked; ?>>
                            <span>один хост у всех почт</span>
                        </label>
                    </div>

                </form>
            </div>

            <!-- 🔧 ОСНОВНАЯ ФОРМА ДЛЯ СОХРАНЕНИЯ EMAIL -->
            <form method="POST" id="email-settings-form">
                <ul class="login_spisok">
                    <?php
                    // Получаем состояние active из базы данных
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'crm_email_accounts';
                    $active_status = $wpdb->get_var("SELECT active FROM $table_name LIMIT 1");
                    if ($active_status === null) {
                        $active_status = 1; // Значение по умолчанию
                    }
                    $hide_host = ($active_status == 1) ? 'style="display: none;"' : '';
                    ?>

                    <?php if (empty($email_accounts)): ?>
                        <li class="login_item">
                            <div class="login_input">
                                <div class="login_wrap">
                                    <label>Почта</label>
                                    <input type="email" name="email[]" placeholder="ваша почта" required
                                        autocomplete="new-password">
                                </div>
                                <div class="login_wrap">
                                    <label>Пароль</label>
                                    <input type="password" name="password[]" placeholder="пароль" required
                                        autocomplete="new-password">
                                </div>
                                <div class=" login_wrap_host" <?php echo $hide_host; ?>>
                                    <label>Хост</label>
                                    <?php
                                    $account_host = $wpdb->get_var($wpdb->prepare(
                                        "SELECT host FROM $table_name WHERE email = %s",
                                        $account->email
                                    ));
                                    $host_required = ($active_status == 1) ? '' : 'required';
                                    ?>

                                    <input type="text" name="host[]" value="<?php echo esc_attr($account_host ?: ''); ?>"
                                        placeholder="введите SMTP хост" <?php echo $host_required; ?>>
                                </div>
                            </div>
                            <button type="submit" class="update_login">добавить /<br> Изменить</button>
                        </li>
                    <?php else: ?>
                        <?php foreach ($email_accounts as $account): ?>
                            <li class="login_item" data-email="<?php echo esc_attr($account->email); ?>">
                                <div class="login_input">
                                    <div class="login_wrap">
                                        <label>Почта</label>
                                        <input type="email" name="email[]" value="<?php echo esc_attr($account->email); ?>"
                                            placeholder="ваша почта" required autocomplete="new-password">
                                    </div>
                                    <div class="login_wrap">
                                        <label>Пароль</label>
                                        <input type="password" name="password[]"
                                            value="<?php echo esc_attr($account->password); ?>" placeholder="пароль" required
                                            autocomplete="new-password">
                                    </div>
                                    <div class=" login_wrap_host" <?php echo $hide_host; ?>>
                                        <label>Хост</label>
                                        <?php
                                        $account_host = $wpdb->get_var($wpdb->prepare(
                                            "SELECT host FROM $table_name WHERE email = %s",
                                            $account->email
                                        ));
                                        $host_required = ($active_status == 1) ? '' : 'required';
                                        ?>
                                        <input type="text" name="host[]" value="<?php echo esc_attr($account_host ?: ''); ?>"
                                            placeholder="введите SMTP хост" <?php echo $host_required; ?>>
                                    </div>
                                </div>
                                <button type="submit" class="update_login">добавить /<br> Изменить</button>
                                <?php if ($account->id > 0): ?>
                                    <button type="button" class="remove_login"
                                        data-email="<?php echo esc_attr($account->email); ?>">удалить</button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </form>

            <!-- 🔧 СКРЫТАЯ ФОРМА ДЛЯ УДАЛЕНИЯ -->
            <form method="POST" id="delete-email-form" style="display: none;">
                <input type="hidden" name="delete_email" id="delete_email_input">
            </form>
            <div class="primer_wrap">
                <div class="primer_host">
                    <h3 class="primer_host_title">Примеры хостов:</h3>
                    <ul class="primer_host_spis">
                        <li>
                            <p class="primer_host_posht">почтовый сервис mail</p>
                            <p class="primer_host">smtp.mail.ru</p>
                        </li>
                        <li>
                            <p class="primer_host_posht">почтовый сервис яндекс</p>
                            <p class="primer_host">smtp.yandex.ru</p>
                        </li>
                        <li>
                            <p class="primer_host_posht">корпоративная почта, сервис reg.ru</p>
                            <p class="primer_host">mail.hosting.reg.ru</p>
                        </li>

                    </ul>
                </div>
                <p class="host_pred"><span class="host_pred_vag">*</span> Для отправки писем через crm систему нужно
                    вести хост, как минимум одну почту и ее
                    пароль, если вы не используете
                    корпаративные почты,
                    то в таком случае для каждой почты вам нужно установить специальный пароль для приложений,
                    <a target="_blank"
                        href="https://www.uiscom.ru/academiya/spravochnyj-centr/analitika-reklamy/kak-sozdat-parol-dlya-prilozheniya/">посмотреть
                        как это сделать можно здесь.</a>
                </p>
            </div>
        </div>
    </div>
    </div>

    <?php
    // Создаем таблицу шаблона письма
    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_mes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) DEFAULT 'Агентство',
    color VARCHAR(255),
    podval VARCHAR(255),
    active BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Проверяем создалась ли таблица
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    if (!$table_exists) {
        echo '<div class="error">Ошибка: таблица не создана</div>';
    }


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Сразу добавляем первую запись если таблица пустая
    $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($row_count == 0) {
        $wpdb->insert(
            $table_name,
            array(
                'name' => 'Агентство',
                'color' => 'black',
                'podval' => 'Пожалуйста, не изменяйте тему письма, иначе связь с вами может пропасть.',
                'active' => false
            )
        );
    }
    ?>


    <div class="set_punkt" id="shablon_link">
        <div class="set_podzag">
            <h2>2. Настройка шаблона письма</h2>
            <button class="shab_mes_set" id="save-template-btn"
                data-ajax-url="<?php echo admin_url('admin-ajax.php'); ?>"
                data-nonce="<?php echo wp_create_nonce('save_agency_nonce'); ?>">
                Сохранить
            </button>
            <div class="wrap_heck">
                <label for="shab_mes_cjeck" class="checkbox-label">
                    <input type="checkbox" id="shab_mes_cjeck" class="shab_mes_cjeck" <?php
                    global $wpdb;
                    $is_active = $wpdb->get_var("SELECT active FROM {$wpdb->prefix}crm_shabl_mes LIMIT 1");
                    echo ($is_active !== null && $is_active) ? 'checked' : '';
                    ?>>
         <p> Использовать шаблон письма</p>
                </label>
            </div>
        </div>
        <div class="shablon_mes_wap">
            <div class="shablon_avat">
                <div class="avat_cont">
                    <?php
                    $crm_editor_path = plugin_dir_path(__FILE__) . 'assets/img/pic.png';
                    ?>
                    <img class="avat_img" src='<?php echo plugin_dir_url(__FILE__) . 'assets/img/pic.png'; ?>' alt="">
                    <div class="avat_text">
                        <p class="avat_zag">
                            <?php
                            global $wpdb;
                            $agency_name = $wpdb->get_var("
        SELECT CONCAT(
            UPPER(SUBSTRING(name, 1, 1)), 
            SUBSTRING(name, 2)
        ) 
        FROM {$wpdb->prefix}crm_shabl_mes 
        LIMIT 1
    ");
                            echo $agency_name ?: 'Агентство';
                            ?>
                        </p>
                        <p class="avat_mail">Кому: ...</p>
                    </div>
                </div>
            </div>
            <div class="shab_mes_block">


                <?php
                $crm_editor_path = plugin_dir_path(__FILE__) . 'crm_shablon_mes.php';

                if (file_exists($crm_editor_path)) {
                    // Буферизуем вывод чтобы захватить результат выполнения
                    ob_start();
                    include $crm_editor_path;
                    $executed_content = ob_get_clean();

                    // Выводим результат выполнения
                    echo '<div class="template-preview">';
                    echo $executed_content;
                    echo '</div>';

                    // Логируем для отладки
                    echo '<script>console.log("CRM: Файл выполнен, длина контента: ' . strlen($executed_content) . '");</script>';
                } else {
                    echo '<div class="editor-content" contenteditable="true" style="border: 1px solid #ccc; padding: 15px; min-height: 400px; margin-top: 10px;">';
                    echo 'Файл шаблона не найден';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    // Создаем таблицу шаблона письма
    
    // Проверяем наличие активной PRO/VIP лицензии
    $is_pro_active = my_plugin_check_license_status();

    // Создаем таблицу ТОЛЬКО если есть подписка
// Но если таблица уже существует (создана ранее), она останется
    if ($is_pro_active) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_shabl_kp';
        $charset_collate = $wpdb->get_charset_collate();


        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    background_image VARCHAR(255) DEFAULT 'wp-content/plugins/crmmagia/assets/img/kp.jpg',
    logo VARCHAR(255) DEFAULT 'wp-content/plugins/crmmagia/assets/img/logo.png', 
    telefon_sait_shortcode VARCHAR(255) DEFAULT '',
    mail_sait_shortcode VARCHAR(255) DEFAULT '',
    avatar VARCHAR(255) DEFAULT 'wp-content/plugins/crmmagia/assets/img/avatar.png',
    name_men VARCHAR(255) DEFAULT 'Имя менеджера',
    tel_men VARCHAR(255) DEFAULT '+7(999)999-99-99',
    file_css VARCHAR(255) DEFAULT 'wp-content/uploads/crm_files/shablon/assets/css/style_kp.css',  
    file_html VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Проверяем создалась ли таблица
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;

        if (!$table_exists) {
            // Выводим информацию об ошибке для отладки
            $error_msg = $wpdb->last_error;
            echo '<div class="error">Ошибка: таблица не создана. Причина: ' . esc_html($error_msg) . '</div>';

            // Попробуем создать таблицу напрямую как резервный вариант
            $wpdb->query($sql);

            // Проверяем снова
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;

            if (!$table_exists) {
                echo '<div class="error">Не удалось создать таблицу даже напрямую</div>';
            }
        }


        // Проверяем есть ли записи и добавляем первую
        if ($table_exists) {
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

            if ($row_count == 0) {
                // Вставляем дефолтные значения
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'background_image' => 'wp-content/plugins/crmmagia/assets/img/kp.jpg',
                        'logo' => 'wp-content/plugins/crmmagia/assets/img/logo.png',
                        'avatar' => 'wp-content/plugins/crmmagia/assets/img/avatar.png',
                        'name_men' => 'Имя менеджера',
                        'tel_men' => '+7(999)999-99-99',
                        'file_css' => 'wp-content/uploads/crm_files/shablon/assets/css/style_kp.css'
                    )
                );

                if ($result !== false) {
                    // Успешно добавлено
    
                } else {
                    // Выводим детальную информацию об ошибке
                    $error_msg = $wpdb->last_error;
                    echo '<div class="error">Ошибка при добавлении записи: ' . esc_html($error_msg) . '</div>';
                }
            }
        }
    }

    // Если нет подписки - таблица НЕ создается, но если она уже существует - остается
// Данные пользователя сохраняются навсегда!
    ?>
    <?php
    // Проверяем лицензию
    $is_pro_active = my_plugin_check_license_status();
    $api_domain = get_option('crm_license_domain', '');
    $pay_url = $api_domain ? trailingslashit($api_domain) : 'https://magtexnology.com';
    ?>
    <div class="set_punkt" id="kp_link">
        <div class="set_podzag">
            <h2>3. редактирование коммерческого предложения</h2>

        </div>
        <?php if ($is_pro_active): ?>
            <div class="set_pro_kp">
                <div>
                    <button class="red_dat_btn">контактная информация</button>


                    <div class="red_dat" style="display: none;">
                        <div>
                            <?php
                            function find_acf_values_with_titles($field_name)
                            {
                                $args = array(
                                    'post_type' => array('post', 'page'),
                                    'meta_key' => $field_name,
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish'
                                );

                                $posts = get_posts($args);
                                $result = array();

                                foreach ($posts as $post) {
                                    $value = get_field($field_name, $post->ID);
                                    if ($value && trim($value) !== '') {
                                        $value = trim($value);
                                        if (!isset($result[$value])) {
                                            $result[$value] = array(
                                                'post_ids' => array(),
                                                'post_titles' => array()
                                            );
                                        }
                                        $result[$value]['post_ids'][] = $post->ID;
                                        $result[$value]['post_titles'][] = get_the_title($post->ID);
                                    }
                                }

                                return $result;
                            }

                            $phones = find_acf_values_with_titles('phone');
                            $emails = find_acf_values_with_titles('mail');
                            ?>

                            <div class="red_wap">
                                <div class="red_punkt">
                                    <?php
                                    global $wpdb;
                                    $table_name = $wpdb->prefix . 'crm_shabl_kp';
                                    $current_settings = $wpdb->get_row("SELECT telefon_sait_shortcode, mail_sait_shortcode FROM $table_name WHERE id = 1");
                                    $current_phone = $current_settings->telefon_sait_shortcode ?? '';
                                    $current_email = $current_settings->mail_sait_shortcode ?? '';
                                    ?>

                                    <div class="red_wap">
                                        <div class="red_punkt">
                                            <h5 class="red_zagl">телефоны</h5>

                                            <?php if (!empty($phones)): ?>
                                                <?php foreach ($phones as $phone_value => $data): ?>
                                                    <div class=" red_addres">
                                                        <p class="address_info">
                                                            <span><?php echo esc_html($phone_value); ?></span>
                                                            <br>
                                                            <small style=" font-size: 9px;">
                                                                Страница: <?php echo implode(', ', $data['post_titles']); ?>
                                                            </small>
                                                        </p>
                                                        <button class="red_dat_tel_vibor red_vibor" data-type="phone"
                                                            data-value="<?php echo esc_attr($phone_value); ?>"
                                                            style="<?php echo ($phone_value == $current_phone) ? 'background: green;' : ''; ?>">
                                                            выбрать
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <!-- Поле для ручного ввода телефона -->
                                            <div class=" red_addres">
                                                <p class="red_vvod_zag">нет нужного, введите</p>
                                                <input class="address_info custom_phone_input" type="tel"
                                                    id="address_input_phone" value="<?php
                                                    // Показываем значение только если его НЕТ в шорткодах
                                                    echo (!empty($current_phone) && !isset($phones[$current_phone]))
                                                        ? esc_attr($current_phone)
                                                        : '';
                                                    ?>" placeholder="+7 (___) ___-__-__">
                                                <button class="red_dat_tel_vibor red_vibor custom_phone_btn"
                                                    data-type="phone" data-value=""
                                                    style="<?php echo (!empty($current_phone) && !isset($phones[$current_phone])) ? 'background: green;' : ''; ?>">
                                                    выбрать
                                                </button>
                                            </div>
                                        </div>

                                        <div class="red_punkt">
                                            <h5 class="red_zagl">почты</h5>

                                            <?php if (!empty($emails)): ?>
                                                <?php foreach ($emails as $email_value => $data): ?>
                                                    <div class=" red_addres">
                                                        <p class="address_info">
                                                            <span><?php echo esc_html($email_value); ?></span>
                                                            <br>
                                                            <small style=" font-size: 9px;">
                                                                Страницы: <?php echo implode(', ', $data['post_titles']); ?>
                                                            </small>
                                                        </p>
                                                        <button class="red_dat_mail_vibor red_vibor" data-type="email"
                                                            data-value="<?php echo esc_attr($email_value); ?>"
                                                            style="<?php echo ($email_value == $current_email) ? 'background: green;' : ''; ?>">
                                                            выбрать
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <!-- Поле для ручного ввода email -->
                                            <div class=" red_addres">
                                                <p class="red_vvod_zag">нет нужной, введите</p>
                                                <input class="address_info custom_email_input" type="email"
                                                    id="address_input_email" value="<?php
                                                    // Показываем значение только если его НЕТ в шорткодах
                                                    echo (!empty($current_email) && !isset($emails[$current_email]))
                                                        ? esc_attr($current_email)
                                                        : '';
                                                    ?>" placeholder="example@mail.com">
                                                <button class="red_dat_mail_vibor red_vibor custom_email_btn"
                                                    data-type="email" data-value=""
                                                    style="<?php echo (!empty($current_email) && !isset($emails[$current_email])) ? 'background: green;' : ''; ?>">
                                                    выбрать
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="redact_shabl_save">
                    <button type="button" class="shabl_button generate-pdf-btn-shablon">Создать шаблон PDF</button>
                    <a href="#" class="view-template-link"
                        style="display: inline-block;  padding: 5px 10px; background: #cccccc; color: #666666; text-decoration: none; border-radius: 3px; font-size: 12px; cursor: not-allowed; opacity: 0.7;"
                        onclick="return false;">
                        <img draggable="false" role="img" class="emoji" alt="📄"
                            src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg">
                        создайте шаблон
                    </a>
                </div>
                <div class="kp_privet_1">потренеруйтесь, посмотрите как может выглядет ваше КП</div>
                <div class="kp_shablon_red">
                    <div class="file-creation-window">
                        <div class="file-window-scrollable">

                            <?php
                            //  Подключаем функционал
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
                            $crm_css_url = plugin_dir_url(__FILE__) . 'assets/css/crm_set_editor.css';

                            // Регистрируем и подключаем стиль через WordPress
                            wp_register_style('crm-editor-style', $crm_css_url);
                            wp_enqueue_style('crm-editor-style');

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
                <div class="sovet"><span>*Совет:</span> если вы после перезагрузки страницы видите старые стили в
                    вашем
                    КП до сохранения, то тогда <span>обновите страницу с очисткой кеша</span> с помощью комбинации
                    клавиш <span>CTRL+SHIFT+R</span> </div>
            </div>
        <?php else: ?>
            <a href="<?= esc_url($pay_url); ?>/crmmagia-pay/#pay" class="crm_set" target="_blank">
                Хотите получить возможность настроить свое уникальное коммерческое предложение оформите подписку на сайте
                <p>
                    <?= esc_url($pay_url); ?>
                </p>
            </a>
        <?php endif; ?>
        <?php
        wp_footer();
        ?>