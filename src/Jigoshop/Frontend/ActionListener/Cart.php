<?php

namespace Jigoshop\Frontend\ActionListener;

use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Entity\Cart as CartEntity;
use Jigoshop\Entity\Customer as CustomerEntity;
use Jigoshop\Entity\Order as OrderEntity;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\CouponServiceInterface;
use Jigoshop\Service\CustomerServiceInterface;
use Jigoshop\Service\OrderServiceInterface;
use Jigoshop\Service\ProductServiceInterface;
use Jigoshop\Service\ShippingServiceInterface;
use WPAL\Wordpress;

/**
 * Class Cart
 * @package Jigoshop\Frontend\ActionListener;
 * @author Krzysztof Kasowski
 */
class Cart
{
    /**
     * Cart constructor.
     * @param Wordpress $wp
     * @param Options $options
     * @param Messages $messages
     * @param CartServiceInterface $cartService
     * @param ProductServiceInterface $productService
     * @param CustomerServiceInterface $customerService
     * @param OrderServiceInterface $orderService
     * @param ShippingServiceInterface $shippingService
     * @param CouponServiceInterface $couponService
     */
    public function __construct(
        Wordpress $wp,
        Options $options,
        Messages $messages,
        CartServiceInterface $cartService,
        ProductServiceInterface $productService,
        CustomerServiceInterface $customerService,
        OrderServiceInterface $orderService,
        ShippingServiceInterface $shippingService,
        CouponServiceInterface $couponService
    ) {
        $this->wp = $wp;
        $this->options = $options;
        $this->messages = $messages;
        $this->cartService = $cartService;
        $this->productService = $productService;
        $this->customerService = $customerService;
        $this->shippingService = $shippingService;
        $this->orderService = $orderService;
        $this->couponService = $couponService;
    }

    public function cancelOrder()
    {
        if ($this->wp->getHelpers()->verifyNonce($_REQUEST['nonce'], 'cancel_order')) {
            /** @var OrderEntity $order */
            $order = $this->orderService->find((int)$_REQUEST['id']);

            if ($order->getKey() != $_REQUEST['key']) {
                $this->messages->addError(__('Invalid order key.', 'jigoshop'));

                return;
            }

            if ($order->getStatus() != OrderEntity\Status::PENDING) {
                $this->messages->addError(__('Unable to cancel order.', 'jigoshop'));

                return;
            }

            $order->setStatus(Status::CANCELLED);
            /** @var CartEntity $cart */
            $cart = $this->cartService->createFromOrder($this->cartService->getCartIdForCurrentUser(), $order);
            $this->orderService->save($order);
            $this->cartService->save($cart);
            $this->messages->addNotice(__('The order has been cancelled', 'jigoshop'));
        }
    }

    public function updateShipping()
    {
        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCurrent();
        $this->updateCustomer($customer);
    }

    public function checkout()
    {
        try {
            $cart = $this->cartService->getCurrent();

            // Update quantities
            $this->updateQuantities($cart);
            // Update customer (if needed)
            if ($this->options->get('shipping.calculator')) {
                $customer = $this->customerService->getCurrent();
                $this->updateCustomer($customer);
            }

            if (isset($_POST['jigoshop_order']['shipping_method'])) {
                // Select shipping method
                $method = $this->shippingService->get($_POST['jigoshop_order']['shipping_method']);
                $cart->setShippingMethod($method);
            }

            if ($cart->getShippingMethod() && !$cart->getShippingMethod()->isEnabled()) {
                $cart->removeShippingMethod();
                $this->messages->addWarning(__('Previous shipping method is unavailable. Please select different one.', 'jigoshop'));
            }

            if ($this->options->get('shopping.validate_zip')) {
                $address = $cart->getCustomer()->getShippingAddress();
                if ($address->getPostcode() && !Validation::isPostcode($address->getPostcode(), $address->getCountry())) {
                    throw new Exception(__('Postcode is not valid!', 'jigoshop'));
                }
            }

            do_action('jigoshop\cart\before_checkout', $cart);

            $this->cartService->save($cart);
            $this->messages->preserveMessages();
            $this->wp->redirectTo($this->options->getPageId(Pages::CHECKOUT));
        } catch (Exception $e) {
            $this->messages->addError(sprintf(__('Error occurred while updating cart: %s', 'jigoshop'), $e->getMessage()));
        }
    }

    public function updateCart()
    {
        if (isset($_POST['cart']) && is_array($_POST['cart'])) {
            try {
                /** @var CartEntity $cart */
                $cart = $this->cartService->getCurrent();
                $this->updateQuantities($cart);
                $this->cartService->save($cart);
                $this->messages->addNotice(__('Successfully updated the cart.', 'jigoshop'));
            } catch (Exception $e) {
                $this->messages->addError(sprintf(__('Error occurred while updating cart: %s', 'jigoshop'), $e->getMessage()));
            }
        }
    }

    /**
     * @param CustomerEntity $customer
     */
    private function updateCustomer(CustomerEntity $customer)
    {
        $address = $customer->getShippingAddress();

        if ($customer->hasMatchingAddresses()) {
            $billingAddress = $customer->getBillingAddress();
            $billingAddress->setCountry($_POST['country']);
            $billingAddress->setState($_POST['state']);
            $billingAddress->setPostcode($_POST['postcode']);
        }

        $address->setCountry($_POST['country']);
        $address->setState($_POST['state']);
        $address->setPostcode($_POST['postcode']);
    }

    /**
     * @param CartEntity $cart
     */
    private function updateQuantities(CartEntity $cart)
    {
        if (isset($_POST['cart']) && is_array($_POST['cart'])) {
            foreach ($_POST['cart'] as $item => $quantity) {
                $cart->updateQuantity($item, (int)$quantity);
            }
        }
    }
}