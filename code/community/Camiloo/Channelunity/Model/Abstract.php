<?php

class Camiloo_Channelunity_Model_Abstract
{
	
		/*	From-To range examples.
			dataRange [xml element array]
			<request>
			<requestbody>
			<range>
				<filter on="date">
						<operator type="from">
							<value>2001-10-20</value>
						</operator>
						<operator type="to">
							<value>2001-10-22</value>
						</operator>
				</filter>
				<filter on="date">
						<operator type="from">
							<value>2001-10-20</value>
						</operator>
						<operator type="to">
							<value>2001-10-22</value>
						</operator>
				</filter>
			</range>
			</request>
		*/
		/*  Simple operator filter example
			dataRange [xml element array]
				<filter on="product_id">
						<operator type="gte">27</operator>
				</filter>
		*/
		/*  Range filter example
			
			dataRange [xml element array]
				<filter on="product_id">
						<operator type="in">
							<value>22</value>
							<value>23</value>
							<value>24</value>
							<value>25</value>
							<value>26</value>
							<value>27</value>
							<value>28</value>
							<value>29</value>
							<value>30</value>
						</operator>
				</filter>
		*/
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

        curl_setopt($session, CURLOPT_URL, "http://my.channelunity.com/event.php");
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
    
            curl_setopt($session, CURLOPT_URL, "http://my.channelunity.com/event.php");
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

    public function postToChannelUnity($Request) {

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

        curl_setopt($session, CURLOPT_URL, "http://my.channelunity.com/event.php");
        curl_setopt($session, CURLOPT_POST, TRUE);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($session, CURLOPT_POSTFIELDS, array('message' => $xml));

        $result = curl_exec($session);

        curl_close($session);

        return $result; 
    }


}
?>