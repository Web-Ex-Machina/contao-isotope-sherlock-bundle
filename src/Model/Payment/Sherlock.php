<?php

declare(strict_types=1);

namespace ContaoIsotopeSherlockBundle\Model\Payment;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input as ContaoInput;
use Contao\Module;
use Contao\System;
use ContaoIsotopeSherlockBundle\Sherlock\Wrapper;
use Exception;
use Haste\Input\Input;
use Isotope\Interfaces\IsotopePostsale;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Address;
use Isotope\Model\Payment;
use Isotope\Model\Payment\Postsale;
use Isotope\Model\ProductCollection\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TODO:
 *
 * - Documentation
 * - Add translations into template files
 * - Extract as many logic as possible
 * - Module can use member address as well so need to handle that behaviour too
 * - Unit tests
 * - Add log system depending on the payment mode & log level
 */
class Sherlock extends Postsale implements IsotopePostsale
{
    protected $order;
    protected $module;
    protected $amount;
    protected $payment;
    protected $member;
    protected $billingAddress;
    protected $shippingAddress;
    protected $strFormTemplate = 'mod_wem_iso_sherlock_payment_form';

    public const LOGCATEGORY = 'SHERLOCK';

    /**
     * Return the Sherlock payment form.
     *
     * @param IsotopeProductCollection
     * @param Module
     *
     * @return string
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule)
    {
        $this->getVars($objOrder, $objModule);

        $objTemplate = new FrontendTemplate($this->strFormTemplate);
        $objTemplate->order = $this->order;
        $objTemplate->member = $this->member;
        $objTemplate->amount = $this->amount;

        $this->wrapper = $this->getWrapper();

        $this->wrapper->amount = (int) $this->amount * 100;
        $this->wrapper->normalReturnUrl = Environment::get('url').'/_isotope/postsale/pay/'.$this->order->payment_id;
        // $this->wrapper->normalReturnUrl = 'https://altradplettacmefran.loc/_isotope/postsale/pay/'.$this->order->payment_id;
        $this->wrapper->keyVersion = 1;
        $this->wrapper->orderId = $this->order->getUniqueId();
        $this->wrapper->customerEmail = $this->payment->billingAddress->email;
        $this->wrapper->transactionReference = $this->order->id.'A'.time();

        $this->wrapper->paymentInit();
        
        $r = (array) json_decode($this->wrapper->get_message());
        die($r[0]);
        // if(JSON_ERROR_NONE !== json_last_error() || empty($r)){
            
        //     $objTemplate->error = true;
        //     $objTemplate->message = "wesh";

        //     return $objTemplate->parse();
        // }

        // if ('00' !== $r['redirectionStatusCode']) {
        //     $objTemplate->error = true;
        //     $objTemplate->message = $r['redirectionStatusMessage'];

        //     return $objTemplate->parse();
        // }

        // $objTemplate->redirectionURL = $r['redirectionURL'];
        // $objTemplate->redirectionVersion = $r['redirectionVersion'];
        // $objTemplate->redirectionData = $r['redirectionData'];

        return $objTemplate->parse();
    }

    public function getPostsaleOrder()
    {
        $vars = $this->getPostFromRequest();

        $this->wrapper = $this->getWrapper();

        $blnError = false;

        try{
            $this->wrapper->verifyResponseSecurity($vars['Data'],$vars['Encode'],$vars['Seal']);
        }catch(Exception $e){
            $blnError = true;
            // $this->addLog(sprintf('CGI 0 : CGI callback for order %s', $orderId));
        }

        $responseData = $this->wrapper->getResponseDataAsArray($vars['Data'],$vars['Encode']);

        $orderId = $responseData['orderId'];

        $this->addLog(sprintf('CGI 0 : CGI callback for order %s', $orderId));

        if($blnError){
            $this->addLog(sprintf('CGI 0 : Error for order %s - seals dot not match !', $orderId));
        }
        
        if (null === $orderId) {
            return null;
        }

        return Order::findOneBy('uniqid',$orderId);
    }

    /**
     * Process payment on checkout page.
     * @param   IsotopeProductCollection    The order being places
     * @param   Module                      The checkout module instance
     * @return  mixed
     */
    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        try{
            $this->getVars($objOrder, null);
            $this->addLog('CGI 0 : Call du retour CGI');

            $vars = $this->getPostFromRequest();
            $this->wrapper = $this->getWrapper();
            $this->wrapper->verifyResponseSecurity($vars['Data'],$vars['Encode'],$vars['Seal']);

            $responseData = $this->wrapper->getResponseDataAsArray($vars['Data'],$vars['Encode']);

            if('00' === $responseData['responseCode']){
                $this->addLog('CGI 1: Payment OK with transaction_id - ' . $responseData['transactionReference']);

                if ($this->order->checkout()) {
                    $this->order->setDatePaid(time());
                    $this->order->updateOrderStatus($this->new_order_status);
                    $this->addLog('CGI 2: Order marked as checked out with new status: ' . $this->new_order_status);
                } else {
                    throw new Exception('Something went wrong when checking out order with valid payment');
                }
            }else{
                $this->addLog('CGI 1: Payment KO with status - ' . $responseData['responseCode'] . ' and reason - ' . $responseData['redirectionStatusMessage']);
                if (null === $this->order->getConfig()) {
                    throw new Exception('Config for Order ID ' . $this->order->getId() . ' not found');
                } elseif ($this->order->checkout()) {
                    $this->order->updateOrderStatus($this->order->getConfig()->orderstatus_error);
                    $this->addLog('CGI 2 : Order marked as checked out with new status: ' . $this->order->getConfig()->orderstatus_error);
                } else {
                    throw new Exception('Something went wrong when checking out order with invalid payment');
                }
            }

        }catch(Exception $e) {
            $this->addLog('CGI error: ' . $e->getMessage());
        }
    }
    
    protected function getReference()
    {
        return $this->order->getDocumentNumber();
    }

    /**
     * Format & return order amount
     * 
     * @return double
     */
    protected function getAmount()
    {
        return number_format(floatval($this->order->getTotal()), 2, ".", "");
    }

    protected function getPostFromRequest()
    {
        return $this->getRequest()->request->all();
    }

    protected function getOrderIdFromRequest()
    {
        return str_replace('REF', '', ContaoInput::get('reference'));
        // $parameters = $this->getPostFromRequest();

        // return str_replace('REF', '', $parameters['reference']);
    }

    protected function getVars(IsotopeProductCollection $objOrder, Module $objModule = null)
    {
        $this->order = $objOrder;
        $this->module = $objModule;
        $this->billingAddress = $objOrder->getRelated('billing_address_id');
        $this->shippingAddress = $objOrder->getRelated('shipping_address_id');
        $this->amount = $this->getAmount();
        $this->payment = $this->order->getRelated('payment_id');
        $this->member = $this->order->getRelated('member');
        $this->reference = $this->getReference();
    }

    private function getWrapper()
    {
        // Retrieve Encyption service
        $encryptionService = System::getContainer()->get('plenta.encryption');

        return new Wrapper(
            $encryptionService->decrypt($this->payment->sherlock_merchant_id ?: $this->sherlock_merchant_id),
            $encryptionService->decrypt($this->payment->sherlock_key_secret ?: $this->sherlock_key_secret),
            [],
            $this->payment->sherlock_mode?: $this->sherlock_mode
        );
    }

    /**
     * Return the Symfony Request object of the current request.
     */
    protected function getRequest(): Request
    {
        return System::getContainer()->get('request_stack')->getCurrentRequest();
    }

    /**
     * Log system
     */
    private function addLog($msg): void
    {
        // use debug_backtrace() to retrieve the last method
        System::log($msg, __METHOD__, self::LOGCATEGORY);
    }
}
