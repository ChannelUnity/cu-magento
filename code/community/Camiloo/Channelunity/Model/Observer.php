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
class Camiloo_Channelunity_Model_Observer extends Camiloo_Channelunity_Model_Abstract
{

    /**
     * Called on saving a product in Magento.
     */
    public function productWasSaved(Varien_Event_Observer $observer)
	{
	try {
            $product = $observer->getEvent()->getProduct();

			$skipProduct = Mage::getModel('channelunity/products')->skipProduct($product);
			
		//$storeViewId = $product->getStoreId();
		$allStores = Mage::app()->getStores();
		foreach ($allStores as $_eachStoreId => $val) 
		{
		$storeId = Mage::app()->getStore($_eachStoreId)->getId();
			
			if(!$skipProduct)
			{

				$xml = "<Products>\n";
				
				$xml .= "<SourceURL>".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)."</SourceURL>\n";

				$xml .= "<StoreViewId>".$storeId."</StoreViewId>\n";

				$xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct($product->getId(), $storeId);

				$xml .= "</Products>\n";

				$this->postToChannelUnity($xml, "ProductData");
				
			} else {
				$xml = "<Products>\n";
			
				$xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)."</SourceURL>\n";
				
				$xml .= "<StoreViewId>".$storeId."</StoreViewId>\n";

				$xml .= "<DeletedProductId>{$product->getId()}</DeletedProductId>\n";

				$xml .= "</Products>\n";

				$this->postToChannelUnity($xml, "ProductData");
				
			}
		}
        }
        catch (Exception $x) {
			Mage::logException($x);

        }
    }

    /**
     * Called on deleting a product in Magento.
     */
    public function productWasDeleted(Varien_Event_Observer $observer)
    {
        try {
            $product = $observer->getEvent()->getProduct();

            $storeViewId = $product->getStoreId();

            $xml = "<Products>\n";
			
            $xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                    . "</SourceURL>\n";
            $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";
			
            $xml .= "<DeletedProductId>{$product->getId()}</DeletedProductId>\n";

            $xml .= "</Products>\n";

            $this->postToChannelUnity($xml, "ProductData");
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Allows the observing of more generic events in Magento.
     * Useful in multiple product save for example.
     */
    public function hookToControllerActionPostDispatch($observer)
    {
        try {
            $evname = $observer->getEvent()->getControllerAction()->getFullActionName();

            if ($evname == 'adminhtml_catalog_product_action_attribute_save') {
                $xml = "<Products>\n";
                $xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                        . "</SourceURL>\n";

                $storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
                $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";

                $pids = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getProductIds();

                foreach ($pids as $productId) {
                    $xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct(
                            $productId, $storeViewId);
                }

                $xml .= "</Products>\n";

                $this->postToChannelUnity($xml, "ProductData");
            } else if ($evname == 'adminhtml_catalog_category_save') {

                $this->categorySave($observer);
            } else if ($evname == 'adminhtml_catalog_category_delete') {

                $this->categoryDelete($observer);
            } else if ($evname == 'adminhtml_catalog_product_delete') {
                $xml = "<Products>\n";
                $xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                        . "</SourceURL>\n";

                $storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
                $xml .= "<StoreViewId>$storeViewId</StoreViewId>\n";

                $productId = $observer->getEvent()->getControllerAction()->getRequest()->getParam('id');

                $xml .= "<DeletedProductId>" . $productId . "</DeletedProductId>\n";

                $xml .= "</Products>\n";

                $this->postToChannelUnity($xml, "ProductData");
            } else if ($evname == 'adminhtml_catalog_product_massStatus') { //update all products status on the massive status update
				
				$updatedProductsId = $observer->getEvent()->getControllerAction()->getRequest()->getParam('product');
				$status = $observer->getEvent()->getControllerAction()->getRequest()->getParam('status');
				
				if(is_array($updatedProductsId) && !empty($updatedProductsId))
				{
					$storeViewId = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getSelectedStoreId();
					
					$xml = "<Products>\n";
					$xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)."</SourceURL>\n";
					$xml .= "<StoreViewId>{$storeViewId}</StoreViewId>\n";
               
					foreach ($updatedProductsId as $productId)
					{
						$product = Mage::getModel('catalog/product')->load($productId);
						
						$skipProduct = Mage::getModel('channelunity/products')->skipProduct($product);
						
						if($skipProduct)
						{
							$xml .= "<DeletedProductId>" . $productId . "</DeletedProductId>\n";
						} else {
							$xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct($productId, $storeViewId);
						}
					}
					
					$xml .= "</Products>\n";
					
					$this->postToChannelUnity($xml, "ProductData");
				}
				
			}
        } catch (Exception $e) {
            Mage::logException($e);
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
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function getItemsForUpdateCommon($items, $storeId)
    {
        try {
            $xml = "<Products>\n";
            $xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                    . "</SourceURL>\n";

            $xml .= "<StoreViewId>$storeId</StoreViewId>\n";
            
            foreach ($items as $item) {

                $sku = $item->getSku();

                $prodTemp = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
                if (!$prodTemp) {

                    continue;
                }
                
                // Item was ordered on website, stock will have reduced, update to CU
                $xml .= Mage::getModel('channelunity/products')->generateCuXmlForSingleProduct(
                        $prodTemp->getId(), $storeId, 0 /* $item->getQtyOrdered() */);

            }
            $xml .= "</Products>\n";
            
            $this->postToChannelUnity($xml, "ProductData");
            
        } catch (Exception $x) {
            Mage::logException($e);
        }
    }

    public function onInvoicePaid(Varien_Event_Observer $observer)
    {
        try {
            if (is_object($observer) && is_object($observer->getInvoice())) {
                $order = $observer->getInvoice()->getOrder();

                if (is_object($order)) {
                    $items = $order->getAllItems();
                    $this->getItemsForUpdateCommon($items, $order->getStore()->getId());
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Order is cancelled and has been saved. post order status change msg to CU
     */
    public function checkForCancellation(Varien_Event_Observer $observer)
    {
        try {
            $order = $observer->getOrder();

            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderStatus($order);
            $this->postToChannelUnity($xml, "OrderStatusUpdate");
        } catch (Exception $e) {
            Mage::logException($e);
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

            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderShip($order, $carrierName, $track->getTitle(), $track->getNumber());
            $result = $this->postToChannelUnity($xml, "OrderStatusUpdate");
            Mage::log('saveTrackingToAmazon: ' . $result);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function shipAmazon(Varien_Event_Observer $observer)
    {
        try {
            $shipment = $observer->getEvent()->getShipment();
            $order = $shipment->getOrder();

            $xml = Mage::getModel('channelunity/orders')->generateCuXmlForOrderShip($order, "", "", "");
            $result = $this->postToChannelUnity($xml, "OrderStatusUpdate");
            Mage::log('shipAmazon: ' . $result);
        } catch (Exception $e) {

            Mage::logException($e);
        }
    }

    /**
     * Category is saved. CU needs to know about it.
     */
    public function categorySave(Varien_Event_Observer $observer)
    {
        try {
            $myStoreURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $categoryStatus = Mage::getModel('channelunity/categories')->postCategoriesToCU($myStoreURL);
        } catch (Exception $e) {

            Mage::logException($e);

        }
    }

    public function configSaveAfter(Varien_Event_Observer $observer)
    {
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
        } catch (Exception $e) {

            Mage::logException($e);

        }
    }

    /**
     * Triggers on a store delete event. Removes store and category data in CU.
     * @author Matthew Gribben
     *
     * @param Varien_Event_Observer $observer
     */
    public function storeDelete(Varien_Event_Observer $observer)
    {

        $event = $observer->getEvent();
        $store = $event->getStore();

        try {


            $xml = "<StoreDelete>\n";
            $xml .= "<SourceURL>" . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
                    . "</SourceURL>\n";

            $storeViewId = $store->getId();
            $storeId = $store->getGroupId();
            $websiteId = $store->getWebsiteId();

            $xml .= "<StoreId>" . $storeId . "</StoreId>\n";
            $xml .= "<DeletedStoreViewId>" . $storeViewId . "</DeletedStoreViewId>\n";
            $xml .= "<WebsiteId>" . $websiteId . "</WebsiteId>\n";

            $xml .="</StoreDelete>\n";



            $result = $this->postToChannelUnity($xml, "storeDelete");

        } catch (Exception $e) {

            Mage::logException($e);
        }
    }

}
