<?php
if (!defined('ABSPATH')) exit;

if (!isset($config)) {
    $config = AG_Product_Manager::get_product_config($product->ID);
}

$fields = $config['fields'] ?? [];
$formula = $config['formula'] ?? [];
?>

<div class="ag-product-calculator" data-product-slug="<?php echo esc_attr($config['slug']); ?>">
    
    <!-- Форма с полями -->
    <div class="ag-calculator-form">
        <?php if (!empty($fields)) : ?>
            <?php foreach ($fields as $index => $field) : ?>
                <?php echo AG_Shortcodes::render_field($field, $index); ?>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="ag-no-fields">Поля не настроены</p>
        <?php endif; ?>
    </div>
    
    <!-- Блок с итоговой ценой -->
    <div class="ag-calculator-total">
        <span class="ag-total-label">Итого:</span>
        <span class="ag-total-price" data-price="0">0 ₽</span>

        <div class="ag-loading-spinner" style="display: none;">
            <span class="ag-spinner-icon"></span>
            <span class="sr-only">Загрузка...</span>
        </div>

    </div>
    
        <div class="ag-error-message" style="display: none;"></div>

    <!-- Кнопка заказа -->
    <div class="ag-order-button-wrapper">
        <button type="button" class="ag-order-btn" disabled>
        Заказать
        </button>
    </div>
    
</div>

