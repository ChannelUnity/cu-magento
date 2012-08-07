<?php
/**
 * ChannelUnity connector for Magento Commerce 
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Camiloo_Channelunity_Model_Abstract
{
	
	public function filterCollection($collection, $dataRange){
		
		foreach($dataRange as $filter){
			
			$attribute = $this->getAttribute($filter,'on');
			$conditions = array();
			foreach($filter->operator as $operator){
				
				$operator = $this->getAttribute($operator,'type');
				
				foreach($operator->value as $value){
					$values[] = (string) $value;
				}
			
				// check if there are multiple values in our array. If not, implode to a string.
				if(sizeof($values) < 2){
					$values = implode("",$values);	
				}
								
				$conditions[$operator] = $values;	
				
				
			}
			
			$collection->addFieldToFilter($attribute,$conditions);
			
		}
		
		return $collection;
		
	}

	private function getAttribute($object, $attribute){
		
   		if(isset($object[$attribute])) {
   		     return (string) $object[$attribute];
	 	}
		
	}
	
	public function doRead($dataRange){
		
		$collection = $this->filterCollection(Mage::getModel($this->_collection)->getCollection(),$dataRange);
		$collection->addAttributeToSelect("*");
		return $collection->toXml();
		
	}
	
	public function doDelete($dataRange){
		
		$collection = $this->filterCollection(Mage::getModel($this->_collection)->getCollection(),$dataRange);
		$deleted_entity_ids = array();
		$deleted_increment_ids = array();
		
		foreach($collection as $item){
			$deleted_entity_ids[] = $item->getId();
			$deleted_increment_ids[] = $item->getIncrementId();
			$item->delete();	
		}
	
		$xml = '<?xml version="1.0" encoding="utf-8" ?>';
		$xml.= '	<Response>';
		$xml.= '     <ResponseHeader>';
		$xml.= '        <Status>200</Status>';
		$xml.= '        <StatusMessage>Orders deleted successfully</StatusMessage>';
		$xml.= '     </ResponseHeader>';
		$xml.= '     <ResponseBody>';
		foreach($deleted_entity_ids as $key=>$id){
		$xml.= '          <DeletedItem>';
		$xml.= '               <EntityId>'.$deleted_entity_ids[$key].'</EntityId>';
		$xml.= '               <IncrementId>'.$deleted_increment_ids[$key].'</IncrementId>';
		$xml.= '          </DeletedItem>';		
		}
		$xml.= '     </ResponseBody>';
		$xml.= '  </Response>';
		
		return $xml;
		
	}

    private function terminate($message) {

        echo '<?xml version="1.0" encoding="utf-8" ?>';
        echo '	<ChannelUnity>';
        echo '        <Status>'.$message.'</Status>';
        echo '  </ChannelUnity>';
        die;
    }

    public function getEndpoint() {
        if (strpos($_SERVER['SERVER_NAME'], "camiloo.co.uk") !== false) {
        
            return "http://staging.channelunity.com/event.php";
        }
        else {
            
            return "http://my.channelunity.com/event.php";
        }
    }


    /**
    * Calls the VerifyNotification API.
    */
    public function verifypost($messageverify) {

        $session = curl_init();

        $xml = urlencode("<?xml version=\"1.0\" encoding=\"utf-8\" ?>
            <ChannelUnity>
            <MerchantName>" . $this->getMerchantName() . "</MerchantName>
            <Authorization>" . $this->getValidUserAuth() . "</Authorization>
            <ApiKey>" .$this->getApiKey(). "</ApiKey>
            <RequestType>VerifyNotification</RequestType>
            <Payload>$messageverify</Payload>
            </ChannelUnity>");

        curl_setopt($session, CURLOPT_URL, $this->getEndpoint());
        curl_setopt($session, CURLOPT_POST, TRUE);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($session, CURLOPT_POSTFIELDS, array('message' => $xml));
        
        $result = curl_exec($session);

        try 
        {
            $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        catch (Exception $e)
        {
            $this->terminate('Error - Unknown response from validation server '.$e);

        }
        curl_close($session);

        if ((string) $xml->Status != "OK"){			
            $this->terminate($xml->Status);

        }
        else
        {
            return $xml;
        }
    }

    public function verifyMyself($request) {

        $result = $this->postToChannelUnity("", "ValidateUser");

        if (strpos($result, "<MerchantName>")
            || strpos($result, "<merchantname>"))
        {
            echo "<Status>OK</Status>\n";
        }
        else {

            $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (isset($xml->Status)) {
                echo "<Status>{$xml->Status}</Status>\n";
            }
            else if (isset($xml->status)) {
                echo "<Status>{$xml->status}</Status>\n";
            }
            else {
                echo "<Status>Error - unexpected response</Status>";
            }
        }
    }

    public function getMerchantName() {
        return Mage::getStoreConfig('channelunityint/generalsettings/merchantname');
    }

    public function getValidUserAuth() {
        $auth = Mage::getStoreConfig('channelunityint/generalsettings/merchantusername')
            . ":" . hash("sha256", Mage::getStoreConfig('channelunityint/generalsettings/merchantpassword'));

        $auth = base64_encode($auth);
        return $auth;
    }

    public function getApiKey() {
        $apikeyTemp = Mage::getStoreConfig('channelunityint/generalsettings/apikey');

        if (strlen($apikeyTemp) > 0) {
            return $apikeyTemp;

        } else {
  
            $session = curl_init();
    
            $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
                <ChannelUnity>
                <MerchantName>" . $this->getMerchantName() . "</MerchantName>
                <Authorization>" . $this->getValidUserAuth() . "</Authorization>
                <RequestType>ValidateUser</RequestType>
                </ChannelUnity>";
    
            $xml = urlencode($xml);
    
            curl_setopt($session, CURLOPT_URL, $this->getEndpoint());
            curl_setopt($session, CURLOPT_POST, TRUE);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($session, CURLOPT_POSTFIELDS, array('message' => $xml));
    
            $result = curl_exec($session);
    
            curl_close($session);
            
            $xml = simplexml_load_string($result, 'SimpleXMLElement', 
                LIBXML_NOCDATA);

            if (isset($xml->ApiKey)) {
                Mage::getModel('core/config')->saveConfig(
                    'channelunityint/generalsettings/apikey', $xml->ApiKey);
                
                return $xml->ApiKey;
            }
        }
        
        return "";
    }

    public function postToChannelUnity($xml, $requestType) {

        $session = curl_init();

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
            <ChannelUnity>
            <MerchantName>" . $this->getMerchantName() . "</MerchantName>
            <Authorization>" . $this->getValidUserAuth() . "</Authorization>
            <ApiKey>" .$this->getApiKey(). "</ApiKey>
            <RequestType>$requestType</RequestType>
            <Payload>$xml</Payload>
            </ChannelUnity>";

        $xml = urlencode($xml);

        curl_setopt($session, CURLOPT_URL, $this->getEndpoint());
        curl_setopt($session, CURLOPT_POST, TRUE);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($session, CURLOPT_POSTFIELDS, array('message' => $xml));

        $result = curl_exec($session);

        curl_close($session);

        return $result; 
    }

    public function postMyURLToChannelUnity($merchantName) {

        $session = curl_init();
        
        $baseurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
            <ChannelUnity>
            <MerchantName>$merchantName</MerchantName>
            <Authorization>" . $this->getValidUserAuth() . "</Authorization>
            <RequestType>SuggestEndpointURL</RequestType>
            <Payload><URL>$baseurl</URL></Payload>
            </ChannelUnity>";

        $xml = urlencode($xml);

        curl_setopt($session, CURLOPT_URL, $this->getEndpoint());
        curl_setopt($session, CURLOPT_POST, TRUE);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($session, CURLOPT_POSTFIELDS, array('message' => $xml));

        $result = curl_exec($session);

        curl_close($session);

        return $result; 
    }
	
	
	/**
	 * skipProduct - checks whether to skip product to pass it to CU
	 * 
	 * @param type $product - can be product id or product object
	 * 
	 * @return boolean - true-skip, false-don't skip
	 */
	public function skipProduct($product)
	{
		$productStatus = 1;
		
		$ignoreDisabled = Mage::getStoreConfig('channelunityint/generalsettings/ignoredisabledproducts');
			
		if($product && $ignoreDisabled == 1)
		{
			if(is_int($product)) {
				$product = Mage::getModel('catalog/product')->load($product);
			}
			
			if(is_object($product) && $product->hasSku()) {
				$productStatus = $product->getStatus(); // 1-Enabled, 2-Disabled
			}
			
			if($productStatus == 2) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * skipProduct - checks whether to skip product to pass it to CU
	 * 
	 * //product field: status, 1-Enabled, 2-Disabled
	 * 
	 * @return boolean - true-ignore disabled, false-don't ignore
	 */
	public function ignoreDisabled()
	{
		$ignoreDisabled = false;
		
		$ignoreDisabled = Mage::getStoreConfig('channelunityint/generalsettings/ignoredisabledproducts');
		
		return $ignoreDisabled;
	}
}
?>