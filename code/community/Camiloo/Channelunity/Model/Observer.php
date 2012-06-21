<?php
/**
 * ChannelUnity connector for Magento Commerce 
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * ChannelUnity observers.
 * Posts events to the CU cloud when various Magento events occur.
 */
class Camiloo_Channelunity_Model_Observer extends Camiloo_Channelunity_Model_Abstract {
    
    /**
     * Called on saving a product in Magento.
     */
    public function productWasSaved(Varien_Event_Observer $observer) {
        try {
            $product = $observer->getEvent()->getProduct();
            
            $storeViewId = $product->getStoreId();

            $xml = "<Products>\n";
            $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                    ."</SourceURL>\n";
                    
            $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
            
            $xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct($product->getId(), $storeViewId);
            
            $xml .= "</Products>\n";
            
            $this->postToChannelUnity($xml, "ProductData");
        }
        catch (Exception $x) {
        }
    }
    
    /**
     * Called on deleting a product in Magento.
     */
    public function productWasDeleted(Varien_Event_Observer $observer) {
    	try {
            $product = $observer->getEvent()->getProduct();
            
            $storeViewId = $product->getStoreId();
            
            $xml = "<Products>\n";
            $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
            ."</SourceURL>\n";
            $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
            $xml .= "<ProductID>{$product->getId()}</ProductID>\n";
            $xml .= "<Deleted>TRUE</Deleted>\n";
            
            $xml .= "</Products>\n";
            
            $this->postToChannelUnity($xml, "ProductData");
            
        }
        catch (Exception $x) {
        }
    }
    
    /**
     * Allows the observing of more generic events in Magento.
     * Useful in multiple product save for example.
     */
    public function hookToControllerActionPostDispatch($observer) {
        try {
            $evname = $observer->getEvent()->getControllerAction()->getFullActionName();
            
            if ($evname == 'adminhtml_catalog_product_action_attribute_save')
            {
                $xml = "<Products>\n";
                $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                        ."</SourceURL>\n";
                        
                $storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
                $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
                
                $pids = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getProductIds();
                
                foreach ($pids as $productId) {
                    $xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct(
                                                                     $productId, $storeViewId);
                }
                
                $xml .= "</Products>\n";
                
                $this->postToChannelUnity($xml, "ProductData");
            }
            else if ($evname == 'adminhtml_catalog_category_save') {
                
                $this->categorySave($observer);
            }
            else if ($evname == 'adminhtml_catalog_category_delete') {
                
                $this->categoryDelete($observer);
            }
            else if ($evname == 'adminhtml_catalog_product_delete') {
                $xml = "<Products>\n";
                $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                ."</SourceURL>\n";
                
                $storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
                $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
                
                $productId = $observer->getEvent()->getControllerAction()->getRequest()->getParam('id');
                
                $xml .= "<DeletedProductId>".$productId."</DeletedProductId>\n";
                
                $xml .= "</Products>\n";
                
                $this->postToChannelUnity($xml, "ProductData");
            }
        }
        catch (Exception $x) {
        }
    }
    
    /**
     * Called on placing an order. Stock levels are updated on CU.
     */
    public function orderWasPlaced(Varien_Event_Observer $observer)
	{
        try {
            if (is_object($observer)) {
                
                $ev = $observer->getEvent();
                
                if (is_object($ev)) {
                    
                    $order = $ev->getOrder();
                    
                    if (is_object($order)) {
                        
                        $items = $order->getAllItems();
                        
                        $this->getItemsForUpdateCommon($items, $order->getStore()->getId());
                        
                    }
                }
            }
        }
        catch (Exception $x) {
        }
	}
    
    public function getItemsForUpdateCommon($items, $storeId) {
        try {
            foreach ($items as $item) {
                
                $sku = $item->getSku();
                
                $prodTemp = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
                if (!$prodTemp) {
                    
                    continue;
                }
                
                // Item was ordered on website, stock will have reduced, update to CU
                $xml = Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct(
                                                                  $prodTemp->getId(), $storeId);
                
                $this->postToChannelUnity($xml, "ProductData");
            }
        }
        catch (Exception $x) {
        }
	}
    
    public function onInvoicePaid(Varien_Event_Observer $observer)
	{
        try {
            if (is_object($observer) && is_object($observer->getInvoice()))
            {
                $order = $observer->getInvoice()->getOrder();
                
                if (is_object($order))
                {
                    $items = $order->getAllItems();
                    $this->getItemsForUpdateCommon($items, $order->getStore()->getId());
                }
            }
        }
        catch (Exception $x) {
        }
	}
    
    /**
     * Order is cancelled and has been saved. post order status change msg to CU
     */
    public function checkForCancellation(Varien_Event_Observer $observer) {
        try {
            $order = $observer->getOrder();
            
            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderStatus($order);
            $this->postToChannelUnity($xml, "OrderStatusUpdate");
        }
        catch (Exception $x) {
        }
	}

    /**
     * Send shipment to CU when tracking information is added.
     */
    public function saveTrackingToAmazon(Varien_Event_Observer $observer)
	{
        try {
            // Only mark as shipped when order has tracking information.
            $track = $observer->getEvent()->getTrack();
            $order = $track->getShipment()->getOrder();
                    
            if ($track->getCarrierCode() == "custom") {
                $carrierName = $track->getTitle();	
            } else {
                $carrierName = $track->getCarrierCode();
            }
            
            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderShip($order,
                                                                                    $carrierName,
                                                                                    $track->getTitle(),
                                                                                    $track->getNumber());
            $this->postToChannelUnity($xml, "OrderStatusUpdate");
        }
        catch (Exception $x) {
        }
	}

    public function shipAmazon(Varien_Event_Observer $observer)
    {
        try {
            $shipment = $observer->getEvent()->getShipment();
            $order = $shipment->getOrder();
            
            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderShip($order,
                                                                                    "",
                                                                                    "",
                                                                                    "");
            $this->postToChannelUnity($xml, "OrderStatusUpdate");
        }
        catch (Exception $x) {
        }
    }
    
    /**
     * Category is saved. CU needs to know about it.
     */
    public function categorySave(Varien_Event_Observer $observer) {
        try {
            $myStoreURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $categoryStatus = Mage::getModel('channelunity/categories')->postCategoriesToCU($myStoreURL);
        }
        catch (Exception $x) {
        }
    }
    
    public function configSaveAfter(Varien_Event_Observer $observer) {
        try {
            if (is_object($observer)) {
                $event = $observer->getEvent();
                
                if (is_object($event)) {
                    
                    $configData = $event->getData('config_data');
                    
                    if (is_object($configData)) {
                        
                        $configData = $configData->getData();
                        
                        if (isset($configData['fieldset_data'])) {
                            
                            $fieldset_data = $configData['fieldset_data'];
                        
                            
                            if (isset($fieldset_data['merchantname'])) {
                                
                                $merchantName = $fieldset_data['merchantname'];
                                
                                Mage::getModel('channelunity/products')->postMyURLToChannelUnity($merchantName);
                                
                            }
                        
                        }
                    }
                }
            }
        }
        catch (Exception $x) {
        }
    }
}
?>