<?php

namespace Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Response;

use Jigoshop\Entity\Order;
use Jigoshop\Integration;

class Response
{
	private static $instance;
	private $orderService;
	private $messages;
	
	private function __construct()
	{
		$this->orderService = Integration::getOrderService();
		$this->messages = Integration::getMessages();
	}
	
	private function __clone(){}
	
	public static function getResponse()
	{
		if(null == self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	public function getSuccessResponse(array $response)
	{
		//paypalProListener=success
		if(isset($_GET['paypalProListener']) && $_GET['paypalProListener'] == 'success'){
			if(isset($response['RESPMSG'])){
				if($response['RESPMSG'] == 'Approved'){
					$orderID = strip_tags((int)$response['ORDERID']);
					if(isset($response['RESULT']) && $response['RESULT'] == '0'){
						/**@var Order $order*/
						$order = $this->orderService->find($orderID);
						$status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
						if(!empty($response['SECURETOKEN'])){
							$secureToken = $this->getOrderMeta($order->getId(), 'secureToken', true);
							if($secureToken == $response['SECURETOKEN']){
								$order->setStatus($status, __('Payment Completed. Order ID: ' . $this->validate($response['PNREF']) .
									'Auth Code: ' . $this->validate($response['AUTHCODE'])));
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
		} else{
			// No reason
			if(isset($_GET['paypalProListener']) && $_GET['paypalProListener'] == 'return'){
				$orderID = $this->validate((int)$_GET['order']);
				/**@var Order $order*/
				$order = $this->orderService->find($orderID);
				$redirect = \Jigoshop\Helper\Order::getPayLink($order);
				$this->messages->addError(__('It seems something went wrong and payment could not be completed. Please try again later.', 'jigoshop_ppp'));
				$this->safeRedirect($redirect);
				exit;
			}
		}
	}
	
	/**
	 * @param array $response
	 */
	public function getErrorPayment(array $response)
	{
		if(isset($response['status'])){
			if(isset($response['paypalProListener']) && $response['paypalProListener'] == 'error'){
				if(isset($_POST['SECURETOKEN'])){
					$orderID = $this->validate((int)$response['order']);
					$_posted = $_POST;
					if($_posted['RESULT'] == '125' || $_posted['RESULT'] == '126'){
						/**@var Order $order*/
						$order = $this->orderService->find($orderID);
						$order->setStatus(Order\Status::ON_HOLD, sprintf(__('Payment was rejected by Fraud Service, reason: <strong>%s</strong>. More info: <stron>%s</stron>. Please contact the PayPal Service'),
							strip_tags($_posted['RESPMSG']), strip_tags($_posted['PREFPSMSG'])));
						$this->orderService->save($order);
						$redirect = \Jigoshop\Helper\Order::getPayLink($order);
						$this->messages->addError(sprintf(__('Payment was rejected by Fraud Service. Please contact the site administrator to resolve the problem.
							Possible reason: <strong> ( %s ) </strong>. ', 'jigoshop_ppp'), $this->validate($_posted['PREFPSMSG'])));
						$this->safeRedirect($redirect);
						exit;
					}else{
						if($_posted['RESULT'] != '0'){
							if($_posted['RESULT'] != '125' || $_posted['RESULT'] != '126' ){
								/**@var Order $order*/
								$order = $this->orderService->find($orderID);
								$order->setStatus(Order\Status::ON_HOLD, sprintf(__('Payment was rejected by PayPal, reason: <strong> %s </strong>.
								More info: <strong> %s </strong>. Please contact the PayPal Service'),
									strip_tags($_posted['RESPMSG']), strip_tags($_posted['PREFPSMSG'])));
								$this->orderService->save($order);
								$redirect = \Jigoshop\Helper\Order::getPayLink($order);
								$this->messages->addError(sprintf(__('Payment was rejected by PayPal. Please contact the site administrator to resolve the problem.
								Possible reason: %s. ', 'jigoshop_ppp'), $this->validate($_posted['PREFPSMSG'])));
								$this->safeRedirect($redirect);
								exit;
							}
						}
					}
				}
			}
		}
	}
	
	public function getCancelPayment($response)
	{
		if(isset($response['cancelPayment'])){
			if($response['cancelPayment'] == 'true'){
				$orderID = $this->validate((int)$response['order']);
				if(isset($response['paypalProStatus']) && $response['paypalProStatus'] == 'cancelPayment'){
					/**@var Order $order*/
					$order = $this->orderService->find($orderID);
					$redirect = \Jigoshop\Helper\Order::getCancelLink($order);
					$this->safeRedirect($redirect);
					exit;
				}
			}
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
		if(!empty($orderId)){
			return get_post_meta($orderId, $metaKey, $single);
		}
		
		return false;
	}
	
	private function safeRedirect($url)
	{
		if(!empty($url)){
			return wp_safe_redirect($url);
		}
		
		return false;
	}
	
	private function validate($settings)
	{
		return trim(strip_tags(esc_attr($settings)));
	}
}