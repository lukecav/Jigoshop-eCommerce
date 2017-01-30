<?php

namespace Jigoshop\Frontend\Page;

use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Core\Types;
use Jigoshop\Entity\Coupon;
use Jigoshop\Entity\Customer;
use Jigoshop\Entity\Order;
use Jigoshop\Entity\Order\Status;
use Jigoshop\Exception;
use Jigoshop\Frontend\NotEnoughStockException;
use Jigoshop\Frontend\Pages;
use Jigoshop\Helper\Country;
use Jigoshop\Helper\Product;
use Jigoshop\Helper\Render;
use Jigoshop\Helper\Scripts;
use Jigoshop\Helper\Styles;
use Jigoshop\Helper\Tax;
use Jigoshop\Helper\Validation;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\CouponServiceInterface;
use Jigoshop\Service\CustomerServiceInterface;
use Jigoshop\Service\OrderServiceInterface;
use Jigoshop\Service\ProductServiceInterface;
use Jigoshop\Service\ShippingServiceInterface;
use Jigoshop\Shipping\Method;
use Jigoshop\Shipping\MultipleMethod;
use Jigoshop\Shipping\Rate;
use WPAL\Wordpress;

class Cart implements PageInterface
{
	/** @var \WPAL\Wordpress */
	private $wp;
	/** @var \Jigoshop\Core\Options */
	private $options;
	/** @var Messages */
	private $messages;
	/** @var CartServiceInterface */
	private $cartService;
	/** @var ProductServiceInterface */
	private $productService;
	/** @var CustomerServiceInterface */
	private $customerService;
	/** @var ShippingServiceInterface */
	private $shippingService;
	/** @var OrderServiceInterface */
	private $orderService;
	/** @var CouponServiceInterface */
	private $couponService;

	public function __construct(Wordpress $wp, Options $options, Messages $messages, CartServiceInterface $cartService, ProductServiceInterface $productService,
		CustomerServiceInterface $customerService, OrderServiceInterface $orderService, ShippingServiceInterface $shippingService, CouponServiceInterface $couponService)
	{
		$this->wp = $wp;
		$this->options = $options;
		$this->messages = $messages;
		$this->cartService = $cartService;
		$this->productService = $productService;
		$this->customerService = $customerService;
		$this->shippingService = $shippingService;
		$this->orderService = $orderService;
		$this->couponService = $couponService;

		Styles::add('jigoshop.shop.cart', \JigoshopInit::getUrl().'/assets/css/shop/cart.css', array(
			'jigoshop.shop',
		));
		Styles::add('jigoshop.vendors.select2', \JigoshopInit::getUrl().'/assets/css/vendors/select2.css', array(
			'jigoshop.shop',
		));

		Scripts::add('jigoshop.vendors.select2', \JigoshopInit::getUrl().'/assets/js/vendors/select2.js', array('jquery'));
		Scripts::add('jigoshop.vendors.bs_tab_trans_tooltip_collapse', \JigoshopInit::getUrl().'/assets/js/vendors/bs_tab_trans_tooltip_collapse.js', array('jquery'));
		Scripts::add('jigoshop.shop.cart', \JigoshopInit::getUrl().'/assets/js/shop/cart.js', array(
			'jquery',
			'jquery-blockui',
			'jigoshop.shop',
			'jigoshop.helpers',
			'jigoshop.vendors.select2',
			'jigoshop.vendors.bs_tab_trans_tooltip_collapse',
		));


		Scripts::localize('jigoshop.shop.cart', 'jigoshop_cart', array(
			'assets' => \JigoshopInit::getUrl().'/assets',
			'i18n' => array(
				'loading' => __('Loading...', 'jigoshop'),
			),
		));

		$wp->addAction('wp_ajax_jigoshop_cart_update_item', array($this, 'ajaxUpdateItem'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_update_item', array($this, 'ajaxUpdateItem'));
		$wp->addAction('wp_ajax_jigoshop_cart_select_shipping', array($this, 'ajaxSelectShipping'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_select_shipping', array($this, 'ajaxSelectShipping'));
		$wp->addAction('wp_ajax_jigoshop_cart_update_discounts', array($this, 'ajaxUpdateDiscounts'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_update_discounts', array($this, 'ajaxUpdateDiscounts'));
		$wp->addAction('wp_ajax_jigoshop_cart_change_country', array($this, 'ajaxChangeCountry'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_change_country', array($this, 'ajaxChangeCountry'));
		$wp->addAction('wp_ajax_jigoshop_cart_change_state', array($this, 'ajaxChangeState'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_change_state', array($this, 'ajaxChangeState'));
		$wp->addAction('wp_ajax_jigoshop_cart_change_postcode', array($this, 'ajaxChangePostcode'));
		$wp->addAction('wp_ajax_nopriv_jigoshop_cart_change_postcode', array($this, 'ajaxChangePostcode'));
	}

	/**
	 * Ajax action for changing country.
	 */
	public function ajaxChangeCountry()
	{
		$customer = $this->customerService->getCurrent();

		if (!Country::isAllowed($_POST['value'])) {
			$locations = array_map(function ($location){
				return Country::getName($location);
			}, $this->options->get('shopping.selling_locations'));
			echo json_encode(array(
				'success' => false,
				'error' => sprintf(__('This location is not supported, we sell only to %s.'), join(', ', $locations)),
			));
			exit;
		}

		if ($customer->hasMatchingAddresses()) {
			$customer->getBillingAddress()->setCountry($_POST['value']);
		}
		$customer->getShippingAddress()->setCountry($_POST['value']);

		$this->customerService->save($customer);
		$cart = $this->cartService->getCurrent();
		$cart->setCustomer($customer);
		$this->cartService->save($cart);

		$response = $this->getAjaxLocationResponse($customer, $cart);

		echo json_encode($response);
		exit;
	}

	/**
	 * Abstraction for location update response.
	 * Prepares and returns array of updated data for location change requests.
	 *
	 * @param Customer              $customer The customer (for location).
	 * @param \Jigoshop\Entity\Cart $cart     Current cart.
	 *
	 * @return array
	 */
	private function getAjaxLocationResponse(Customer $customer, \Jigoshop\Entity\Cart $cart)
	{
		$response = $this->getAjaxCartResponse($cart);
		$address = $customer->getShippingAddress();
		// Add some additional fields
		$response['has_states'] = Country::hasStates($address->getCountry());
		$response['states'] = Country::getStates($address->getCountry());
		$response['html']['estimation'] = $address->getLocation();

		return $response;
	}

	/**
	 * Abstraction for cart update response.
	 * Prepares and returns response array for cart update requests.
	 *
	 * @param \Jigoshop\Entity\Cart $cart Current cart.
	 *
	 * @return array
	 */
	private function getAjaxCartResponse(\Jigoshop\Entity\Cart $cart)
	{
		$tax = array();
		foreach ($cart->getCombinedTax() as $class => $value) {
			$tax[$class] = array(
				'label' => Tax::getLabel($class, $cart),
				'value' => Product::formatPrice($value),
			);
		}

		$shipping = array();
		$shippingHtml = array();
		foreach ($this->shippingService->getAvailable() as $method) {
			/** @var $method Method */
			if ($method instanceof MultipleMethod) {
				/** @var $method MultipleMethod */
				foreach ($method->getRates($cart) as $rate) {
					/** @var $rate Rate */
					$shipping[$method->getId().'-'.$rate->getId()] = $method->isEnabled() ? $rate->calculate($cart) : -1;
					if ($method->isEnabled()) {
						$shippingHtml[$method->getId().'-'.$rate->getId()] = array(
							'price' => Product::formatPrice($rate->calculate($cart)),
							'html' => Render::get('shop/cart/shipping/rate', array('method' => $method, 'rate' => $rate, 'cart' => $cart)),
						);
					}
				}
			} else {
				$shipping[$method->getId()] = $method->isEnabled() ? $method->calculate($cart) : -1;
				if ($method->isEnabled()) {
					$shippingHtml[$method->getId()] = array(
						'price' => Product::formatPrice($cart->getShippingPrice()),
						'html' => Render::get('shop/cart/shipping/method', array('method' => $method, 'cart' => $cart)),
					);
				}
			}
		}

		$shippingMethod = $cart->getShippingMethod();
        if($shippingMethod) {
            try {
                $cart->setShippingMethod($shippingMethod);
            } catch(Exception $e) {
                $cart->removeShippingMethod();
            }
        }

        $productSubtotal = $this->options->get('tax.price_tax') == 'with_tax' ? $cart->getProductSubtotal() + $cart->getTotalTax() : $cart->getProductSubtotal();
		$coupons = join(',', array_map(function ($coupon){
			/** @var $coupon Coupon */
			return $coupon->getCode();
		}, $cart->getCoupons()));
		$response = array(
			'success' => true,
			'shipping' => $shipping,
			'subtotal' => $cart->getSubtotal(),
			'product_subtotal' => $productSubtotal,
			'discount' => $cart->getDiscount(),
			'coupons' => $coupons,
			'tax' => $cart->getCombinedTax(),
			'total' => $cart->getTotal(),
			'html' => array(
				'shipping' => $shippingHtml,
				'discount' => Product::formatPrice($cart->getDiscount()),
				'subtotal' => Product::formatPrice($cart->getSubtotal()),
				'product_subtotal' => Product::formatPrice($productSubtotal),
				'tax' => $tax,
				'total' => Product::formatPrice($cart->getTotal()),
			),
		);

		return $response;
	}

	/**
	 * Ajax action for changing state.
	 */
	public function ajaxChangeState()
	{
		$customer = $this->customerService->getCurrent();
		if ($customer->hasMatchingAddresses()) {
			$customer->getBillingAddress()->setState($_POST['value']);
		}
		$customer->getShippingAddress()->setState($_POST['value']);
		$this->customerService->save($customer);
		$cart = $this->cartService->getCurrent();
		$cart->setCustomer($customer);
		$this->cartService->save($cart);

		$response = $this->getAjaxLocationResponse($customer, $cart);

		echo json_encode($response);
		exit;
	}

	/**
	 * Ajax action for changing postcode.
	 */
	public function ajaxChangePostcode()
	{
		$customer = $this->customerService->getCurrent();

		if ($this->options->get('shopping.validate_zip') && !Validation::isPostcode($_POST['value'], $customer->getShippingAddress()->getCountry())) {
			echo json_encode(array(
				'success' => false,
				'error' => __('Postcode is not valid!', 'jigoshop'),
			));
			exit;
		}

		if ($customer->hasMatchingAddresses()) {
			$customer->getBillingAddress()->setPostcode($_POST['value']);
		}

		$customer->getShippingAddress()->setPostcode($_POST['value']);
		$this->customerService->save($customer);
		$cart = $this->cartService->getCurrent();
		$cart->setCustomer($customer);
		$this->cartService->save($cart);

		$response = $this->getAjaxLocationResponse($customer, $cart);

		echo json_encode($response);
		exit;
	}

	/**
	 * Processes change of selected shipping method and returns updated cart details.
	 */
	public function ajaxSelectShipping()
	{
		try {
			$method = $this->shippingService->get($_POST['method']);
			$cart = $this->cartService->getCurrent();

			if ($method instanceof MultipleMethod) {
				if (!isset($_POST['rate'])) {
					throw new Exception(__('Method rate is required.', 'jigoshop'));
				}

				$method->setShippingRate($_POST['rate']);
			}

			$cart->setShippingMethod($method);
			$this->cartService->save($cart);

			$response = $this->getAjaxCartResponse($cart);
		} catch (Exception $e) {
			$response = array(
				'success' => false,
				'error' => $e->getMessage(),
			);
		}

		echo json_encode($response);
		exit;
	}

	/**
	 * Processes updates of coupons and returns updated cart details.
	 */
	public function ajaxUpdateDiscounts()
	{
		try {
			$cart = $this->cartService->getCurrent();

			if (isset($_POST['coupons'])) {
				$errors = array();
				$codes = array_filter(explode(',', $_POST['coupons']));
				$cart->removeAllCouponsExcept($codes);
				$coupons = $this->couponService->getByCodes($codes);

				foreach ($coupons as $coupon) {
					try {
						$cart->addCoupon($coupon);
					} catch (Exception $e) {
						$errors[] = $e->getMessage();
					}
				}

				if (!empty($errors)) {
					throw new Exception(join('<br/>', $errors));
				}
			}

			// TODO: Add support for other discounts

			$this->cartService->save($cart);

			$response = $this->getAjaxCartResponse($cart);
		} catch (Exception $e) {
			$response = array(
				'success' => false,
				'error' => $e->getMessage(),
			);
		}

		echo json_encode($response);
		exit;
	}

	/**
	 * Processes change of item quantity and returns updated item value and cart details.
	 */
	public function ajaxUpdateItem()
	{
		$cart = $this->cartService->getCurrent();

		try {
			$cart->updateQuantity($_POST['item'], (int)$_POST['quantity']);
			$this->cartService->save($cart);
			$item = $cart->getItem($_POST['item']);

			if ($item === null) {
				throw new Exception(__('Item not found.', 'jigoshop'));
			}

			// TODO: Support for "Prices includes tax"
			$price = $this->options->get('tax.price_tax') == 'with_tax' ? $item->getPrice() + $item->getTax() / $item->getQuantity() : $item->getPrice();
            $response = $this->getAjaxCartResponse($cart);

			// Add some additional fields
			$response['item_price'] = $price;
			$response['item_subtotal'] = $price * $item->getQuantity();
			$response['html']['item_price'] = Product::formatPrice($price);
			$response['html']['item_subtotal'] = Product::formatPrice($price * $item->getQuantity());
		} catch (NotEnoughStockException $e) {
			$response = array(
				'success' => false,
				'error' => sprintf(__('Sorry, we do not have enough units in stock. We have got only %s in stock', 'jigoshop'), $e->getStock())
			);
		} catch (Exception $e) {
			if ($cart->isEmpty()) {
				$response = array(
					'success' => true,
					'empty_cart' => true,
					'html' => Render::get('shop/cart/empty', array('shopUrl' => $this->wp->getPermalink($this->options->getPageId(Pages::SHOP)))),
				);
			} else {
				$response = $this->getAjaxCartResponse($cart);
				$response['remove_item'] = true;
			}
		}

		echo json_encode($response);
		exit;
	}

	public function action()
	{
		if (isset($_REQUEST['action'])) {
			switch ($_REQUEST['action']) {
				case 'cancel_order':

					break;
				case 'update-shipping':
					break;
				case 'checkout':

					break;
				case 'update-cart':

			}
		}

		if (isset($_GET['action']) && isset($_GET['item']) && $_GET['action'] === 'remove-item' && is_numeric($_GET['item'])) {
			$cart = $this->cartService->getCurrent();
			$cart->removeItem((int)$_GET['item']);
			$this->cartService->save($cart);
			$this->messages->addNotice(__('Successfully removed item from cart.', 'jigoshop'), false);
		}
	}





	public function render()
	{
		$cart = $this->cartService->getCurrent();
		$content = $this->wp->getPostField('post_content', $this->options->getPageId(Pages::CART));
		$content = do_shortcode($content);

		$termsUrl = '';
		$termsPage = $this->options->get('advanced.pages.terms');
		if ($termsPage > 0) {
			$termsUrl = $this->wp->getPermalink($termsPage);
		}

		return Render::get('shop/cart', array(
			'content' => $content,
			'cart' => $cart,
			'messages' => $this->messages,
			'productService' => $this->productService,
			'customer' => $this->customerService->getCurrent(),
			'shippingMethods' => $this->shippingService->getEnabled(),
			'shopUrl' => $this->wp->getPermalink($this->options->getPageId(Pages::SHOP)),
			'showWithTax' => $this->options->get('tax.price_tax') == 'with_tax',
			'showShippingCalculator' => $this->options->get('shipping.calculator'),
			'termsUrl' => $termsUrl,
		));
	}
}
