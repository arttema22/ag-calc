<?php
/**
 * Admin UI для настройки калькулятора
 */

if (!defined('ABSPATH'))
    exit;

class FK_Admin_UI
{

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_fk_product', [$this, 'save_product_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('manage_fk_product_posts_columns', [$this, 'custom_columns']);
        add_action('manage_fk_product_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_action('wp_ajax_fk_test_formula', [$this, 'ajax_test_formula']);
    }

    public function register_meta_boxes()
    {
        add_meta_box(
            'fk_product_settings',
            __('Настройки продукта', 'foto-kniga-calc'),
            [$this, 'render_product_settings'],
            'fk_product',
            'normal',
            'high'
        );

        add_meta_box(
            'fk_product_fields',
            __('Поля калькулятора', 'foto-kniga-calc'),
            [$this, 'render_product_fields'],
            'fk_product',
            'normal',
            'high'
        );

        add_meta_box(
            'fk_product_formula',
            __('Формула расчета', 'foto-kniga-calc'),
            [$this, 'render_product_formula'],
            'fk_product',
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

        if ($post_type !== 'fk_product')
            return;

        wp_enqueue_style('fk-admin-css', FK_CALC_PLUGIN_URL . 'assets/css/admin-styles.css', [], FK_CALC_VERSION);
        wp_enqueue_script('fk-admin-js', FK_CALC_PLUGIN_URL . 'assets/js/admin-formula-builder.js', ['jquery', 'jquery-ui-sortable'], FK_CALC_VERSION, true);

        wp_localize_script('fk-admin-js', 'fk_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fk_admin_nonce'),
            'fields_registry' => FK_Field_Registry::get_all_fields(),
            'operators' => FK_Formula_Engine::get_allowed_operators(),
            'i18n' => [
                'add_field' => __('Добавить поле', 'foto-kniga-calc'),
                'remove_field' => __('Удалить', 'foto-kniga-calc'),
                'add_pricing_row' => __('Добавить значение', 'foto-kniga-calc'),
                'test_calculation' => __('Проверить расчет', 'foto-kniga-calc'),
                'calculation_result' => __('Результат:', 'foto-kniga-calc'),
                'field_key' => __('Ключ поля', 'foto-kniga-calc'),
                'field_label' => __('Подпись', 'foto-kniga-calc'),
                'field_type' => __('Тип', 'foto-kniga-calc'),
                'select_type' => __('— Выберите тип поля —', 'foto-kniga-calc'),
                'value' => __('Значение', 'foto-kniga-calc'),
                'price' => __('Цена', 'foto-kniga-calc'),
                'empty_formula' => __('Введите формулу', 'foto-kniga-calc')
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
                $new_columns['fk_slug'] = __('Слаг', 'foto-kniga-calc');
                $new_columns['fk_fields_count'] = __('Полей', 'foto-kniga-calc');
            }
        }
        return $new_columns;
    }

    public function custom_column_content($column, $post_id)
    {
        switch ($column) {
            case 'fk_slug':
                $slug = get_post_meta($post_id, '_fk_slug', true);
                echo $slug ? '<code>' . esc_html($slug) . '</code>' : '—';
                break;
            case 'fk_fields_count':
                $fields = get_post_meta($post_id, '_fk_fields', true);
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
        wp_nonce_field('fk_product_settings', 'fk_product_settings_nonce');

        $slug = get_post_meta($post->ID, '_fk_slug', true);
        $display = get_post_meta($post->ID, '_fk_display', true);
        $display = wp_parse_args($display, [
            'tab_title' => '',
            'menu_order' => 0
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="fk_slug"><?php _e('Слаг продукта', 'foto-kniga-calc'); ?></label></th>
                <td>
                    <input type="text" name="fk_slug" id="fk_slug" value="<?php echo esc_attr($slug); ?>" class="regular-text"
                        placeholder="butterfly-photo" required>
                    <p class="description"><?php _e('Уникальный идентификатор для шорткода и API', 'foto-kniga-calc'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="fk_tab_title"><?php _e('Заголовок вкладки', 'foto-kniga-calc'); ?></label></th>
                <td>
                    <input type="text" name="fk_display[tab_title]" id="fk_tab_title"
                        value="<?php echo esc_attr($display['tab_title']); ?>" class="regular-text">
                    <p class="description"><?php _e('Отображается в главном шорткоде с вкладками', 'foto-kniga-calc'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="fk_menu_order"><?php _e('Порядок отображения', 'foto-kniga-calc'); ?></label></th>
                <td>
                    <input type="number" name="fk_display[menu_order]" id="fk_menu_order"
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
        $fields = get_post_meta($post->ID, '_fk_fields', true);
        $fields = is_array($fields) ? $fields : [];
        $fields_registry = FK_Field_Registry::get_all_fields();
        ?>
        <div id="fk-fields-container">
            <div class="fk-fields-list">
                <?php if (!empty($fields)): ?>
                    <?php foreach ($fields as $index => $field): ?>
                        <?php $this->render_field_row($index, $field, $fields_registry); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="description"><?php _e('Добавьте поля для этого продукта', 'foto-kniga-calc'); ?></p>
                <?php endif; ?>
            </div>

            <div class="fk-add-field-wrapper">
                <select id="fk-new-field-type" class="regular-text">
                    <option value=""><?php _e('Выберите тип поля', 'foto-kniga-calc'); ?></option>
                    <?php foreach ($fields_registry as $key => $field_type): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($field_type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="fk-add-field" class="button button-secondary">
                    <?php _e('Добавить поле', 'foto-kniga-calc'); ?>
                </button>
            </div>
        </div>

        <input type="hidden" name="fk_fields_data" id="fk_fields_data" value='<?php echo esc_attr(json_encode($fields)); ?>'>
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
        <div class="fk-field-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="fk-field-header">
                <span class="fk-field-handle dashicons dashicons-menu"></span>
                <strong><?php echo esc_html($field['label'] ?? __('Поле', 'foto-kniga-calc')); ?></strong>
                <span class="fk-field-type-badge"><?php echo esc_html($field_config['label']); ?></span>
                <button type="button" class="fk-remove-field button-link-delete">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>

            <div class="fk-field-settings">
                <div class="fk-field-setting">
                    <label><?php _e('Ключ поля', 'foto-kniga-calc'); ?></label>
                    <input type="text" name="fk_field_key[<?php echo $index; ?>]"
                        value="<?php echo esc_attr($field['key'] ?? ''); ?>" class="fk-field-key" required>
                </div>

                <div class="fk-field-setting">
                    <label><?php _e('Подпись', 'foto-kniga-calc'); ?></label>
                    <input type="text" name="fk_field_label[<?php echo $index; ?>]"
                        value="<?php echo esc_attr($field['label'] ?? ''); ?>" class="fk-field-label">
                </div>

                <div class="fk-field-setting">
                    <label><?php _e('Тип', 'foto-kniga-calc'); ?></label>
                    <select name="fk_field_type[<?php echo $index; ?>]" class="fk-field-type">
                        <?php foreach ($registry as $key => $field_type): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>>
                                <?php echo esc_html($field_type['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fk-field-type-settings" data-type="<?php echo esc_attr($type); ?>">
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
            <div class="fk-field-setting">
                <label><?php _e('Минимум', 'foto-kniga-calc'); ?></label>
                <input type="number" name="fk_field_config[<?php echo $index; ?>][min]"
                    value="<?php echo esc_attr($field['config']['min'] ?? 1); ?>" class="small-text">
            </div>
            <div class="fk-field-setting">
                <label><?php _e('Максимум', 'foto-kniga-calc'); ?></label>
                <input type="number" name="fk_field_config[<?php echo $index; ?>][max]"
                    value="<?php echo esc_attr($field['config']['max'] ?? 100); ?>" class="small-text">
            </div>
            <div class="fk-field-setting">
                <label><?php _e('Шаг', 'foto-kniga-calc'); ?></label>
                <input type="number" name="fk_field_config[<?php echo $index; ?>][step]"
                    value="<?php echo esc_attr($field['config']['step'] ?? 1); ?>" class="small-text">
            </div>
            <?php
        }

        if ($type === 'hidden_number') {
            ?>
            <div class="fk-field-setting">
                <label><?php _e('Значение', 'foto-kniga-calc'); ?></label>
                <input type="number" name="fk_field_config[<?php echo $index; ?>][value]"
                    value="<?php echo esc_attr($field['config']['value'] ?? 0); ?>" class="small-text" step="0.01">
            </div>
            <?php
        }

        if ($type === 'select' || $type === 'radio') {
            ?>
            <div class="fk-field-setting fk-pricing-table-setting">
                <label><?php _e('Значения и цены', 'foto-kniga-calc'); ?></label>
                <div class="fk-pricing-table" data-field-index="<?php echo $index; ?>">
                    <?php
                    $options = $field['config']['options'] ?? [];
                    if (!empty($options)):
                        foreach ($options as $opt_index => $option):
                            ?>
                            <div class="fk-pricing-row">
                                <input type="text" name="fk_field_options[<?php echo $index; ?>][<?php echo $opt_index; ?>][value]"
                                    value="<?php echo esc_attr($option['value'] ?? ''); ?>"
                                    placeholder="<?php _e('Значение', 'foto-kniga-calc'); ?>" class="">
                                <input type="number" name="fk_field_options[<?php echo $index; ?>][<?php echo $opt_index; ?>][price]"
                                    value="<?php echo esc_attr($option['price'] ?? 0); ?>"
                                    placeholder="<?php _e('Цена', 'foto-kniga-calc'); ?>" class="small-text">
                                <button type="button" class="fk-remove-pricing-row button-link-delete">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php
                        endforeach;
                    else:
                        ?>
                        <div class="fk-pricing-row">
                            <input type="text" name="fk_field_options[<?php echo $index; ?>][0][value]"
                                placeholder="<?php _e('Значение', 'foto-kniga-calc'); ?>" class="regular-text">
                            <input type="number" name="fk_field_options[<?php echo $index; ?>][0][price]"
                                placeholder="<?php _e('Цена', 'foto-kniga-calc'); ?>" class="small-text" value="0">
                            <button type="button" class="fk-remove-pricing-row button-link-delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
                <button type="button" class="fk-add-pricing-row button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Добавить значение', 'foto-kniga-calc'); ?>
                </button>
            </div>
            <?php
        }

        if ($type === 'checkbox') {
            ?>
            <div class="fk-field-setting">
                <label><?php _e('Цена если отмечено', 'foto-kniga-calc'); ?></label>
                <input type="number" name="fk_field_config[<?php echo $index; ?>][price]"
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
        $formula = get_post_meta($post->ID, '_fk_formula', true);
        $formula = wp_parse_args($formula, [
            'expression' => '',
            'variables' => []
        ]);

        $fields = get_post_meta($post->ID, '_fk_fields', true);
        $fields = is_array($fields) ? $fields : [];
        ?>
        <div class="fk-formula-builder">
            <div class="fk-formula-variables">
                <label><?php _e('Доступные переменные:', 'foto-kniga-calc'); ?></label>
                <div class="fk-variables-list">
                    <?php if (!empty($fields)): ?>
                        <?php foreach ($fields as $field): ?>
                            <span class="fk-variable-tag" data-var="<?php echo esc_attr($field['key'] ?? ''); ?>">
                                {{<?php echo esc_html($field['key'] ?? ''); ?>}}
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="description"><?php _e('Сначала добавьте поля', 'foto-kniga-calc'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="fk-formula-operators">
                <label><?php _e('Операции:', 'foto-kniga-calc'); ?></label>
                <div class="fk-operators-list">
                    <?php
                    $operators = FK_Formula_Engine::get_allowed_operators();
                    foreach ($operators as $op => $desc):
                        ?>
                        <span class="fk-operator-tag" data-op="<?php echo esc_attr($op); ?>">
                            <?php echo esc_html($op); ?>
                        </span>
                        <?php
                    endforeach;
                    ?>
                </div>
            </div>

            <div class="fk-formula-input-wrapper">
                <label for="fk_formula_expression"><?php _e('Формула', 'foto-kniga-calc'); ?></label>
                <textarea name="fk_formula[expression]" id="fk_formula_expression" class="large-text code" rows="4"
                    placeholder="((base_price * pages) + cover_price) * quantity"><?php
                    echo esc_textarea(html_entity_decode($formula['expression'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    ?></textarea>
            </div>

            <div class="fk-formula-test">
                <button type="button" id="fk-test-formula" class="button button-secondary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Проверить расчет', 'foto-kniga-calc'); ?>
                </button>
                <div id="fk-formula-test-result" class="fk-test-result"></div>
            </div>

            <p class="description">
                <?php _e('Используйте ключи полей как переменные. Пример: {{quantity}} * {{price}}', 'foto-kniga-calc'); ?>
            </p>

            <div style="margin-top: 15px; padding: 12px; background: #f0f0f1; border-left: 4px solid #0073aa;">
                <strong><?php _e('Пользовательские переменные:', 'foto-kniga-calc'); ?></strong><br>
                <?php _e('Можно определять свои переменные для сложных расчётов:', 'foto-kniga-calc'); ?><br>
                <code style="display: block; margin: 8px 0; padding: 8px; background: #fff; border: 1px solid #ddd;">
        base_price = if({{kolvo}} &gt;= 100, 120, 150);<br>
        {{kolvo}} * ({{base_price}} + {{razvorot}})
                        </code>
                <?php _e('Каждая переменная с новой строки, основная формула — последней.', 'foto-kniga-calc'); ?>
            </div>
        </div>

        <input type="hidden" name="fk_formula_data" id="fk_formula_data" value='<?php echo esc_attr(json_encode($formula)); ?>'>
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
            !isset($_POST['fk_product_settings_nonce']) ||
            !wp_verify_nonce($_POST['fk_product_settings_nonce'], 'fk_product_settings')
        )
            return;

        // Сохранение настроек
        if (isset($_POST['fk_slug'])) {
            update_post_meta($post_id, '_fk_slug', sanitize_text_field($_POST['fk_slug']));
        }

        // Сохранение отображения
        if (isset($_POST['fk_display'])) {
            $display = [
                'tab_icon' => sanitize_text_field($_POST['fk_display']['tab_icon'] ?? 'dashicons-calculator'),
                'tab_title' => sanitize_text_field($_POST['fk_display']['tab_title'] ?? ''),
                'menu_order' => intval($_POST['fk_display']['menu_order'] ?? 0)
            ];
            update_post_meta($post_id, '_fk_display', $display);
        }

        // Сохранение полей
        if (isset($_POST['fk_field_key']) && is_array($_POST['fk_field_key']) && !empty($_POST['fk_field_key'])) {
            $fields = [];
            $keys = $_POST['fk_field_key'];
            $labels = $_POST['fk_field_label'] ?? [];
            $types = $_POST['fk_field_type'] ?? [];

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
                        'min' => floatval($_POST['fk_field_config'][$index]['min'] ?? 1),
                        'max' => floatval($_POST['fk_field_config'][$index]['max'] ?? 100),
                        'step' => floatval($_POST['fk_field_config'][$index]['step'] ?? 1)
                    ];
                }

                // Конфигурация для hidden_number — только значение
                if ($field['type'] === 'hidden_number') {
                    $field['config'] = [
                        'value' => floatval($_POST['fk_field_config'][$index]['value'] ?? 0)
                    ];
                }

                // Опции для select/radio
                if (in_array($field['type'], ['select', 'radio']) && isset($_POST['fk_field_options'][$index])) {
                    $options = [];
                    foreach ($_POST['fk_field_options'][$index] as $opt) {
                        $options[] = [
                            'value' => sanitize_text_field($opt['value'] ?? ''),
                            'price' => floatval($opt['price'] ?? 0)
                        ];
                    }
                    $field['config']['options'] = $options;
                }

                // Цена для checkbox
                if ($field['type'] === 'checkbox') {
                    $field['config']['price'] = floatval($_POST['fk_field_config'][$index]['price'] ?? 0);
                }

                $fields[] = $field;
            }

            update_post_meta($post_id, '_fk_fields', $fields);
        } else {
            // Если полей нет - удаляем мета
            delete_post_meta($post_id, '_fk_fields');
        }

        // Сохранение формулы
        if (isset($_POST['fk_formula'])) {
            $formula = [
                'expression' => sanitize_textarea_field($_POST['fk_formula']['expression'] ?? ''),
                'variables' => []
            ];
            update_post_meta($post_id, '_fk_formula', $formula);
        }
    }

    /**
     * AJAX тест формулы
     */
    public function ajax_test_formula()
    {
        check_ajax_referer('fk_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Нет прав', 'foto-kniga-calc')]);
        }

        $expression = sanitize_text_field($_POST['expression'] ?? '');
        $variables = $_POST['variables'] ?? [];

        // Получаем поля продукта для корректной обработки
        $post_id = intval($_POST['post_id'] ?? 0);
        $fields = $post_id ? get_post_meta($post_id, '_fk_fields', true) : [];
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
        $validation = FK_Formula_Engine::validate_formula($expression, $field_keys);

        if (!$validation['valid']) {
            wp_send_json_error(['error' => implode(', ', $validation['errors'])]);
        }

        $result = FK_Formula_Engine::calculate($expression, $processed_variables);

        if ($result['success']) {
            wp_send_json_success(['price' => $result['price']]);
        } else {
            wp_send_json_error(['error' => $result['error']]);
        }
    }
}