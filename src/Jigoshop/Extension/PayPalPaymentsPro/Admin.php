<?php

namespace Jigoshop\Extension\PayPalPaymentsPro;


class Admin
{
	public function __construct()
	{
		add_filter('plugin_action_links_' . plugin_basename(JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_DIR . '/bootstrap.php'), array($this, 'actionLinks'));
	}
	
	/**
	 * Show action links on plugins page.
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function actionLinks($links)
	{
		$links[] = '<a href="https://www.jigoshop.com/product/jigoshop-paypal-payments-pro/" target="_blank">' . __('Documentation', 'jigoshop_ppp') . '</a>';
		$links[] = '<a href="https://www.jigoshop.com/support/" target="_blank">' . __('Support', 'jigoshop_ppp') . '</a>';
		$links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/jigoshop#$postform" target="_blank">'  . __('Rate Us', 'jigoshop_ppp') . '</a>';
		$links[] = '<a href="https://www.jigoshop.com/product-category/extensions/" target="_blank">' . __('More plugins for Jigoshop', 'jigoshop_ppp') . '</a>';
		
		return $links;
	}
}
new Admin();