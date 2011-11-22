<?php

class Camiloo_Channelunity_Model_Checkforupdates extends Varien_Object
{
    public function getRemoteXMLFileData($urltograb){
        // this function gets the requested data
        $session = curl_init("$urltograb");
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($session, CURLOPT_TIMEOUT, 60);
        $result = curl_exec($session);
        curl_close($session);
        return simplexml_load_string($result,'SimpleXMLElement', LIBXML_NOCDATA);
    }
    

	
}

?>