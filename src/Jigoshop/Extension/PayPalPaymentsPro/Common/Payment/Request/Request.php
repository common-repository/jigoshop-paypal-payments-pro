<?php

namespace Jigoshop\Extension\PayPalPaymentsPro\Common\Payment\Request;


use Jigoshop\Extension\PaypalPaymentsPro\Common\Payment\PaypalPaymentsPro;

class Request
{
	private static $instance;
	private function __construct(){}
	
	public static function getInstance()
	{
		if(null == self::$instance){
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	public function createRequest($params)
	{
		$paramList = [];
		
		foreach ($params as $index => $value) {
			$paramList[] = $index . "[" . strlen($value) . "]=" . $value;
		}
		
		$apiStr = implode("&", $paramList);
		
		$url = PaypalPaymentsPro::getEndpoint();
		
		// Initialize our cURL handle.
		$curl = curl_init($url);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $apiStr);
		
		$curlResult = curl_exec($curl);
		if (curl_errno($curl) !== 0) {
			throw new \Exception("cURL error: " . curl_error($curl));
		}
		curl_close($curl);
		
		return $this->parseString($curlResult);
	}
	
	
	private function parseString($str)
	{
		$workstr = $str;
		$out = array();
		while (strlen($workstr) > 0) {
			$loc = strpos($workstr, '=');
			if ($loc === false) {
				// Truncate the rest of the string, it's not valid
				$workstr = "";
				continue;
			}
			
			$substr = substr($workstr, 0, $loc);
			$workstr = substr($workstr, $loc + 1); // "+1" because we need to get rid of the "="
			
			if (preg_match('/^(\w+)\[(\d+)]$/', $substr, $matches)) {
				// This one has a length tag with it.  Read the number of characters
				// specified by $matches[2].
				$count = intval($matches[2]);
				
				$out[$matches[1]] = substr($workstr, 0, $count);
				$workstr = substr($workstr, $count + 1); // "+1" because we need to get rid of the "&"
			} else {
				// Read up to the next "&"
				$count = strpos($workstr, '&');
				if ($count === false) { // No more "&"'s, read up to the end of the string
					$out[$substr] = $workstr;
					$workstr = "";
				} else {
					$out[$substr] = substr($workstr, 0, $count);
					$workstr = substr($workstr, $count + 1); // "+1" because we need to get rid of the "&"
				}
			}
		}
		
		return $out;
	}
}