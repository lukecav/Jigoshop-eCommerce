<?php

namespace Jigoshop\Frontend;

use Jigoshop\Container;
use Jigoshop\Exception;
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
            'cancel_order' => [$this, 'cancelOrder']
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
}