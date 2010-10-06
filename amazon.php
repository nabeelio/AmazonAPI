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

class AmazonError extends Exception 
{
	public $message, $code, $detail;

	public function __construct($message, $code = -1 , $detail = '')
	{
		$this->message = $message;
		$this->code = $code;
		$this->detail = $detail;

		parent::__construct($message, $code, null);
	}

	public function getDetail() 
	{
		return $this->detail;
	}
}

class AmazonProductLookup
{
	protected $AWS_KEY = null;
	protected $SECRET_KEY = null;
	protected $LOCALE = 'US';

	public $USE_SSL = false;
	public $SERVICE_DOMAIN = 'webservices.amazon.';
	public $REQUEST_URI = '/onca/xml';
	
	protected $curl;

	protected $USE_JSON = false;
	protected $json_style = 'http://xml2json-xslt.googlecode.com/svn/trunk/xml2json.xslt';

	public $last_error = null;
	public $last_errordetail = 0;
	public $throw_exceptions = true;

	public $default_args = array(
		'Service' => 'AWSECommerceService',
		'Version' => '2009-03-31'
	);

	protected $locales = array(
		'CA' => 'ca',
		'DE' => 'de',
		'FR' => 'fr',
		'JP' => 'jp',
		'UK' => 'co.uk',
		'US' => 'com',
	);

	public function __construct($AWS_KEY, $SECRET_KEY, $locale = 'US')
	{
		$this->AWS_KEY = $AWS_KEY;
		$this->SECRET_KEY = $SECRET_KEY;
		$this->LOCALE = strtoupper($locale);

		# Build the full URL based on the locale passed in
		$this->SERVICE_DOMAIN = $this->SERVICE_DOMAIN.$this->locales[$this->LOCALE];

		if(!function_exists('curl_init'))
		{
			$this->last_error = 'cURL must exist!';
			return false;
		}

		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 30); 
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
	}

	public function __destruct()
	{
		curl_close($this->curl);
	}

	public function addArgument($name, $value)
	{
		$this->default_args[$name] = $value;
	}

	public function getError() 
	{
		return $this->last_error;
	}

	public function getErrorDetail()
	{
		return $this->last_errordetail;
	}

	public function setSSL($bool)
	{
		$this->USE_SSL = $bool;
	}

	/*public function setJSON($bool)
	{
		$this->USE_JSON = $bool;
		if($this->USE_JSON === false)
		{
			#unset($this->defaultArgs['Style']);
		}
		else
		{
			#$this->defaultArgs['Style'] = $this->json_style;
		}
	}*/

	public function __call($requestName, $args)
	{
		if($this->AWS_KEY === null || $this->SECRET_KEY === null)
		{
			$this->last_error = 'Invalid AWS Key and/or Secret Key';
			$this->last_errordetail = $this->last_error;

			if($this->throw_exceptions === true)
				throw new AmazonError($this->last_error, -1, $this->last_errordetail);
			else
				return false;			
		}

		$args = array_merge($this->default_args, array(
			'AWSAccessKeyId' => $this->AWS_KEY,
			'AWSSecretAccessKey' => $this->SECRET_KEY,
			'Operation' => $requestName,
			'Timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
			), $args[0]
		);

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
		
		# Sign the request and get the HMAC signature code
		$signstring = "GET\n{$this->SERVICE_DOMAIN}\n{$this->REQUEST_URI}\n$query_string";
		$signature = base64_encode(hash_hmac('sha256', $signstring, $this->SECRET_KEY, true));
		$signature = str_replace("%7E", "~", rawurlencode($signature));
		
		if($this->USE_SSL === true)
		{
			$prefix = 'https://';
		}
		else
		{
			$prefix = 'http://';
		}

		$request = "{$prefix}{$this->SERVICE_DOMAIN}{$this->REQUEST_URI}?".$query_string."&Signature=".$signature;

		# Make the request and load the XML
		$response = simplexml_load_string($this->makeRequest($request));
		
		if(!$response)
		{
			$this->last_error = 'Invalid XML';
			$this->last_errordetail = $this->last_error;

			if($this->throw_exceptions === true)
				throw new AmazonError($this->last_error, -1, $this->last_errordetail);
			else
				return false;
		}

		if(isset($response->Error))
		{
			$this->last_error = (string) $response->Error->Code;
			$this->last_errordetail = (string) $response->Error->Message;

			if($this->throw_exceptions === true)
				throw new AmazonError($this->last_error, -1, $this->last_errordetail);
			else
				return false;
		}

		if(isset($response->Items->Request->Errors->Error))
		{
			$this->last_error = (string) $response->Items->Request->Errors->Error->Code;
			$this->last_errordetail = (string) $response->Items->Request->Errors->Error->Message;

			if($this->throw_exceptions === true)
				throw new AmazonError($this->last_error, -1, $this->last_errordetail);
			else
				return false;
		}

		# Finally good
		return $response;
	}

	/**
	 * Make a request via cURL to the web service. Can
	 * be overridden to use something else (file_get_contents(), maybe)
	 * in a child class
	 * 
	 * @param string $url The URL to request
	 * @return Returns the raw data from Amazon
	 */
	protected function makeRequest($url)
	{
		// @TODO: Error handling
		curl_setopt($this->curl, CURLOPT_URL, $url); 
		$response = curl_exec($this->curl);

		if($response === false)
		{
			if($this->throw_exceptions === true)
			{
				throw new Exception ('');
			}
		}

		return $response;
	}
}