<?php
/**
 * Template Name: CRM System Template
 * Template Post Type: page
 */
$current_user_id = get_current_user_id();
// Простой шаблон без лишних элементов
?>
<link rel="stylesheet" type="text/css" href="<?php echo plugin_dir_url(__FILE__); ?>crm-style.css">

<!-- Подключаем jQuery (если ещё не подключен) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Подключаем наш JavaScript файл -->
<script type="text/javascript" src="<?php echo plugin_dir_url(__FILE__); ?>crm-license.js"></script>
<title>CRM-приветствие</title>
<div class="main_system">
    <?php while (have_posts()):
        the_post(); ?>
        <h1>CR<span class="mm-gradient">MM</span>agia</h1>
        <div class="page-content">
            <p>Добро пожаловать! Эта страница была создана автоматически с помощью плагина <span
                    class="pod_h">CRMMagia</span> </p>

            <p><strong>Все страницы плагина видны только авторизованным пользователям</strong></p>



            <p>Для дальнейшей работы пройдите по странице: <a href="<?php echo home_url('/CRMMagia/'); ?>">
                    <?php echo home_url('/CRMMagia/'); ?>
                </a></p>



            <p>Для того чтобы настроить отправку сообщений нажмите здесь: <a
                    href="<?php echo home_url('/crm_settings/'); ?>">
                    <?php echo home_url('/crm_settings/'); ?>
                </a></p>

            <p><strong>Служба поддержки всегда на свзяи</strong>
            </p>
            <p>Нашли баг, подробно опишите его на почту <a
                    href="mailto:webmaster@magtexnology.com?subject=Баг в CRMMagia">webmaster@magtexnology.com</a></p>
            <p>Напишите свои пожелания и предложения, чтобы вы хотели видеть в будущих обновлениях
                <a href="mailto:webmaster@magtexnology.com?subject=Предложения в CRMMagia">webmaster@magtexnology.com</a>
            </p>
            <?php
            // ========== НАЧАЛО: ОПРЕДЕЛЯЕМ ДАННЫЕ ОДИН РАЗ ==========
// 1. Получаем домен ИЗ БАЗЫ ДАННЫХ КЛИЕНТА
            $saved_domain = get_user_meta($current_user_id, 'crm_license_domain', true);

            // 2. Если в базе пусто (новый пользователь) - используем ВАШ ОФИЦИАЛЬНЫЙ сайт
            if (empty($saved_domain)) {
                $saved_domain = 'https://magtexnology.com'; // Ваш реальный сайт
            }

            // 3. Также получаем остальные данные для подписки
            $saved_plan = get_user_meta($current_user_id, 'crm_license_plan', true);
            $license_status = get_user_meta($current_user_id, 'crm_license_status', true);
            $expires_date = get_user_meta($current_user_id, 'crm_license_expires', true);
            // ========== КОНЕЦ ОПРЕДЕЛЕНИЯ ДАННЫХ ==========
            ?>
            <section>
                <?php
                // Форматируем сообщение о статусе подписки
                // Теперь $saved_domain здесь уже определена правильно!
                if (!empty($saved_plan) && $license_status === 'active') {
                    $icon = '✅';
                    $message = "У вас активирована подписка <strong>" . esc_html($saved_plan) . "</strong>";

                    // Добавляем дату для PRO
                    if (strtoupper($saved_plan) === 'PRO' && !empty($expires_date)) {
                        $formatted_date = date('d.m.Y', strtotime($expires_date));
                        $current_time = current_time('timestamp');
                        $expires_time = strtotime($expires_date);
                        $days_left = ceil(($expires_time - $current_time) / (24 * 3600));

                        // Проверяем за 3 дня до окончания
                        if ($days_left <= 3 && $days_left > 0) {
                            $message .= " (до <span style='color: orange; font-weight: bold;'>$formatted_date</span>)";
                        } else {
                            $message .= " (до $formatted_date)";
                        }
                    } elseif (strtoupper($saved_plan) === 'VIP') {
                        $message .= " (без ограничений)";
                    }

                    echo '<div id="domen_message" style="color: green; padding: 8px; margin: 10px 0; border-radius: 4px; background: #e8f5e8;">';
                    echo $icon . ' ' . $message;
                    echo '</div>';

                } elseif (!empty($saved_plan) && $license_status !== 'active') {
                    $icon = '❌';
                    if (strtoupper($saved_plan) === 'PRO' && !empty($expires_date)) {
                        $formatted_date = date('d.m.Y', strtotime($expires_date));
                        $message = "Подписка <strong>" . esc_html($saved_plan) . "</strong> истекла $formatted_date <span>для продления перейдите по ссылке
                       <a href='" . esc_url($saved_domain . '/crmmagia-pay/#pay') . "' target='_blank'>
                   " . esc_html($saved_domain) . "</a>
                        </span>";
                    } else {
                        $message = "Подписка <strong>" . esc_html($saved_plan) . "</strong> не активна, для оформления перейдите по ссылке 
                        <a href='" . esc_url($saved_domain . '/crmmagia-pay/#pay') . "' target='_blank'>
                   " . esc_html($saved_domain) . "</a>";
                    }
                    echo '<div id="domen_message" style="color: red; padding: 8px; margin: 10px 0; border-radius: 4px; background: #ffebee;">';
                    echo $icon . ' ' . $message;
                    echo '</div>';
                } else {
                    // Ссылка в блоке "нет подписки" теперь тоже ведет на правильный домен
                    echo '<a class="no_license" href="' . esc_url($saved_domain . '/crmmagia-pay/#pay') . '" target="_blank">
                Хотите получить возможность настроить свое уникальное коммерческое предложение? Оформите подписку на сайте ';
                    echo esc_html($saved_domain) . '</a>';
                    echo '<div id="domen_message" style="margin-top: 10px;"></div>';
                }
                ?>
            </section>

            <div class="support-section">
                <strong class="active_h">Активация подписки</strong>
                <div class="license-activation">
                    <div class="active_vvod">
                        <?php
                        // Получаем сохраненный ключ из базы
                        $saved_key = get_user_meta($current_user_id, 'crm_license_key', true);
                        $saved_email = get_user_meta($current_user_id, 'crm_license_email', true);
                        ?>
                        <div class="activate_input">
                            <label class="activate_lab" for="">Mail</label>
                            <input class="act_input" type="text" id="license_mail" placeholder="Введите ваш email"
                                value="<?php echo esc_attr($saved_email); ?>" />
                            <div id="license_message"></div>
                        </div>
                        <div class="activate_input">
                            <label class="activate_lab" for="">Ключ</label>
                            <input class="act_input" type="text" id="license_key"
                                placeholder="Введите ваш лицензионный ключ" value="<?php echo esc_attr($saved_key); ?>" />
                            <div id="license_message"></div>
                        </div>


                        <?php
                        // ОТДЕЛЬНО ПОЛУЧАЕМ ДОМЕН ДЛЯ ЭТОГО ПОЛЯ
                        $saved_domain = get_user_meta($current_user_id, 'crm_license_domain', true);
                        if (empty($saved_domain)) {
                            $saved_domain = 'https://magtexnology.com/';
                        }
                        ?>
                        <!-- <div class="activate_input">
                            <label class="activate_lab" for="">Домен</label>
                            <input class="act_input" type="text" id="domen" placeholder="Введите домен сайта"
                                value="<?php echo esc_attr($saved_domain); ?>" />
                        </div> -->
                    </div>
                    <button id="activate_domen">Активировать</button>
                    <p class="activate_info">Для активации вам требуется <span>ввести ключ</span>, который был вам выдан
                        на
                        <a href="<?php echo esc_url($saved_domain . '/crmmagia-pay/#pay'); ?>" target="_blank"> сайте
                            <?php echo esc_html(rtrim($saved_domain, '/')); ?>
                        </a>
                        <br><br>
                        Хотите следить за ообновлениями плагина заходите на сайт
                        <a href="<?php echo esc_url($saved_domain . '/news'); ?>" target="_blank">
                            <?php echo esc_html(rtrim($saved_domain, '/')); ?>
                        </a>

                    </p>

                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <div class="poddergka">
        Разработано при поддержке: <a href="https://connectica.pro" target="_blank">агентство "Коннектика"</a>
    </div>
</div>
<!-- Передаём PHP переменные в JavaScript -->
<script type="text/javascript">
    // Глобальные переменные для JavaScript
    var crmLicenseConfig = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        home_url: '<?php echo home_url(); ?>',
        nonce: '<?php echo wp_create_nonce("add_license_key_nonce"); ?>'  // ← ИСПРАВЛЕНО!
    };
</script>