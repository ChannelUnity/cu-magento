<?php

/**
 * Camiloo Limited
 *
 * NOTICE OF LICENSE

 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2011 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://www.camiloo.co.uk/license.txt
 */
class Camiloo_Channelunity_Block_Configheader extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    protected $_template = 'channelunity/configheader.phtml';

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->toHtml();
    }

}