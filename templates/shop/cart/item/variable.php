<?php
use Jigoshop\Helper\Order;
use Jigoshop\Helper\Product;

/**
 * @var $cart \Jigoshop\Entity\Cart Cart object.
 * @var $key string Cart item key.
 * @var $item \Jigoshop\Entity\Order\Item Cart item to display.
 * @var $showWithTax bool Whether to show product price with or without tax.
 * @var $suffix string
 */

/** @var \Jigoshop\Entity\Product\Variable $product */
$product = $item->getProduct();
$variation = $product->getVariation($item->getMeta('variation_id')->getValue());
$url = apply_filters('jigoshop\cart\product_url', get_permalink($product->getId()), $key);
$price = $showWithTax ? $item->getPrice() + $item->getTax() / $item->getQuantity() : $item->getPrice();
?>
<tr data-id="<?php echo $key; ?>" data-product="<?php echo $product->getId(); ?>">
	<td class="product-remove">
		<a href="<?php echo Order::getRemoveLink($key); ?>" class="remove" title="<?php echo __('Remove', 'jigoshop'); ?>">&times;</a>
	</td>
	<td class="product-thumbnail"><a href="<?php echo $url; ?>"><?php echo Product::getFeaturedImage($product, 'shop_tiny'); ?></a></td>
	<td class="product-name">
		<a href="<?php echo $url; ?>"><?php echo apply_filters('jigoshop\template\shop\cart\product_title', $product->getName(), $product, $item); ?></a>
		<?php echo Product::getVariation($variation, $item); ?>
		<?php do_action('jigoshop\template\shop\cart\after_product_title', $product, $item); ?>
	</td>
	<td class="product-price"><?php echo apply_filters('jigoshop\template\shop\cart\product_price', Product::formatPrice($price), $price, $product, $item); ?></td>
	<td class="product-quantity"><input type="number" name="cart[<?php echo $key; ?>]" value="<?php echo $item->getQuantity(); ?>" /></td>
    <td class="product-subtotal"><?php echo apply_filters('jigoshop\template\shop\cart\product_subtotal', Product::formatPrice($item->getQuantity() * $price, $suffix), $item->getQuantity() * $price, $product, $item); ?></td>
</tr>
