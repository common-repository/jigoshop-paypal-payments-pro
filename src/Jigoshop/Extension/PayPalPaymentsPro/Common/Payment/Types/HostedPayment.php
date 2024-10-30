<?php

namespace Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Types;


use Jigoshop\Entity\Customer\Address;
use Jigoshop\Entity\Order;
use Jigoshop\Extension\PaypalPaymentsPro\Common\Payment\PaypalPaymentsPro;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Request\Request;
use Jigoshop\Helper\Currency;

class HostedPayment
{
	private $settings;
	private $returnUrl;
	private static $instance;
	
	private function __construct()
	{
		$this->settings = PaypalPaymentsPro::getSettings();
		
		if($this->settings['testMode']){
			$ssl = false;
		}else{
			$ssl = true;
		}
		$this->returnUrl = HostedPayment::request('?status=paypalPaymentsPro', $ssl);
	}
	
	private static function request($request, $ssl = null)
	{
		if (is_null($ssl)) {
			$scheme = parse_url(get_option('home'), PHP_URL_SCHEME);
		} elseif ($ssl) {
			$scheme = 'https';
		} else {
			$scheme = 'http';
		}
		return esc_url_raw(home_url('/', $scheme) . $request);
	}
	
	public static function getInstance()
	{
		if(null == self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * @var Order $order
	 * @return array
	 * @throws \Exception
	 */
	public function doHostedPayment($order)
	{
		$successUrl = $this->returnUrl . '&paypalProListener=success&order=' . $order->getId();
		$errorUrl = add_query_arg('order', $order->getId(), add_query_arg('paypalProListener', 'error', $this->returnUrl));
		$cancelUrl = add_query_arg('paypalProStatus','cancelPayment',
			add_query_arg('order', $order->getId(),
				add_query_arg('cancelPayment', 'true', home_url('/'))));
		
		$result = array(
			'USER' => $this->settings['user'],
			'VENDOR' => $this->settings['vendor'],
			'PARTNER' => $this->settings['partner'],
			'PWD' => $this->settings['password'],
			"TENDER" => $this->settings['paymentMethod'],
			"TRXTYPE" => $this->settings['transactionType'],
			'BUTTONSOURCE' => 'JigoLtd_SP',
			"CURRENCY" => Currency::code(),
			'INVNUM' => $order->getId(),
			"AMT" => round($order->getTotal(), 2),
			"ORDERID" => $order->getId(),
			"CREATESECURETOKEN" => "Y",
			"SECURETOKENID" => uniqid(), //Should be unique, never used before
			'RETURNURL' => $successUrl,
			'ERRORURL' => $errorUrl,
			'CANCELURL' => $cancelUrl,
			'URLMETHOD' => 'POST',
			'VERBOSITY' => 'HIGH',
			"BILLTOFIRSTNAME" => $this->getBillingAddress($order)->getFirstName(),
			"BILLTOLASTNAME" => $this->getBillingAddress($order)->getLastName(),
			"BILLTOSTREET" => $this->getBillingAddress($order)->getAddress(),
			"BILLTOCITY" => $this->getBillingAddress($order)->getCity(),
			"BILLTOSTATE" => $this->getBillingAddress($order)->getState(),
			"BILLTOZIP" => $this->getBillingAddress($order)->getPostcode(),
			"BILLTOCOUNTRY" => $this->getBillingAddress($order)->getCountry(),
		);
		
		if($this->settings['template'] == 'A'){
			$result['TEMPLATE'] = 'TEMPLATEA';
		}elseif($this->settings['template'] == 'B'){
			$result['TEMPLATE'] = 'TEMPLATEB';
		}else{
			$result['TEMPLATE'] = '';
		}
		if ($this->getShippingAddress($order)->getAddress()) {
			$result["SHIPTOFIRSTNAME"] = $this->getShippingAddress($order)->getFirstName();
			$result["SHIPTOLASTNAME"] = $this->getShippingAddress($order)->getLastName();
			$result["SHIPTOSTREET"] = $this->getShippingAddress($order)->getAddress();
			$result["SHIPTOCITY"] = $this->getShippingAddress($order)->getCity();
			$result["SHIPTOSTATE"] = $this->getShippingAddress($order)->getState();
			$result["SHIPTOZIP"] = $this->getShippingAddress($order)->getPostcode();
			$result["SHIPTOCOUNTRY"] = $this->getShippingAddress($order)->getCountry();
		}
		
		
		return Request::getInstance()->createRequest($result);
	}
	
	/**
	 * @param Order $order
	 * @return Address
	 */
	private function getBillingAddress($order)
	{
		return $order->getCustomer()->getBillingAddress();
	}
	
	/**
	 * @param Order $order
	 * @return Address
	 */
	private function getShippingAddress($order)
	{
		return $order->getCustomer()->getShippingAddress();
	}
}