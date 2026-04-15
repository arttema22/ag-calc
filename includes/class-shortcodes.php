<?php
/**
 * Шорткоды для вывода калькулятора
 */

if (!defined('ABSPATH'))
    exit;

class AG_Shortcodes
{

    public function __construct()
    {
        add_shortcode('ag_calculator_tabs', [$this, 'render_tabs']);
        add_shortcode('ag_calculator_product', [$this, 'render_single']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Подключение фронтенд-скриптов и стилей
     */
    public function enqueue_frontend_assets()
    {
        // Bootstrap CSS (если не подключен в теме)
        if (!wp_style_is('bootstrap', 'registered')) {
            wp_register_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css', [], '4.6.2');
        }
        wp_enqueue_style('bootstrap');

        // Стили калькулятора
        wp_enqueue_style('ag-calc-css', AG_CALC_PLUGIN_URL . 'assets/css/frontend-styles.css', [], AG_CALC_VERSION);

        // Bootstrap JS
        if (!wp_script_is('bootstrap', 'registered')) {
            wp_register_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js', ['jquery'], '4.6.2', true);
        }
        wp_enqueue_script('bootstrap');

        // Скрипт калькулятора
        wp_enqueue_script('ag-calc-js', AG_CALC_PLUGIN_URL . 'assets/js/frontend-calculator.js', ['jquery', 'bootstrap'], AG_CALC_VERSION, true);

        // Локализация для JS
        wp_localize_script('ag-calc-js', 'ag_calc', [
            'api_root' => rest_url('ag-calc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'loading' => __('Загрузка...', 'ag-calc'),
                'error' => __('Ошибка расчета', 'ag-calc'),
                'price_label' => __('Итого:', 'ag-calc'),
                'currency' => __('₽', 'ag-calc')
            ]
        ]);
    }

    /**
     * Главный шорткод - все продукты во вкладках
     * [ag_calculator_tabs]
     */
    public function render_tabs($atts)
    {
        $products = AG_Product_Manager::get_all_products();

        if (empty($products)) {
            return '<div class="ag-calculator-message">' .
                __('Продукты не настроены', 'ag-calc') .
                '</div>';
        }

        // Сортировка по menu_order
        usort($products, function ($a, $b) {
            $order_a = get_post_meta($a->ID, '_ag_display', true);
            $order_b = get_post_meta($b->ID, '_ag_display', true);
            return ($order_a['menu_order'] ?? 0) - ($order_b['menu_order'] ?? 0);
        });

        ob_start();
        include AG_CALC_PLUGIN_DIR . 'templates/tabs-wrapper.php';
        return ob_get_clean();
    }

    /**
     * Шорткод одного продукта
     * [ag_calculator_product slug="butterfly-photo"]
     */
    public function render_single($atts)
    {
        $atts = shortcode_atts(['slug' => ''], $atts);

        if (empty($atts['slug'])) {
            return '<div class="ag-calculator-message">' .
                __('Укажите slug продукта', 'ag-calc') .
                '</div>';
        }

        $product = AG_Product_Manager::get_product_by_slug($atts['slug']);

        if (!$product) {
            return '<div class="ag-calculator-message">' .
                __('Продукт не найден', 'ag-calc') .
                '</div>';
        }

        $config = AG_Product_Manager::get_product_config($product->ID);

        ob_start();
        include AG_CALC_PLUGIN_DIR . 'templates/single-product.php';
        return ob_get_clean();
    }

    /**
     * Рендер поля формы
     */
    public static function render_field($field, $index = 0)
    {
        $key = $field['key'];
        $label = $field['label'] ?? $key;
        $type = $field['type'] ?? 'number';
        $config = $field['config'] ?? [];

        if ($type === 'hidden_number') {
            return '';
        }

        $html = '<div class="ag-field ag-field-' . esc_attr($type) . '" data-field-key="' . esc_attr($key) . '">';
        $html .= '<label class="ag-field-label">' . esc_html($label) . '</label>';

        switch ($type) {
            case 'number':
                $html .= '<input type="number" 
                            name="' . esc_attr($key) . '" 
                            class="ag-field-input form-control" 
                            data-field-key="' . esc_attr($key) . '"
                            min="' . esc_attr($config['min'] ?? 1) . '" 
                            max="' . esc_attr($config['max'] ?? 100) . '" 
                            step="' . esc_attr($config['step'] ?? 1) . '" 
                            value="' . esc_attr($config['default'] ?? ($config['min'] ?? 1)) . '">';
                break;

            case 'range':
                $min = $config['min'] ?? 1;
                $max = $config['max'] ?? 100;
                $step = $config['step'] ?? 1;
                $default = $config['default'] ?? $min;

                $html .= '<div class="ag-range-wrapper">';
                $html .= '<input type="range" 
                            name="' . esc_attr($key) . '" 
                            class="ag-field-range form-control-range" 
                            data-field-key="' . esc_attr($key) . '"
                            min="' . esc_attr($min) . '" 
                            max="' . esc_attr($max) . '" 
                            step="' . esc_attr($step) . '" 
                            value="' . esc_attr($default) . '">';
                $html .= '<span class="ag-range-value">' . esc_html($default) . '</span>';
                $html .= '</div>';
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($key) . '"
                            class="ag-field-select form-control"
                            data-field-key="' . esc_attr($key) . '">';
                if (!empty($config['options'])) {
                    foreach ($config['options'] as $option) {
                        $price = floatval($option['price'] ?? 0);
                        $html .= '<option value="' . esc_attr($option['value']) . '" ' .
                            'data-price="' . esc_attr($price) . '">' .
                            esc_html($option['value']) . '</option>';
                    }
                }
                $html .= '</select>';
                break;

            case 'radio':
                $html .= '<div class="ag-field ag-field-radio" data-field-key="' . esc_attr($key) . '">';
                $html .= '<div class="ag-radio-group">';
                if (!empty($config['options'])) {
                    foreach ($config['options'] as $i => $option) {
                        $price = floatval($option['price'] ?? 0);
                        $html .= '<div class="form-check">';
                        $html .= '<input type="radio"
                                    name="' . esc_attr($key) . '"
                                    class="form-check-input ag-field-radio-input"
                                    data-field-key="' . esc_attr($key) . '"
                                    data-price="' . esc_attr($price) . '"
                                    value="' . esc_attr($option['value']) . '"
                                    id="' . esc_attr($key . '_' . $i) . '"
                                    ' . checked($i, 0, false) . '>';
                        $html .= '<label class="form-check-label" for="' . esc_attr($key . '_' . $i) . '">' .
                            esc_html($option['value']) . '</label>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div></div>';
                break;

            case 'checkbox':
                $html .= '<div class="form-check">';
                $html .= '<input type="checkbox"
                            name="' . esc_attr($key) . '"
                            class="form-check-input ag-field-checkbox"
                            data-field-key="' . esc_attr($key) . '"
                            value="on" id="' . esc_attr($key) . '">';
                $html .= '<label class="form-check-label" for="' . esc_attr($key) . '">' .
                    esc_html($label) . '</label>';
                $html .= '</div>';
                break;

            case 'text':
                break;
        }

        $html .= '</div>';

        return $html;
    }
}