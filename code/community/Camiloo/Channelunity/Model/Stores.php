<?php

class Camiloo_Channelunity_Model_Stores extends Camiloo_Channelunity_Model_Abstract
{

    protected $_collection = 'core/store';

    public function postStoresToCU($myURL)
    {

        $messageToSend = "";

        $putData = tmpfile();
        $bytes = 0;

        $messageToSend .= "<StoreList>\n";
        $bytes = $bytes + fwrite($putData, $messageToSend);

        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);


        $websites = Mage::app()->getWebsites();


        foreach ($websites as $website) {


            $stores = Mage::getModel('core/store_group')
                            ->getCollection()->addFieldToFilter('website_id', array('eq' => $website->getData('website_id')));

            foreach ($stores as $store) {

                $storeViews = Mage::getModel('core/store')
                        ->getCollection()->addFieldToFilter('website_id', array('eq' => $website->getData('website_id')))
                        ->addFieldToFilter('group_id', array('eq' => $store->getData('group_id')));

                foreach ($storeViews as $storeView) {

                    $messageToSend = "<Store>
                        <FriendlyName><![CDATA[{$storeView->getData('name')} - {$storeView->getData('code')}]]></FriendlyName>
                        <URL><![CDATA[{$myURL}]]></URL>
                        <MainCountry><![CDATA[Unknown]]></MainCountry>
                        <FrameworkType><![CDATA[Magento]]></FrameworkType>
                        <WebsiteId><![CDATA[{$storeView->getData('website_id')}]]></WebsiteId>
                        <StoreId><![CDATA[{$storeView->getData('group_id')}]]></StoreId>
                        <StoreviewId><![CDATA[{$storeView->getData('store_id')}]]></StoreviewId>
                    </Store>";
                    $bytes = $bytes + fwrite($putData, $messageToSend);
                }
            }
        }

        $messageToSend = "</StoreList>\n";

        $bytes = $bytes + fwrite($putData, $messageToSend);
        fseek($putData, 0);
        $senditnow = fread($putData, $bytes);
        fclose($putData);

        $result = $this->postToChannelUnity($senditnow, "StoreData");
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

        $returnXmlMsg = "";

        if (isset($xml->Status)) {
            $returnXmlMsg .= "<Status>{$xml->Status}</Status>";
        } else if (isset($xml->status)) {
            $returnXmlMsg .= "<Status>{$xml->status}</Status>";
        } else {
            $returnXmlMsg .= "<Status>Error - unexpected response</Status>";
        }

        $returnXmlMsg .= "<CreatedStores>";

        foreach ($xml->CreatedStoreId as $storeIdCreated) {
            $returnXmlMsg .= "<StoreId>$storeIdCreated</StoreId>";
        }

        $returnXmlMsg .= "</CreatedStores>";

        return $returnXmlMsg;
    }

    public function doCreate($dataArray)
    {

    }

    public function doUpdate($dataArray)
    {

    }

}

?>