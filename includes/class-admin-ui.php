<?php
/**
 * Admin UI для настройки калькулятора
 */

if (!defined('ABSPATH'))
    exit;

class AG_Admin_UI
{

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_ag_product', [$this, 'save_product_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('manage_ag_product_posts_columns', [$this, 'custom_columns']);
        add_action('manage_ag_product_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_action('wp_ajax_ag_test_formula', [$this, 'ajax_test_formula']);
    }

    public function register_meta_boxes()
    {
        add_meta_box(
            'ag_product_settings',
            __('Настройки продукта', 'ag-calc'),
            [$this, 'render_product_settings'],
            'ag_product',
            'normal',
            'high'
        );

        add_meta_box(
            'ag_product_fields',
            __('Поля калькулятора', 'ag-calc'),
            [$this, 'render_product_fields'],
            'ag_product',
            'normal',
            'high'
        );

        add_meta_box(
            'ag_product_formula',
            __('Формула расчета', 'ag-calc'),
            [$this, 'render_product_formula'],
            'ag_product',
            'normal',
            'high'
        );
    }

    /**
     * Подключение скриптов и стилей
     */
    public function enqueue_admin_assets($hook)
    {
        global $post_type;

        if ($post_type !== 'ag_product')
            return;

        wp_enqueue_style('ag-admin-css', AG_CALC_PLUGIN_URL . 'assets/css/admin-styles.css', [], AG_CALC_VERSION);
        wp_enqueue_script('ag-admin-js', AG_CALC_PLUGIN_URL . 'assets/js/admin-formula-builder.js', ['jquery', 'jquery-ui-sortable'], AG_CALC_VERSION, true);

        wp_localize_script('ag-admin-js', 'ag_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ag_admin_nonce'),
            'fields_registry' => AG_Field_Registry::get_all_fields(),
            'operators' => AG_Formula_Engine::get_allowed_operators(),
            'i18n' => [
                'add_field' => __('Добавить поле', 'ag-calc'),
                'remove_field' => __('Удалить', 'ag-calc'),
                'add_pricing_row' => __('Добавить значение', 'ag-calc'),
                'test_calculation' => __('Проверить расчет', 'ag-calc'),
                'calculation_result' => __('Результат:', 'ag-calc'),
                'field_key' => __('Ключ поля', 'ag-calc'),
                'field_label' => __('Подпись', 'ag-calc'),
                'field_type' => __('Тип', 'ag-calc'),
                'select_type' => __('— Выберите тип поля —', 'ag-calc'),
                'value' => __('Значение', 'ag-calc'),
                'price' => __('Цена', 'ag-calc'),
                'empty_formula' => __('Введите формулу', 'ag-calc')
            ]
        ]);
    }

    /**
     * Кастомные колонки в списке продуктов
     */
    public function custom_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['ag_slug'] = __('Слаг', 'ag-calc');
                $new_columns['ag_fields_count'] = __('Полей', 'ag-calc');
            }
        }
        return $new_columns;
    }

    public function custom_column_content($column, $post_id)
    {
        switch ($column) {
            case 'ag_slug':
                $slug = get_post_meta($post_id, '_ag_slug', true);
                echo $slug ? '<code>' . esc_html($slug) . '</code>' : '—';
                break;
            case 'ag_fields_count':
                $fields = get_post_meta($post_id, '_ag_fields', true);
                $fields = is_array($fields) ? $fields : [];
                echo count($fields);
                break;
        }
    }

    /**
     * Рендер мета-бокса "Настройки продукта"
     */
    public function render_product_settings($post)
    {
        wp_nonce_field('ag_product_settings', 'ag_product_settings_nonce');

        $slug = get_post_meta($post->ID, '_ag_slug', true);
        $display = get_post_meta($post->ID, '_ag_display', true);
        $display = wp_parse_args($display, [
            'tab_title' => '',
            'menu_order' => 0
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="ag_slug"><?php _e('Слаг продукта', 'ag-calc'); ?></label></th>
                <td>
                    <input type="text" name="ag_slug" id="ag_slug" value="<?php echo esc_attr($slug); ?>" class="regular-text"
                        placeholder="butterfly-photo" required>
                    <p class="description"><?php _e('Уникальный идентификатор для шорткода и API', 'ag-calc'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ag_tab_title"><?php _e('Заголовок вкладки', 'ag-calc'); ?></label></th>
                <td>
                    <input type="text" name="ag_display[tab_title]" id="ag_tab_title"
                        value="<?php echo esc_attr($display['tab_title']); ?>" class="regular-text">
                    <p class="description"><?php _e('Отображается в главном шорткоде с вкладками', 'ag-calc'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ag_menu_order"><?php _e('Порядок отображения', 'ag-calc'); ?></label></th>
                <td>
                    <input type="number" name="ag_display[menu_order]" id="ag_menu_order"
                        value="<?php echo esc_attr($display['menu_order']); ?>" class="small-text" min="0">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Рендер мета-бокса "Поля калькулятора"
     */
    public function render_product_fields($post)
    {
        $fields = get_post_meta($post->ID, '_ag_fields', true);
        $fields = is_array($fields) ? $fields : [];
        $fields_registry = AG_Field_Registry::get_all_fields();
        ?>
        <div id="ag-fields-container">
            <div class="ag-fields-list">
                <?php if (!empty($fields)): ?>
                    <?php foreach ($fields as $index => $field): ?>
                        <?php $this->render_field_row($index, $field, $fields_registry); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="description"><?php _e('Добавьте поля для этого продукта', 'ag-calc'); ?></p>
                <?php endif; ?>
            </div>

            <div class="ag-add-field-wrapper">
                <select id="ag-new-field-type" class="regular-text">
                    <option value=""><?php _e('Выберите тип поля', 'ag-calc'); ?></option>
                    <?php foreach ($fields_registry as $key => $field_type): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($field_type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="ag-add-field" class="button button-secondary">
                    <?php _e('Добавить поле', 'ag-calc'); ?>
                </button>
            </div>
        </div>

        <input type="hidden" name="ag_fields_data" id="ag_fields_data" value='<?php echo esc_attr(json_encode($fields)); ?>'>
        <?php
    }

    /**
     * Рендер строки поля
     */
    private function render_field_row($index, $field, $registry)
    {
        $type = $field['type'] ?? 'number';
        $field_config = $registry[$type] ?? $registry['number'];
        ?>
        <div class="ag-field-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="ag-field-header">
                <span class="ag-field-handle dashicons dashicons-menu"></span>
                <strong><?php echo esc_html($field['label'] ?? __('Поле', 'ag-calc')); ?></strong>
                <span class="ag-field-type-badge"><?php echo esc_html($field_config['label']); ?></span>
                <button type="button" class="ag-remove-field button-link-delete">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>

            <div class="ag-field-settings">
                <div class="ag-field-setting">
                    <label><?php _e('Ключ поля', 'ag-calc'); ?></label>
                    <input type="text" name="ag_field_key[<?php echo $index; ?>]"
                        value="<?php echo esc_attr($field['key'] ?? ''); ?>" class="ag-field-key" required>
                </div>

                <div class="ag-field-setting">
                    <label><?php _e('Подпись', 'ag-calc'); ?></label>
                    <input type="text" name="ag_field_label[<?php echo $index; ?>]"
                        value="<?php echo esc_attr($field['label'] ?? ''); ?>" class="ag-field-label">
                </div>

                <div class="ag-field-setting">
                    <label><?php _e('Тип', 'ag-calc'); ?></label>
                    <select name="ag_field_type[<?php echo $index; ?>]" class="ag-field-type">
                        <?php foreach ($registry as $key => $field_type): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>>
                                <?php echo esc_html($field_type['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ag-field-type-settings" data-type="<?php echo esc_attr($type); ?>">
                    <?php $this->render_field_type_settings($index, $field, $type); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Настройки для разных типов полей
     */
    private function render_field_type_settings($index, $field, $type)
    {
        if ($type === 'number' || $type === 'range') {
            ?>
            <div class="ag-field-setting">
                <label><?php _e('Минимум', 'ag-calc'); ?></label>
                <input type="number" name="ag_field_config[<?php echo $index; ?>][min]"
                    value="<?php echo esc_attr($field['config']['min'] ?? 1); ?>" class="small-text">
            </div>
            <div class="ag-field-setting">
                <label><?php _e('Максимум', 'ag-calc'); ?></label>
                <input type="number" name="ag_field_config[<?php echo $index; ?>][max]"
                    value="<?php echo esc_attr($field['config']['max'] ?? 100); ?>" class="small-text">
            </div>
            <div class="ag-field-setting">
                <label><?php _e('Шаг', 'ag-calc'); ?></label>
                <input type="number" name="ag_field_config[<?php echo $index; ?>][step]"
                    value="<?php echo esc_attr($field['config']['step'] ?? 1); ?>" class="small-text">
            </div>
            <?php
        }

        if ($type === 'hidden_number') {
            ?>
            <div class="ag-field-setting">
                <label><?php _e('Значение', 'ag-calc'); ?></label>
                <input type="number" name="ag_field_config[<?php echo $index; ?>][value]"
                    value="<?php echo esc_attr($field['config']['value'] ?? 0); ?>" class="small-text" step="0.01">
            </div>
            <?php
        }

        if ($type === 'select' || $type === 'radio') {
            ?>
            <div class="ag-field-setting ag-pricing-table-setting">
                <label><?php _e('Значения и цены', 'ag-calc'); ?></label>
                <div class="ag-pricing-table" data-field-index="<?php echo $index; ?>">
                    <?php
                    $options = $field['config']['options'] ?? [];
                    if (!empty($options)):
                        foreach ($options as $opt_index => $option):
                            ?>
                            <div class="ag-pricing-row">
                                <input type="text" name="ag_field_options[<?php echo $index; ?>][<?php echo $opt_index; ?>][value]"
                                    value="<?php echo esc_attr($option['value'] ?? ''); ?>"
                                    placeholder="<?php _e('Значение', 'ag-calc'); ?>" class="">
                                <input type="number" name="ag_field_options[<?php echo $index; ?>][<?php echo $opt_index; ?>][price]"
                                    value="<?php echo esc_attr($option['price'] ?? 0); ?>"
                                    placeholder="<?php _e('Цена', 'ag-calc'); ?>" class="small-text">
                                <button type="button" class="ag-remove-pricing-row button-link-delete">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php
                        endforeach;
                    else:
                        ?>
                        <div class="ag-pricing-row">
                            <input type="text" name="ag_field_options[<?php echo $index; ?>][0][value]"
                                placeholder="<?php _e('Значение', 'ag-calc'); ?>" class="regular-text">
                            <input type="number" name="ag_field_options[<?php echo $index; ?>][0][price]"
                                placeholder="<?php _e('Цена', 'ag-calc'); ?>" class="small-text" value="0">
                            <button type="button" class="ag-remove-pricing-row button-link-delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
                <button type="button" class="ag-add-pricing-row button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Добавить значение', 'ag-calc'); ?>
                </button>
            </div>
            <?php
        }

        if ($type === 'checkbox') {
            ?>
            <div class="ag-field-setting">
                <label><?php _e('Цена если отмечено', 'ag-calc'); ?></label>
                <input type="number" name="ag_field_config[<?php echo $index; ?>][price]"
                    value="<?php echo esc_attr($field['config']['price'] ?? 0); ?>" class="small-text">
            </div>
            <?php
        }
    }

    /**
     * Рендер мета-бокса "Формула расчета"
     */
    public function render_product_formula($post)
    {
        $formula = get_post_meta($post->ID, '_ag_formula', true);
        $formula = wp_parse_args($formula, [
            'expression' => '',
            'variables' => []
        ]);

        $fields = get_post_meta($post->ID, '_ag_fields', true);
        $fields = is_array($fields) ? $fields : [];
        ?>
        <div class="ag-formula-builder">
            <div class="ag-formula-variables">
                <label><?php _e('Доступные переменные:', 'ag-calc'); ?></label>
                <div class="ag-variables-list">
                    <?php if (!empty($fields)): ?>
                        <?php foreach ($fields as $field): ?>
                            <span class="ag-variable-tag" data-var="<?php echo esc_attr($field['key'] ?? ''); ?>">
                                {{<?php echo esc_html($field['key'] ?? ''); ?>}}
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="description"><?php _e('Сначала добавьте поля', 'ag-calc'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ag-formula-operators">
                <label><?php _e('Операции:', 'ag-calc'); ?></label>
                <div class="ag-operators-list">
                    <?php
                    $operators = AG_Formula_Engine::get_allowed_operators();
                    foreach ($operators as $op => $desc):
                        ?>
                        <span class="ag-operator-tag" data-op="<?php echo esc_attr($op); ?>">
                            <?php echo esc_html($op); ?>
                        </span>
                        <?php
                    endforeach;
                    ?>
                </div>
            </div>

            <div class="ag-formula-input-wrapper">
                <label for="ag_formula_expression"><?php _e('Формула', 'ag-calc'); ?></label>
                <textarea name="ag_formula[expression]" id="ag_formula_expression" class="large-text code" rows="4"
                    placeholder="((base_price * pages) + cover_price) * quantity"><?php
                    echo esc_textarea(html_entity_decode($formula['expression'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    ?></textarea>
            </div>

            <div class="ag-formula-test">
                <button type="button" id="ag-test-formula" class="button button-secondary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Проверить расчет', 'ag-calc'); ?>
                </button>
                <div id="ag-formula-test-result" class="ag-test-result"></div>
            </div>

            <p class="description">
                <?php _e('Используйте ключи полей как переменные. Пример: {{quantity}} * {{price}}', 'ag-calc'); ?>
            </p>

            <div style="margin-top: 15px; padding: 12px; background: #f0f0f1; border-left: 4px solid #0073aa;">
                <strong><?php _e('Пользовательские переменные:', 'ag-calc'); ?></strong><br>
                <?php _e('Можно определять свои переменные для сложных расчётов:', 'ag-calc'); ?><br>
                <code style="display: block; margin: 8px 0; padding: 8px; background: #fff; border: 1px solid #ddd;">
                                base_price = if({{kolvo}} &gt;= 100, 120, 150);<br>
                                {{kolvo}} * ({{base_price}} + {{razvorot}})
                                                </code>
                <?php _e('Каждая переменная с новой строки, основная формула — последней.', 'ag-calc'); ?>
            </div>
        </div>

        <input type="hidden" name="ag_formula_data" id="ag_formula_data" value='<?php echo esc_attr(json_encode($formula)); ?>'>
        <?php
    }

    /**
     * Сохранение мета-данных продукта
     */
    public function save_product_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
        if (!current_user_can('edit_post', $post_id))
            return;
        if (
            !isset($_POST['ag_product_settings_nonce']) ||
            !wp_verify_nonce($_POST['ag_product_settings_nonce'], 'ag_product_settings')
        )
            return;

        // Сохранение настроек
        if (isset($_POST['ag_slug'])) {
            update_post_meta($post_id, '_ag_slug', sanitize_text_field($_POST['ag_slug']));
        }

        // Сохранение отображения
        if (isset($_POST['ag_display'])) {
            $display = [
                'tab_icon' => sanitize_text_field($_POST['ag_display']['tab_icon'] ?? 'dashicons-calculator'),
                'tab_title' => sanitize_text_field($_POST['ag_display']['tab_title'] ?? ''),
                'menu_order' => intval($_POST['ag_display']['menu_order'] ?? 0)
            ];
            update_post_meta($post_id, '_ag_display', $display);
        }

        // Сохранение полей
        if (isset($_POST['ag_field_key']) && is_array($_POST['ag_field_key']) && !empty($_POST['ag_field_key'])) {
            $fields = [];
            $keys = $_POST['ag_field_key'];
            $labels = $_POST['ag_field_label'] ?? [];
            $types = $_POST['ag_field_type'] ?? [];

            foreach ($keys as $index => $key) {
                $field = [
                    'key' => sanitize_text_field($key),
                    'label' => sanitize_text_field($labels[$index] ?? ''),
                    'type' => sanitize_text_field($types[$index] ?? 'number'),
                    'config' => []
                ];

                // Конфигурация для number/range
                if (in_array($field['type'], ['number', 'range'])) {
                    $field['config'] = [
                        'min' => floatval($_POST['ag_field_config'][$index]['min'] ?? 1),
                        'max' => floatval($_POST['ag_field_config'][$index]['max'] ?? 100),
                        'step' => floatval($_POST['ag_field_config'][$index]['step'] ?? 1)
                    ];
                }

                // Конфигурация для hidden_number — только значение
                if ($field['type'] === 'hidden_number') {
                    $field['config'] = [
                        'value' => floatval($_POST['ag_field_config'][$index]['value'] ?? 0)
                    ];
                }

                // Опции для select/radio
                if (in_array($field['type'], ['select', 'radio']) && isset($_POST['ag_field_options'][$index])) {
                    $options = [];
                    foreach ($_POST['ag_field_options'][$index] as $opt) {
                        $options[] = [
                            'value' => sanitize_text_field($opt['value'] ?? ''),
                            'price' => floatval($opt['price'] ?? 0)
                        ];
                    }
                    $field['config']['options'] = $options;
                }

                // Цена для checkbox
                if ($field['type'] === 'checkbox') {
                    $field['config']['price'] = floatval($_POST['ag_field_config'][$index]['price'] ?? 0);
                }

                $fields[] = $field;
            }

            update_post_meta($post_id, '_ag_fields', $fields);
        } else {
            // Если полей нет - удаляем мета
            delete_post_meta($post_id, '_ag_fields');
        }

        // Сохранение формулы
        if (isset($_POST['ag_formula'])) {
            $formula = [
                'expression' => sanitize_textarea_field($_POST['ag_formula']['expression'] ?? ''),
                'variables' => []
            ];
            update_post_meta($post_id, '_ag_formula', $formula);
        }
    }

    /**
     * AJAX тест формулы
     */
    public function ajax_test_formula()
    {
        check_ajax_referer('ag_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Нет прав', 'ag-calc')]);
        }

        $expression = sanitize_text_field($_POST['expression'] ?? '');
        $variables = $_POST['variables'] ?? [];

        // Получаем поля продукта для корректной обработки
        $post_id = intval($_POST['post_id'] ?? 0);
        $fields = $post_id ? get_post_meta($post_id, '_ag_fields', true) : [];
        $fields = is_array($fields) ? $fields : [];

        // Преобразуем тестовые данные так же, как в REST API
        $processed_variables = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = isset($variables[$key]) ? $variables[$key] : null;
            $type = $field['type'] ?? 'number';

            if (in_array($type, ['select', 'radio']) && !empty($field['config']['options'])) {
                // Для select/radio ищем цену выбранного значения
                $price = 0;
                foreach ($field['config']['options'] as $option) {
                    if ($option['value'] === $value) {
                        $price = floatval($option['price'] ?? 0);
                        break;
                    }
                }
                $processed_variables[$key] = $price;
                $processed_variables[$key . '_value'] = $value;

            } else if ($type === 'checkbox') {
                // Для checkbox используем цену из конфига
                $is_checked = ($value === 'on' || $value === true || $value === 1 || $value === '1' || $value === 10);
                $processed_variables[$key] = $is_checked ? floatval($field['config']['price'] ?? 0) : 0;
                $processed_variables[$key . '_value'] = $is_checked ? 1 : 0;

            } else if ($type === 'hidden_number') {
                // Для hidden_number значение всегда берётся из конфига админки
                $processed_variables[$key] = floatval($field['config']['value'] ?? 0);

            } else {
                // Для number/range/text - обычное числовое значение
                $processed_variables[$key] = floatval($value ?? 0);
            }
        }

        // Если поля не получены, используем переменные как есть (для обратной совместимости)
        if (empty($fields)) {
            $processed_variables = $variables;
        }

        $field_keys = array_keys($processed_variables);
        $validation = AG_Formula_Engine::validate_formula($expression, $field_keys);

        if (!$validation['valid']) {
            wp_send_json_error(['error' => implode(', ', $validation['errors'])]);
        }

        $result = AG_Formula_Engine::calculate($expression, $processed_variables);

        if ($result['success']) {
            wp_send_json_success(['price' => $result['price']]);
        } else {
            wp_send_json_error(['error' => $result['error']]);
        }
    }
}