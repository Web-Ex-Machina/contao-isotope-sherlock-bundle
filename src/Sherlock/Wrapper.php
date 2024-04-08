<?php

namespace ContaoIsotopePaytweakBundle\Sherlock;

class Wrapper
{
	// Global
    protected $merchant_id = null;
	protected $secret_key = null;
	protected $mode = null;

	// Urls
	protected $api = null;
	public $api_prod = 'https://sherlocks-paiement.secure.lcl.fr/';
	public $api_dev = 'https://sherlocks-payment-webinit-simu.secure.lcl.fr/';

	// Config
	protected $data = [];

	// Messages
	protected $message = null;

	// Endpoints
	// paymentInit
	// payment

	public function __construct($merchant_id = '', $secret_key = '', $data = [], $mode = 'DEV')
    {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        $this->mode = $mode;
        $this->api = 'DEV' === $this->mode ? $this->api_dev : $this->api_prod;
        $this->data = $data;
        $this->message = array();

        $this->data['keyVersion'] = $this->secret_key;
        $this->data['merchantId'] = $this->merchant_id;

        // Setup mandatory vars
        if (!array_key_exists('interfaceVersion', $this->data)) {
        	$this->data['interfaceVersion'] = 'HP_3.2';
        }

        if (!array_key_exists('currencyCode', $this->data)) {
        	$this->data['currencyCode'] = '978';
        }

        if (!array_key_exists('orderChannel', $this->data)) {
        	$this->data['orderChannel'] = 'INTERNET';
        }   
    }

    public function __get($key)
    {
    	return $this->data[$key];
    }

    public function __set($key, $value)
    {
    	$this->data[$key] = $value;
    }

    // paymentInit endpoint
    // Mandatory: amount, currencyCode, interfaceVersion, keyVersion, normalReturnUrl, orderChannel Seal
    // Data Example: amount=5500|currencyCode=978|merchantId=011223744550001|normalReturnUrl=http://www.normalreturnurl.com|transactionReference=534654|keyVersion=1
    // Doc: https://sherlocks-documentation.secure.lcl.fr/fr/dictionnaire-des-donnees/paypage/paymentwebinit.html
    public function paymentInit()
    {
    	$this->api_method('paymentInit');

    	
    }

    /**
     * Return payment seal
     * 
     * Accepted : SHA-1, SHA-256, SHA-512
     */
    protected function getSeal()
    {
    	return hash_hmac($this->data['hashAlgorithm1'] ?: 'sha256', $this->data, $this->secret_key);
    }


    public function api_method($endpoint, $args, $type = 'POST')
    {
    	$url = $this->api . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

        if ('POST' === $type) {
        	$query = http_build_query($args);
        	curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        $result = curl_exec($ch);

        $this->message = array();
        $this->add_response($result);
        curl_close($ch);
    }
}