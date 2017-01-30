<?php

namespace Jigoshop\Frontend\ActionListener;

use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Entity\Cart as CartEntity;
use Jigoshop\Entity\Order\Item as ItemEntity;
use Jigoshop\Entity\Product as ProductEntity;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\ProductServiceInterface;
use WPAL\Wordpress;

/**
 * Class Product
 * @package Jigoshop\Frontend\ActionListener;
 * @author Krzysztof Kasowski
 */
class Product
{
    /** @var \WPAL\Wordpress */
    private $wp;
    /** @var \Jigoshop\Core\Options */
    private $options;
    /** @var ProductServiceInterface */
    private $productService;
    /** @var CartServiceInterface */
    private $cartService;
    /** @var Messages */
    private $messages;

    /**
     * Product constructor.
     * @param Wordpress $wp
     * @param Options $options
     * @param ProductServiceInterface $productService
     * @param CartServiceInterface $cartService
     * @param Messages $messages
     */
    public function __construct(Wordpress $wp, Options $options, ProductServiceInterface $productService, CartServiceInterface $cartService, Messages $messages)
    {
        $this->wp = $wp;
        $this->options = $options;
        $this->productService = $productService;
        $this->cartService = $cartService;
        $this->messages = $messages;
    }

    /**
     *
     */
    public function addToCart()
    {
        $post = $this->wp->getGlobalPost();
        /** @var ProductEntity $product */
        $product = $this->productService->findForPost($post);

        try {
            /** @var ItemEntity $item */
            $item = $this->wp->applyFilters('jigoshop\cart\add', null, $product);

            if ($item === null && !$item instanceof Item) {
                throw new Exception(__('Unable to add product to the cart.', 'jigoshop'));
            }

            $item->setKey($this->productService->generateItemKey($item));

            if (isset($_POST['quantity'])) {
                $item->setQuantity($_POST['quantity']);
            }
            /** @var CartEntity $cart */
            $cart = $this->cartService->get($this->cartService->getCartIdForCurrentUser());
            $cart->addItem($item);
            $this->cartService->save($cart);

            $url = false;
            $button = '';
            switch ($this->options->get('shopping.redirect_add_to_cart')) {
                case 'cart':
                    $url = $this->wp->getPermalink($this->options->getPageId(Pages::CART));
                    break;
                case 'checkout':
                    $url = $this->wp->getPermalink($this->options->getPageId(Pages::CHECKOUT));
                    break;
                /** @noinspection PhpMissingBreakStatementInspection */
                case 'product_list':
                    $url = $this->wp->getPermalink($this->options->getPageId(Pages::SHOP));
                case 'product':
                case 'same_page':
                default:
                    $button = sprintf('<a href="%s" class="btn btn-warning pull-right">%s</a>', $this->wp->getPermalink($this->options->getPageId(Pages::CART)), __('View cart', 'jigoshop'));
            }

            $this->messages->addNotice(sprintf(__('%s successfully added to your cart. %s', 'jigoshop'), $product->getName(), $button));
            if ($url !== false) {
                $this->messages->preserveMessages();
                $this->wp->wpRedirect($url);
            }
        } catch (NotEnoughStockException $e) {
            if ($e->getStock() == 0) {
                $message = sprintf(__('Sorry, we do not have "%s" in stock.', 'jigoshop'), $product->getName());
            } else if ($this->options->get('products.show_stock')) {
                $message = sprintf(__('Sorry, we do not have enough "%s" in stock to fulfill your order. We only have %d available at this time. Please edit your cart and try again. We apologize for any inconvenience caused.', 'jigoshop'), $product->getName(), $e->getStock());
            } else {
                $message = sprintf(__('Sorry, we do not have enough "%s" in stock to fulfill your order. Please edit your cart and try again. We apologize for any inconvenience caused.', 'jigoshop'), $product->getName());
            }

            $this->messages->addError($message);
        } catch (Exception $e) {
            $this->messages->addError(sprintf(__('A problem ocurred when adding to cart: %s', 'jigoshop'), $e->getMessage()));
        }
    }
}