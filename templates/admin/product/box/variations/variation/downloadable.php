<?php
use Jigoshop\Admin\Helper\Forms;
use Jigoshop\Entity\Product;

/**
 * @var $product Product The product.
 * @var $parent Product\Variable Parent of current variation..
 * @var $variation Product\Variable\Variation The variation.
 */
?>
<fieldset class="product-downloadable<?php echo $product instanceof Product\Downloadable ? '' : ' not-active'; ?>">
    <?php
    Forms::text(array(
        'name' => 'product[variation][' . $variation->getId() . '][product][url]',
        'label' => __('File path', 'jigoshop'),
        'classes' => array('product-downloadable', $product instanceof Product\Downloadable ? '' : 'not-active'),
        'placeholder' => __('Enter file URL...', 'jigoshop'),
        'size' => 11,
        'value' => $product instanceof Product\Downloadable ? $product->getUrl() : '',
    ));
    ?>
    <?php
    Forms::text(array(
        'name' => 'product[variation][' . $variation->getId() . '][product][limit]',
        'type' => 'number',
        'label' => __('Downloads limit', 'jigoshop'),
        'description' => __('Leave empty for unlimited downloads.', 'jigoshop'),
        'classes' => array('product-downloadable', $product instanceof Product\Downloadable ? '' : 'not-active'),
        'placeholder' => 0,
        'size' => 11,
        'value' => $product instanceof Product\Downloadable ? $product->getLimit() : '',
    ));
    ?>
</fieldset>
