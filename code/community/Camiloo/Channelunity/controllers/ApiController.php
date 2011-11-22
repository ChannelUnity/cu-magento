<?php

class Camiloo_Channelunity_ApiController extends Mage_Core_Controller_Front_Action
{
    private function terminate($message) {
        
        echo '<?xml version="1.0" encoding="utf-8" ?>';
        echo '	<ChannelUnity>';
        echo '        <Status>'.$message.'</Status>';
        echo '  </ChannelUnity>';
        die;
    }
    
    
    /**
   	*	This is the main API beacon for the connector module
	*	It will verify the request the pass it onto the model
	**/
	public function indexAction(){
			
		$xml = $this->getRequest()->getPost('xml');
		if (!isset($xml)) {

			$this->terminate("Error - could not find XML within request");

		} else {
            $xml = urldecode($xml);

			// load the XML into the simplexml parser
			$xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
			
			// we now need to verify that this message is genuine. We do this by calling
			// to ChannelUnity HQ with just the contents of the signedmessage element in
			// the XML message.

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
            }
            else {
                $request = "";
            }
            // RequestHeader contains the request type. Lets find out what type of request
            // we are handling by creating a switch.

            $type = (string) $xml->Notification->Type;
            if ($type == '') {
                $type = (string) $xml->Notification->type;
            }
			
			ini_set("display_errors","1");
			error_reporting(E_ALL);

            echo '<?xml version="1.0" encoding="utf-8" ?>';
            echo '	<ChannelUnity>';
            echo '    <RequestType>'.$type.'</RequestType>';
            
            switch ($type) {
                
                case "Ping":
                
                    Mage::getModel('channelunity/orders')->verifyMyself($request);
                
                break;
                
                case "OrderNotification":
                    Mage::getModel('channelunity/orders')->doCreate($request);
                break;				
                
                case "ProductData":
                	error_reporting(E_ALL);
					ini_set("display_errors","On");
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
            die;
        }
        
		
	
	}
	
}