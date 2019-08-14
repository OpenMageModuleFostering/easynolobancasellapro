<?php
/**
 * Class     Token.php
 *
 * @method string getToken()
 * @method EasyNolo_BancaSellaPro_Model_Token setToken(string $token)
 * @method string getExpiryDate()
 * @method EasyNolo_BancaSellaPro_Model_Token setExpiryDate(string $date)
 * @method int getProfileId()
 * @method EasyNolo_BancaSellaPro_Model_Token setProfileId(int $profile_id)
 * @method int getCustomerId()
 * @method EasyNolo_BancaSellaPro_Model_Token setCustomerId(int $customer_id)
 *
 * @category EasyNolo_Bancasellapro
 * @package  EasyNolo
 * @author   Easy Nolo <ecommerce@sella.it>
 */

class EasyNolo_BancaSellaPro_Model_Token extends Mage_Core_Model_Abstract{

    public function _construct()
    {
        $this->_init('easynolo_bancasellapro/token');
    }

    /**
     * Set the profile inside the model
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function setProfile($profile){
        $this->setProfileId($profile->getId());
        $this->setCustomerId($profile->getCustomerId());
        return $this;
    }

    /**
     * Set the token information inside the model
     * @param string $token string with 16 characters
     * @param string $expiryMonth month in 2 digits
     * @param string $expiryYear year in 2 digits
     * @return $this
     */
    public function setTokenInfo($token, $expiryMonth, $expiryYear){
        $date = Mage::getModel('core/date');

        $stringDate = "20$expiryYear-$expiryMonth-01";
        $expiryDate = $date->date('Y-m-t',$stringDate);
        $this->setExpiryDate($expiryDate);
        $this->setToken($token);

        return $this;

    }

    /**
     * Method to check if the token is expiry
     * @return bool
     */
    public function isExpiry()
    {
        $now = strtotime(Varien_Date::now());
        if($now > strtotime($this->getExpiryDate())){
            return true;
        }
        return false;
    }

    /**
     * Method that return a valid token for the sent profile
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function getFirstValidTokenForProfile( Mage_Payment_Model_Recurring_Profile$profile)
    {
        return $this->getCollection()
            ->addProfileToFilter($profile)
            ->addValidDateFilter()
            ->getFirstItem();
    }

    /**
     * Return the first token for the sent profile
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function getFirstTokenForProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this->getCollection()
            ->addProfileToFilter($profile)
            ->getFirstItem();
    }

} 