<?php
/**
 * @category EasyNolo
 * @package  EasyNolo_BancaSellaPro
 * @author   Easy Nolo <ecommerce@sella.it>
 */

class EasyNolo_BancaSellaPro_Model_Observer extends Mage_Core_Model_Abstract{

    /**
     * Metodo richiamato quando viene richiesto il salvataggio del metodo di pagamento con il modulo IWD_Opc
     * @param $observer
     */
    public function addDataToResultSavePaymentIWD($observer){

        $sendParam = $observer->getResult();
        $json = $sendParam->getJson();

        if($encryptString = $observer->getMethod()->getEncryptString()){
            //se il metodo ha l'encryptString allora lo salvo nel result
            $json['encrypt_string'] = $encryptString;
        }

        $observer->getResult()->setJson($json);
    }

    /**
     * Save encrypt string for frontend JS
     * @param $observer
     */
    public function addDataToResultSaveOrderIframe($observer){
        try {
            $action = $observer->getControllerAction();
            if ($response = $action->getResponse()->getBody()) {
                $json = json_decode($response, true);

                if (is_array($json) && !empty($json['success'])) {
                    $payment = $action->getOnepage()->getQuote()->getPayment();
                    if ($payment && $payment->getMethod() == 'gestpaypro') {
                        if ($instance = $payment->getMethodInstance()) {
                            //se il metodo ha l'encryptString allora lo salvo nel result
                            $json['encrypt_string'] = $instance->getEncryptString();
                        }
                    }
                    $action->getResponse()->setBody(json_encode($json));
                }
            }
        } catch (Exception $e) {}

        return $this;
    }

    /**
     * Metodo che si occupa dell'esecuzione dei pagamenti per i profili ricorrenti
     */
    public function chargeRecurringProfiles(){

        //recuperare tutte i profili attivi da eseguire
        $recurringProfieles = Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToFilter('method_code', EasyNolo_BancaSellaPro_Model_Gestpay::METHOD_CODE)
            ->addFieldToFilter('state', Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE)
            ->addFieldToFilter('start_datetime',array('lteq' => Varien_Date::now(true)));

        /** @var EasyNolo_BancaSellaPro_Helper_Data $_helper */
        $_helper = Mage::helper('easynolo_bancasellapro');
        $_helper->log('Cron per il pagamento dei reccuring profile. Ci sono '.count($recurringProfieles).' profili da analizzare');

        foreach($recurringProfieles as $recurringProfile){

            //metodo per deserializzare i dati all'interno del profilo
            $recurringProfieles->getResource()->unserializeFields($recurringProfile);

            //recupero il metodo di pagamento, è certo che è gestpay perché è uno dei criteri della query sopra
            $methodInstance = Mage::helper('payment')->getMethodInstance($recurringProfile->getMethodCode());
            $info = Mage::getModel('payment/info');

            $methodInstance->submitRecurringProfile($recurringProfile, $info);

        }
    }

    public function saveFingerPrint($event){
        $order = $event->getOrder();
        if($payment = Mage::app()->getRequest()->getParam('payment')) {
            if(isset($payment['blackBox']) && $fingerPrint = $payment['blackBox']) {
                $fp = Mage::getModel('easynolo_bancasellapro/sales_order_fingerPrint');
                $fp->setData('order_id', $order->getId());
                $fp->setData('finger_print', $fingerPrint);
                $fp->save();
            }
        }
        return $this;
    }

} 