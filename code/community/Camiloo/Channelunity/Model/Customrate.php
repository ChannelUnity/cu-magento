<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_Model_Customrate extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'channelunitycustomrate';
    protected $_isFixed = true;

    public function isActive()
    {
        return false;
    }

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        // check if we are getting the rate for a CU request.
        // to do this, the easiest way is to check for some form
        // of CU data incoming.
        $cuOrderCheck = Mage::registry('cu_order_in_progress');
        if ($cuOrderCheck == 1) {
            $result = Mage::getModel('shipping/rate_result');

            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier('channelunitycustomrate');
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod('channelunitycustomrate');
            // $method->setMethodTitle(Mage::getSingleton('core/session')->getShippingMethod());

            $shipPrice = Mage::getSingleton('core/session')->getShippingPrice();

            $method->setPrice($shipPrice);
            $method->setCost($shipPrice);

            $result->append($method);

            return $result;
        } else {
            return false;
        }
    }

    public function getAllowedMethods()
    {
        return array('channelunitycustomrate' => $this->getConfigData('name'));
    }

}

?>