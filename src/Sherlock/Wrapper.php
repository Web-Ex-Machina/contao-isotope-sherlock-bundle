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
    }

    // paymentInit endpoint
    // Mandatory: Data, InterfaceVersion, Seal
    // Optional: Encode, SealAlgorithm
    // Data Example: amount=5500|currencyCode=978|merchantId=011223744550001|normalReturnUrl=http://www.normalreturnurl.com|transactionReference=534654|keyVersion=1
    // Doc: https://sherlocks-documentation.secure.lcl.fr/fr/dictionnaire-des-donnees/paypage/paymentwebinit.html
    public function paymentInit()
    {
    	$url = $this->api . 'paymentInit';

    	
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
}