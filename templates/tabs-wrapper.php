<?php
if (!defined('ABSPATH')) exit;
?>

<div class="fk-calculator fk-calculator-tabs" data-mode="tabs">
    
    <!-- Навигация вкладок -->
    <ul class="nav nav-tabs fk-tabs-nav" role="tablist">
        <?php $first = true; ?>
        <?php foreach ($products as $product) : 
            $config = FK_Product_Manager::get_product_config($product->ID);
            $display = $config['display'];
            $slug = $config['slug'];
            $tab_id = 'fk-tab-' . sanitize_title($slug);
        ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $first ? 'active' : ''; ?>"
                   id="<?php echo esc_attr($tab_id); ?>-btn"
                   data-toggle="tab"
                   href="#<?php echo esc_attr($tab_id); ?>"
                   role="tab"
                   data-product-slug="<?php echo esc_attr($slug); ?>">
                    <?php echo esc_html($display['tab_title'] ?: get_the_title($product)); ?>
                </a>
            </li>
        <?php 
            $first = false;
        endforeach; ?>
    </ul>
    
    <!-- Содержимое вкладок -->
    <div class="tab-content fk-tabs-content">
        <?php $first = true; ?>
        <?php foreach ($products as $product) : 
            $config = FK_Product_Manager::get_product_config($product->ID);
            $slug = $config['slug'];
            $tab_id = 'fk-tab-' . sanitize_title($slug);
        ?>
            <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                 id="<?php echo esc_attr($tab_id); ?>" 
                 role="tabpanel"
                 data-product-slug="<?php echo esc_attr($slug); ?>">
                
                <?php include FK_CALC_PLUGIN_DIR . 'templates/single-product.php'; ?>
                
            </div>
        <?php 
            $first = false;
        endforeach; ?>
    </div>
    
</div>