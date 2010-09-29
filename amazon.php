<?php
/**
* Copyright (c) 2010 Nabeel Shahzad
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.

* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
* @author Nabeel Shahzad
* @copyright Copyright (c) 2009 - 2010, Nabeel Shahzad
* @link http://github.com/nshahzad/AmazonAPI
* @license MIT License
*
*/

class AmazonProductLookup
{
	protected $AWS_KEY;
	protected $SECRET_KEY;

	public $SERVICE_DOMAIN = 'webservices.amazon.com';
	#public $SERVICE_DOMAIN = 'ecs.amazonaws.com';

	protected $use_json = false;
	protected $json_style = 'http://xml2json-xslt.googlecode.com/svn/trunk/xml2json.xslt';

	public $defaultArgs = array(
		'Service' => 'AWSECommerceService',
		'Version' => '2009-03-31'
		);

	public function __construct($AWS_KEY, $SECRET_KEY)
	{
		$this->AWS_KEY = $AWS_KEY;
		$this->SECRET_KEY = $SECRET_KEY;

		//@TODO: Ensure cURL exists
	}

	public function setJSON($bool)
	{
		$this->use_json = $bool;
		if($this->use_json === false)
		{
			unset($this->defaultArgs['Style']);
		}
		else
		{
			$this->defaultArgs['Style'] = $this->json_style;
		}
	}

	public function __call($requestName, $args)
	{
		$defaultArgs = array_merge($this->defaultArgs, array(
			'AWSAccessKeyId' => $this->AWS_KEY,
			'AWSSecretAccessKey' => $this->SECRET_KEY,
			'Operation' => $requestName,
			'Timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
			)
		);

		$args = array_merge($defaultArgs, $args[0]);
		return $this->buildRequest($args);		
	}

	public function buildRequest($params)
	{
		ksort($params, SORT_STRING);
		
		$query_string = array();
		
		foreach ($params as $key => $value)
		{
			$value = str_replace("%7E", "~", rawurlencode($value));
			$query_string[] = trim($key)."=".trim($value);
		}
		
		$query_string = implode("&", $query_string);
		$signstring = "GET\n{$this->SERVICE_DOMAIN}\n/onca/xml\n".$query_string;
		
		$signature = base64_encode(hash_hmac('sha256', $signstring, $this->SECRET_KEY, true));
		$signature = str_replace("%7E", "~", rawurlencode($signature));
		
		$request = "http://{$this->SERVICE_DOMAIN}/onca/xml?".$query_string."&Signature=".$signature;

		return $this->wsCall($request);
	}

	protected function wsCall($url)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($curl, CURLOPT_TIMEOUT, 30); 
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

		// @TODO: Error handling
		$response = curl_exec($curl);

		return $response;
	}
}