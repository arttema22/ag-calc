<?php
if (!defined('ABSPATH')) exit;

if (!isset($config)) {
    $config = FK_Product_Manager::get_product_config($product->ID);
}

$fields = $config['fields'] ?? [];
$formula = $config['formula'] ?? [];
?>

<div class="fk-product-calculator" data-product-slug="<?php echo esc_attr($config['slug']); ?>">
    
    <!-- Форма с полями -->
    <div class="fk-calculator-form">
        <?php if (!empty($fields)) : ?>
            <?php foreach ($fields as $index => $field) : ?>
                <?php echo FK_Shortcodes::render_field($field, $index); ?>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="fk-no-fields">Поля не настроены</p>
        <?php endif; ?>
    </div>
    
    <!-- Блок с итоговой ценой -->
    <div class="fk-calculator-total">
        <span class="fk-total-label">Итого:</span>
        <span class="fk-total-price" data-price="0">0 ₽</span>

        <div class="fk-loading-spinner" style="display: none;">
    <span class="spinner-border spinner-border-sm" role="status"></span>
    <span class="sr-only">Загрузка...</span>
</div>

    </div>
    
        <div class="fk-error-message" style="display: none;"></div>

    <!-- Кнопка заказа -->
    <div class="fk-order-button-wrapper">
        <button type="button" class="btn btn-primary fk-order-btn" disabled>
        Заказать
        </button>
    </div>
    
</div>

