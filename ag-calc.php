<?php
/**
 * Plugin Name: AG Calculator
 * Description: Гибкий калькулятор стоимости продукции с настройкой в админке
 * Version: 1.0.0
 * Author: Artem Gusev
 * Text Domain: ag-calc
 */

if (!defined('ABSPATH'))
    exit;

define('AG_CALC_VERSION', '1.0.1');
define('AG_CALC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AG_CALC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Подключаем все классы
require_once AG_CALC_PLUGIN_DIR . 'includes/class-field-registry.php';
require_once AG_CALC_PLUGIN_DIR . 'includes/class-formula-engine.php';
require_once AG_CALC_PLUGIN_DIR . 'includes/class-product-manager.php';
require_once AG_CALC_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once AG_CALC_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once AG_CALC_PLUGIN_DIR . 'includes/class-admin-ui.php';

// Инициализация плагина
add_action('plugins_loaded', 'ag_calc_init');

function ag_calc_init()
{
    // Инициализация синглтонов
    AG_Field_Registry::get_instance();
    AG_Formula_Engine::get_instance();

    // Регистрация CPT (статический метод)
    AG_Product_Manager::register_post_type();

    // Инициализация только тех классов, которые требуют экземпляра
    new AG_Product_Manager();
    new AG_REST_API();
    new AG_Shortcodes();

    if (is_admin()) {
        new AG_Admin_UI();
    }
}

// Хуки активации/деактивации
register_activation_hook(__FILE__, 'ag_calc_activate');
register_deactivation_hook(__FILE__, 'ag_calc_deactivate');

function ag_calc_activate()
{
    AG_Product_Manager::register_post_type();
    flush_rewrite_rules();
}

function ag_calc_deactivate()
{
    flush_rewrite_rules();
}