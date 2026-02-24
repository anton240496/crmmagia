<?php
/**
 * PDF Functions for CRM with DomPDF
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// ==================== НАСТРОЙКИ ШАПКИ ====================
$ENABLE_HEADER = true; // true - шапка включена, false - выключена

// ==================== ГЕНЕРАЦИЯ PDF ФАЙЛОВ С DOMPDF ====================


function get_lead_data_for_folder($lead_id, $dialog_id = 0)
{
    global $wpdb;

    error_log("🔍 DEBUG: Получаем данные для заявки ID: {$lead_id}, диалог ID: {$dialog_id}");

    // Получаем данные заявки
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}crm_leads WHERE id = %d",
        $lead_id
    ));

    if (!$lead) {
        error_log("❌ DEBUG: Заявка с ID {$lead_id} не найдена");
        return [
            'id' => $lead_id,
            'title' => 'Заявка_' . $lead_id,
            'client_name' => 'Клиент',
            'dialog_name' => 'Диалог'
        ];
    }

    $title = !empty($lead->name_zayv) ? $lead->name_zayv : 'Заявка_' . $lead_id;
    $client_name = !empty($lead->name) ? $lead->name : 'Клиент';

    $dialog_name = 'Диалог';

    // 🔥 ЕСЛИ ПЕРЕДАН КОНКРЕТНЫЙ DIALOG_ID - ИЩЕМ ИМЕННО ЕГО
    if ($dialog_id > 0) {
        $current_dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}crm_dialogs WHERE id = %d",
            $dialog_id
        ));

        if ($current_dialog && !empty($current_dialog->name)) {
            $dialog_name = $current_dialog->name;
            error_log("✅ DEBUG: Используем конкретный диалог ID {$dialog_id}: " . $dialog_name);
        } else {
            error_log("❌ DEBUG: Диалог с ID {$dialog_id} не найден, используем первый");
            // Если не нашли по ID, берем первый диалог заявки
            $first_dialog = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}crm_dialogs WHERE lead_id = %d LIMIT 1",
                $lead_id
            ));
            if ($first_dialog) {
                $dialog_name = $first_dialog->name;
            }
        }
    } else {
        // Если dialog_id не передан, берем первый диалог
        $first_dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}crm_dialogs WHERE lead_id = %d LIMIT 1",
            $lead_id
        ));
        if ($first_dialog) {
            $dialog_name = $first_dialog->name;
        }
    }

    $result = [
        'id' => $lead_id,
        'title' => sanitize_file_name($title),
        'client_name' => sanitize_file_name($client_name),
        'dialog_name' => sanitize_file_name($dialog_name)
    ];

    error_log("✅ DEBUG: Итоговые данные: " . print_r($result, true));

    return $result;
}
// 🔥 ДОБАВЬТЕ ЭТУ ФУНКЦИЮ ТОЖЕ
function generate_folder_name($lead_data)
{
    $name = $lead_data['id'] . '_' . $lead_data['title'] . '_' . $lead_data['client_name'] . '_' . $lead_data['dialog_name'];

    // Очищаем имя от недопустимых символов
    $name = preg_replace('/[^\wа-яА-ЯёЁ_\-]/u', '_', $name);
    $name = mb_substr($name, 0, 100); // ограничиваем длину

    return $name;
}
function generate_pdf_from_html($html_content = null, $message_id = null, $title = 'Сообщение из CRM')
{
    // 🔥 ЕСЛИ ВЫЗВАНА ЧЕРЕЗ AJAX - ОБРАБАТЫВАЕМ AJAX ЗАПРОС
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Получаем данные из AJAX
        $lead_id = intval($_POST['lead_id']);
        $dialog_id = intval($_POST['dialog_id']);
        $html_content = $_POST['file_content'];
        $custom_file_name = sanitize_text_field($_POST['custom_file_name'] ?? '');

        $custom_file_name = wp_unslash($custom_file_name);

        // Используем переданное имя или создаем автоматическое
        if (!empty($custom_file_name)) {
            $title = $custom_file_name;
            // 🔥 УБИРАЕМ .pdf ЕСЛИ ОН УЖЕ ЕСТЬ
            $title = str_replace('.pdf', '', $title);
        } else {
            $title = 'Коммерческое_предложение';
        }

        // Потом при создании имени файла:
        $filename = $title . '.pdf';
        $message_id = $lead_id; // Используем lead_id как message_id

        error_log("🔍 DEBUG PDF GENERATION:");
        error_log("- lead_id: " . $lead_id);
        error_log("- dialog_id: " . $dialog_id);
        error_log("- custom_file_name: " . $custom_file_name);
        error_log("- title: " . $title);
    }

    // 🔥 ДАЛЕЕ ИДЕТ ТВОЙ СУЩЕСТВУЮЩИЙ КОД БЕЗ ИЗМЕНЕНИЙ
    global $ENABLE_HEADER;

    try {
        // Подключаем DomPDF
        $dompdf_loaded = load_dompdf();

        if (!$dompdf_loaded) {
            throw new Exception('Не удалось загрузить библиотеку DomPDF');
        }

    

        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        // 🔥 ПРОВЕРЯЕМ, ЭТО ШАБЛОН ИЛИ ДИАЛОГ
        $is_template = isset($_POST['is_template']) && $_POST['is_template'] == true;

        if ($is_template) {
            // СОЗДАЕМ ПАПКУ ШАБЛОНОВ
            $folder_path = $crm_dir . '/shablon';
            if (!file_exists($folder_path)) {
                if (!wp_mkdir_p($folder_path)) {
                    throw new Exception('Не удалось создать папку "shablon"');
                }
            }

            $filepath = $folder_path . '/' . $filename;
            // 🔥 ИСПРАВЛЕНО: для шаблонов не нужна $folder_name
            $file_url = $upload_dir['baseurl'] . '/crm_files//' . $filename;

            error_log("✅ Сохраняем PDF шаблон в папку: shablon/");

        } else {
            // СОЗДАЕМ ПАПКУ от_меня (старая логика для диалогов)
            $ot_menya_dir = $crm_dir . '/от_меня';
            if (!file_exists($ot_menya_dir)) {
                if (!wp_mkdir_p($ot_menya_dir)) {
                    throw new Exception('Не удалось создать папку "от_меня"');
                }
            }

            // Получаем данные заявки для имени папки
            $lead_data = get_lead_data_for_folder($lead_id, $dialog_id);
            $folder_name = generate_folder_name($lead_data);

            $folder_path = $ot_menya_dir . '/' . $folder_name;

            // СОЗДАЕМ ПАПКУ ЗАЯВКИ
            if (!file_exists($folder_path)) {
                if (!wp_mkdir_p($folder_path)) {
                    throw new Exception('Не удалось создать папку заявки: ' . $folder_name);
                }
            }

            $filepath = $folder_path . '/' . $filename;
            $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $filename;
        }



        // Остальной код создания PDF...
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'Unbounded');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', get_template_directory());

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');

        $dompdf->set_option('isRemoteEnabled', true);

        $prepared_html = prepare_dark_html_for_dompdf($html_content, $title);
        $dompdf->loadHtml($prepared_html);
        $dompdf->render();

        // Сохраняем PDF
        $pdf_output = $dompdf->output();
        if (file_put_contents($filepath, $pdf_output) === false) {
            throw new Exception('Не удалось сохранить PDF файл');
        }

        if (!file_exists($filepath)) {
            throw new Exception('PDF файл не был создан');
        }

        $file_url = $upload_dir['baseurl'] . '/crm_files/от_меня/' . $folder_name . '/' . $filename;
        error_log('CRM PDF created with DomPDF: ' . $filename);

        // 🔥 ЕСЛИ AJAX - ВОЗВРАЩАЕМ JSON ОТВЕТ
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_success([
                'message' => 'PDF файл успешно создан!',
                'file_url' => $file_url,
                'file_name' => pathinfo($filename, PATHINFO_FILENAME)
            ]);
        }

        return $file_url;

    } catch (Exception $e) {
        error_log('CRM DomPDF Generation Error: ' . $e->getMessage());

        // 🔥 ВМЕСТО ОТПРАВКИ ОШИБКИ СРАЗУ - ВЫЗЫВАЕМ FALLBACK
        // Для AJAX вызовов
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // 🔥 ПЕРЕДАЕМ ВСЕ НЕОБХОДИМЫЕ ПАРАМЕТРЫ
            $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
            $dialog_id = isset($_POST['dialog_id']) ? intval($_POST['dialog_id']) : 0;
            $html_content = isset($_POST['file_content']) ? $_POST['file_content'] : $html_content;
            $custom_file_name = isset($_POST['custom_file_name']) ? sanitize_text_field($_POST['custom_file_name']) : '';

            error_log("🔥 Calling HTML fallback for AJAX request");
            generate_html_fallback($html_content, $lead_id, $title);
            return;
        }

        // Для не-AJAX вызовов
        return generate_html_fallback($html_content, $message_id, $title);
    }
}

// 🔥 РЕГИСТРИРУЕМ ФУНКЦИЮ КАК AJAX ОБРАБОТЧИК
add_action('wp_ajax_generate_pdf_file', 'generate_pdf_from_html');

// Подготовка HTML для DomPDF
function prepare_dark_html_for_dompdf($html_content, $title)
{
    global $ENABLE_HEADER;

    // Очищаем HTML контент
    $html_content = stripslashes($html_content);
    $html_content = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');

    // Обрабатываем длинный контент в блоках osnova
    $html_content = process_long_osnova_content($html_content);

    // Получаем CSS
    $css_content = get_css_for_pdf();
    $css_tex = get_css_tex_pdf();

    // Создаем шапку если включена
    $header_html = '';
    if ($ENABLE_HEADER) {
        $header_html = create_pdf_header();
    }

    // Определяем переменную
    $plugin_url = plugin_dir_url(__FILE__); // Или другой способ получения пути



    global $wpdb;
    $table_name = $wpdb->prefix . 'crm_shabl_kp';

    // Получаем путь из БД
    $image_path = $wpdb->get_var("SELECT background_image FROM $table_name LIMIT 1");

    // Логирование
    error_log("========================================");
    error_log("PDF ГЕНЕРАЦИЯ - НАЧАЛО");
    error_log("Время: " . date('Y-m-d H:i:s'));
    error_log("Таблица: " . $table_name);
    error_log("Путь из БД (сырой): " . ($image_path ?: 'NULL или ПУСТО'));

    // Формируем URL для background
    if (!empty($image_path)) {
  
        $background_url = home_url('/' . ltrim($image_path, '/'));

        error_log("Home URL: " . home_url());
        error_log("Очищенный путь: " . ltrim($image_path, '/'));
        error_log("Сформированный URL: " . $background_url);

        // Проверка существования файла
        $file_path = ABSPATH . ltrim($image_path, '/');
        error_log("Путь к файлу на сервере: " . $file_path);
        error_log("Файл существует: " . (file_exists($file_path) ? 'ДА' : 'НЕТ'));
        if (file_exists($file_path)) {
            error_log("Размер файла: " . filesize($file_path) . " байт");
        }
    } else {
        error_log("БД ПУСТАЯ - используем дефолтный фон");
        error_log("Plugin URL: " . $plugin_url);

        // Раскомментируйте эту строку!
        $background_url = $plugin_url . 'assets/img/kp.jpg';

        error_log("Дефолтный URL: " . $background_url);
    }

    error_log("Итоговый background_url: " . ($background_url ?? 'НЕ ОПРЕДЕЛЕН'));
    error_log("PDF ГЕНЕРАЦИЯ - КОНЕЦ");
    error_log("========================================");
    error_log(""); // Пустая строка для разделения

    // Если $background_url не определен - определяем его
    if (!isset($background_url) || empty($background_url)) {
        error_log("ВНИМАНИЕ: background_url не был установлен, используем дефолтный");
        $background_url = $plugin_url . 'assets/img/kp.jpg';
    }

    // Также добавим отладку в сам HTML
    $debug_comment = "<!-- 
DEBUG INFO:
DB Path: " . htmlspecialchars($image_path) . "
Background URL: " . htmlspecialchars($background_url) . "
Generated: " . date('Y-m-d H:i:s') . "
-->";

    $all_css = get_css_for_pdf();

    $full_html = $debug_comment . "
<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
    <style>
        /* ВМЕСТО @page ИСПОЛЬЗУЕМ PADDING НА BODY */
            " . $css_tex . "
        " . $all_css . "
        
        .bodym {
            background: url('" . esc_url($background_url) . "') !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
        }
    </style>
</head>

<body class='bodym'>
    {$header_html}
    <div class=\"content-wrapper\">
       
        <div class=\"pdf-container\">
            {$html_content}
        </div>
    </div>
</body>
</html>";

    return $full_html;
}
// Функция для создания HTML шапки
function create_pdf_header()
{

    return '
        <div class="header-container">
            <div class="header-content">
                <div class="header-logo">
                   
                </div>
                <div class="header-text glav_color">
                    Коммерческое предложение
                </div>
            </div>
        </div>
    ';
}

// Функция для обработки длинного контента в блоках osnova
function process_long_osnova_content($html_content)
{
    // Регулярное выражение для поиска блоков с классом osnova
    $pattern = '/<div class="osnova">(.*?)<\/div>/s';

    preg_match_all($pattern, $html_content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $full_match = $match[0];
        $content = $match[1];

        // Проверяем длину контента - перенос при 2350 символов для первой страницы
        if (strlen($content) > 2350) {
            // Разбиваем контент на части с разными лимитами
            $content_parts = split_content_into_parts($content);

            if (count($content_parts) > 1) {
                $new_content = '';

                // Первая часть с заголовком (если он есть в родительском блоке)
                $first_part = '<div class="osnova">' . $content_parts[0] . '</div>';

                // Остальные части без заголовка
                $other_parts = '';
                for ($i = 1; $i < count($content_parts); $i++) {
                    $other_parts .= '<tr class="page-break"><td class="text" colspan="2"><div class="osnova">' . $content_parts[$i] . '</div></td></tr>';
                }

                // Заменяем оригинальный блок
                $html_content = str_replace(
                    $full_match,
                    $first_part . $other_parts,
                    $html_content
                );
            }
        }
    }

    return $html_content;
}



// Загрузка DomPDF
function load_dompdf()
{
    // Способ 1: Composer autoload
    $composer_autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
        return true;
    }

    // Способ 2: Ручная загрузка DomPDF
    $dompdf_autoload = plugin_dir_path(__FILE__) . 'dompdf/autoload.inc.php';
    if (file_exists($dompdf_autoload)) {
        require_once $dompdf_autoload;
        return true;
    }

    // Способ 3: Прямая загрузка основных файлов
    $dompdf_path = plugin_dir_path(__FILE__) . 'dompdf/src/Dompdf.php';
    if (file_exists($dompdf_path)) {
        require_once $dompdf_path;

        // Подключаем необходимые зависимости
        $dependencies = [
            '/dompdf/src/Options.php',
            '/dompdf/src/Canvas.php',
            '/dompdf/src/CanvasFactory.php',
            '/dompdf/src/Frame.php',
            '/dompdf/src/FrameDecorator/AbstractFrameDecorator.php',
            '/dompdf/src/FrameDecorator/Page.php',
            '/dompdf/src/FrameReflower/Page.php',
            '/dompdf/src/Adapter/CPDF.php',
            '/dompdf/src/Helpers.php'
        ];

        foreach ($dependencies as $dep) {
            $dep_path = get_template_directory() . $dep;
            if (file_exists($dep_path)) {
                require_once $dep_path;
            }
        }

        return true;
    }

    return false;
}

// Функция для разбивки контента на части (только по словам)
function split_content_into_parts($content, $first_page_max_length = 2350, $other_pages_max_length = 6000)
{
    $parts = [];

    // Разбиваем текст на слова
    $words = preg_split('/\s+/', $content);

    $current_part = '';
    $is_first_page = true;

    foreach ($words as $word) {
        // Определяем максимальную длину для текущей части
        $current_max_length = $is_first_page ? $first_page_max_length : $other_pages_max_length;

        // Если добавление следующего слова не превышает лимит
        $potential_length = strlen($current_part) + strlen($word) + 1; // +1 для пробела

        if ($potential_length <= $current_max_length || empty($current_part)) {
            // Добавляем слово к текущей части
            if (!empty($current_part)) {
                $current_part .= ' ' . $word;
            } else {
                $current_part = $word;
            }
        } else {
            // Сохраняем текущую часть и начинаем новую
            if (!empty($current_part)) {
                $parts[] = $current_part;
                $current_part = $word;
                $is_first_page = false; // После первой части переключаем на другие страницы
            }
        }
    }

    // Добавляем последнюю часть
    if (!empty($current_part)) {
        $parts[] = $current_part;
    }

    // Если текст не удалось разбить, возвращаем как одну часть
    if (empty($parts)) {
        $parts[] = $content;
    }

    return $parts;
}

// Функция для получения CSS
function get_css_for_pdf()
{
    // Первый CSS (из плагина)
    $css_path = plugin_dir_path(__FILE__) . 'assets/css/crm-documents.css';

    // Второй CSS (из uploads) - ПРАВИЛЬНЫЙ путь
    $upload_dir = wp_upload_dir();
    $css_path1 = $upload_dir['basedir'] . '/crm_files/shablon/assets/css/style_kp.css';

    $result_css = '';

    // Загружаем первый CSS
    if (file_exists($css_path)) {
        $css_content = file_get_contents($css_path);
        $result_css .= optimize_css_for_dark_pdf($css_content);
        error_log('CRM PDF: CSS loaded from ' . $css_path);
    } else {
        error_log('CRM PDF: CSS file not found at ' . $css_path);
        $result_css .= "/* CRM Documents CSS not found */\n";
    }

    // Загружаем второй CSS
    if (file_exists($css_path1)) {
        $css_content1 = file_get_contents($css_path1);
        $result_css .= "\n" . optimize_css_for_dark_pdf($css_content1);
        error_log('CRM PDF: CSS loaded from ' . $css_path1);
    } else {
        error_log('CRM PDF: CSS file not found at ' . $css_path1);
        $result_css .= "\n/* Style KP CSS not found */\n";
    }

    return $result_css;
}

function get_css_tex_pdf()
{
    $css_path = plugin_dir_path(__FILE__) . 'assets/css/crm-tex.css';

    if (!file_exists($css_path)) {
        error_log('CRM PDF: CSS file not found at ' . $css_path);
        return '/* CRM Documents CSS not found */';
    }

    $css_tex = file_get_contents($css_path);

    // Оптимизируем CSS для темной темы
    $css_tex = optimize_css_for_dark_pdf($css_tex);

    error_log('CRM PDF: CSS loaded from ' . $css_path);
    return $css_tex;
}

// Оптимизация CSS для темной темы DomPDF
function optimize_css_for_dark_pdf($css)
{
    // Преобразуем цвета для темной темы
    $color_replacements = [
        // Фоны: светлые -> темные


        // Текст: темный -> светлый



        // Границы
        '/border-color:\s*#dee2e6/i' => 'border-color: #444444',
        '/border-color:\s*#ced4da/i' => 'border-color: #555555',
    ];

    $css = preg_replace(array_keys($color_replacements), array_values($color_replacements), $css);

    // Убираем неподдерживаемые свойства, но ОСТАВЛЯЕМ position: fixed
    $unsupported = [
        '/transition:\s*[^;]+;/i',
        '/animation:\s*[^;]+;/i',
        '/transform:\s*[^;]+;/i',
        '/filter:\s*[^;]+;/i',
        '/backdrop-filter:\s*[^;]+;/i',
        '/mix-blend-mode:\s*[^;]+;/i',
        '/@media[^{]+\{[^}]+\}/s',
        '/@keyframes[^{]+\{[^}]+\}/s',
    ];

    $css = preg_replace($unsupported, '', $css);

    // Убираем комментарии
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

    // Убираем лишние пробелы
    $css = preg_replace('/\s+/', ' ', $css);

    return trim($css);
}

// Резервная функция - создает HTML файл если PDF не работает
function generate_html_fallback($html_content, $message_id, $title = 'Сообщение из CRM')
{
    global $ENABLE_HEADER;

    try {
        $upload_dir = wp_upload_dir();
        $crm_dir = $upload_dir['basedir'] . '/crm_files';

        if (!file_exists($crm_dir)) {
            wp_mkdir_p($crm_dir);
        }

        $filename = 'message_' . $message_id . '_' . time() . '.html';
        $filepath = $crm_dir . '/' . $filename;

        // Очищаем HTML контент
        $html_content = stripslashes($html_content);
        $html_content = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');

        // Обрабатываем длинный контент
        $html_content = process_long_osnova_content($html_content);

        // Создаем шапку если включена
        $header_html = '';
        if ($ENABLE_HEADER) {
            $header_html = create_pdf_header();
        }

        // Получаем CSS
        $css_content = get_css_for_pdf();
        $css_tex = get_css_tex_pdf();

        // Определяем переменную
        $plugin_url = plugin_dir_url(__FILE__);


        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_shabl_kp';

        // Получаем путь из БД
        $image_path = $wpdb->get_var("SELECT background_image FROM $table_name LIMIT 1");

        // Логирование
        error_log("========================================");
        error_log("PDF ГЕНЕРАЦИЯ - НАЧАЛО");
        error_log("Время: " . date('Y-m-d H:i:s'));
        error_log("Таблица: " . $table_name);
        error_log("Путь из БД (сырой): " . ($image_path ?: 'NULL или ПУСТО'));

        // Формируем URL для background
        if (!empty($image_path)) {
        
            $background_url = home_url('/' . ltrim($image_path, '/'));

            error_log("Home URL: " . home_url());
            error_log("Очищенный путь: " . ltrim($image_path, '/'));
            error_log("Сформированный URL: " . $background_url);

            // Проверка существования файла
            $file_path = ABSPATH . ltrim($image_path, '/');
            error_log("Путь к файлу на сервере: " . $file_path);
            error_log("Файл существует: " . (file_exists($file_path) ? 'ДА' : 'НЕТ'));
            if (file_exists($file_path)) {
                error_log("Размер файла: " . filesize($file_path) . " байт");
            }
        } else {
            error_log("БД ПУСТАЯ - используем дефолтный фон");
            error_log("Plugin URL: " . $plugin_url);

            // Раскомментируйте эту строку!
            $background_url = $plugin_url . 'assets/img/kp.jpg';

            error_log("Дефолтный URL: " . $background_url);
        }

        error_log("Итоговый background_url: " . ($background_url ?? 'НЕ ОПРЕДЕЛЕН'));
        error_log("PDF ГЕНЕРАЦИЯ - КОНЕЦ");
        error_log("========================================");
        error_log(""); // Пустая строка для разделения

        // Если $background_url не определен - определяем его
        if (!isset($background_url) || empty($background_url)) {
            error_log("ВНИМАНИЕ: background_url не был установлен, используем дефолтный");
            $background_url = $plugin_url . 'assets/img/kp.jpg';
        }

        // Также добавим отладку в сам HTML
        $debug_comment = "<!-- 
DEBUG INFO:
DB Path: " . htmlspecialchars($image_path) . "
Background URL: " . htmlspecialchars($background_url) . "
Generated: " . date('Y-m-d H:i:s') . "
-->";

        // Получаем ВСЕ CSS одной строкой
        $all_css = get_css_for_pdf();

        $full_html = $debug_comment . "
<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
    <style>
        /* ВМЕСТО @page ИСПОЛЬЗУЕМ PADDING НА BODY */
            " . $css_tex . "
        " . $all_css . "
        
        .bodym {
            background: url('" . esc_url($background_url) . "') !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
        }
    </style>
</head>

<body class='bodym'>
    {$header_html}
    <div class=\"content-wrapper\">
       
        <div class=\"pdf-container\">
            {$html_content}
        </div>
    </div>
</body>
</html>";

        /* Рекомендую добавить кавычки для безопасности */

        file_put_contents($filepath, $full_html);

        error_log('CRM HTML fallback created: ' . $filename);
        return $upload_dir['baseurl'] . '/crm_files/' . $filename;

    } catch (Exception $e) {
        error_log('CRM HTML Fallback Error: ' . $e->getMessage());
        return false;
    }
}
