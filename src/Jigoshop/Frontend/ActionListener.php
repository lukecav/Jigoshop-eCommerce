<?php

namespace Jigoshop\Frontend;

use Jigoshop\Container;
use Jigoshop\Exception;
use Jigoshop\Frontend\Page\Cart;
use Jigoshop\Frontend\Page\Checkout;
use Jigoshop\Frontend\Page\Product;
use WPAL\Wordpress;

/**
 * Class ActionListener
 * @package Jigoshop\Frontend;
 * @author Krzysztof Kasowski
 */
class ActionListener
{
    /** @var  array  */
    private $actions;
    /** @var Container */
    private $di;

    /**
     * ActionListener constructor.
     * @param Wordpress $wp
     * @param Container $di
     */
    public function __construct(Wordpress $wp, Container $di)
    {
        $this->di = $di;
        $this->actions = [
            'add-to-cart' => [$this, 'addToCart'],
            'purchase' => [$this, 'purchase'],
            'cancel_order' => [$this, 'cancelOrder'],
            'update-shipping' => [$this, 'updateShipping'],
            'checkout' => [$this, 'checkout'],
            'update-cart' => [$this, 'updateCart'],
            'remove-item' => [$this, 'removeItem'],
        ];
    }

    /**
     * @param $hook
     * @param $callable
     */
    public function addAction($hook, $callable)
    {
        if(isset($this->actions[$hook])) {
            //TODO:
            throw new Exception('');
        }
        $this->actions[$hook] = $callable;
    }

    /**
     * @param $hook
     */
    public function removeAction($hook)
    {
        if(isset($this->actions[$hook])) {
            unset($this->actions[$hook]);
        }
    }

    /**
     * Run on Init.
     */
    public function run()
    {
        if(isset($_REQUEST['action'], $this->actions[$_REQUEST['action']])) {
            call_user_func_array($this->actions[$_REQUEST['action']], [$this->di]);
        }
    }

    /**
     * @param Container $di
     */
    public function addToCart(Container $di)
    {
        
    }

    /**
     * @param Container $di
     */
    public function purchase(Container $di)
    {
        /** @var Checkout  $page */
        $page = $di->get('jigoshop.page.checkout');
        $page->action();
    }

    public function cancelOrder(Container $di)
    {

    }
}