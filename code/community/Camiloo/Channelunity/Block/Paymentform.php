<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2011 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_Block_Paymentform extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        // the below will never be visible, but its necessary to have a form block.
        $this->setTemplate('channelunity/paymentform.phtml');
    }

}
