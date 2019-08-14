<?php
/**
 * @category EasyNolo
 * @package  EasyNolo_BancaSellaPro
 * @author   Easy Nolo <ecommerce@sella.it>
 */
class EasyNolo_BancaSellaPro_Model_Webservice_Wscryptdecrypt extends EasyNolo_BancaSellaPro_Model_Webservice_Abstract{

    const PATH_WS_CRYPT_DECRIPT = '/gestpay/gestpayws/WSCryptDecrypt.asmx?WSDL';

    const TRANSACTION_RESULT_OK = 'OK';
    const TRANSACTION_RESULT_KO = 'KO';
    const TRANSACTION_RESULT_PENDING = 'XX';

    public function getWSUrl(){
        return $this->url_home . self::PATH_WS_CRYPT_DECRIPT;
    }

    /**
     * metodo che imposta i dati dell'ordine all'interno
     * @param Mage_Sales_Model_Order $order
     */
    public function setOrder(Mage_Sales_Model_Order $order){

        /**@var $gestpay EasyNolo_BancaSellaPro_Model_Gestpay */
        $gestpay = $order->getPayment()->getMethodInstance();
        $total = $gestpay->getTotalByOrder($order);

        if($gestpay instanceof EasyNolo_BancaSellaPro_Model_Gestpay){

            $shopTransactionId = Mage::helper('easynolo_bancasellapro')->getShopTransactionId($order);
            $this->setData('shopLogin', $gestpay->getMerchantId());
            $this->setData('shopTransactionId', $shopTransactionId);
            $this->setData('uicCode', $gestpay->getCurrency());
            $this->setData('languageId', $gestpay->getLanguage());
            $this->setData('amount', round($total, 2));

            if($gestpay->isRedEnabled()){
                $this->setData('redEnabled', true);
                $this->setData('orderId', $order->getId());
            }

            if($gestpay->isRiskifiedEnabled()){
                $this->setData('riskifiedEnabled', true);
                $this->setData('orderId', $order->getId());
            }

            if($gestpay->isIframeEnabled()){
                $this->setData('iframeEnabled', true);
            }

            $this->setData('alternativePayments', $gestpay->getAlternativePayments());

            if($gestpay->isTokenizationEnabled()){
                $_recurringProfileHelper = Mage::helper('easynolo_bancasellapro/recurringprofile');
                $this->setData('requestToken', true);
                $this->setData('tokenizationContext', $gestpay->getConfigData('tokenization_context'));
                if($gestpay->canManageRecurringProfiles() && $_recurringProfileHelper->isRecurringProfile($order)){
                    $this->setRecurringProfile(true);
                    $recurringProfile = $_recurringProfileHelper->getRecurringProfile($order);
                    $_newAmount = $_recurringProfileHelper->recalculateAmount($recurringProfile);
                    $this->setData('amount', round($_newAmount, 2));
                }
            }

        }

    }

    public function setInfoBeforeOrder(EasyNolo_BancaSellaPro_Model_Gestpay $method){

        $quote = $method->getQuote();
        $total = $method->getTotalByOrder($quote);
        $shopTransactionId = Mage::helper('easynolo_bancasellapro')->getShopTransactionId($method);

        $this->setData('shopLogin', $method->getMerchantId());
        $this->setData('shopTransactionId', $shopTransactionId);
        $this->setData('uicCode', $method->getCurrency());
        $this->setData('languageId', $method->getLanguage());
        $this->setData('amount', round($total, 2));

        if($method->isTokenizationEnabled()){
            $_recurringProfileHelper = Mage::helper('easynolo_bancasellapro/recurringprofile');
            $this->setData('requestToken', true);
            $this->setData('tokenizationContext', $method->getConfigData('tokenization_context'));
            if($method->canManageRecurringProfiles() && $_recurringProfileHelper->isRecurringProfile($quote)){
                $this->setRecurringProfile(true);
                $recurringProfile = $_recurringProfileHelper->getRecurringProfile($quote);
                $_newAmount = $_recurringProfileHelper->recalculateAmount($recurringProfile);
                $this->setData('amount', round($_newAmount, 2));
            }
        }
    }

    public function setPaymentInfo(Mage_Sales_Model_Order $order) {
        $method = $order->getPayment()->getMethodInstance();
        $additionalData = $method->getInfoInstance()->getAdditionalData();
        if ($additionalData && ($additionalData = @unserialize($additionalData))) {
            if (!empty($additionalData['alternative_payment'])) {
                $method = Mage::helper('easynolo_bancasellapro/alternativePayments')->getMethod($additionalData['alternative_payment']);
                if ($method) {
                    $paymentTypes = array('paymentType' => array());
                    $paymentTypes['paymentType'][] = $method['type'];
                    if (!empty($method['encrypt_helper'])) {
                        $helperPayment = Mage::helper($method['encrypt_helper']);
                        if ($helperPayment) {
                            $additional = $helperPayment->getEncryptParams($order);
                            if ($additional) {
                                $this->setPaymentAdditionalParams($additional);
                            }
                        }
                    }
                    $this->setData('paymentTypes', $paymentTypes);
                }
            }
        }
    }

    /**
     * metodo che restituisce i parametri per creare la stringa criptata per effettuare una richiesta di pagamento a bancasella
     * @return array
     */
    public function getParamToEncrypt(){

        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Imposto i parametri da inviare all\'encrypt');

        $param = array();
        $param['shopLogin'] =  $this->getData('shopLogin');
        $param['shopTransactionId'] =  $this->getData('shopTransactionId');
        $param['uicCode'] =  $this->getData('uicCode');

        if($this->getData('languageId')!=0){
            $param['languageId'] =  $this->getData('languageId');
        }
        $param['amount'] = $this->getData('amount');

        if ($this->getData('paymentTypes')) {
            $param['paymentTypes'] = $this->getData('paymentTypes');
        }

        if($this->getData('requestToken')) {
            $param['requestToken'] = 'MASKEDPAN';
        }

        if($this->getData('redEnabled')){
            Mage::helper('easynolo_bancasellapro/red')->addRedParams($param, $this->getData('orderId'));
        }

        if($this->getData('riskifiedEnabled')){
            Mage::helper('easynolo_bancasellapro/riskified')->addOrderDetailsParams($param, $this->getData('orderId'));
        }

        if ($additional = $this->getPaymentAdditionalParams()) {
            if (is_array($additional)) {
                $param = array_merge_recursive($param, $additional);
            }
        }

        $_helper->log($param);

        return $param;
    }

    /**
     * Metodo che restituisce i dati da inviare per decriptare un pagamento
     * @return array
     */
    public function getParamToDecrypt(){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Imposto i parametri da inviare al decrypt');

        $param = array();
        $param['shopLogin'] =  $this->getParamA();
        $param['CryptedString'] =  $this->getParamB();

        $_helper->log($param);

        return $param;
    }

    /**
     * metodo che importa i risultati dell'encrypt
     * @param $result
     */
    public function setResponseEncrypt($result){

        $realResult = simplexml_load_string($result->EncryptResult->any);

        $this->setTransactionType((string)$realResult->TransactionType);
        $this->setTransactionResult((string)$realResult->TransactionResult);
        $this->setErrorCode((string)$realResult->ErrorCode);
        $this->setErrorDescription((string)$realResult->ErrorDescription);

        if($this->getTransactionResult() == 'OK')
        {
            $this->setCryptDecryptString((string)$realResult->CryptDecryptString);
        }
        else
        {
            Mage::throwException($this->getErrorDescription());
        }
    }
    /**
     * metodo che importa i risultati del decrypt
     * @param $result
     */
    public function setResponseDecrypt($result){

        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati');

        $realResult = simplexml_load_string($result->DecryptResult->any);

        $this->setTransactionType((string)$realResult->TransactionType);
        $this->setTransactionResult((string)$realResult->TransactionResult);
        $this->setErrorCode((string)$realResult->ErrorCode);
        $this->setErrorDescription((string)$realResult->ErrorDescription);

        $this->setShopTransactionID((string)$realResult->ShopTransactionID);
        $this->setBankTransactionID((string)$realResult->BankTransactionID);
        $this->setAuthorizationCode((string)$realResult->AuthorizationCode);
        $this->setCurrency((string)$realResult->Currency);
        $this->setAmount((string)$realResult->Amount);
        $this->setCountry((string)$realResult->Country);
        $this->setCustomInfo((string)$realResult->CustomInfo);
        $this->setBuyerName((string)$realResult->Buyer->BuyerName);
        $this->setBuyerEmail((string)$realResult->Buyer->BuyerEmail);
        $this->setTDLevel((string)$realResult->TDLevel);
        $this->setAlertCode((string)$realResult->AlertCode);

        $this->setAlertDescription((string)$realResult->AlertDescription);
        $this->setVbVRisp((string)$realResult->VbVRisp);
        $this->setVbVBuyer((string)$realResult->VbVBuyer);
        $this->setVbVFlag((string)$realResult->VbVFlag);
        $this->setTransactionKey((string)$realResult->TransactionKey);
        $this->setPaymentMethod((string)$realResult->PaymentMethod);

        //token
        $this->setToken((string)$realResult->TOKEN);
        $this->setTokenExpiryMonth((string)$realResult->TokenExpiryMonth);
        $this->setTokenExpiryYear((string)$realResult->TokenExpiryYear);

        //RED
        // ACCEPT, DENY, CHALLENGE, NOSCORE, ERROR, ENETFP, ETMOUT, EIVINF
        $this->setRedResponseCode((string)$realResult->RedResponseCode);
        $this->setRedResponseDescription((string)$realResult->RedResponseDescription);

        //Riskified
        $this->setRiskResponseCode((string)$realResult->RiskResponseCode);
        $this->setRiskResponseDescription((string)$realResult->RiskResponseDescription);

        $_helper->log($this->getData());

    }

    public function setDecryptParam($a , $b){
        $this->setParamA($a);
        $this->setParamB($b);
    }

    /**
     * Metodo per sapere in modo veloce se il pagamento è stato effettuato
     * @return bool true se lo stato è pagato oppure in attesa di bonifico, false altrimenti
     */
    public function getFastResultPayment(){
        if(!$this->getTransactionResult() || $this->getTransactionResult() == 'KO')
            return false;
        return true;
    }
}