<?php
/**
 * Class     Wss2s.php
 * @category EasyNolo
 * @package  EasyNolo_BancaSellaPro
 * @author   Easy Nolo <ecommerce@sella.it>
 */
class EasyNolo_BancaSellaPro_Model_Webservice_Wss2s extends EasyNolo_BancaSellaPro_Model_Webservice_Abstract{

    const PATH_WS_CRYPT_DECRIPT = '/gestpay/gestpayws/WSS2S.asmx?WSDL';

    public function getWSUrl(){
        return $this->url_home . self::PATH_WS_CRYPT_DECRIPT;
    }


    /**
     * metodo che imposta i dati dell'ordine all'interno
     * @param Mage_Sales_Model_Order $order
     */
    public function setOrder(Mage_Sales_Model_Order $order){

        /**@var $gestpay EasyNolo_BancaSella_Model_Gestpay */
        $gestpay = $order->getPayment()->getMethodInstance();
        $total = $gestpay->getTotalByOrder($order);
        $payment = $order->getPayment();
        $bankId = false;
        $add = unserialize($payment->getAdditionalData());
        if($add){
             $bankId = $add['bank_transaction_id'];
        }

        if($gestpay instanceof EasyNolo_BancaSellaPro_Model_Gestpay){

            $shopTransactionId = Mage::helper('easynolo_bancasellapro')->getShopTransactionId($order);
            $this->setData('shopLogin', $gestpay->getMerchantId() );
            $this->setData('shopTransactionId', $shopTransactionId);
            $this->setData('bankTransactionId', $bankId);
            $this->setData('uicCode', $gestpay->getCurrency() );
            $this->setData('languageId', $gestpay->getLanguage() );
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

        }

    }

    public function setToken(EasyNolo_BancaSellaPro_Model_Token $token){
        $this->setData('tokenValue',$token->getToken());
    }


    /**
     * metodo che restituisce i parametri per creare la stringa criptata per effettuare una richiesta di pagamento a bancasella
     * @return array
     */
    public function getParamToCallPagamS2S($amount){
        if(!$amount) $amount = $this->getData('amount');
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Imposto i parametri da inviare all\'encrypt per effettuare il pagamento con token');

        $param = array();
        $param['shopLogin'] =  $this->getData('shopLogin');
        $param['shopTransactionId'] =  $this->getData('shopTransactionId');
        $param['uicCode'] =  $this->getData('uicCode');
        if($this->getData('bankTransactionId')){
            $param['bankTransactionId'] = $this->getData('bankTransactionId');
        }
        if($this->getData('languageId')!=0){
            $param['languageId'] =  $this->getData('languageId');
        }
        $param['amount'] = $amount;
        $param['tokenValue'] = $this->getData('tokenValue');

        $ap = $this->getData('alternativePayments');
        if(count($ap) > 0 && !$this->getData('iframeEnabled')){
            $param['paymentTypes'] = array('paymentType' => array());
            $param['paymentTypes']['paymentType'][] = 'CREDITCARD';
            foreach ($ap as $alternative_payment){
                $param['paymentTypes']['paymentType'][] = $alternative_payment;
                Mage::helper('easynolo_bancasellapro/alternativePayments_'.strtolower($alternative_payment));
            }
        }

        if($this->getData('redEnabled')){
            Mage::helper('easynolo_bancasellapro/red')->addRedParams($param, $this->getData('orderId'));
        }

        if($this->getData('riskifiedEnabled')){
            Mage::helper('easynolo_bancasellapro/riskified')->addOrderDetailsParams($param, $this->getData('orderId'));
        }


        $_helper->log($param);

        return $param;
    }

    public function getParamToCall3DPaymentS2S($order, $transactionKey, $paRes){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Imposto i parametri da inviare all\'encrypt per effettuare la conferma 3D');
        $gestpay = $order->getPayment()->getMethodInstance();
        $params = array(
            "shopLogin" => $gestpay->getMerchantId(),
            "uicCode" => $gestpay->getCurrency(),
            "amount" => round($gestpay->getTotalByOrder($order), 2),
            "shopTransactionId" => $_helper->getShopTransactionId($order),
            "transKey" => $transactionKey,
            "PARes" => $paRes
        );
        $_helper->log($params);
        return $params;
    }


    public function getParamToCallCaptureS2S($amount){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Imposto i parametri da inviare all\'encrypt per effettuare il pagamento con token');

        $param = array();
        $param['shopLogin'] =  $this->getData('shopLogin');
        $param['shopTransID'] =  $this->getData('shopTransactionId');
        $param['uicCode'] =  $this->getData('uicCode');
        if($this->getData('bankTransactionId')){
            $param['bankTransID'] = $this->getData('bankTransactionId');
        }
        if($this->getData('languageId')!=0){
            $param['languageId'] =  $this->getData('languageId');
        }
        $param['amount'] = $amount;
        //$param['tokenValue'] = $this->getData('tokenValue');

        $_helper->log($param);

        return $param;
    }
    /**
     * metodo che importa i risultati del decrypt
     * @param $result
     */
    public function setResponseCallPagamS2S($result){

        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati');

        $this->_getRealResult($result,'callPagamS2SResult');

    }

    /**
     * metodo che importa i risultati del decrypt
     * @param $result
     */
    public function setResponseCallSettleS2S($result){

        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati');

        $this->_getRealResult($result,'callSettleS2SResult');

    }

    /**
     * metodo che importa i risultati del decrypt
     * @param $result
     */
    public function setResponseCallRefundS2S($result){

        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati');

        $this->_getRealResult($result,'callRefundS2SResult');

    }

    /**
     * metodo che importa i risultati del decrypt
     * @param $result
     */
    public function setResponseCallDeleteS2S($result){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati');

        $this->_getRealResult($result,'callDeleteS2SResult');
    }

    protected function _getRealResult($result,$method){
        $_helper= Mage::helper('easynolo_bancasellapro');
        $_helper->log('Salvo i parametri decriptati - '.$method);

        $realResult = simplexml_load_string($result->$method->any);
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
        $this->setVbVRisp((string)$realResult->VbV->VbVRisp);
        $this->setVbVBuyer((string)$realResult->VbV->VbVBuyer);
        $this->setVbVFlag((string)$realResult->VbV->VbVFlag);
        $this->setTransactionKey((string)$realResult->TransactionKey);
        $this->setPaymentMethod((string)$realResult->PaymentMethod);

        //token
        $this->setData('token',(string)$realResult->TOKEN);
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
        return $this;
    }
}