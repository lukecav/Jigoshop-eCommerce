<?php

namespace Jigoshop\Entity;

use Jigoshop\Entity\Customer\Guest;
use Jigoshop\Entity\Order\Item;
use Jigoshop\Entity\Order\Status;
use Jigoshop\Exception;
use Jigoshop\Payment;
use Jigoshop\Shipping;
use Monolog\Registry;

/**
 * Order class.
 *
 * @package Jigoshop\Entity
 * @author  Amadeusz Starzykiewicz
 */
class Order implements OrderInterface, \JsonSerializable
{
	/** @var int */
	private $id;
	/** @var string */
	private $key;
	/** @var string */
	private $number;
	/** @var \DateTime */
	private $createdAt;
	/** @var \DateTime */
	private $updatedAt;
	/** @var \DateTime */
	private $completedAt;
	/** @var Customer */
	private $customer;
	/** @var Item[] */
	private $items = array();
	/** @var Shipping\Method */
	private $shippingMethod;
	/** @var int */
	private $shippingMethodRate;
	/** @var Payment\Method */
	private $paymentMethod;
	/** @var float */
	private $productSubtotal;
	/** @var float */
	private $subtotal = 0.0;
	/** @var float */
	private $total = 0.0;
	/** @var float */
	private $discount = 0.0;
	/** @var array */
	private $coupons = array();
	/** @var array */
	private $tax = array();
	/** @var array */
	private $taxDefinitions = array();
	/** @var array */
	private $shippingTax = array();
	/** @var float */
	private $totalTax;
	/** @var float */
	private $totalCombinedTax;
	/** @var float */
	private $shippingPrice = 0.0;
	/** @var string */
	private $status = Status::PENDING;
	/** @var string */
	private $customerNote;
	/** @var array */
	private $updateMessages = array();
    /** @var bool  */
    private $taxIncluded = false;

	public function __construct(array $taxClasses)
	{
		$this->customer = new Guest();
		$this->createdAt = new \DateTime();
		$this->updatedAt = new \DateTime();
		$this->totalTax = null;
		$this->totalCombinedTax = null;

		foreach ($taxClasses as $class) {
			$this->tax[$class['class']] = 0.0;
			$this->shippingTax[$class['class']] = 0.0;
		}
	}

	/**
	 * @return int Entity ID.
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param $id int Order ID.
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return string Title of the order.
	 */
	public function getTitle()
	{
		return sprintf(__('Order %s', 'jigoshop'), $this->getNumber());
	}

	/**
	 * @return int Order number.
	 */
	public function getNumber()
	{
		return $this->number;
	}

	/**
	 * @param string $number The order number.
	 */
	public function setNumber($number)
	{
		$this->number = $number;
	}

	/**
	 * @return string Order security key.
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @param string $key New security key for the order.
	 */
	public function setKey($key)
	{
		$this->key = $key;
	}

	/**
	 * @return \DateTime Time the order was created at.
	 */
	public function getCreatedAt()
	{
		return $this->createdAt;
	}

	/**
	 * @param \DateTime $createdAt Creation time.
	 */
	public function setCreatedAt($createdAt)
	{
		$this->createdAt = $createdAt;
	}

	/**
	 * @return \DateTime Time the order was updated at.
	 */
	public function getUpdatedAt()
	{
		return $this->updatedAt;
	}

	/**
	 * @param \DateTime $updatedAt Last update time.
	 */
	public function setUpdatedAt($updatedAt)
	{
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Updates completion time to current date.
	 */
	public function setCompletedAt()
	{
		$this->completedAt = new \DateTime();
	}

	/**
	 * @return Customer The customer.
	 */
	public function getCustomer()
	{
		return $this->customer;
	}

	/**
	 * @param Customer $customer
	 */
	public function setCustomer($customer)
	{
		$this->customer = $customer;
	}

	/**
	 * @return float Value of discounts added to the order.
	 */
	public function getDiscount()
	{
		return $this->discount;
	}

	/**
	 * @param float $discount Total value of discounts for the order.
	 */
	public function setDiscount($discount)
	{
		$this->discount = $discount;
	}

	/**
	 * @param float $discount New discount for the order.
	 */
	public function addDiscount($discount)
	{
		$this->discount += $discount;
		$this->total -= $discount;
	}

	/**
	 * @param float $discount Discount to remove from the order.
	 */
	public function removeDiscount($discount)
	{
		$this->discount -= $discount;
		$this->total += $discount;
	}

	/**
	 * @return array List of used coupons codes.
	 */
	public function getCoupons()
	{
		return $this->coupons;
	}

	/**
	 * @param array $coupons New list of used coupons codes.
	 */
	public function setCoupons($coupons)
	{
		$this->coupons = $coupons;
	}

	/**
	 * @param Coupon|string $coupon New coupon object or code.
	 */
	public function addCoupon($coupon)
	{
		if (is_object($coupon)) {
			$coupon = $coupon->getCode();
		}
		if (array_search($coupon, $this->coupons) === false) {
			$this->coupons[] = $coupon;
		}
	}

	/**
	 * @param string $coupon Code to remove.
	 */
	public function removeCoupon($coupon)
	{
		$key = array_search($coupon, $this->coupons);
		if ($key !== false) {
			unset($this->coupons[$key]);
		}
	}

	/**
	 * @return array Tax definitions.
	 */
	public function getTaxDefinitions()
	{
		return $this->taxDefinitions;
	}

	/**
	 * @param array $taxDefinitions New tax definitions.
	 */
	public function setTaxDefinitions($taxDefinitions)
	{
		//@TODO sprawdzić dlaczego key są czasami null'em
		$this->taxDefinitions = array_filter($taxDefinitions);
	}

	/**
	 * Returns item of selected key.
	 *
	 * @param $key string Item key to fetch.
	 *
	 * @return Item Order item.
	 * @throws Exception When item is not found.
	 */
	public function getItem($key)
	{
		if (!isset($this->items[$key])) {
			if (WP_DEBUG) {
				throw new Exception(sprintf(__('No item with ID %d in order %d', 'jigoshop'), $key, $this->id));
			}

			Registry::getInstance(JIGOSHOP_LOGGER)->addWarning(sprintf('No item with ID %d in order %d', $key, $this->id));

			return null;
		}

		return $this->items[$key];
	}

	/**
	 * Returns whether order contains selected item by it's key.
	 *
	 * @param $key string Item key to find.
	 *
	 * @return bool Whether order has the item.
	 */
	public function hasItem($key)
	{
		return isset($this->items[$key]);
	}

	/**
	 * Removes all items, shipping method and associated taxes from the order.
	 */
	public function removeItems()
	{
		$this->removeShippingMethod();
		$this->items = array();
		$this->coupons = array();
		$this->productSubtotal = 0.0;
		$this->subtotal = 0.0;
		$this->total = 0.0;
		$this->discount = 0.0;
		$this->tax = array_map(function (){
			return 0.0;
		}, $this->tax);
		$this->totalTax = null;
		$this->totalCombinedTax = null;
	}

	/**
	 * Removes shipping method and associated taxes from the order.
	 */
	public function removeShippingMethod()
	{
		$this->subtotal -= $this->shippingPrice;
		$this->total -= $this->shippingPrice + array_reduce($this->shippingTax, function ($value, $item){
				return $value + $item;
			}, 0.0);

		$this->shippingMethod = null;
		$this->shippingMethodRate = null;
		$this->shippingPrice = 0.0;
		$this->shippingTax = array_map(function (){
			return 0.0;
		}, $this->shippingTax);
		$this->totalCombinedTax = null;
	}

	/**
	 * @return Item[] List of items bought.
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * @return Payment\Method Payment gateway object.
	 */
	public function getPaymentMethod()
	{
		return $this->paymentMethod;
	}

	/**
	 * @param Payment\Method $payment Method used to pay.
	 */
	public function setPaymentMethod($payment)
	{
		$this->paymentMethod = $payment;
	}

	/**
	 * @return float
	 */
	public function getShippingPrice()
	{
		return $this->shippingPrice;
	}

	/**
	 * @return Shipping\Method Shipping method.
	 */
	public function getShippingMethod()
	{
		return $this->shippingMethod;
	}

	/**
	 * @param Shipping\Method $method Method used for shipping the order.
	 */
	public function setShippingMethod(Shipping\Method $method)
	{
		$this->removeShippingMethod();

		$this->shippingMethod = $method;
		$this->shippingPrice = $method->calculate($this);
		$this->subtotal += $this->shippingPrice;
		$this->shippingTax = apply_filters('jigoshop\order\shipping_tax', $this->shippingTax, $method, $this);
		$this->total += apply_filters('jigoshop\order\shipping_price', $this->shippingPrice, $method, $this);
		$this->totalCombinedTax = null;
	}

	/**
	 * @param int $shippingMethodRate
	 */
	public function setShippingMethodRate($shippingMethodRate)
	{
		$this->shippingMethodRate = $shippingMethodRate;
	}

	/**
	 * Checks whether given shipping method is set for current cart.
	 *
	 * @param $method Shipping\Method Shipping method to check.
	 * @param $rate   Shipping\Rate Shipping rate to check.
	 *
	 * @return bool Is the method selected?
	 */
	public function hasShippingMethod($method, $rate = null)
	{
		if ($this->shippingMethod != null) {
			return $this->shippingMethod->is($method, $rate);
		}

		return false;
	}

	/**
	 * Checks whether at least one item requires shipping.
	 *
	 * @return bool Is shipping required for the cart?
	 */
	public function isShippingRequired()
	{
		$required = false;
		foreach ($this->items as $item) {
			/** @var $item Item */
			$product = $item->getProduct();
			if ($product instanceof Product\Shippable) {
				$required |= $product->isShippable();
			}
		}

		return $required;
	}

	/**
	 * @return string Current order status.
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * @param string $status  Status to set.
	 * @param string $message Message to add with status change.
	 */
	public function setStatus($status, $message = '')
	{
		$currentStatus = $this->status;
		$this->status = $status;

		if ($currentStatus != $status) {
			$this->updateMessages[] = array(
				'message' => (!$message ? '' : $message . '<br />'),
				'old_status' => $currentStatus,
				'new_status' => $status,
			);
		}
	}

	/**
	 * @return string Customer's note on the order.
	 */
	public function getCustomerNote()
	{
		return $this->customerNote;
	}

	/**
	 * @param string $customerNote Customer's note on the order.
	 */
	public function setCustomerNote($customerNote)
	{
		$this->customerNote = $customerNote;
	}

	/**
	 * @return float
	 */
	public function getProductSubtotal()
	{
		return $this->productSubtotal;
	}

	/**
	 * @param float $productSubtotal
	 */
	public function setProductSubtotal($productSubtotal)
	{
		$this->productSubtotal = $productSubtotal;
	}

	/**
	 * @return float Subtotal value of the cart.
	 */
	public function getSubtotal()
	{
		return $this->subtotal;
	}

	/**
	 * @param float $subtotal New subtotal value.
	 */
	public function setSubtotal($subtotal)
	{
		$this->subtotal = $subtotal;
	}

	/**
	 * @return float Total value of the cart.
	 */
	public function getTotal()
	{
		return $this->total;
	}

	/**
	 * @param float $total New total value.
	 */
	public function setTotal($total)
	{
		$this->total = $total;
	}

	/**
	 * @return array List of applied tax classes with it's values.
	 */
	public function getTax()
	{
		return $this->tax;
	}

	/**
	 * @param array $tax Tax data array.
	 */
	public function setTax($tax)
	{
		$this->totalTax = null;
		$this->totalCombinedTax = null;
		$this->tax = $tax;
	}

	/**
	 * Updates stored tax array with provided values.
	 *
	 * @param array $tax Tax divided by classes.
	 */
	public function updateTaxes(array $tax)
	{
		$this->totalTax = null;
		$this->totalCombinedTax = null;
		foreach ($tax as $class => $value) {
			$this->tax[$class] += $value;
		}
	}

	/**
	 * @return array List of applied tax classes for shipping with it's values.
	 */
	public function getShippingTax()
	{
		return $this->shippingTax;
	}

	/**
	 * @param array $shippingTax Tax data array for shipping.
	 */
	public function setShippingTax($shippingTax)
	{
		$this->totalCombinedTax = null;
		$this->shippingTax = $shippingTax;
	}

	/**
	 * @return float Total tax of the order.
	 */
	public function getTotalTax()
	{
		if ($this->totalTax === null) {
			$this->totalTax = array_sum($this->tax);
		}

		return $this->totalTax;
	}

	/**
	 * @return float Total, combined tax of the order (includes shipping tax).
	 */
	public function getTotalCombinedTax()
	{
		if ($this->totalCombinedTax === null) {
			$this->totalCombinedTax = array_sum($this->getCombinedTax());
		}

		return $this->totalCombinedTax;
	}

	/**
	 * @return array All tax data combined.
	 */
	public function getCombinedTax()
	{
		$tax = $this->tax;
		foreach ($this->shippingTax as $class => $value) {
			if (!isset($tax[$class])) {
				$tax[$class] = 0.0;
			}

			$tax[$class] += $value;
		}

		return $tax;
	}

	/**
	 * Updates quantity of selected item by it's key.
	 *
	 * @param $key      string Item key in the order.
	 * @param $quantity int Quantity to set.
	 *
	 * @throws Exception When product does not exists or quantity is not numeric.
	 */
	public function updateQuantity($key, $quantity)
	{
		if (!isset($this->items[$key])) {
			throw new Exception(__('Item does not exists', 'jigoshop'));
		}

		if (!is_numeric($quantity)) {
			throw new Exception(__('Quantity has to be numeric value', 'jigoshop'));
		}

		$item = $this->removeItem($key);

		if ($item === null) {
			throw new Exception(__('Item not found.', 'jigoshop'));
		}

		if ($quantity <= 0) {
			return;
		}

		$item->setQuantity($quantity);
		$this->addItem($item);
	}

	/**
	 * @param $key string Item key to remove.
	 *
	 * @return Item Removed item.
	 */
	public function removeItem($key)
	{
		if (isset($this->items[$key])) {
			/** @var Item $item */
			$item = $this->items[$key];
			do_action('jigoshop\order\remove_item', $item, $this);
			$this->total -= $item->getCost() + $item->getTax();
			$this->subtotal -= $item->getCost();
			$this->productSubtotal -= $item->getCost();
			$this->totalTax = null;
			$this->totalCombinedTax = null;
			unset($this->items[$key]);

			return $item;
		}

		return null;
	}

	/**
	 * @param Item $item Item to add.
	 */
	public function addItem(Item $item)
	{
		do_action('jigoshop\order\add_item', $item, $this);
		$this->items[$item->getKey()] = $item;
		$this->productSubtotal += $item->getCost();
		$this->subtotal += $item->getCost();
		$this->total += $item->getCost() + $item->getTax();
		$this->totalTax = null;
		$this->totalCombinedTax = null;
	}

	/**
	 * @return array List of fields to update with according values.
	 */
	public function getStateToSave()
	{
		$shipping = false;
		if (is_object($this->shippingMethod)) {
			$shipping = $this->shippingMethod->getState();
		}

		$payment = false;
		if (is_object($this->paymentMethod)) {
			$payment = $this->paymentMethod->getId();
		}

		return array(
			'id' => $this->id,
			'number' => $this->number,
			'updated_at' => $this->updatedAt->getTimestamp(),
			'completed_at' => $this->completedAt ? $this->completedAt->getTimestamp() : 0,
			'items' => $this->items,
			'customer' => serialize($this->customer),
			'customer_id' => $this->customer->getId(),
			'shipping' => array(
				'method' => $shipping,
				'price' => $this->shippingPrice,
				'rate' => $this->shippingMethodRate,
			),
			'payment' => $payment,
			'customer_note' => $this->customerNote,
			'total' => $this->total,
			'subtotal' => $this->subtotal,
			'discount' => $this->discount,
			'coupons' => $this->coupons,
			'shipping_tax' => $this->shippingTax,
			'status' => $this->status,
			'update_messages' => $this->updateMessages,
            'tax_included' => $this->taxIncluded
		);
	}

    /**
     * @param bool $taxIncluded
     */
    public function setTaxIncluded($taxIncluded)
    {
        $this->taxIncluded = $taxIncluded;
    }

    /**
     * @return bool
     */
    public function isTaxIncluded()
    {
        return $this->taxIncluded;
    }

	/**
	 * @param array $state State to restore entity to.
	 */
	public function restoreState(array $state)
	{
		if (isset($state['key'])) {
			$this->key = $state['key'];
		}
		if (isset($state['number'])) {
			$this->number = $state['number'];
		}
		if (isset($state['created_at'])) {
			$this->createdAt->setTimestamp($state['created_at']);
		}
		if (isset($state['updated_at'])) {
			$this->updatedAt->setTimestamp($state['updated_at']);
		}
		if (isset($state['completed_at'])) {
			$this->completedAt = new \DateTime();
			$this->completedAt->setTimestamp($state['completed_at']);
		}
		if (isset($state['status'])) {
			$this->status = $state['status'];
		}
		if (isset($state['items'])) {
			foreach ($state['items'] as $item) {
				$this->addItem($item);
			}
		}
		if (isset($state['customer']) && $state['customer'] !== false) {
			$this->customer = $state['customer'];
		}
		if (isset($state['shipping']) && is_array($state['shipping'])) {
			$this->shippingMethod = $state['shipping']['method'];
			$this->shippingMethodRate = $state['shipping']['rate'];

			if ($state['shipping']['price'] > -1) {
				$this->shippingPrice = $state['shipping']['price'];
			} else {
				$this->shippingPrice = $this->shippingMethod->calculate($this);
			}

			$this->subtotal += $this->shippingPrice;
		}
		if (isset($state['payment']) && !empty($state['payment'])) {
			$this->paymentMethod = $state['payment'];
		}
		if (isset($state['customer_note'])) {
			$this->customerNote = $state['customer_note'];
		}
		if (isset($state['shipping_tax'])) {
			$tax = maybe_unserialize($state['shipping_tax']);
			foreach ($tax as $class => $value) {
				if (!isset($this->shippingTax[$class])) {
					$this->shippingTax[$class] = 0.0;
				}

				$this->shippingTax[$class] += $value;
			}
		}
		if (isset($state['product_subtotal'])) {
			$this->productSubtotal = (float)$state['product_subtotal'];
		}
		if (isset($state['discount'])) {
			$this->discount = (float)$state['discount'];
		}
		if (isset($state['coupons'])) {
			$this->coupons = maybe_unserialize($state['coupons']);
		}
		if (isset($state['tax_definitions'])) {
			$this->taxDefinitions = $state['tax_definitions'];
		}

		$this->total = $this->subtotal + array_reduce($this->tax, function ($value, $item){
				return $value + $item;
			}, 0.0)
			+ array_reduce($this->shippingTax, function ($value, $item){
				return $value + $item;
			}, 0.0) - $this->discount;
        if (isset($state['price_includes_tax'])) {
            $this->taxIncluded = (bool)$state['price_includes_tax'];
        }
	}

    /**
     * Used by json_encode method to proprly
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $shipping = false;
        if (is_object($this->shippingMethod)) {
            $shipping = $this->shippingMethod->getState();
        }
        $payment = false;
        if (is_object($this->paymentMethod)) {
            $payment = $this->paymentMethod->getId();
        }
        $completedAt = false;
        if (is_object($this->completedAt) && $this->completedAt->getTimestamp()) {
            $completedAt = [
                'timestamp' => $this->completedAt->getTimestamp(),
                'format' => $this->completedAt->format('Y-m-d H:i:s')
            ];
        }

       return [
           'id' => $this->id,
           'number' => $this->number,
           'created_at' => [
               'timestamp' => $this->createdAt->getTimestamp(),
               'format' => $this->createdAt->format('Y-m-d H:i:s')
           ],
           'updated_at' => [
               'timestamp' => $this->updatedAt->getTimestamp(),
               'format' => $this->updatedAt->format('Y-m-d H:i:s')
           ],
           'completed_at' => $completedAt,
           'items' => array_values($this->items),
           'price_includes_tax' => $this->taxIncluded,
           'customer' => $this->customer,
           'shipping' => [
               'method' => $shipping,
               'price' => $this->shippingPrice,
               'rate' => $this->shippingMethodRate,
           ],
           'payment' => $payment,
           'customer_note' => $this->customerNote,
           'total' => $this->total,
           'tax' => $this->tax,
           'shipping_tax' => $this->shippingTax,
           'subtotal' => $this->subtotal,
           'discount' => $this->discount,
           'coupons' => $this->coupons,
           'status' => $this->status,
           'update_messages' => $this->updateMessages,
       ];
    }
}
