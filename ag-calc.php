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

define('FK_CALC_VERSION', '1.0.1');
define('FK_CALC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FK_CALC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Подключаем все классы
require_once FK_CALC_PLUGIN_DIR . 'includes/class-field-registry.php';
require_once FK_CALC_PLUGIN_DIR . 'includes/class-formula-engine.php';
require_once FK_CALC_PLUGIN_DIR . 'includes/class-product-manager.php';
require_once FK_CALC_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once FK_CALC_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once FK_CALC_PLUGIN_DIR . 'includes/class-admin-ui.php';

// Инициализация плагина
add_action('plugins_loaded', 'fk_calc_init');

function fk_calc_init()
{
    // Инициализация синглтонов
    FK_Field_Registry::get_instance();
    FK_Formula_Engine::get_instance();

    // Регистрация CPT (статический метод)
    FK_Product_Manager::register_post_type();

    // Инициализация только тех классов, которые требуют экземпляра
    new FK_Product_Manager();
    new FK_REST_API();
    new FK_Shortcodes();

    if (is_admin()) {
        new FK_Admin_UI();
    }
}

// Хуки активации/деактивации
register_activation_hook(__FILE__, 'fk_calc_activate');
register_deactivation_hook(__FILE__, 'fk_calc_deactivate');

function fk_calc_activate()
{
    FK_Product_Manager::register_post_type();
    flush_rewrite_rules();
}

function fk_calc_deactivate()
{
    flush_rewrite_rules();
}