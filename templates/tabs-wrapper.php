<?php
if (!defined('ABSPATH')) exit;
?>

<div class="ag-calculator ag-calculator-tabs" data-mode="tabs">

    <!-- Навигация вкладок -->
    <ul class="ag-tabs-nav" role="tablist">
        <?php $first = true; ?>
        <?php foreach ($products as $product) :
            $config = AG_Product_Manager::get_product_config($product->ID);
            $display = $config['display'];
            $slug = $config['slug'];
            $tab_id = 'ag-tab-' . sanitize_title($slug);
        ?>
            <li class="ag-tab-item">
                <a class="ag-tab-link <?php echo $first ? 'active' : ''; ?>"
                   id="<?php echo esc_attr($tab_id); ?>-btn"
                   data-tab-target="<?php echo esc_attr($tab_id); ?>"
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
    <div class="ag-tabs-content">
        <?php $first = true; ?>
        <?php foreach ($products as $product) :
            $config = AG_Product_Manager::get_product_config($product->ID);
            $slug = $config['slug'];
            $tab_id = 'ag-tab-' . sanitize_title($slug);
        ?>
            <div class="ag-tab-pane <?php echo $first ? 'active' : ''; ?>"
                 id="<?php echo esc_attr($tab_id); ?>"
                 role="tabpanel"
                 data-product-slug="<?php echo esc_attr($slug); ?>">

                <?php include AG_CALC_PLUGIN_DIR . 'templates/single-product.php'; ?>

            </div>
        <?php
            $first = false;
        endforeach; ?>
    </div>

</div>
