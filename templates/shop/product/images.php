<?php
use Jigoshop\Helper\Product;

/**
 * @var $product \Jigoshop\Entity\Product Product object.
 * @var $featured string Featured image.
 * @var $featuredUrl string URL to featured image.
 * @var $thumbnails \Jigoshop\Entity\Product\Attachment\Image[] List of product thumbnails.
 * @var $imageClasses array List of classes to attach to image.
 */
?>
<div class="images">
	<?php if (Product::isOnSale($product)): ?>
		<span class="on-sale"><?= apply_filters('jigoshop\template\product\sale_text', __('Sale!', 'jigoshop'), $product) ?></span>
	<?php endif; ?>
	<?php do_action('jigoshop\template\product\before_featured_image', $product); ?>
	<a href="<?= $featuredUrl; ?>" class="<?= join(' ', $imageClasses); ?>" data-lightbox="product-gallery"><?= $featured; ?></a>
	<?php do_action('jigoshop\template\product\before_thumbnails', $product); ?>
	<div class="thumbnails">
		<?php foreach($thumbnails as $thumbnail): ?>
			<a href="<?= $thumbnail->getUrl(); ?>" data-lightbox="product-gallery" data-title="<?= $thumbnail->getTitle(); ?>" title="<?= $thumbnail->getTitle(); ?>" class="zoom">
				<?= apply_filters('jigoshop\template\product\thumbnail', $thumbnail->getImage(), $thumbnail, $product); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php do_action('jigoshop\template\product\after_thumbnails', $product); ?>
</div>
