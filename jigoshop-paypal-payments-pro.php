<?php
/**
 * Plugin Name: Jigoshop Paypal Payments Pro
 * Plugin URI: https://www.jigoshop.com/product/jigoshop-paypal-payments-advanced
 * Description: PayPal Payments Pro Extension for your Jigoshop eCommerce online based store
 * Version: 1.0.4
 * Author: Jigoshop
 * Author URI: https://www.jigoshop.com/product/jigoshop-paypal-payments-advanced
 * Init File Version: 1.3
 * Init File Date: 01.04.2016
 */
// Define plugin name
define('JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_NAME', 'Jigoshop Paypal Payments Pro');
add_action('plugins_loaded', function () {
	load_plugin_textdomain('jigoshop_ppp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
	if (class_exists('\Jigoshop\Core')) {
		//Check version.
		if (\Jigoshop\addRequiredVersionNotice(JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_NAME, '2.1.11')) {
			return;
		}
		//Check license.
		/*$licence = new \Jigoshop\Licence(__FILE__, '52561', 'http://www.jigoshop.com');
		if (!$licence->isActive()) {
			return;
		}*/
		// Define plugin directory for inclusions
		define('JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_DIR', dirname(__FILE__));
		// Define plugin URL for assets
		define('JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_URL', plugins_url('', __FILE__));
		//Init components.
		require_once(JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_DIR . '/src/Jigoshop/Extension/PayPalPaymentsPro/Init.php');
		if (is_admin()) {
			require_once(JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_DIR . '/src/Jigoshop/Extension/PayPalPaymentsPro/Admin.php');
		}
	} else {
		add_action('admin_notices', function () {
			echo '<div class="error"><p>';
			printf(__('%s requires Jigoshop plugin to be active. Code for plugin %s was not loaded.',
				'jigoshop_ppp'), JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_NAME, JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_NAME);
			echo '</p></div>';
		});
	}
});
