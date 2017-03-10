<?php
/**
 * @var $currentTab string Currently selected tab.
 * @var $product \Jigoshop\Entity\Product Currently displayed product.
 */
?>
<div role="tabpanel" id="tab-description" class="tab-pane<?php $currentTab == 'description' and print ' active'; ?>">
	<?= do_shortcode($product->getDescription()); ?>
</div>
