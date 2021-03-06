<?php

namespace Jigoshop\Factory;

use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Core\Types;
use Jigoshop\Entity\Coupon;
use Jigoshop\Entity\Customer as CustomerEntity;
use Jigoshop\Entity\Order as Entity;
use Jigoshop\Entity\OrderInterface;
use Jigoshop\Entity\Product as ProductEntity;
use Jigoshop\Exception;
use Jigoshop\Helper\Product as ProductHelper;
use Jigoshop\Shipping\Method as ShippingMethod;
use Jigoshop\Payment\Method as PaymentMethod;
use Jigoshop\Service\CouponServiceInterface;
use Jigoshop\Service\CustomerServiceInterface;
use Jigoshop\Service\PaymentServiceInterface;
use Jigoshop\Service\ProductServiceInterface;
use Jigoshop\Service\ShippingServiceInterface;
use Jigoshop\Shipping\MultipleMethod;
use WPAL\Wordpress;

class Order implements EntityFactoryInterface
{
    /** @var \WPAL\Wordpress */
    private $wp;
    /** @var Options */
    private $options;
    /** @var Messages */
    private $messages;
    /** @var CustomerServiceInterface */
    private $customerService;
    /** @var ProductServiceInterface */
    private $productService;
    /** @var ShippingServiceInterface */
    private $shippingService;
    /** @var PaymentServiceInterface */
    private $paymentService;
    /** @var CouponServiceInterface */
    private $couponService;

    public function __construct(Wordpress $wp, Options $options, Messages $messages)
    {
        $this->wp = $wp;
        $this->options = $options;
        $this->messages = $messages;
    }

    public function init(
        CustomerServiceInterface $customerService,
        ProductServiceInterface $productService,
        ShippingServiceInterface $shippingService,
        PaymentServiceInterface $paymentService,
        CouponServiceInterface $couponService
    ) {
        $this->customerService = $customerService;
        $this->productService = $productService;
        $this->shippingService = $shippingService;
        $this->paymentService = $paymentService;
        $this->couponService = $couponService;
    }

    /**
     * Creates new order properly based on POST variable data.
     *
     * @param $id int Post ID to create object for.
     *
     * @return Entity
     */
    public function create($id)
    {
        $post = $this->wp->getPost($id);

        // Support for our own post types and "Publish" button.
        if (isset($_POST['original_post_status'])) {
            $post->post_status = $_POST['original_post_status'];
        }

        $order = $this->fetch($post);
        $data = array(
            'updated_at' => time(),
        );

        if (isset($_POST['jigoshop_order']['status'])) {
            $order->setStatus($_POST['jigoshop_order']['status']);
        }
        if (isset($_POST['post_excerpt'])) {
            $data['customer_note'] = trim($_POST['post_excerpt']);
        }

        if (isset($_POST['jigoshop_order'])) {
            $data = array_merge($data, $_POST['jigoshop_order']);
        }

        $data['items'] = $this->getItems($id);

        if (isset($_POST['order']['shipping'])) {
            $data['shipping'] = array(
                'method' => null,
                'rate' => null,
                'price' => -1,
            );

            $method = $this->shippingService->get($_POST['order']['shipping']);
            if ($method instanceof MultipleMethod && isset($_POST['order']['shipping_rate'])) {
                $method->setShippingRate($_POST['order']['shipping_rate']);
                $data['shipping']['rate'] = $method->getShippingRate();
            }

            $data['shipping']['method'] = $method;
        }

        return $order = $this->wp->applyFilters('jigoshop\factory\order\create', $this->fill($order, $data));
    }

    /**
     * Fetches order from database.
     *
     * @param $post \WP_Post Post to fetch order for.
     *
     * @return \Jigoshop\Entity\Order
     */
    public function fetch($post)
    {
        if($post->post_type != Types::ORDER) {
            return null;
        }

        $order = new Entity($this->options->get('tax.classes'));
        /** @var Entity $order */
        $order = $this->wp->applyFilters('jigoshop\factory\order\fetch\before', $order);
        $state = array();

        if ($post) {
            $state = array_map(function ($item) {
                return $item[0];
            }, $this->wp->getPostMeta($post->ID));

            $order->setId($post->ID);
            if (isset($state['customer'])) {
                // Customer must be unserialized twice "thanks" to WordPress second serialization.
                /** @var CustomerEntity */
                $state['customer'] = unserialize(unserialize($state['customer']));
                if($state['customer'] instanceof CustomerEntity &&
                    !($state['customer'] instanceof CustomerEntity\Guest) &&
                    $state['customer_id'] > 0) {
                    $customer = $this->customerService->find($state['customer_id']);
                    $customer->setBillingAddress($state['customer']->getBillingAddress());
                    $customer->setShippingAddress($state['customer']->getShippingAddress());
                    $state['customer'] = $customer;
                }
            }
            $state['customer_note'] = $post->post_excerpt;
            $state['status'] = $post->post_status;
            $state['created_at'] = strtotime($post->post_date);
            $state['items'] = $this->getItems($post->ID);
            if (isset($state['shipping'])) {
                $shipping = unserialize($state['shipping']);
                if (!empty($shipping['method'])) {
                    $state['shipping'] = array(
                        'method' => $this->shippingService->findForState($shipping['method']),
                        'price' => $shipping['price'],
                        'rate' => isset($shipping['rate']) ? $shipping['rate'] : null,
                    );
                }
            }
            if (isset($state['payment'])) {
                $state['payment'] = $this->paymentService->get($state['payment']);
            }

            $order = $this->fill($order, $state);
        }

        return $this->wp->applyFilters('jigoshop\find\order', $order, $state);
    }

    /**
     * @param $id int Order ID.
     *
     * @return array List of items assigned to the order.
     */
    private function getItems($id)
    {
        $wpdb = $this->wp->getWPDB();
        $query = $wpdb->prepare("
			SELECT * FROM {$wpdb->prefix}jigoshop_order_item joi
			LEFT JOIN {$wpdb->prefix}jigoshop_order_item_meta joim ON joim.item_id = joi.id
			WHERE joi.order_id = %d
			ORDER BY joi.id",
            array($id));
        $results = $wpdb->get_results($query, ARRAY_A);
        $items = array();

        for ($i = 0, $endI = count($results); $i < $endI;) {
            $id = $results[$i]['id'];
            $item = new Entity\Item();
            $item->setId($results[$i]['item_id']);
            $item->setType($results[$i]['product_type']);
            $item->setName($results[$i]['title']);
            $item->setTaxClasses($results[$i]['tax_classes']);
            $item->setQuantity($results[$i]['quantity']);
            $item->setPrice($results[$i]['price']);
            $item->setTax($results[$i]['tax']);

            $product = $this->productService->find($results[$i]['product_id']);
            $product = $this->wp->applyFilters('jigoshop\factory\order\find_product', $product, $item);
            if($product == null || !$product instanceof ProductEntity) {
                $product = new ProductEntity\Simple();
                $product->setId($results[$i]['product_id']);
            }

            while ($i < $endI && $results[$i]['id'] == $id) {
//				Securing against empty meta's, but still no piece of code does not add the meta.
                if ($results[$i]['meta_key']) {
                    $meta = new Entity\Item\Meta();
                    $meta->setKey($results[$i]['meta_key']);
                    $meta->setValue($results[$i]['meta_value']);
                    $item->addMeta($meta);
                }
                $i++;
            }
            $item->setProduct($product);
            $item->setKey($this->productService->generateItemKey($item));
            $items[] = $item;
        }

        return $items;
    }

    public function fill(OrderInterface $order, array $data)
    {
        if (!empty($data['customer']) && is_numeric($data['customer'])) {
            $data['customer'] = $this->customerService->find($data['customer']);
        }

        if (isset($data['customer'])) {

            if (!empty($data['customer'])) {
                $data['customer'] = $this->wp->getHelpers()->maybeUnserialize($data['customer']);
            } else {
                $data['customer'] = new CustomerEntity\Guest();
            }

            if (isset($data['billing_address'])) {
                $data['billing_address'] = array_merge(
                    array_flip(array_keys(ProductHelper::getBasicBillingFields())),
                    $data['billing_address']
                );
                /** @var CustomerEntity $customer */
                $customer = $data['customer'];
                $customer->setBillingAddress($this->createAddress($data['billing_address']));
            }
            if (isset($data['shipping_address'])) {
                $data['shipping_address'] = array_merge(
                    array_flip(array_keys(ProductHelper::getBasicShippingFields())),
                    $data['shipping_address']
                );

                /** @var CustomerEntity $customer */
                $customer = $data['customer'];
                $customer->setShippingAddress($this->createAddress($data['shipping_address']));
            }

            $order->setCustomer($data['customer']);
            unset($data['customer']);
        }
        /** @var OrderInterface $order */
        $order = $this->wp->applyFilters('jigoshop\factory\order\fetch\after_customer', $order);

        if (isset($data['items'])) {
            $order->removeItems();
        }

        $order->restoreState($data);

        return $this->wp->applyFilters('jigoshop\factory\order\fill', $order);
    }

    private function createAddress($data)
    {
        if (!empty($data['company'])) {
            $address = new CustomerEntity\CompanyAddress();
            $address->setCompany($data['company']);
            if (isset($data['euvatno'])) {
                $address->setVatNumber($data['euvatno']);
            }
        } else {
            $address = new CustomerEntity\Address();
        }

        $address->setFirstName($data['first_name']);
        $address->setLastName($data['last_name']);
        $address->setAddress($data['address']);
        $address->setCountry($data['country']);
        $address->setState($data['state']);
        $address->setCity($data['city']);
        $address->setPostcode($data['postcode']);

        if (isset($data['phone'])) {
            $address->setPhone($data['phone']);
        }

        if (isset($data['email'])) {
            $address->setEmail($data['email']);
        }

        return $address;
    }

    public function fromCart(\Jigoshop\Entity\Cart $cart)
    {
        $order = new \Jigoshop\Entity\Order($this->options->get('tax.classes'));
        $state = $cart->getStateToSave();
        $state['items'] = unserialize($state['items']);
        $state['customer'] = unserialize($state['customer']);
        unset($state['shipping'], $state['payment']);

        $order->setTaxDefinitions($cart->getTaxDefinitions());
        $order->restoreState($state);

        $shipping = $cart->getShippingMethod();
        if ($shipping && $shipping instanceof ShippingMethod) {
            $order->setShippingMethod($shipping);
            $order->setShippingTax($cart->getShippingTax());
        }

        $payment = $cart->getPaymentMethod();
        if ($payment && $payment instanceof PaymentMethod) {
            $order->setPaymentMethod($payment);
        }

        return $order;
    }
}
