<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2011 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_Model_Payment extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'channelunitypayment';
    protected $_formBlockType = 'channelunity/paymentform';
    protected $_infoBlockType = 'channelunity/paymentinfo';
    protected $_canUseCheckout = false;
    protected $_canUseForMultishipping = false;
    protected $_canUseInternal = false;
    protected $_canCapture = true;
    
    
    public function isAvailable($quote = null){
    	
    	return true;
    	
    }

    /**
     * overwrites the method of Mage_Payment_Model_Method_Cc
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        parent::assignData($data);
        $info = $this->getInfoInstance();

        //	Data stored on payment method:
        //		Remote Order ID
        //		Remote Channel Name
        //		Remote Customer Username

        $info->setRemoteOrderId($data->getRemoteOrderId())
                ->setRemoteChannelName($data->getRemoteChannelName())
                ->setRemoteCustomerUsername($data->getRemoteCustomerUsername());

        return $this;
    }

}

?>