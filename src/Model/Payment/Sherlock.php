<?php

declare(strict_types=1);

namespace ContaoIsotopeSherlockBundle\Model\Payment;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input as ContaoInput;
use Contao\Module;
use Contao\PageModel;
use Contao\RequestToken;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Isotope\Module\Checkout;
use ContaoIsotopeSherlockBundle\Exception\PaymentException;
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

        $this->wrapper->amount = (float) $this->amount * 100;

        $this->wrapper->normalReturnUrl = System::getContainer()->get('router')->generate('sherlock_isotope_postsale', ['mod' => 'pay', 'id' => $this->id], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->wrapper->automaticResponseUrl = System::getContainer()->get('router')->generate('isotope_postsale', ['mod' => 'pay', 'id' => $this->id], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->wrapper->orderId = $this->order->getUniqueId();
        $this->wrapper->customerEmail = $this->payment->billingAddress->email;
        // $this->wrapper->transactionReference = $this->order->id.'TS'.time(); // DO NOT SEND IN PROD §§

        $this->wrapper->paymentInit();
        
        $htmlPageReturnedByAPI = json_decode($this->wrapper->get_message());

        die($htmlPageReturnedByAPI); // Yup, API returns an HTML page to redirect us to their payment interface

        return $objTemplate->parse();
    }

    public function checkPaymentReturn(IsotopeProductCollection $objOrder)
    {
        $this->addLog('CGI 1: order ' . $objOrder->getId());

        $vars = $this->getPostFromRequest();

        $this->wrapper = $this->getWrapper();

        $objTemplate = new FrontendTemplate('wem_iso_sherlock_postsale_result');
        $objTemplate->message = $GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['paymentOk'];
        $objTemplate->backHref = Environment::get('url'); // module checkout "order complete" page

        try{
            if(!array_key_exists('Data',$vars)
            // Encode is not a mandatory key
            || !array_key_exists('Seal',$vars)
            ){
                throw new Exception($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['noDataFromPaymentFound']);
            }

            try{
                $this->wrapper->verifyResponseSecurity($vars['Data'],$vars['Encode'],$vars['Seal']);
            }catch(Exception $e){
                throw new Exception($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['paymentSealNotCorrect']);
            }

            $responseData = $this->wrapper->getResponseDataAsArray($vars['Data'],$vars['Encode']);

            if(!$this->isPaymentOk($responseData)){
                switch($responseData['responseCode']){
                    case "05":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error05']);
                    break;
                    case "17":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error17']);
                    break;
                    case "34":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error34']);
                    break;
                    case "75":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error75']);
                    break;
                    case "90":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error90']);
                    break;
                    case "99":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error99']);
                    break;
                    case "97":
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['error97']);
                    break;
                    default:
                        throw new PaymentException($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['errorGeneric']);
                }
            }else{
                if($objOrder->orderdetails_page){
                   $objTemplate->backHref = PageModel::findByPk($objOrder->orderdetails_page)->getAbsoluteUrl().'?uid=' . $objOrder->uniqid;
                }
                if(!$objOrder->isCheckoutComplete()){
                    if ($objOrder->checkout()) {
                        $objOrder->setDatePaid(time());
                        $objOrder->updateOrderStatus($this->new_order_status);
                        $this->addLog('CGI 2: Order marked as checked out with new status: ' . $this->new_order_status);
                    } else {
                        throw new Exception('Something went wrong when checking out order with valid payment');
                    }
                }else{
                    $this->addLog('CGI 2 : Order already marked as checked out');
                }
            }
        }catch(PaymentException $e){
            if(!$objOrder->isCheckoutComplete()){
                $this->addLog('CGI 2: Payment KO with status - ' . $responseData['responseCode'] . ' and reason - ' . $responseData['redirectionStatusMessage']);
                if (null === $objOrder->getConfig()) {
                    throw new Exception('Config for Order ID ' . $objOrder->getId() . ' not found');
                } else{
                    $objOrder->updateOrderStatus($objOrder->getConfig()->orderstatus_error);
                    if ($objOrder->checkout()) {
                        $this->addLog('CGI 3 : Order marked as checked out with new status: ' . $objOrder->getConfig()->orderstatus_error);
                    } else {
                        throw new Exception('Something went wrong when checking out order with invalid payment');
                    }
                }
            }else{
                $this->addLog('CGI 2 : Order already marked as checked out');
            }
            $objTemplate->error = true;
            $objTemplate->message = $GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['anErrorOccured'];
            $objTemplate->details = sprintf($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['errorDetails'],$e->getMessage());

            $this->addLog('CGI ERR: error - '.$e->getMessage());
        }catch(Exception $e){
            $objTemplate->error = true;
            $objTemplate->message = $GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['anErrorOccured'];
            $objTemplate->details = sprintf($GLOBALS['TL_LANG']['WEM']['isotopeSherlock']['paymentResult']['errorDetails'],$e->getMessage());

            $this->addLog('CGI ERR: error - '.$e->getMessage());

        }


        return new Response($objTemplate->parse());
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

            if($this->order->isCheckoutComplete()){
                $this->addLog('CGI 1: order ' . $this->order->getId() . ' already complete - transaction_id - ' . $responseData['transactionReference']);
                return;
            }

            if($this->isPaymentOk($responseData)){
                $this->addLog('CGI 1: Payment OK with transaction_id - ' . $responseData['transactionReference']);

                if(!$this->order->isCheckoutComplete()){
                    if ($this->order->checkout()) {
                        $this->order->setDatePaid(time());
                        $this->order->updateOrderStatus($this->new_order_status);
                        $this->addLog('CGI 2: Order marked as checked out with new status: ' . $this->new_order_status);
                    } else {
                        throw new Exception('Something went wrong when checking out order with valid payment');
                    }
                }else{
                    $this->addLog('CGI 2 : Order already marked as checked out');
                }
            }else{
                $this->addLog('CGI 1: Payment KO with status - ' . $responseData['responseCode'] . ' and reason - ' . $responseData['redirectionStatusMessage']);
                if (null === $this->order->getConfig()) {
                    throw new Exception('Config for Order ID ' . $this->order->getId() . ' not found');
                } else{
                    $this->order->updateOrderStatus($this->order->getConfig()->orderstatus_error);

                    if(!$this->order->isCheckoutComplete()){
                        if ($this->order->checkout()) {
                            $this->addLog('CGI 2 : Order marked as checked out with new status: ' . $this->order->getConfig()->orderstatus_error);
                        } else {
                            throw new Exception('Something went wrong when checking out order with invalid payment');
                        }
                    }else{
                        $this->addLog('CGI 2 : Order already marked as checked out');
                    }
                }
            }

        }catch(Exception $e) {
            $this->addLog('CGI error: ' . $e->getMessage());
        }
    }

    public static function isPaymentOk(array $responseData): bool
    {
        return '00' === $responseData['responseCode'];
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
            // dump($this->sherlock_merchant_id);
            // dump($encryptionService->decrypt($this->payment->sherlock_merchant_id ?: $this->sherlock_merchant_id));
            // dump($this->sherlock_key_secret);
            // dump($encryptionService->decrypt($this->payment->sherlock_key_secret ?: $this->sherlock_key_secret));
            // dump($this->sherlock_key_version);
            // dump($encryptionService->decrypt($this->payment->sherlock_key_version ?: $this->sherlock_key_version));

        return new Wrapper(
            $encryptionService->decrypt($this->payment->sherlock_merchant_id ?: $this->sherlock_merchant_id),
            $encryptionService->decrypt($this->payment->sherlock_key_secret ?: $this->sherlock_key_secret),
            $encryptionService->decrypt($this->payment->sherlock_key_version ?: $this->sherlock_key_version),
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
