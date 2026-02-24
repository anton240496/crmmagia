<?php
// Определяем путь к кастомной иконке

if (!defined('ABSPATH')) {
    exit;
}
$upload_dir = wp_upload_dir();
$custom_zak_path = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/zak.png';
$default_icon = plugin_dir_url(__FILE__) . 'assets/img/zakladka.png';

//🔥 1. ПРОВЕРЯЕМ CSS ФАЙЛ НА НАЛИЧИЕ ЦВЕТА
$css_file = $upload_dir['basedir'] . '/crm_files/shablon/assets/css/style_kp.css';
$zak_color = '';
$has_zak_color = false;

if (file_exists($css_file)) {
    $css_content = file_get_contents($css_file);
    // Ищем цвет закладки в CSS
    if (preg_match('/\.zakladka::before\s*\{[^}]*background-color:\s*([^;!]+)/', $css_content, $matches)) {
        $zak_color = trim($matches[1]);
        $has_zak_color = true;
        error_log("✅ PHP: Найден цвет закладки в CSS: " . $zak_color);
    } else {
        error_log("ℹ️ PHP: Цвет закладки не найден в CSS файле");
    }
}

// 🔥 2. ПРОВЕРЯЕМ КАРТИНКУ
$icon_url = $default_icon;
$has_zak_image = false;

if (file_exists($custom_zak_path)) {
    if (filesize($custom_zak_path) > 0) {
        $icon_url = $upload_dir['baseurl'] . '/crm_files/shablon/assets/img/zak.png?v=' . filemtime($custom_zak_path);
        $has_zak_image = true;
        error_log("✅ PHP: Используем кастомную иконку: " . $icon_url);
    } else {
        error_log("⚠️ PHP: Файл zak.png существует, но пустой (0 байт)");
    }
} else {
    error_log("ℹ️ PHP: Файл zak.png не найден, используем дефолтную");
}

// 🔥 3. ЛОГИКА: что показывать?
$show_color = $has_zak_color && !empty($zak_color) && !$has_zak_image;
// Цвет показываем ТОЛЬКО если есть цвет в CSS и НЕТ картинки
?>
<?php if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS): ?>
    <div class="editor_st_wrap">

        <div class="kp_privet">постройте таблицы</div>
    <?php endif; ?>
    <div class="editor-toolbar">
        <div class="toolbar-section buts">
            <button type="button" class="format-btn inst1" id="insertTableBtn" title="Таблица">
                <img draggable="false" role="img" class="emoji" alt=""
                    src='<?php echo plugin_dir_url(__FILE__) . 'assets/img/stolb.png'; ?>'>
            </button>
            <div class="func">
                <button type="button" class="format-btn zakladka-btn inst2" id="zakladkaBtnOne"
                    title="Удалить/вставить закладку">
                    <?php if ($show_color): ?>
                        <!-- 🔥 ПОКАЗЫВАЕМ ЦВЕТ из CSS файла -->
                        <div class="zak-color-dot"
                            style="width: 20px; height: 20px; background-color: <?php echo esc_attr($zak_color); ?>; border-radius: 50%; display: block;">
                        </div>
                    <?php else: ?>
                        <!-- ПОКАЗЫВАЕМ КАРТИНКУ -->
                        <img draggable="false" role="img" class="emoji" alt="" src='<?php echo esc_url($icon_url); ?>'>
                    <?php endif; ?>
                </button>
                <button type="button" class="format-btn delete-table-btn" id="deleteTableBtn" title="Удалить таблицу">
                    <img draggable="false" role="img" class="emoji" alt="🗑️"
                        src="https://s.w.org/images/core/emoji/16.0.1/svg/1f5d1.svg">
                </button>
            </div>
        </div>
        <div class="toolbar-section buts">
            <button type="button" class="format-btn inst1" id="insertTableTwoBtn" title="Таблица с двумя колонками">
                <img draggable="false" role="img" class="emoji" alt=""
                    src='<?php echo plugin_dir_url(__FILE__) . 'assets/img/stolbtwo.png'; ?>'>
            </button>
            <div class="func">
                <button type="button" class="format-btn zakladka-btn inst2" id="zakladkaBtn"
                    title="Удалить/вставить закладку">
                    <?php if ($show_color): ?>
                        <!-- 🔥 ПОКАЗЫВАЕМ ЦВЕТ из CSS файла -->
                        <div class="zak-color-dot"
                            style="width: 20px; height: 20px; background-color: <?php echo esc_attr($zak_color); ?>; border-radius: 50%; display: block;">
                        </div>
                    <?php else: ?>
                        <!-- ПОКАЗЫВАЕМ КАРТИНКУ -->
                        <img draggable="false" role="img" class="emoji" alt="" src='<?php echo esc_url($icon_url); ?>'>
                    <?php endif; ?>
                </button>
                <button type="button" class="format-btn delete-table-btn" id="deleteTableTwoBtn"
                    title="Удалить таблицу с двумя колонками">
                    <img draggable="false" role="img" class="emoji" alt="🗑️"
                        src="https://s.w.org/images/core/emoji/16.0.1/svg/1f5d1.svg">
                </button>
                <button type="button" class="format-btn delete-own" id="delete-own" title="Удалить один столбец">
                    <p>-1</p>
                </button>
            </div>
        </div>

        <div class="toolbar-section buts">
            <button type="button" class="format-btn instcalk" id="insertTablemoreBtn" title="таблица для подсчета">
                <img draggable="false" role="img" class="emoji" alt=""
                    src='<?php echo plugin_dir_url(__FILE__) . 'assets/img/table.png'; ?>'>
            </button>
            <div class="func">
                <button type="button" class="format-btn delete-table-btn" id="deleteTablemoreBtn"
                    title="Удалить таблицу подсчета">
                    <img draggable="false" role="img" class="emoji" alt="🗑️"
                        src="https://s.w.org/images/core/emoji/16.0.1/svg/1f5d1.svg">
                </button>
            </div>
        </div>

        <button type="button" class="format-btn reset-template-btn" title="Сбросить к шаблону">🔄
            Шаблон</button>
    </div>

    <?php
    // 🔥 4. ДОБАВЛЯЕМ JS ДЛЯ ОБНОВЛЕНИЯ ПРИ СМЕНЕ ЦВЕТА
    if ($show_color): ?>
        <script>
            jQuery(document).ready(function ($) {
                // Функция конвертации rgb/rgba в HEX (если цвет в CSS в формате rgb)
                function rgbToHex(rgb) {
                    if (rgb.startsWith('#')) return rgb;

                    var parts = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+\.?\d*))?\)$/);
                    if (!parts) return rgb;

                    var r = parseInt(parts[1]).toString(16).padStart(2, '0');
                    var g = parseInt(parts[2]).toString(16).padStart(2, '0');
                    var b = parseInt(parts[3]).toString(16).padStart(2, '0');
                    return '#' + r + g + b;
                }

                // Устанавливаем начальный цвет из CSS в input
                var cssColor = '<?php echo esc_js($zak_color); ?>';
                var hexColor = rgbToHex(cssColor);

                if (hexColor && hexColor !== '#ffffff') {
                    $('#kp_zakladka_color').val(hexColor);
                }
            });
        </script>
    <?php endif; ?>
    <?php if (defined('IS_CRM_SETTINGS') && IS_CRM_SETTINGS): ?>
        <div class="style_cont">
            <button class="red_style_btn">Оформите КП</button>
            <div class="red_style_wrap" style="display: none;">
                <div class="style_osnova">
                    <div class="picture_wap">
                        <div class="background_wap">
                            <button class="red_bacground set_btn">Смена <br> фона</button>
                            <button class="scale set_btn">Добавить тень</button>

                        </div>

                        <div class="logo_av">
                            <button class="red_logo set_btn">смена <br> логотипа</button>
                            <button class="red_avatar set_btn">смена <br> аватарки</button>
                        </div>
                    </div>

                    <div class="color_wrap">
                        <label class="kp_glav_color kp_color red_btn">
                            <input type="color" id="kp_glav_color" value="#ffffff">

                            <div class="red_color_glav">
                                <span>Главный цвет текста</span>
                            </div>
                        </label>
                        <label class="kp_two_color kp_color red_btn">
                            <input type="color" id="kp_two_color" value="#000">

                            <div class="red_color_two">
                                <span>Доп.</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="zak_wrap">
                    <h6>один, два столбца</h6>
                    <div class="zakladka_shablon">

                        <label class="zakladka_lab kp_color">
                            <input type="color" id="kp_zakladka_color" value="#ffffff">

                            <span>Цвет закладки</span> или
                        </label>
                        <button class="zakladka_pic set_btn">Иконка</button>
                    </div>
                </div>
                <div class="tabl_calc_style">
                    <h6>таблица-калькулятор</h6>
                    <div class="tabl_calc_wap">
                        <p>
                            Наименования столбцов</p>
                        <div class="tabl_calc_cont">
                            <label class="tabl_calc_lab kp_color bord_hov">
                                <input type="color" id="calc_name_bord" value="#ffffff">
                                <div class="calc_name_bord">
                                    <span>граница </span>
                                </div>
                            </label>
                            <label class="tabl_calc_lab kp_color bord_hov">
                                <input type="color" id="kp_calc_name_text" value="#ffffff">
                                <div class="kp_calc_name_text">
                                    <span>их цвет</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="tabl_calc_wap">
                        <p>Штука и Итоги</p>
                        <div class="tabl_calc_cont">
                            <label class="tabl_calc_lab kp_color sht_hov">
                                <input type="color" id="calc_name_sht_bac" value="#ffffff">
                                <div class="calc_name_sht_bac">
                                    <span>Задний фон</span>
                                </div>
                            </label>
                            <label class="tabl_calc_lab kp_color sht_hov">
                                <input type="color" id="calc_name_sht_text" value="#000">
                                <div class="calc_name_sht_text">
                                    <span>Цвет текста</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="tabl_calc_wap">
                        <p>Услуга и НДС</p>
                        <div class="tabl_calc_cont">
                            <label class="tabl_calc_lab kp_color ysl_hov">

                                <input type="color" id="calc_name_sht_ysl_bac" value="#808080">
                                <div class="calc_name_sht_ysl_bac">
                                    <span>Задний фон</span>
                                </div>
                            </label>
                            <label class="tabl_calc_lab kp_color ysl_hov">
                                <input type="color" id="calc_name_sht_ysl_text" value="#ffffff">
                                <div class="calc_name_sht_ysl_text">
                                    <span>Цвет текста</span>
                                </div>
                            </label>
                        </div>

                    </div>
                </div>

                <button class="shab_kp" data-ajax-url="<?php echo admin_url('admin-ajax.php'); ?>">
                    Сохранить стили
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>