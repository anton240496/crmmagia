<?php
/**
 * Plugin Name: CRMMagia
 * Description:Ваш личный центр для работы с клиентами. Организуйте заявки, отправляйте письма по шаблону с вложениями. Создавайте фирменные коммерческие предложения (в платной версии).
 * <br>Версия 1.0.0 beta | Автор: <a href="https://magtexnology.com" target="_blank" rel="noopener">MagTeXnology</a>
 * Version: 1.0.0
 * Author: MagTeXnology
 * Author URI: https://magtexnology.com
 */

if (!defined('ABSPATH')) {
    exit;
}

// ПРОСТОЕ ПОДКЛЮЧЕНИЕ БЕЗ ЛИШНИХ ХУКОВ
$functions_path = plugin_dir_path(__FILE__) . 'functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
}

class Crm_system
{

    private $page_title = 'CRM system';

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));

        add_action('init', array($this, 'add_rewrite_rules'), 1);
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'load_crm_template'));

        add_filter('theme_page_templates', array($this, 'add_crm_template'));
        add_filter('template_include', array($this, 'load_crm_page_template'));

          add_filter('plugin_row_meta', array($this, 'add_plugin_description_link'), 10, 2);

        

        // add_action('admin_init', array($this, 'check_activation_redirect'));
    }

 public function add_plugin_description_link($links, $file)
{
    if (plugin_basename(__FILE__) === $file) {
        $page_id = get_option('crm_system_page_id');
        if ($page_id) {
            // Добавляем ссылку в meta информацию (рядом с автором)
            $crm_link = '<a href="' . get_permalink($page_id) . '" style="color: #2271b1; font-weight: bold;">🚀 Открыть CRM</a>';
            
            // Добавляем нашу ссылку в конец meta информации
            $links[] = $crm_link;
        }
    }
    return $links;
}

    // public function check_activation_redirect()
    // {
    //     // Проверяем флаг
    //     if (get_transient('crm_plugin_just_activated')) {
    //         delete_transient('crm_plugin_just_activated');

    //         $page_id = get_option('crm_system_page_id');
    //         if ($page_id) {
    //             wp_safe_redirect(get_permalink($page_id));
    //             exit;
    //         }
    //     }
    // }

    public function add_crm_template($templates)
    {
        $templates['crm_system_template'] = 'CRM System Template';
        return $templates;
    }

    public function load_crm_page_template($template)
    {
        global $post;

        if ($post && get_post_meta($post->ID, '_wp_page_template', true) === 'crm_system_template') {
            return plugin_dir_path(__FILE__) . 'templates/crm-system-template.php';
        }

        return $template;
    }

    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            '^CRMMagia/?$',
            'index.php?crm_custom_page=1',
            'top'
        );
        // Добавляем правило для crm_settings
        add_rewrite_rule(
            '^crm_settings/?$',
            'index.php?crm_settings_page=1',
            'top'
        );
    }



    public function add_query_vars($vars)
    {
        $vars[] = 'crm_custom_page';
        $vars[] = 'crm_settings_page';
        return $vars;
    }

    public function load_crm_template()
    {
        if (get_query_var('crm_custom_page')) {
            $crm_template = plugin_dir_path(__FILE__) . 'crm.php';

            if (file_exists($crm_template)) {
                status_header(200);
                include $crm_template;
                exit;
            } else {
                wp_die('Файл crm.php не найден в папке плагина');
            }
        }

        if (get_query_var('crm_settings_page')) {
            $crm_settings_template = plugin_dir_path(__FILE__) . 'crm_settings.php';

            if (file_exists($crm_settings_template)) {
                status_header(200);
                include $crm_settings_template;
                exit;
            } else {
                wp_die('Файл crm_settings.php не найден в папке плагина');
            }
        }
    }


    public function activate()
    {
        $this->create_hello_world_page();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        $this->create_crm_file();
        $this->create_template_file();

        // set_transient('crm_plugin_just_activated', true, 30);
    }

    public function deactivate()
    {
        $this->delete_all_crm_pages();
        flush_rewrite_rules();
    }

    public static function uninstall()
    {
        delete_option('crm_system_page_id');
    }

    public function create_hello_world_page()
    {
        $this->delete_all_crm_pages();

        $page_data = array(
            'post_title' => 'CRMMagia',
            'post_content' => $this->get_page_content(),
            'post_status' => 'private',
            'post_type' => 'page',
            'post_author' => 1,
            'post_name' => 'CRMMagia_welcome',
            'page_template' => 'crm_system_template'
        );

        $page_id = wp_insert_post($page_data);

        if ($page_id && !is_wp_error($page_id)) {
            update_option('crm_system_page_id', $page_id);
            update_post_meta($page_id, '_crm_system_page', 'yes');
            update_post_meta($page_id, '_wp_page_template', 'crm_system_template');
        }

        return $page_id;
    }

    public function delete_all_crm_pages()
    {
        global $wpdb;

        // Удаляем страницы и по старому названию (CRM system) и по новому (CRMMagia)
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
         WHERE (post_title = %s OR post_title = %s) 
         AND post_type = 'page'",
            'CRM system',
            'CRMMagia_welcome'
        ));

        foreach ($pages as $page) {
            wp_delete_post($page->ID, true);
        }

        // Также удаляем все страницы, начинающиеся с crmmagia
        $pages_like = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
         WHERE post_name LIKE %s 
         AND post_type = 'page'",
            'CRMMagia_welcome%'
        ));

        foreach ($pages_like as $page) {
            wp_delete_post($page->ID, true);
        }

        $page_id = get_option('crm_system_page_id');
        if ($page_id) {
            wp_delete_post($page_id, true);
        }

        delete_option('crm_system_page_id');
        delete_option('_crm_force_redirect');
    }



    private function get_page_content()
    {
        return '';
    }

    private function create_crm_file()
    {
        $crm_file_path = plugin_dir_path(__FILE__) . 'crm.php';

        if (!file_exists($crm_file_path)) {
            $default_content = '<p>Привет</p>';
            file_put_contents($crm_file_path, $default_content);
        }
    }

    private function create_template_file()
    {
        $template_dir = plugin_dir_path(__FILE__) . 'templates/';
        if (!file_exists($template_dir)) {
            mkdir($template_dir, 0755, true);
        }

        $template_file = $template_dir . 'crm-system-template.php';

        if (!file_exists($template_file)) {
            $template_content = '<?php
/**
 * Template Name: CRM System Template
 * Template Post Type: page
 */

get_header(); 
?>

<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <?php while (have_posts()) : the_post(); ?>
        <h1><?php the_title(); ?></h1>
        <div class="page-content">
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>';

            file_put_contents($template_file, $template_content);
        }
    }
}

new Crm_system();

