<?php

namespace Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Process;


use Jigoshop\Extension\PaypalPaymentsPro\Common\Payment\PaypalPaymentsPro;
use Jigoshop\Integration;
use Jigoshop\Entity\Order;

class Process
{
	private static $instance;
	private $orderService;
	private $messages;
	
	private function __construct()
	{
		$this->orderService = Integration::getOrderService();
		$this->messages = Integration::getMessages();
	}
	
	private function __clone()
	{
	}
	
	public static function getResponse()
	{
		if (null == self::$instance) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * @param Order $order Order to process payment for.
	 * @param $result.
	 *
	 * @return string URL to redirect to.
	 * @throws Exception On any payment error.
	 */
	public function doPaypalProCreditCardPayment($order, $result)
	{
		if(!empty($result)){
			if(isset($result['RESULT'])){
				if($result['RESULT'] == '0'){
					if($result['RESPMSG'] == 'Approved'){
						$status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
						$order->setStatus($status,
							__('Payment Completed. Authcode: ' . esc_attr($result['AUTHCODE'] . 'Transaction ID:' . esc_attr($result['PNREF']))));
						$this->orderService->save($order);
						$redirect = \Jigoshop\Helper\Order::getThankYouLink($order);
						$redirect .= '&ppp_fee=true';
						return $redirect;
					}
				}else{
					$order->setStatus(Order\Status::PENDING, __('Payment Failed. Reference:: ' . esc_attr($result['PNREF'] . 'Message:' . esc_attr($result['RESPMSG']))));
					//
					$this->orderService->save($order);
					$this->messages->addError(sprintf(__('Something went wrong and payment could not be proceed. Please try again later. >>> %s'), $this->validate($result['RESPMSG'])));
					
					return \Jigoshop\Helper\Order::getPayLink($order);
				}
			}
		}
		
		return \Jigoshop\Helper\Order::getPayLink($order);
	}
	
	/**
	 * @param Order $order Order to process payment for.
	 * @param $result.
	 * @return string URL to redirect to.
	 */
	public function doPayPalProHostedPayment($order, $result)
	{
		if (isset($result['RESULT'])) {
			if ($result['RESULT'] == 0) {
				$secureToken = '';
				if (isset($result['SECURETOKEN'])) {
					$secureToken = $result['SECURETOKEN'];
				}
				$secureTokenId = '';
				if (isset($result['SECURETOKENID'])) {
					$secureTokenId = $result['SECURETOKENID'];
				}
				if(!empty($secureToken)){
					$this->updateOrderMeta($order->getId(), 'secureToken', $secureToken);
					/** @noinspection PhpUnusedLocalVariableInspection */
					$url = esc_url(PaypalPaymentsPro::hostedEndpoint()) .
						'?SECURETOKEN=' . strip_tags($secureToken) .
						'&SECURETOKENID=' . strip_tags($secureTokenId);
					
					return $url;
				}
				
				return \Jigoshop\Helper\Order::getPayLink($order);
				
			} else {
				$errorMsg = sprintf(__('Error occurred setting up the order. Error message: <strong>%s</strong>. Order was canceled', 'jigoshop_ppp'), $result['RESPMSG']);
				$order->setStatus(Order\Status::CANCELLED, sprintf(__('Payment by Paypal was canceled. Message: %s. ', 'jigoshop_ppp'), $errorMsg));
				$this->orderService->save($order);
				$this->messages->addError($errorMsg);
				return \Jigoshop\Helper\Order::getPayLink($order);
			}
		}
		
		return \Jigoshop\Helper\Order::getPayLink($order);
	}
	
	/**
	 * @param $orderId
	 * @param $metaKey
	 * @param $metaValue
	 * @return bool|int
	 */
	private function updateOrderMeta($orderId, $metaKey, $metaValue)
	{
		if(!empty($orderId)){
			return update_post_meta($orderId, $metaKey, $metaValue);
		}
		
		return false;
	}
	
	private function validate($settings)
	{
		return trim(strip_tags(esc_attr($settings)));
	}
}