<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_ApiController extends Mage_Core_Controller_Front_Action
{

    private function terminate($message)
    {

        echo '<?xml version="1.0" encoding="utf-8" ?>';
        echo '	<ChannelUnity>';
        echo '        <Status>' . $message . '</Status>';
        echo '  </ChannelUnity>';
        die;
    }

    public function testtableAction()
    {
        if (Mage::getModel('channelunity/orders')->table_exists("moo")) {
            echo "true";
        } else {
            echo "false";
        }
    }

    public function ordertestAction()
    {

        $xml = <<<EOD
<?xml version="1.0" encoding="utf-8" ?><ChannelUnity><Notification><ID>1334834111876</ID><Timestamp>Thu Apr 19 12:15:11 BST 2012</Timestamp><Type>OrderNotification</Type><Payload><MerchantName>marktest</MerchantName><SourceId>10</SourceId><FriendlyName>English</FriendlyName><URL>http://__.camiloo.co.uk/channelunity/api/index</URL><MainCountry>United Kingdom</MainCountry><FrameworkType>Magento</FrameworkType><WebsiteId>1</WebsiteId><StoreId>1</StoreId><StoreviewId>1</StoreviewId><SubscriptionId>304</SubscriptionId><SkuAttribute>sku</SkuAttribute><Orders>
        <Order>
        <ServiceSku>CU_AMZ_UK</ServiceSku>
        <OrderId>228-8888888-0277162</OrderId>
        <PurchaseDate>2012-06-10T01:33:10+00:00</PurchaseDate>
        <Currency>GBP</Currency>
        <OrderFlags></OrderFlags>
        <OrderStatus>Processing</OrderStatus>
        <StockReservedCart>0</StockReservedCart>
        <ShippingInfo>
            <RecipientName><![CDATA[Mrs Ship]]></RecipientName>
            <Email><![CDATA[ship@ship.com]]></Email>
            <Address1><![CDATA[1 High St]]></Address1>
            <Address2><![CDATA[]]></Address2>
            <Address3><![CDATA[]]></Address3>
            <City><![CDATA[Manchester]]></City>
            <State>Greater Manchester</State>
            <PostalCode>M1 1AA</PostalCode>
            <Country><![CDATA[GB]]></Country>
            <PhoneNumber><![CDATA[01981 239329]]></PhoneNumber>
            <ShippingPrice>2.00</ShippingPrice>
            <ShippingTax>0.00</ShippingTax>
            <Service><![CDATA[Std UK Europe 2]]></Service>
            <DeliveryInstructions><![CDATA[]]></DeliveryInstructions>
            <GiftWrapPrice>0.00</GiftWrapPrice>
            <GiftWrapTax>0.00</GiftWrapTax>
            <GiftWrapType></GiftWrapType>
            <GiftMessage><![CDATA[]]></GiftMessage>
        </ShippingInfo>
        <BillingInfo>
            <Name><![CDATA[Mr Billing]]></Name>
            <Email><![CDATA[b@c.com]]></Email>
            <PhoneNumber><![CDATA[01987 228228]]></PhoneNumber>
        </BillingInfo>
        <OrderItems>
            <Item>
                <SKU><![CDATA[XRIT250]]></SKU>
                <Name><![CDATA[X-Rite ColorChecker Passport]]></Name>
                <Quantity>1.000</Quantity>
                <Price>1.00</Price>
                <Tax>0.00</Tax>
            </Item>
        </OrderItems></Order></Orders></Payload></Notification></ChannelUnity>
EOD;

        $this->doApiProcess($xml, true);
    }

    public function doApiProcess($xml, $testMode = false)
    {
        // load the XML into the simplexml parser
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        // we now need to verify that this message is genuine. We do this by calling
        // to ChannelUnity HQ with just the contents of the signedmessage element in
        // the XML message.

        if (!$testMode) {

            $payload = (string) $xml->Notification->Payload;

            if ($payload == '') {
                $payload = (string) $xml->Notification->payload;
            }

            // we pass the signedmessage, intact, to the ChannelUnity API
            // by posting it as signedmessage. Verifypost will only return
            // to the variable if the response was successful. It will fail
            // on invalid messages, so we won't have to worry about that here.
            // It will return a simplexml object too, so we can get straight
            // down to work.

            $payload = trim($payload);

            if ($payload != '') {
                $request = Mage::getModel('channelunity/products')->verifypost($payload);
            } else {
                $request = "";
            }
        } else {
            $request = $xml->Notification->Payload;
        }
        // RequestHeader contains the request type. Lets find out what type of request
        // we are handling by creating a switch.

        $type = (string) $xml->Notification->Type;
        if ($type == '') {
            $type = (string) $xml->Notification->type;
        }

        ini_set("display_errors", "1");
        error_reporting(E_ALL);

        echo '<?xml version="1.0" encoding="utf-8" ?>';
        echo '	<ChannelUnity>';
        echo '    <RequestType>' . $type . '</RequestType>';

        switch ($type) {

            case "Ping":
                Mage::getModel('channelunity/orders')->verifyMyself($request);
                break;

            case "OrderNotification":
                Mage::getModel('channelunity/orders')->doUpdate($request);
                break;

            case "AttributePush":
                Mage::getModel('channelunity/products')->doSetValue($request);
                break;

            case "GetAllSKUs":
                Mage::getModel('channelunity/products')->getAllSKUs($request);
                break;

            case "ProductData":
                error_reporting(E_ALL);
                ini_set("display_errors", "On");
                $attributeStatus = Mage::getModel('channelunity/products')->postAttributesToCU();
                Mage::getModel('channelunity/products')->postProductTypesToCU($request);
                Mage::getModel('channelunity/products')->doRead($request);

                break;

            case "CartDataRequest":

                // get URL out of the CartDataRequest
                $myStoreURL = $xml->Notification->URL;
                $storeStatus = Mage::getModel('channelunity/stores')->postStoresToCU($myStoreURL);
                $categoryStatus = Mage::getModel('channelunity/categories')->postCategoriesToCU($myStoreURL);
                $attributeStatus = Mage::getModel('channelunity/products')->postAttributesToCU();

                echo "<StoreStatus>$storeStatus</StoreStatus>
                <CategoryStatus>$categoryStatus</CategoryStatus>
                <ProductAttributeStatus>$attributeStatus</ProductAttributeStatus>";

                break;
        }

        echo '  </ChannelUnity>';
    }

    /**
     * 	This is the main API beacon for the connector module
     * 	It will verify the request then pass it onto the model.
     * */
    public function indexAction()
    {

        $xml = $this->getRequest()->getPost('xml');
        if (!isset($xml)) {

            $this->terminate("Error - could not find XML within request");
        } else {
            $xml = urldecode($xml);

            $this->doApiProcess($xml);

            die;
        }
    }

}