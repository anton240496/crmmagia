<?php
if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('generate_email_template')) {
    function generate_email_template($subject, $message, $css_content, $attachments = [])
    {
        ob_start();
        ?>
        <html>

        <head>
            <title><?php echo htmlspecialchars($subject); ?></title>
            <style>
                <?php echo $css_content; ?>
            </style>
        </head>

        <body>
            <div class='container'>
                <div class='header'>
                    <h2 class="massage_h2">Вас приветствует <span class="shablon_name" contenteditable="true"
                            id="agency-name-field">
                            <?php
                            global $wpdb;
                            $agency_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}crm_shabl_mes LIMIT 1");
                            if ($agency_name) {
                                $agency_name = mb_strtolower(mb_substr($agency_name, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($agency_name, 1, null, 'UTF-8');
                            }
                            echo esc_html($agency_name);
                            ?>
                        </span></h2>
                </div>
                <div class='content'>
                    <p><?php echo nl2br(htmlspecialchars($message)); ?></p>
                </div>

                <?php if (!empty($attachments)): ?>
                    <div class='attachments'>
                        <h3>📎 Прикрепленные файлы:</h3>
                        <ul>
                            <?php foreach ($attachments as $attachment): ?>
                                <?php if (isset($attachment['url']) && isset($attachment['name'])): ?>
                                    <?php
                                    $full_file_name = basename($attachment['url']);
                                    $full_file_name = urldecode($full_file_name);
                                    $display_name = (strlen($full_file_name) > strlen($attachment['name']))
                                        ? $full_file_name
                                        : $attachment['name'];
                                    ?>
                                    <li>
                                        <a href="<?php echo esc_url($attachment['url']); ?>" style="color: #0073aa; text-decoration: none;"
                                            target="_blank">
                                            <?php echo esc_html($display_name); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class='footer_mes'>

                    <div>
                        <?php if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS): ?>
                            <div class="shablon_color wrap_heck">
                                <?php
                                global $wpdb;
                                $current_color = $wpdb->get_var("SELECT color FROM {$wpdb->prefix}crm_shabl_mes LIMIT 1") ?: '#0073aa';
                                ?>
                                <input type="color" id="color-picker" value="<?php echo esc_attr($current_color); ?>">
                                <button type="button" class="color-picker-btn"
                                    onclick="document.getElementById('color-picker').click()">

                                    Цвет ссылки
                                </button>
                            </div>
                        <?php endif; ?>
                        <small>Это сообщение было отправлено с сайта
                            <?php
                            global $wpdb;
                            $agency_color = $wpdb->get_var("SELECT color FROM {$wpdb->prefix}crm_shabl_mes LIMIT 1");
                            $color_style = $agency_color ? 'style="color: ' . esc_attr($agency_color) . ';"' : '';
                            ?>

                            <a <?php echo $color_style; ?> class="mes_link" href="<?php echo esc_url(home_url()); ?>">
                                <?php echo esc_attr($_SERVER['HTTP_HOST']); ?>
                            </a>
                        </small>
                    </div>
                    <?php
                    date_default_timezone_set('Europe/Moscow');
                    ?>
                    <p><small>Дата отправки: <?php echo date('d.m.Y H:i'); ?> (МСК)</small></p>
                    <p class="shablon_podval"><b contenteditable="true" id="agency-podval-field">
                            <?php
                            global $wpdb;
                            $agency_podval = $wpdb->get_var("SELECT podval FROM {$wpdb->prefix}crm_shabl_mes LIMIT 1");
                            echo $agency_podval ? esc_html($agency_podval) : '';
                            ?>
                        </b></p>

                </div>
            </div>
        </body>

        </html>
        <?php
        return ob_get_clean();
    }
}

// 🔥 АВТОВЫВОД ТОЛЬКО ЕСЛИ НЕ AJAX ЗАПРОС
if (!defined('DOING_AJAX') || !DOING_AJAX) {
    // 🔥 ПОДКЛЮЧАЕМ CSS ТОЛЬКО ДЛЯ ПРЕДПРОСМОТРА
    $css_file_path = plugin_dir_path(__FILE__) . 'assets/css/crm_message.css';
    $css_content = '';

    if (file_exists($css_file_path)) {
        $css_content = file_get_contents($css_file_path);
    }

    echo generate_email_template(
        'Предпросмотр письма',
        'здесь будет ваш текст письма клиенту',
        $css_content, // 🔥 Передаем CSS только для предпросмотра
        [
            ['name' => 'example-document.pdf', 'url' => '#'],
            ['name' => 'photo.jpg', 'url' => '#']
        ]
    );
}
?>