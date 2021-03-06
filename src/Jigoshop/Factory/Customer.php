<?php

namespace Jigoshop\Factory;

use Jigoshop\Entity\Customer as Entity;
use Jigoshop\Entity\Session as SessionEntity;
use Jigoshop\Service\SessionServiceInterface;
use WPAL\Wordpress;

class Customer implements EntityFactoryInterface
{
	const CUSTOMER = 'jigoshop_customer';

	/** @var \WPAL\Wordpress */
	private $wp;
    /** @var  SessionEntity */
    private $session;

	public function __construct(Wordpress $wp, SessionServiceInterface $sessionService)
	{
		$this->wp = $wp;
        $this->session = $sessionService->get($sessionService->getCurrentKey());
	}

	/**
	 * Creates new customer properly based on POST variable data.
	 *
	 * @param $id int Post ID to create object for.
	 *
	 * @return Entity
	 */
	public function create($id)
	{
		$customer = new Entity();
		$customer->setId($id);

		return $customer;
	}

	/**
	 * Fetches customer from database.
	 *
	 * @param $user \WP_User User object to fetch customer for.
	 *
	 * @return \Jigoshop\Entity\Customer
	 */
	public function fetch($user)
	{
		$state = array();

		if ($user->ID == 0) {
			$customer = new Entity\Guest();

			if ($this->session->getField(self::CUSTOMER)) {
				$customer->restoreState($this->session->getField(self::CUSTOMER));
			}
		} else {
			$customer = new Entity();
			$meta = $this->wp->getUserMeta($user->ID);

			if (is_array($meta)) {
				$state = array_map(function ($item){
					return $item[0];
				}, $meta);
			}

			$state['id'] = $user->ID;
			$state['login'] = $user->get('login');
			$state['email'] = $user->get('user_email');
			$state['name'] = $user->get('display_name');

			$customer->restoreState($state);
		}

		return $this->wp->applyFilters('jigoshop\find\customer', $customer, $state);
	}
}
