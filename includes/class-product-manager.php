<?php
/**
 * Регистрация и управление типами продукции
 */

if (!defined('ABSPATH'))
    exit;

class FK_Product_Manager
{

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('post_updated_messages', [$this, 'post_updated_messages']);
    }

    public static function register_post_type()
    {
        $labels = [
            'name' => __('Типы продукции', 'foto-kniga-calc'),
            'singular_name' => __('Тип продукции', 'foto-kniga-calc'),
            'add_new' => __('Добавить новый', 'foto-kniga-calc'),
            'add_new_item' => __('Добавить новый тип продукции', 'foto-kniga-calc'),
            'edit_item' => __('Редактировать тип продукции', 'foto-kniga-calc'),
            'new_item' => __('Новый тип продукции', 'foto-kniga-calc'),
            'view_item' => __('Просмотреть тип продукции', 'foto-kniga-calc'),
            'search_items' => __('Искать типы продукции', 'foto-kniga-calc'),
            'not_found' => __('Типы продукции не найдены', 'foto-kniga-calc'),
            'not_found_in_trash' => __('В корзине не найдено', 'foto-kniga-calc'),
            'menu_name' => __('Калькулятор', 'foto-kniga-calc')
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-calculator',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title'],
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
            'delete_with_user' => false,
            'show_in_rest' => true
        ];

        register_post_type('fk_product', $args);
    }

    public function post_updated_messages($messages)
    {
        $post = get_post();

        $messages['fk_product'] = [
            0 => '',
            1 => __('Тип продукции обновлен.', 'foto-kniga-calc'),
            2 => __('Произвольное поле обновлено.', 'foto-kniga-calc'),
            3 => __('Произвольное поле удалено.', 'foto-kniga-calc'),
            4 => __('Тип продукции обновлен.', 'foto-kniga-calc'),
            5 => isset($_GET['revision']) ? sprintf(__('Тип продукции восстановлен из версии от %s', 'foto-kniga-calc'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Тип продукции опубликован.', 'foto-kniga-calc'),
            7 => __('Тип продукции сохранен.', 'foto-kniga-calc'),
            8 => __('Тип продукции отправлен на проверку.', 'foto-kniga-calc'),
            9 => sprintf(__('Тип продукции запланирован на: <strong>%1$s</strong>.', 'foto-kniga-calc'), date_i18n(__('M j, Y @ G:i', 'foto-kniga-calc'), strtotime($post->post_date))),
            10 => __('Черновик типа продукции обновлен.', 'foto-kniga-calc')
        ];

        return $messages;
    }

    public static function get_all_products()
    {
        $products = get_posts([
            'post_type' => 'fk_product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);

        return $products;
    }

    public static function get_product_by_slug($slug)
    {
        $products = get_posts([
            'post_type' => 'fk_product',
            'numberposts' => 1,
            'post_status' => 'publish',
            'meta_key' => '_fk_slug',
            'meta_value' => $slug
        ]);

        return !empty($products) ? $products[0] : null;
    }

    public static function get_product_config($post_id)
    {
        $fields = get_post_meta($post_id, '_fk_fields', true);
        $fields = is_array($fields) ? $fields : [];

        $formula = get_post_meta($post_id, '_fk_formula', true);
        $formula = is_array($formula) ? $formula : ['expression' => '', 'variables' => []];

        $display = get_post_meta($post_id, '_fk_display', true);
        $display = wp_parse_args($display, [
            'tab_title' => get_the_title($post_id),
            'menu_order' => 0
        ]);

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'slug' => get_post_meta($post_id, '_fk_slug', true),
            'fields' => $fields,
            'formula' => $formula,
            'display' => $display
        ];
    }
}