<?php

namespace Jigoshop\Extension\PayPalPaymentsPro;

use Jigoshop\Integration;
use Jigoshop\Container;

class Init
{
	
	public function __construct()
	{
		Integration::addPsr4Autoload(__NAMESPACE__ . '\\', __DIR__);
		
		// CreditCard
		\Jigoshop\Integration\Render::addLocation('jigoshop_ppp', JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_DIR);
		/**@var Container $creditCard */
		$creditCard = Integration::getService('di');
		$creditCard->services->setDetails(
			'jigoshop.payment.jigoshop_ppp', __NAMESPACE__ . '\\Common\\Payment\\PaypalPaymentsPro', [
				'jigoshop.options',
				'jigoshop.service.cart',
				'jigoshop.service.order',
				'jigoshop.messages',
			]
		);
		
		$creditCard->triggers->add('jigoshop.service.payment', 'jigoshop.service.payment', 'addMethod', ['jigoshop.payment.jigoshop_ppp']);
		
	}
}

new Init();