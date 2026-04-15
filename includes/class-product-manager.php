<?php
/**
 * Регистрация и управление типами продукции
 */

if (!defined('ABSPATH'))
    exit;

class AG_Product_Manager
{

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('post_updated_messages', [$this, 'post_updated_messages']);
    }

    public static function register_post_type()
    {
        $labels = [
            'name' => __('Типы продукции', 'ag-calc'),
            'singular_name' => __('Тип продукции', 'ag-calc'),
            'add_new' => __('Добавить новый', 'ag-calc'),
            'add_new_item' => __('Добавить новый тип продукции', 'ag-calc'),
            'edit_item' => __('Редактировать тип продукции', 'ag-calc'),
            'new_item' => __('Новый тип продукции', 'ag-calc'),
            'view_item' => __('Просмотреть тип продукции', 'ag-calc'),
            'search_items' => __('Искать типы продукции', 'ag-calc'),
            'not_found' => __('Типы продукции не найдены', 'ag-calc'),
            'not_found_in_trash' => __('В корзине не найдено', 'ag-calc'),
            'menu_name' => __('Калькулятор', 'ag-calc')
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

        register_post_type('ag_product', $args);
    }

    public function post_updated_messages($messages)
    {
        $post = get_post();

        $messages['ag_product'] = [
            0 => '',
            1 => __('Тип продукции обновлен.', 'ag-calc'),
            2 => __('Произвольное поле обновлено.', 'ag-calc'),
            3 => __('Произвольное поле удалено.', 'ag-calc'),
            4 => __('Тип продукции обновлен.', 'ag-calc'),
            5 => isset($_GET['revision']) ? sprintf(__('Тип продукции восстановлен из версии от %s', 'ag-calc'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Тип продукции опубликован.', 'ag-calc'),
            7 => __('Тип продукции сохранен.', 'ag-calc'),
            8 => __('Тип продукции отправлен на проверку.', 'ag-calc'),
            9 => sprintf(__('Тип продукции запланирован на: <strong>%1$s</strong>.', 'ag-calc'), date_i18n(__('M j, Y @ G:i', 'ag-calc'), strtotime($post->post_date))),
            10 => __('Черновик типа продукции обновлен.', 'ag-calc')
        ];

        return $messages;
    }

    public static function get_all_products()
    {
        $products = get_posts([
            'post_type' => 'ag_product',
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
            'post_type' => 'ag_product',
            'numberposts' => 1,
            'post_status' => 'publish',
            'meta_key' => '_ag_slug',
            'meta_value' => $slug
        ]);

        return !empty($products) ? $products[0] : null;
    }

    public static function get_product_config($post_id)
    {
        $fields = get_post_meta($post_id, '_ag_fields', true);
        $fields = is_array($fields) ? $fields : [];

        $formula = get_post_meta($post_id, '_ag_formula', true);
        $formula = is_array($formula) ? $formula : ['expression' => '', 'variables' => []];

        $display = get_post_meta($post_id, '_ag_display', true);
        $display = wp_parse_args($display, [
            'tab_title' => get_the_title($post_id),
            'menu_order' => 0
        ]);

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'slug' => get_post_meta($post_id, '_ag_slug', true),
            'fields' => $fields,
            'formula' => $formula,
            'display' => $display
        ];
    }
}