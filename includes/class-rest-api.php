<?php
/**
 * REST API для калькулятора
 */

if (!defined('ABSPATH')) exit;

class FK_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // Список всех продуктов
        register_rest_route('fk-calc/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products_list'],
            'permission_callback' => '__return_true'
        ]);
        
        // Конфигурация конкретного продукта
        register_rest_route('fk-calc/v1', '/products/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_config'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Расчет стоимости
        register_rest_route('fk-calc/v1', '/calculate', [
            'methods' => 'POST',
            'callback' => [$this, 'calculate_price'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'fields' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
    }
    
    public function get_products_list() {
        $products = FK_Product_Manager::get_all_products();
        $result = [];
        
        foreach ($products as $product) {
            $config = FK_Product_Manager::get_product_config($product->ID);
            $result[] = [
                'id' => $product->ID,
                'title' => $product->post_title,
                'slug' => $config['slug'],
                'display' => $config['display']
            ];
        }
        
        return rest_ensure_response($result);
    }
    
    public function get_product_config($request) {
        $slug = $request['slug'];
        $product = FK_Product_Manager::get_product_by_slug($slug);
        
        if (!$product) {
            return new WP_Error('not_found', __('Продукт не найден', 'foto-kniga-calc'), ['status' => 404]);
        }
        
        $config = FK_Product_Manager::get_product_config($product->ID);
        return rest_ensure_response($config);
    }
    
    public function calculate_price($request) {
        $slug = $request['product_slug'];
        $fields_data = $request['fields'];
        
        $product = FK_Product_Manager::get_product_by_slug($slug);
        
        if (!$product) {
            return new WP_Error('not_found', __('Продукт не найден', 'foto-kniga-calc'), ['status' => 404]);
        }
        
        $config = FK_Product_Manager::get_product_config($product->ID);
        
        // Подготовка переменных для формулы
        $variables = [];
        foreach ($config['fields'] as $field) {
            $key = $field['key'];
            $value = isset($fields_data[$key]) ? $fields_data[$key] : null;

            // Для полей select/radio - находим цену выбранной опции
            if (in_array($field['type'], ['select', 'radio']) && !empty($field['config']['options'])) {
                $price = 0;
                foreach ($field['config']['options'] as $option) {
                    if ($option['value'] === $value) {
                        $price = floatval($option['price'] ?? 0);
                        break;
                    }
                }
                // Основная переменная содержит цену (для использования в формуле)
                $variables[$key] = $price;
                // Дополнительная переменная с суффиксом _value содержит исходное значение
                $variables[$key . '_value'] = $value;

            } else if ($field['type'] === 'checkbox') {
                // Для checkbox переменная содержит цену (если отмечено)
                $is_checked = ($value === 'on' || $value === true || $value === 1 || $value === '1');
                $variables[$key] = $is_checked ? floatval($field['config']['price'] ?? 0) : 0;
                // Дополнительная переменная с суффиксом _value содержит 1 или 0
                $variables[$key . '_value'] = $is_checked ? 1 : 0;

            } else if ($field['type'] === 'hidden_number') {
                // Для hidden_number значение всегда берётся из конфига админки
                $variables[$key] = floatval($field['config']['value'] ?? 0);

            } else {
                // Для number, range, text - обычное числовое значение
                $variables[$key] = floatval($value ?? 0);
            }
        }
        
        // Расчет
        $formula = $config['formula']['expression'] ?? '';
        $result = FK_Formula_Engine::calculate($formula, $variables);
        
        return rest_ensure_response([
            'success' => $result['success'],
            'price' => $result['price'],
            'expression' => $result['expression'] ?? '',
            'error' => $result['error'] ?? null
        ]);
    }
}