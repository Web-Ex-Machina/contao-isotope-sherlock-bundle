<?php

namespace ContaoIsotopeSherlockBundle\Sherlock;

use Exception;

class Wrapper
{
	// Global
    protected $merchant_id = null;
	protected $secret_key = null;
    protected $key_version = null;
	protected $mode = null;
    protected $interfaceVersion = null;
    protected $merchant_id_dev = '002016000000001';
    protected $secret_key_dev = '002016000000001_KEY1';
    protected $key_version_dev = '1';

	// Urls
	protected $api = null;
    protected $api_prod = 'https://sherlocks-payment-webinit.secure.lcl.fr/';
	protected $api_dev = 'https://sherlocks-payment-webinit-simu.secure.lcl.fr/';

	// Config
	protected $data = [];

	// Messages
	protected $message = null;

	// Endpoints
	// paymentInit
	// payment

	public function __construct($merchant_id = '', $secret_key = '', $key_version='1', $data = [], $mode = 'DEV')
    {
        // $mode = 'DEV';
        $this->mode = $mode;
        $this->merchant_id = 'DEV' === $this->mode ? $this->merchant_id_dev : $merchant_id;
        $this->secret_key = 'DEV' === $this->mode ? $this->secret_key_dev : $secret_key;
        $this->key_version = 'DEV' === $this->mode ? $this->key_version_dev : $key_version;
        $this->api = 'DEV' === $this->mode ? $this->api_dev : $this->api_prod;
        $this->data = $data;
        $this->message = array();

        $this->data['keyVersion'] = $this->key_version;
        $this->data['merchantId'] = $this->merchant_id;

        // Setup mandatory vars
        // if (!array_key_exists('interfaceVersion', $this->data)) {
        	// $this->data['interfaceVersion'] = 'HP_3.4'; // REQUEST INVALID IF PRESENT IN DATA FIELD (EVEN IF MANDATORY ACCORDING TO DOCS)
            $this->interfaceVersion = 'HP_3.4';        
        // }

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
    // Mandatory: amount, currencyCode, interfaceVersion, keyVersion, normalReturnUrl, orderChannel, seal
    // Data Example: amount=5500|currencyCode=978|merchantId=011223744550001|normalReturnUrl=http://www.normalreturnurl.com|transactionReference=534654|keyVersion=1
    // Doc: https://sherlocks-documentation.secure.lcl.fr/fr/dictionnaire-des-donnees/paypage/paymentwebinit.html
    public function paymentInit()
    {
        if(!$this->amount){
            throw new Exception('"amount" missing');
        }
        if(!$this->currencyCode){
            throw new Exception('"currencyCode" missing');
        }
        if(!$this->interfaceVersion){
            throw new Exception('"interfaceVersion" missing');
        }
        if(!$this->keyVersion){
            throw new Exception('"keyVersion" missing');
        }
        if(!$this->normalReturnUrl){
            throw new Exception('"normalReturnUrl" missing');
        }
        if(!$this->orderChannel){
            throw new Exception('"orderChannel" missing');
        }

        if('DEV' === $this->mode && !in_array('transactionReference',$this->data)){
            $this->data['transactionReference'] = 'DEV'.$this->data['orderId'].'TS'.time();
        }

        ksort($this->data);
    
        $this->api_method('paymentInit',[
                'Data'=>$this->formatData($this->data),
                'InterfaceVersion'=>$this->interfaceVersion,
                'Seal'=>$this->getSeal($this->formatData($this->data)),
                'Encode'=>'base64',
                'SealAlgorithm'=>'HMAC-SHA-256'
            ],'POST'
        );
    }
    /**
     * Check response seal
     * @see https://sherlocks-documentation.secure.lcl.fr/fr/guide-de-demarrage-rapide.html#Verifier-secu-reponse_
     * @param  string $data   The response's data
     * @param  string $encode The way response's data is encoded
     * @param  string $seal   The response's seal
     * 
     * @throws Exception if seals do not match
     */
    public function verifyResponseSecurity(string $undecodedData, string $encode, string $seal)
    {
        $calculatedSeal = $this->getSeal($undecodedData);

        if(!hash_equals($calculatedSeal,$seal)){
            throw new Exception('Seals do not match');
        }
    }

    public function getResponseDataAsArray(string $data, string $encode)
    {
        $data = $this->decodeResponseData($data, $encode);
        $arr = [];
        foreach (explode('|', $data) as $item){
            $parts = explode('=', $item);
            $arr[trim($parts[0])] = $parts[1];
        }

        return $arr;
    }

    protected function decodeResponseData(string $data, string $encode)
    {
        if('base64' === $encode){
            $data = base64_decode($data);
        }elseif('base64url' === $encode){
            $data = base64_decode(strtr($data, '-_', '+/'));
        }

        return $data;
    }

    protected function formatData(array $data){
        // return http_build_query($data,'','|');
        $str = '';

        foreach($data as $key => $value){
            $str.='|'.$key.'='.$value;
        }

        return base64_encode(mb_convert_encoding(substr($str,1),'UTF-8'));
    }

    /**
     * Return payment seal
     * 
     * Accepted : SHA-1, SHA-256, SHA-512
     */
    protected function getSeal(string $data)
    {
    	return hash_hmac($this->data['sealAlgorithm'] ?: 'sha256', $data, mb_convert_encoding($this->secret_key,'UTF-8'));
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

    /* ----- RESPONSES & MESSAGING ------ */

    private function add_response($resp)
    {
        $str = json_decode($resp);
        if(JSON_ERROR_NONE === json_last_error()){
            $this->message = $str;
        }else{
            $this->message = $resp;
        }
    }

    private function add_message($arg1, $arg2)
    {
        $this->message[$arg1] = $arg2;
    }

    public function show_message()
    {
        print_r(json_encode($this->message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, 4092));
    }

    public function get_message()
    {
        return json_encode($this->message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, 4092);
    }
}