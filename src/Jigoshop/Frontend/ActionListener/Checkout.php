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
use Jigoshop\Shipping\Method as ShippingMethod;
use WPAL\Wordpress;

/**
 * Class Checkout
 * @package Jigoshop\Frontend\ActionListener;
 * @author Krzysztof Kasowski
 */
class Checkout
{
    public function purchase()
    {
        /** @var CartEntity $cart */
        $cart = $this->cartService->getCurrent();

        try {
            $allowRegistration = $this->options->get('shopping.allow_registration');
            if ($allowRegistration && !$this->wp->isUserLoggedIn()) {
                $this->createUserAccount();
            }

            if (!$this->isAllowedToCheckout($cart)) {
                if ($allowRegistration) {
                    throw new Exception(__('You need either to log in or create account to purchase.', 'jigoshop'));
                }

                throw new Exception(__('You need to log in before purchasing.', 'jigoshop'));
            }

            if ($this->options->get('advanced.pages.terms') > 0 && (!isset($_POST['terms']) || $_POST['terms'] != 'on')) {
                throw new Exception(__('You need to accept terms &amp; conditions!', 'jigoshop'));
            }

            $this->cartService->validate($cart);
            $this->customerService->save($cart->getCustomer());

            if (!Country::isAllowed($cart->getCustomer()->getBillingAddress()->getCountry())) {
                $locations = array_map(function ($location){
                    return Country::getName($location);
                }, $this->options->get('shopping.selling_locations'));
                throw new Exception(sprintf(__('This location is not supported, we sell only to %s.'), join(', ', $locations)));
            }

            /** @var ShippingMethod $shipping */
            $shipping = $cart->getShippingMethod();
            if ($this->isShippingRequired($cart) && (!$shipping || !$shipping->isEnabled())) {
                throw new Exception(__('Shipping is required for this order. Please select shipping method.', 'jigoshop'));
            }

            $payment = $cart->getPaymentMethod();
            $isPaymentRequired = $this->isPaymentRequired($cart);
            $this->wp->doAction('jigoshop\checkout\payment', $payment);
            if ($isPaymentRequired && (!$payment || !$payment->isEnabled())) {
                throw new Exception(__('Payment is required for this order. Please select payment method.', 'jigoshop'));
            }

            $order = $this->orderService->createFromCart($cart);
            /** @var Order $order */
            $order = $this->wp->applyFilters('jigoshop\checkout\order', $order);
            $this->orderService->save($order);
            $this->cartService->remove($cart);

            $url = '';
            if ($isPaymentRequired) {
                $url = $payment->process($order);
            } else {
                $order->setStatus(\Jigoshop\Helper\Order::getStatusAfterCompletePayment($order));
                $this->orderService->save($order);
            }

            // Redirect to thank you page
            if (empty($url)) {
                $url = $this->wp->getPermalink($this->wp->applyFilters('jigoshop\checkout\redirect_page_id', $this->options->getPageId(Pages::THANK_YOU)));
                $url = $this->wp->getHelpers()->addQueryArg(array('order' => $order->getId(), 'key' => $order->getKey()), $url);
            }

            $this->wp->wpRedirect($url);
            exit;
        } catch (Exception $e) {
            $this->messages->addError($e->getMessage());
        }
    }
}