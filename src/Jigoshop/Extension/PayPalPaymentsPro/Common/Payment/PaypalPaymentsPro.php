<?php

namespace Jigoshop\Extension\PaypalPaymentsPro\Common\Payment;


use Jigoshop\Admin\Pages;
use Jigoshop\Frontend\Pages as FrontPage;
use Jigoshop\Core\Messages;
use Jigoshop\Entity\Cart;
use Jigoshop\Entity\Order;
use Jigoshop\Entity\Product\Virtual;
use Jigoshop\Exception;
use Jigoshop\Core\Options;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Process\Process;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Response\Response;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Types\CreditCardPayment;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Types\HostedPayment;
use Jigoshop\Helper\Currency;
use Jigoshop\Helper\Product;
use Jigoshop\Helper\Scripts;
use Jigoshop\Integration;
use Jigoshop\Integration\Helper\Render;
use Jigoshop\Payment\Method3;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\OrderServiceInterface;
use Jigoshop\Helper\Options as OptionsHelper;

class PaypalPaymentsPro implements Method3
{
	const ID = 'paypal_payments_pro';
	
	/**@var Options $options */
	private $options;
	/**@var Messages $messages */
	private $messages;
	/**@var CartServiceInterface $cartService */
	private $cartService;
	/**@var OrderServiceInterface $orderService */
	private $orderService;
	/**@var array $settings */
	private static $settings;
	
	private static $acceptedCardTypes = [];
	
	private static $endPoint;
	
	private static $hostedUrl;
	
	private static $supportedCurrencies = [];
	
	private static $currency;
	
	private $country;
	
	/** @var array list of available countries and payments cards * */
	private $availableCountries = array(
		'GB' => [
			'Visa',
			'MasterCard',
			'Maestro',
			'Solo'
		],
		'US' => [
			'Visa',
			'MasterCard',
			'Discover',
			'AmEx'
		],
		'CA' => [
			'Visa',
			'MasterCard'
		]
	);
	
	public function __construct(Options $options, CartServiceInterface $cartService, OrderServiceInterface $orderService, Messages $messages)
	{
		$this->options = $options;
		$this->messages = $messages;
		$this->cartService = $cartService;
		$this->orderService = $orderService;
		
		$this->country = $options->get('general.country');
		
		self::$acceptedCardTypes = [
			'VSA' => __('Visa', 'jigoshop_ppp'),
			'MSC' => __('Master Card', 'jigoshop_ppp'),
			'DNC' => __('Dinner\'s Club', 'jigoshop_ppp'),
			'DIS' => __('Discover', 'jigoshop_ppp'),
			'AMX' => __('American Express', 'jigoshop_ppp'),
		];
		
		self::$supportedCurrencies = [
			'AUD',
			'CAD',
			'EUR',
			'GBP',
			'JPY',
			'USD',
		];
		self::$currency = Currency::code();
		
		OptionsHelper::setDefaults(self::ID, [
			'enabled' => false,
			'title' => __('PayPal Payments Pro', 'jigoshop_ppp'),
			'description' => __('Pay via PayPal Pro', 'jigoshop_ppp'),
			'partner' => '',
			'vendor' => '',
			'user' => '',
			'password' => '',
			'transactionType' => [],
			'paymentMethod' => [],
			'paymentType' => [],
			'template' => [],
			'testMode' => false,
			'adminOnly' => false,
		]);
		
		self::$settings = $options->get('payment.' . self::ID);
		
		self::$endPoint = self::$settings['testMode'] ? 'https://pilot-payflowpro.paypal.com' : 'https://payflowpro.paypal.com';
		self::$hostedUrl = self::$settings['testMode'] ? 'https://pilot-payflowlink.paypal.com' : 'https://payflowlink.paypal.com';
		
		add_action('init', [$this, 'checkResponseFromHostedPayment']);
		add_action('init', [$this, 'cancelPayment']);
		add_action('init', [$this, 'returnPayment']);
	}
	
	/**
	 * Empty
	 */
	public function adminScripts()
	{
		Scripts::add('jigoshop.backend.admin.paypal_payments_pro',
			JIGOSHOP_PAYPAL_PAYMENTS_PRO_GATEWAY_URL .
			'/assets/js/adminScript.js');
	}
	
	/**
	 * @return array|mixed
	 */
	public static function getSettings()
	{
		return self::$settings;
	}
	
	/*
	 * Transaction Methods: Authorize, Sale, Credit
	 * Registration: https://manager.paypal.com/
	 */
	
	/**
	 * @return array
	 */
	private function cardTypes()
	{
		return self::$acceptedCardTypes;
	}
	
	/**
	 * @return string
	 */
	public static function getEndpoint()
	{
		return self::$endPoint;
	}
	
	/**
	 * @return string
	 */
	public static function hostedEndpoint()
	{
		return self::$hostedUrl;
	}
	
	/**
	 * @return array
	 */
	private function currencies()
	{
		return self::$supportedCurrencies;
	}
	
	/**
	 * @return string ID of payment method.
	 */
	public function getId()
	{
		return self::ID;
	}
	
	/**
	 * @return string Human readable name of method.
	 */
	public function getName()
	{
		return $this->isAdmin() ? $this->getLogoImage() . ' ' . __('Paypal Payments Pro', 'jigoshop_ppp') : self::$settings['title'];
	}
	
	/**
	 * @return bool
	 */
	private function isAdmin()
	{
		return is_admin() ? true : false;
	}
	
	private function getLogoImage()
	{
		return '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-small.png" alt="PayPal Acceptance" />';
	}
	
	/**
	 * @return bool Whether current method is enabled and able to work.
	 */
	public function isEnabled()
	{
		return self::$settings['enabled'];
	}
	
	/**
	 * @return array List of options to display on Payment settings page.
	 */
	public function getOptions()
	{
		$defaults = [
			[
				'name' => 'enabled',
				'title' => __(
					'Enable', 'jigoshop_ppp'
				),
				'description' => __('', 'jigoshop_ppp'),
				'type' => 'checkbox',
				'checked' => self::$settings['enabled'],
				'classes' => ['switch-medium'],
			],
			[
				'name' => 'title',
				'title' => __(
					'Method Title', 'jigoshop_ppp'
				),
				'description' => __('This controls the title on checkout page', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['title'],
			],
			[
				'name' => 'description',
				'title' => __(
					'Method Description', 'jigoshop_ppp'
				),
				'description' => __('This controls the description on checkout page', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['description'],
			],
			[
				'name' => 'partner',
				'title' => __('Partner', 'jigoshop_ppp'),
				'description' => sprintf(
					__('Your PayPal Pro Partner. If you do not have a credentials you can register <a href="%s">' . __('here') . '</a>', 'jigoshop_ppp'), esc_url('https://manager.paypal.com/')),
				'tip' => __('Partner is given during the registration in your PayPal account page', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['partner'],
			],
			[
				'name' => 'vendor',
				'title' => __(
					'Vendor', 'jigoshop_ppp'
				),
				'description' => __('Your PayPal Vendor', 'jigoshop_ppp'),
				'tip' => __('Vendor is given during the registration in your PayPal account page', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['vendor'],
			],
			[
				'name' => 'user',
				'title' => __(
					'User', 'jigoshop_ppp'
				),
				'description' => __('Your PayPal User', 'jigoshop_ppp'),
				'tip' => __('User is given during the registration in your PayPal account page', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['user'],
			],
			[
				'name' => 'password',
				'title' => __(
					'Password', 'jigoshop_ppp'
				),
				'description' => __('Your PayPal Pro Password', 'jigoshop_ppp'),
				'tip' => __('Your PayPal Pro account Password', 'jigoshop_ppp'),
				'type' => 'text',
				'value' => self::$settings['password'],
			],
			[
				'name' => 'paymentType',
				'title' => __(
					'Payment Type', 'jigoshop_ppp'
				),
				'description' => __('Choose the Payment Type', 'jigoshop_ppp'),
				'type' => 'select',
				'options' => [
					'CCP' => __('Credit Card Payment (Customer stays at checkout page)', 'jigoshop_ppp'),
					'HP' => __('Hosted Payment (Paypal Hosted Payment Page)', 'jigoshop_ppp'),
					//'TR' => __('Transparent Redirect (Customer Stays at checkout page)', 'jigoshop_ppp'),
				],
				'value' => self::$settings['paymentType'],
			],
			[
				'name' => 'transactionType',
				'title' => __(
					'Transaction Type', 'jigoshop_ppp'
				),
				'description' => __('Choose the Transaction Type of Payment', 'jigoshop_ppp'),
				'type' => 'select',
				'options' => [
					'A' => __('Authorize', 'jigoshop_ppp'),
					'S' => __('Sale', 'jigoshop_ppp'),
				],
				'value' => self::$settings['transactionType'],
			],
			[
				'name' => 'paymentMethod',
				'title' => __(
					'Payment Method', 'jigoshop_ppp'
				),
				'description' => __('Choose the Payment Method on PayPal Hosted Page', 'jigoshop_ppp'),
				'type' => 'select',
				'options' => [
					'C' => __('Credit Card Payment', 'jigoshop_ppp'),
					'P' => __('PayPal (Required valid PayPal Account)', 'jigoshop_ppp'),
				],
				'value' => self::$settings['paymentMethod'],
			],
			[
				'name' => 'template',
				'title' => __(
					'Template', 'jigoshop_ppp'
				),
				'description' => __('Choose the template (Paypal Hosted Page)', 'jigoshop_ppp'),
				'type' => 'select',
				'options' => [
					'A' => __('Template A', 'jigoshop_ppp'),
					'B' => __('Template B', 'jigoshop_ppp'),
					'C' => __('Default Template', 'jigoshop_ppp')
				],
				'value' => self::$settings['template'],
			],
			[
				'name' => 'testMode',
				'title' => __('Sandbox/Testing', 'jigoshop_ppp'),
				'description' => __('Enable Sandbox for testing payment', 'jigoshop_ppp'),
				'type' => 'checkbox',
				'checked' => self::$settings['testMode'],
				'classes' => ['switch-medium'],
			],
			[
				'name' => 'adminOnly',
				'title' => __('Enable only for Admin', 'jigoshop_ppp'),
				'description' => __('Enable this if you would like to test it only for Site Admin', 'jigoshop_ppp'),
				'type' => 'checkbox',
				'checked' => self::$settings['adminOnly'],
				'classes' => ['switch-medium'],
			]
		];
		
		for ($i = 0; $i < count($defaults); $i++) {
			$defaults[$i]['name'] = sprintf('[%s][%s]', self::ID, $defaults[$i]['name']);
		}
		
		return $defaults;
	}
	
	/**
	 * Validates and returns properly sanitized options.
	 *
	 * @param $settings array Input options.
	 *
	 * @return array Sanitized result.
	 */
	public function validateOptions($settings)
	{
		$disabled = null;
		$settings['enabled'] = $settings['enabled'] == 'on';
		$settings['testMode'] = $settings['testMode'] == 'on';
		$settings['adminOnly'] = $settings['adminOnly'] == 'on';
		$settings['title'] = trim(htmlspecialchars(strip_tags($settings['title'])));
		$settings['description'] = esc_attr($settings['description']);
		
		$settings['partner'] = $this->validate($settings['partner']);
		$settings['vendor'] = $this->validate($settings['vendor']);
		$settings['user'] = $this->validate($settings['user']);
		$settings['password'] = $this->validate($settings['password']);
		$settings['transactionType'] = $this->validate($settings['transactionType']);
		$settings['paymentMethod'] = $this->validate($settings['paymentMethod']);
		$settings['paymentType'] = $this->validate($settings['paymentType']);
		$settings['template'] = $this->validate($settings['template']);
		
		if (!array_search(self::$currency, $this->currencies())) {
			$this->messages->addError(
				__('PayPal Pro accepts payment in one of the following currencies: ' . implode(',', $this->currencies()) .
					'. Your current currency is: ' . self::$currency . '. Please change the currency to accepted ones to make the Gateway work properly.', 'jigoshop_ppp'));
		}
		
		if (!$this->options->get('shopping.force_ssl')) {
			$this->messages->addError(sprintf(
				__('Paypal Pro requires <a href="' . admin_url('admin.php?page=jigoshop_settings&tab=shopping') . '">' . __('Force SSL') . '</a> to be enabled, otherwise Gateway will be disabled', 'jigoshop_ppp')));
			$settings['enabled'] = $disabled;
		}
		
		return $settings;
	}
	
	private function validate($settings)
	{
		return trim(strip_tags(esc_attr($settings)));
	}
	
	/**
	 * Renders method fields and data in Checkout page.
	 */
	public function render()
	{
		if (self::$settings['description']) {
			echo wpautop(self::$settings['description']);
		}
		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			$timestamp = mktime(0, 0, 0, $i, 1);
			$months[date('n', $timestamp)] = date('F', $timestamp);
		}
		
		$availableCards = $this->availableCountries[$this->country];
		
		$years = range(date('Y'), date('Y') + 15);
		if (self::$settings['paymentType'] == 'CCP') {
			Render::output('jigoshop_ppp', 'frontend/checkout', [
				'availableCards' => $availableCards,
				'months' => $months,
				'years' => $years,
			]);
		}
		
	}
	
	/**
	 * @param Order $order Order to process payment for.
	 *
	 * @return string URL to redirect to.
	 * @throws Exception On any payment error.
	 */
	public function process($order)
	{
		if (self::$settings['paymentType'] == 'CCP') {
			$this->validateFields();
			$posted = $_POST['paypal_pro'];
			$cardExpirationYear = $posted['card']['exp_year'];
			$cardExpirationMonth = $posted['card']['exp_month'];
			if ($cardExpirationMonth < 10) {
				$cardExpirationMonth = '0' . $cardExpirationMonth;
			}
			$cardNumber = str_replace([' ', '-'], '', $posted['card']['number']);
			$cardType = $posted['card']['type'];
			$cardCsc = $posted['card']['csc'];
			
			$result = CreditCardPayment::getInstance()->doCreditCardPayment($order, [
				'cardType' => $cardType,
				'cardNumber' => $cardNumber,
				'expMonth' => $cardExpirationMonth,
				'expYear' => $cardExpirationYear,
				'cardCvv' => $cardCsc,
			]);
			
			return Process::getResponse()->doPaypalProCreditCardPayment($order, $result);
			
		} elseif (self::$settings['paymentType'] == 'HP') {
			$return = null;
			$result = HostedPayment::getInstance()->doHostedPayment($order);
			
			return Process::getResponse()->doPayPalProHostedPayment($order, $result);
		}
		
		return \Jigoshop\Helper\Order::getPayLink($order);
	}
	
	public function checkResponseFromHostedPayment()
	{
		//paypalProListener=success
		if (isset($_GET['paypalProListener']) && $_GET['paypalProListener'] == 'success') {
			if (isset($_POST['RESPMSG'])) {
				if ($_POST['RESPMSG'] == 'Approved') {
					$orderID = strip_tags((int)$_POST['ORDERID']);
					if (isset($_POST['RESULT']) && $_POST['RESULT'] == '0') {
						/**@var Order $order */
						$order = $this->orderService->find($orderID);
						$status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
						if (!empty($_POST['SECURETOKEN'])) {
							$secureToken = $this->getOrderMeta($order->getId(), 'secureToken', true);
							if ($secureToken == $_POST['SECURETOKEN']) {
								$order->setStatus($status, __('Payment Completed. Order ID: ' . $this->validate($_POST['PNREF']) .
									'Auth Code: ' . $this->validate($_POST['AUTHCODE'])));
								$this->orderService->save($order);
								delete_post_meta($order->getId(), 'secureToken');
								$redirect = \Jigoshop\Helper\Order::getThankYouLink($order);
								$this->safeRedirect($redirect);
								exit;
							}
						}
					}
				}
			}
		} else {
			// No reason
			if (isset($_GET['paypalProListener']) && $_GET['paypalProListener'] == 'return') {
				$orderID = $this->validate((int)$_GET['order']);
				/**@var Order $order */
				$order = $this->orderService->find($orderID);
				$redirect = \Jigoshop\Helper\Order::getPayLink($order);
				$this->messages->addError(__('It seems something went wrong and payment could not be completed. Please try again later.', 'jigoshop_ppp'));
				$this->safeRedirect($redirect);
				exit;
			}
		}
	}
	
	public function returnPayment()
	{
		Response::getResponse()->getErrorPayment($_GET);
	}
	
	public function cancelPayment()
	{
		Response::getResponse()->getCancelPayment($_GET);
	}
	
	/**
	 * Whenever method was enabled by the user.
	 *
	 * @return boolean Method enable state.
	 */
	public function isActive()
	{
		if (self::$settings['enabled']) {
			$enabled = true;
		} else {
			$enabled = false;
		}
		return $enabled;
	}
	
	/**
	 * Set method enable state.
	 *
	 * @param boolean $state Method enable state.
	 *
	 * @return array Method current settings (after enable state change).
	 */
	public function setActive($state)
	{
		self::$settings['enabled'] = $state;
		
		return self::$settings;
	}
	
	/**
	 * Whenever method was configured by the user (all required data was filled for current scenario).
	 *
	 * @return boolean Method config state.
	 */
	public function isConfigured()
	{
		if (self::$settings['enabled']) {
			if (isset(self::$settings['vendor']) && self::$settings['vendor']
				&& isset(self::$settings['partner']) && self::$settings['partner']
				&& isset(self::$settings['user']) && self::$settings['user']
				&& isset(self::$settings['password']) && self::$settings['password']) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Whenever method has some sort of test mode.
	 *
	 * @return boolean Method test mode presence.
	 */
	public function hasTestMode()
	{
		return true;
	}
	
	/**
	 * Whenever method test mode was enabled by the user.
	 *
	 * @return boolean Method test mode state.
	 */
	public function isTestModeEnabled()
	{
		$testModeState = false;
		if (self::$settings['testMode']) {
			$testModeState = true;
		}
		
		return $testModeState;
	}
	
	/**
	 * Set Method test mode state.
	 *
	 * @param boolean $state Method test mode state.
	 *
	 * @return array Method current settings (after test mode state change).
	 */
	public function setTestMode($state)
	{
		self::$settings['testMode'] = $state;
		
		return self::$settings;
	}
	
	/**
	 * Whenever method requires SSL to be enabled to function properly.
	 *
	 * @return boolean Method SSL requirment.
	 */
	public function isSSLRequired()
	{
		return true;
	}
	
	/**
	 * Whenever method is set to enabled for admin only.
	 *
	 * @return boolean Method admin only state.
	 */
	public function isAdminOnly()
	{
		if (true == self::$settings['adminOnly']) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Sets admin only state for the method and returns complete method options.
	 *
	 * @param boolean $state Method admin only state.
	 *
	 * @return array Complete method options after change was applied.
	 */
	public function setAdminOnly($state)
	{
		self::$settings['adminOnly'] = $state;
		
		return self::$settings;
	}
	
	/**
	 * Validate payment form fields
	 */
	private function validateFields()
	{
		$posted = $_POST['paypal_pro'];
		$cardType = $posted['card']['type'];
		$cardNumber = $posted['card']['number'];
		$cardCSC = $posted['card']['csc'];
		$cardExpirationMonth = intval($posted['card']['exp_month']);
		$cardExpirationYear = intval($posted['card']['exp_year']);
		
		if (!isset($this->availableCountries[$this->country])) {
			throw new Exception(__('Invalid configuration: shop country is not supported.',
				'jigoshop_ppp'));
		}
		
		$availableCards = $this->availableCountries[$this->country];
		/*if (!$this->is3dSecureEnabled() && ($maestro = array_search('Maestro', $availableCards)) !== false) {
			unset($availableCards[$maestro]);
		}*/
		
		if (!in_array($cardType, $availableCards)) {
			throw new Exception(__('Selected card type in not available', 'jigoshop_ppp'));
			
		}
		
		//check security code
		if (!ctype_digit($cardCSC)) {
			throw new Exception(__('Card security code is invalid (only digits are allowed)',
				'jigoshop_ppp'));
		}
		
		if ((strlen($cardCSC) != 3 && in_array($cardType, array(
					'Visa',
					'MasterCard',
					'Discover'
				))) || (strlen($cardCSC) != 4 && $cardType == 'American Express')
		) {
			throw new Exception(__('Card security code is invalid (wrong length)', 'jigoshop_ppp'));
		}
		
		//check expiration data
		$currentYear = intval(date('Y'));
		if ($cardExpirationMonth > 12 ||
			$cardExpirationMonth < 1 ||
			$cardExpirationYear < $currentYear ||
			$cardExpirationYear > $currentYear + 15
		) {
			throw new Exception(__('Card expiration date is invalid', 'jigoshop_ppp'));
		}
		
		//check card number
		$cardNumber = str_replace(array(' ', '-'), '', $cardNumber);
		
		if (empty($cardNumber) || !ctype_digit($cardNumber)) {
			throw new Exception(__('Card number is invalid', 'jigoshop_ppp'));
		}
	}
	
	/**
	 * @param $orderId
	 * @param $metaKey
	 * @param bool $single
	 * @return bool|mixed
	 */
	private function getOrderMeta($orderId, $metaKey, $single = false)
	{
		if (!empty($orderId)) {
			return get_post_meta($orderId, $metaKey, $single);
		}
		
		return false;
	}
	
	private function safeRedirect($url)
	{
		if (!empty($url)) {
			return wp_safe_redirect($url);
		}
		
		return false;
	}
}