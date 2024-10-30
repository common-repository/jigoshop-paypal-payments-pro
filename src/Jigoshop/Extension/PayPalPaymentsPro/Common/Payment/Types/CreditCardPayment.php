<?php

namespace Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Types;


use Jigoshop\Entity\Customer\Address;
use Jigoshop\Entity\Order;
use Jigoshop\Extension\PaypalPaymentsPro\Common\Payment\PaypalPaymentsPro;
use Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Request\Request;
use Jigoshop\Helper\Currency;

class CreditCardPayment
{
	private static $instance;
	
	private $settings;
	
	private function __construct()
	{
		$this->settings = PaypalPaymentsPro::getSettings();
	}
	
	public static function getInstance()
	{
		if(null == self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * @param Order $order
	 * @return array
	 * @internal param $array
	 * @param array $data
	 */
	public function doCreditCardPayment($order, $data)
	{
		$items = [];
		$i = $itemamt = $taxamt = 0;
		
		foreach ($order->getItems() as $item) {
			/** @var Order\Item $item */
			$items['L_NAME' . $i] = $item->getName();
			$items['L_DESC' . $i] = '';
			$items['L_AMT' . $i] = round($item->getPrice(), 2);
			$items['L_NUMBER' . $i] = $item->getId();
			$items['L_QTY' . $i] = $item->getQuantity();
			//TODO: Support for prices including tax
			$items['L_TAXAMT' . $i] = round(($item->getTax() / $item->getQuantity()), 2);
			$itemamt += round($items['L_AMT' . $i] * $items['L_QTY' . $i], 2);
			$taxamt += round($items['L_TAXAMT' . $i] * $items['L_QTY' . $i], 2);
			$i++;
		}

		// Process discounts.
		if($order->getDiscount() > 0) {
			$items['L_NAME' . $i] = __('Discount', 'jigoshop_ppp');
			$items['L_DESC' . $i] = '';
			$items['L_AMT' . $i] = round(-$order->getDiscount(), 2);
			$items['L_NUMBER' . $i] = 0;
			$items['L_QTY' . $i] = 1;

			$itemamt += round(-$order->getDiscount(), 2);

			$i++;
		}

		if($order->getProcessingFee() > 0) {
			$items['L_NAME' . $i] = __('Processing fee', 'jigoshop_ppp');
			$items['L_DESC' . $i] = '';
			$items['L_AMT' . $i] = round($order->getProcessingFee(), 2);
			$items['L_NUMBER' . $i] = 0;
			$items['L_QTY' . $i] = 1;
			$items['L_TAXAMT' . $i] = 0.00;

			$i++;

			$itemamt += round($order->getProcessingFee(), 2);
		}

		$items['ITEMAMT'] = round($itemamt, 2);
		$items['TAXAMT'] = round($taxamt, 2);
		//TODO Support for prices including tax
		$items['SHIPPINGAMT'] = round($order->getShippingPrice(), 2);

		// need to correct roundings of numbers
		$total = $items['ITEMAMT'] + $items['TAXAMT'] + $items['SHIPPINGAMT'];
		$items['SHIPDISCAMT'] = 0.00;

		$shipping = [];
		if ($order->isShippingRequired()) {
			$shipping = [
				'SHIPTONAME' => $this->getShippingAddress($order)->getFirstName(),
				'SHIPTOSTREET' => $this->getShippingAddress($order)->getAddress(),
				'SHIPTOSTREET2' => '',
				'SHIPTOCITY' => $this->getShippingAddress($order)->getCity(),
				'SHIPTOSTATE' => $this->getShippingAddress($order)->getState(),
				'SHIPTOZIP' =>$this->getShippingAddress($order)->getPostcode(),
				'SHIPTOCOUNTRY' => $this->getShippingAddress($order)->getCountry(),
				'SHIPTOPHONENUM' => $this->getShippingAddress($order)->getPhone(),
			];
		}
		
		$args = [
				'TRXTYPE' => $this->settings['transactionType'],
				'TENDER' => 'C',
				'USER' => $this->settings['user'],
				'VENDOR' => $this->settings['vendor'],
				'PARTNER' => $this->settings['partner'],
				'PWD' => $this->settings['password'],
				'AMT' => $total,
				'CURRENCYCODE' => Currency::code(),
				'BUTTONSOURCE' => 'JigoLtd_SP',
				'CREDITCARDTYPE' => $data['cardType'],
				'ACCT' => $data['cardNumber'],
				'EXPDATE' => $data['expMonth'] . $data['expYear'],
				'handling_cart' => '100',
				'CVV2' => $data['cardCvv'],
				'INVNUM' => $order->getId(),
				'BILLTOFIRSTNAME' => $this->getBillingAddress($order)->getFirstName(),
				'BILLTOLASTNAME' =>  $this->getBillingAddress($order)->getLastName(),
				'BILLTOSTREET' =>  $this->getBillingAddress($order)->getAddress(),
				'BILLTOCITY' =>  $this->getBillingAddress($order)->getCity(),
				'BILLTOSTATE' =>  $this->getBillingAddress($order)->getState(),
				'BILLTOZIP' =>  $this->getBillingAddress($order)->getPostcode(),
				'BILLTOCOUNTRY' =>  $this->getBillingAddress($order)->getCountry(),
				'EMAIL' =>  $this->getBillingAddress($order)->getEmail(),
			] + $items + $shipping;

		return Request::getInstance()->createRequest($args);
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