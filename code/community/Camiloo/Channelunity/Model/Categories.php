<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_Model_Categories extends Camiloo_Channelunity_Model_Abstract
{

    protected $_collection = 'catalog/category';

    /**
     * Returns an XML list of all categories in this Magento install.
     */
    public function doRead($request)
    {
        $collection = Mage::getModel($this->_collection)->getCollection()
                ->addAttributeToSelect("name");

        // position, category_path, parent, level

        echo "<CategoryList>\n";
        foreach ($collection as $category) {
            echo "<Category>\n";
            echo "  <ID>{$category->getId()}</ID>\n";
            echo "  <Name>{$category->getName()}</Name>\n";
            echo "  <Position>{$category->getData('position')}</Position>\n";
            echo "  <CategoryPath>{$category->getData('path')}</CategoryPath>\n";
            echo "  <ParentID>{$category->getData('parent_id')}</ParentID>\n";
            echo "  <Level>{$category->getData('level')}</Level>\n";
            echo "</Category>\n\n";
        }

        echo "</CategoryList>";
    }

    public function enumerateCategoriesForStoreView($urlTemp, $frameworkType, $websiteId, $storeId, $rootCatId, $storeViewId)
    {
        $messageToSend = "";

        // Load in this root category and enumerate all children
        $collection = Mage::getModel($this->_collection)->getCollection()
                ->addAttributeToSelect("name");

        // need to be able to link categories up to the right source/store in CU.

        $messageToSend .= "<CategoryList>
            <URL><![CDATA[{$urlTemp}]]></URL>
            <FrameworkType><![CDATA[{$frameworkType}]]></FrameworkType>
            <WebsiteId><![CDATA[{$websiteId}]]></WebsiteId>
            <StoreId><![CDATA[{$storeId}]]></StoreId>
            <StoreviewId><![CDATA[{$storeViewId}]]></StoreviewId>\n";

        foreach ($collection as $category) {

            //	$children = $category->getChildren();
            $pid = $category->getData('parent_id');
            $lvl = $category->getData('level');
            //	$childCount = $children->count();

            $catPathTemp = $category->getData('path');

            if (strpos($catPathTemp, "$rootCatId/") === 0     // start of path
                    || strpos($catPathTemp, "/$rootCatId/") > 0   // middle of path
                    || strpos($catPathTemp, "/$rootCatId") == (strlen($catPathTemp) - strlen("/$rootCatId"))) { // OR at END of path
                $messageToSend .= "<Category>\n";
                $messageToSend .= "  <ID><![CDATA[{$category->getId()}]]></ID>\n";
                $messageToSend .= "  <Name><![CDATA[{$category->getName()}]]></Name>\n";
                $messageToSend .= "  <Position><![CDATA[{$category->getData('position')}]]></Position>\n";
                $messageToSend .= "  <CategoryPath><![CDATA[{$catPathTemp}]]></CategoryPath>\n";
                $messageToSend .= "  <ParentID><![CDATA[{$category->getData('parent_id')}]]></ParentID>\n";
                $messageToSend .= "  <Level><![CDATA[{$category->getData('level')}]]></Level>\n";
                $messageToSend .= "</Category>\n\n";
            }
        }

        $messageToSend .= "</CategoryList>";

        return $messageToSend;
    }

    public function postCategoriesToCU($urlTemp)
    {
        $messageToSend = '';
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $websites = Mage::app()->getWebsites();

        $putData = tmpfile();
        $bytes = 0;

        // For each store view ...
        foreach ($websites as $website) {

            $stores = Mage::getModel('core/store_group')
                            ->getCollection()->addFieldToFilter('website_id', array('eq' => $website->getData('website_id')));

            foreach ($stores as $store) {

                // Get the root category ID ...

                $rootCatId = $store->getData('root_category_id');

                $storeViews = Mage::getModel('core/store')
                        ->getCollection()->addFieldToFilter('website_id', array('eq' => $website->getData('website_id')))
                        ->addFieldToFilter('group_id', array('eq' => $store->getData('group_id')));

                foreach ($storeViews as $storeView) {

                    $frameworkType = "Magento";
                    $websiteId = $storeView->getData('website_id');
                    $storeId = $storeView->getData('group_id');
                    $storeViewId = $storeView->getData('store_id');

                    $messageToSend = $this->enumerateCategoriesForStoreView($urlTemp, $frameworkType, $websiteId, $storeId, $rootCatId, $storeViewId);

                    $bytes = $bytes + fwrite($putData, $messageToSend);
                }
            }
        }

        fseek($putData, 0);
        $senditnow = fread($putData, $bytes);
        $result = $this->postToChannelUnity($senditnow, "CategoryData");
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

        fclose($putData);

        if (isset($xml->Status)) {
            return $xml->Status;
        } else if (isset($xml->status)) {
            return $xml->status;
        } else {
            return "Error - unexpected response";
        }
    }

}