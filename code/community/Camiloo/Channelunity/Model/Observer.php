<?php

class Camiloo_Channelunity_Model_Observer extends Camiloo_Channelunity_Model_Abstract {
    
    public function productWasSaved(Varien_Event_Observer $observer) {
        $product = $observer->getEvent()->getProduct();
        
        $storeViewId = $product->getStoreId();

		$xml = "<Products>\n";
        $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                ."</SourceURL>\n";
                
        $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
                
        $xml .= Mage::getModel('channelunity/products')->generateCuXmlForProduct($product);
        
        $xml .= "</Products>\n";
        
        $this->postToChannelUnity($xml, "ProductData");
    }
    
    public function hookToControllerActionPostDispatch($observer) {
     
        if ($observer->getEvent()->getControllerAction()->getFullActionName() 
            == 'adminhtml_catalog_product_action_attribute_save')
        {
            $xml = "<Products>\n";
            $xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                    ."</SourceURL>\n";
                    
            $storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
            $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
                    
            
            $pids = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getProductIds();
            
            foreach ($pids as $productId) {
                
                $product = Mage::getModel('catalog/product')->setStoreId($storeViewId)
                    ->load($productId);
            
                $xml .= Mage::getModel('channelunity/products')->generateCuXmlForProduct($product);
                
            }
            
            $xml .= "</Products>\n";
            
            $this->postToChannelUnity($xml, "ProductData");
        }
        
    }
    
    public function orderWasPlaced(Varien_Event_Observer $observer)
	{
	/*	if (is_object($observer)) {
            
			$ev = $observer->getEvent();
            
			if (is_object($ev)) {
                
				$order = $ev->getOrder();
                
				if (is_object($order)) {
                    
					$items = $order->getAllItems();
                    
					$this->getItemsForUpdateCommon($items);
                    
				}
			}
		}*/
	}
    
    public function getItemsForUpdateCommon($items) {
        
		foreach($items as $item) {
            
            $sku = $item->getSku();
            
            $prodTemp = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
            if (!$prodTemp) {
                
                continue;
            }
            
            // Item was ordered on website, stock will have reduced, update to CU
            $xml = Mage::getModel('channelunity/products')->generateCuXmlForProduct($prodTemp);
            
            $this->postToChannelUnity($xml, "ProductData");
		}
	}
    
    public function onInvoicePaid(Varien_Event_Observer $observer)
	{
	    /*
		if (is_object($observer) && is_object($observer->getInvoice()))
		{
			$order = $observer->getInvoice()->getOrder();
            
			if (is_object($order))
			{
				$items = $order->getAllItems();
				$this->getItemsForUpdateCommon($items);
			}
		}
		*/
	}
    
    /**
     * Order is cancelled and has been saved. post order status change msg to CU
     */
    public function checkForCancellation(Varien_Event_Observer $observer) {
        /*
		$order = $observer->getOrder();
                        
        $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderStatus($order);
        $this->postToChannelUnity($xml, "OrderStatusUpdate");
        */
	}

    /**
     * Send shipment to CU when tracking information is added.
     */
    public function saveTrackingToAmazon(Varien_Event_Observer $observer)
	{
	    /*
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
        */
	}

    public function shipAmazon(Varien_Event_Observer $observer)
    {
        /*
        // TODO
            
	    $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        */
    }
}
?>