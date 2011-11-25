<?php

class Camiloo_Channelunity_Model_Products extends Camiloo_Channelunity_Model_Abstract
{
	protected $_collection = 'catalog/product';
    private $starttime;
	private $endtime;
	private $runtime = 0;
	private $beforeMemory;
	private $maxMemory;
	private $maxMemoryChar;
    private $maxruntime = 30;
	private $changeMemory = 0;
    private $upperLimit = 500;
	private $countCurr = 0;
	private $rangeNext = 0;
    private $premExit = false;
   
	public function postProductTypesToCU($request) {
	    
        $url = (string) $request->URL;
        $putData = tmpfile(); 
		$bytes = 0;
    
	    $messageToSend = "";
	
	    $prodAttrEntType = Mage::getModel('catalog/product')
            ->getResource()->getEntityType()->getId();
            
        $attrColl = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($prodAttrEntType);
            
        $messageToSend = "<URL>$url</URL>\n";
		$bytes = $bytes + fwrite($putData, $messageToSend); 
                
        $messageToSend = "<ProductTypes>\n";
		$bytes = $bytes + fwrite($putData, $messageToSend); 
        foreach ($attrColl as $attrModel) {
            
            $messageToSend = "<ProductType>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
            
            $attribute_set_id = $attrModel->getData('attribute_set_id');
            $attribute_set_name = $attrModel->getData('attribute_set_name');
            
            $messageToSend = "  <ProductTypeId>$attribute_set_id</ProductTypeId>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
            $messageToSend = "  <ProductTypeName><![CDATA[{$attribute_set_name}]]></ProductTypeName>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
            
            
            $messageToSend = "  <AttributeGroups>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
                
			 	$product = Mage::getModel('catalog/product')->getCollection()
				->addFieldToFilter('attribute_set_id', array('eq' => $attribute_set_id))
				->addAttributeToSelect('entity_id');
				$product->getSelect()->limit(1);
				$product = $product->getFirstItem();
				
						
					
	                $prodIdOfAttrSet = $product->getId();
                $atrGrpColl = Mage::getModel('eav/entity_attribute_group')->getCollection()
    	            ->addFieldToFilter('attribute_set_id', array('eq' => $attribute_set_id));
            foreach ($atrGrpColl as $atGrModel) {
                $attribute_group_name = $atGrModel->getData('attribute_group_name');
                $attribute_group_id = $atGrModel->getData('attribute_group_id');
                
                $messageToSend = "    <AttributeGroup>\n";
				$bytes = $bytes + fwrite($putData, $messageToSend);
                $messageToSend = "      <GroupName><![CDATA[{$attribute_group_name}]]></GroupName>\n";
            	$bytes = $bytes + fwrite($putData, $messageToSend);    
                
                $messageToSend = "      <Attributes>\n";
                $bytes = $bytes + fwrite($putData, $messageToSend);
             
                if ($prodIdOfAttrSet != 0) {
                    $product = Mage::getModel('catalog/product')->load($prodIdOfAttrSet);
                  
                    $collection = $product->getAttributes($attribute_group_id, false);
                    foreach ($collection as $attribute) {
                   		$messageToSend = "        <AttributeCode>" . $attribute->getAttributeCode()  . "</AttributeCode>\n";
						$bytes = $bytes + fwrite($putData, $messageToSend);
                    }
                    
                }
                
                $messageToSend = "      </Attributes>\n";
                $bytes = $bytes + fwrite($putData, $messageToSend);
				
                $messageToSend = "    </AttributeGroup>\n";
	            $bytes = $bytes + fwrite($putData, $messageToSend);
			}
        	
            $messageToSend = "  </AttributeGroups>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
            $messageToSend = "</ProductType>\n";
			$bytes = $bytes + fwrite($putData, $messageToSend);
        }
        
        $messageToSend = "</ProductTypes>\n";
        $bytes = $bytes + fwrite($putData, $messageToSend);

        fseek($putData, 0); 
		$senditnow = fread($putData, $bytes);
	
	    $this->postToChannelUnity($senditnow, "ProductTypes");
		
		fclose($putData);
    }
	
    public function postAttributesToCU() {
        
        $putData = tmpfile(); 
		$bytes = 0;
        
		$messageToSend = "<ProductAttributes>\n";
		
		$bytes = $bytes + fwrite($putData, $messageToSend);
		$product = Mage::getModel('catalog/product');
		$attributes = Mage::getResourceModel('eav/entity_attribute_collection')
			->setEntityTypeFilter($product->getResource()->getTypeId())
			->load(false);
			
		foreach($attributes as $attribute){
			
			$attr = $attribute->getData('attribute_code');
			
			if ($attr != 'name' && $attr != 'description' && $attr != 'sku' && $attr != 'price' && $attr != 'qty' && $attr != 'stock_item') {
				
					$attrType = trim($attribute->getBackendType());
                    $friendlyName = trim($attribute->getFrontendLabel());
	   
                    $messageToSend = "  <Attribute><Name>$attr</Name><Type>$attrType</Type>
                        <FriendlyName><![CDATA[{$friendlyName}]]></FriendlyName></Attribute>\n";
												
					$bytes = $bytes + fwrite($putData, $messageToSend); 
			}
					
		}
		
        $messageToSend = "</ProductAttributes>\n";
		$bytes = $bytes + fwrite($putData, $messageToSend); 
        
		fseek($putData, 0); 
		$senditnow = fread($putData, $bytes);
	   	fclose($putData);
	    $result = $this->postToChannelUnity($senditnow, "ProductAttributes");
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if (isset($xml->Status)) {
            return $xml->Status;
        }
        else if (isset($xml->status)) {
            return $xml->status;
        }
        else {
            return "Error - unexpected response";
        }
    }
    
	
    public function generateCuXmlForProduct($args) {
		$productXml = '';
		$this->countCurr++;
		
		$this->maxMemory = $this->return_bytes(ini_get('memory_limit'));
		
		if ($this->runtime < $this->maxruntime 
			&& (memory_get_peak_usage() + $this->changeMemory) < $this->maxMemory
			&& $this->countCurr <= $this->upperLimit) 
		{
			$product = Mage::getModel('catalog/product');
			$id = $args->getEntityId();

			$product->load($id);
			
			
			$this->rangeNext = $product->getId() + 1;
			// existing function code here....
			
							try {
								$imageUrl = $product->getImageUrl();
							} catch (Exception $e) {
								$imageUrl = '';
							}
							
							$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
							$qty = $stock->getData('qty');
							
							$catids = implode(',', $product->getCategoryIds());
							$categories = $product->getCategoryIds();
							$catnames = "";
							
							foreach ($categories as $k => $_category_id) {
								$_category = Mage::getModel('catalog/category')->load($_category_id);
								$catnames .= ($_category->getName()) . ", ";
							}
							
							$_name = ($product->getData('name'));
							$_description = ($product->getData('description'));
							$_sku = ($product->getData('sku'));
							
							$attributeSetModel = Mage::getModel("eav/entity_attribute_set");
							$attributeSetModel->load($product->getAttributeSetId());
							$attributeSetName = $attributeSetModel->getAttributeSetName();
														
							$productXml = "<Product>\n";
							$productXml .= "  <RemoteId>".$product->getId()."</RemoteId>\n";
							$productXml .= "  <ProductType>".$attributeSetName."</ProductType>\n";
							$productXml .= "  <Title><![CDATA[{$_name}]]></Title>\n";
							$productXml .= "  <Description><![CDATA[{$_description}]]></Description>\n";
							$productXml .= "  <SKU><![CDATA[{$_sku}]]></SKU>\n";
							$productXml .= "  <Price>{$product->getData('price')}</Price>\n";
							$productXml .= "  <Quantity>{$qty}</Quantity>\n";
							$productXml .= "  <Category>{$catids}</Category>\n";
							$productXml .= "  <CategoryName><![CDATA[{$catnames}]]></CategoryName>\n";
							$productXml .= "  <Image><![CDATA[{$imageUrl}]]></Image>\n";
							
							// Add associated/child product references if applicable
							$productXml .= "  <RelatedSKUs>\n";
								
							$variationXml = "  <Variations>\n";
							
							if ($product->getTypeId() == 'configurable') {
								
								$childProducts = Mage::getModel('catalog/product_type_configurable')
									->getUsedProducts(null, $product);
								
								foreach ($childProducts as $cp) {
								
									$productXml .= "  <SKU><![CDATA[{$cp->getData('sku')}]]></SKU>\n";
								}
							
								$confAttributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
								
								// Get the attribute(s) which vary
								if (is_array($confAttributes)) {
									foreach ($confAttributes as $cattr) {
										$cattr = serialize($cattr);
										
										$findTemp = "\"attribute_code\";";
										
										$cattr = explode($findTemp, $cattr);
										
										if (isset($cattr[1])) {
										
											$cattr = explode("\"", $cattr[1]);
											
											if (isset($cattr[1])) {
											
												$variationXml .= "<Variation><![CDATA[{$cattr[1]}]]></Variation>\n";
												
											}
										}
									}
								}
								
							}
							else if ($product->getTypeId() == 'grouped') {
								
								// TODO need to do variations?
								
								$childProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
								foreach ($childProducts as $cp) {
									
									$productXml .= "  <SKU><![CDATA[{$cp->getData('sku')}]]></SKU>\n";
								}
							}
							
							$variationXml .= "  </Variations>\n";
							$productXml .= "  </RelatedSKUs>\n";
							$productXml .= $variationXml;
							
							$productXml .= "  <Custom>\n";
							
							// Enumerate all other attribute values
							
							$attributeNames = array_keys($product->getData());
							
							foreach ($attributeNames as $k => $attr) {
								
								if ($attr != 'name' && $attr != 'description' && $attr != 'sku'
									&& $attr != 'price' && $attr != 'qty'
									&& $attr != 'stock_item' && $attr != 'tier_price') {
									
									if ($attribute = $product->getResource()->getAttribute($attr)) {
										
										$myval = $product->getData($attr);
										
										if (is_array($myval)) {
											$myval = serialize($myval);
										}
										
										$prodDataValue = $attribute->getSource()
											->getOptionText($myval);
										
										if ($prodDataValue == '') {
																	
											$prodDataValue = $myval;
										}
									} else {
										$prodDataValue = $product->getData($attr);
									}
									
									if ('Varien_Object' == get_class($prodDataValue)) {
										
										$prodDataValue = $prodDataValue->toXml();
									}
									if (is_array($prodDataValue)) {
										
										$prodDataValue = $product->getData($attr);
										$productXml .= "    <$attr><![CDATA[$prodDataValue]]></$attr>\n";
									}
									else {
										
										if ('Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Type_Configurable_Attribute_Collection' == get_class($prodDataValue)) {
											$productXml .= "    <$attr><![CDATA[Mage_Core_Model_Mysql4_Collection_Abstract]]></$attr>\n";
										}
										elseif(!is_object($prodDataValue)){
											$productXml .= "    <$attr><![CDATA[".$prodDataValue."]]></$attr>\n";
										}
									}
								}
							}
							
							$productXml .= "  </Custom>\n";
							$productXml .= "</Product>\n";
							
							
				// after....			
				$this->endtime = $this->microtime_float();
                $this->runtime = round($this->endtime - $this->starttime);
                
                $tmpval = memory_get_peak_usage() - $this->beforeMemory;
                if($tmpval > $this->changeMemory){
                    $this->changeMemory = $tmpval;
                }

            }else{

                $this->premExit = true; // i.e. exited before got through all prods

            }
								
			return $productXml;
    }
    
	public function microtime_float() {
		list ($msec, $sec) = explode(' ', microtime());
		$microtime = (float)$msec + (float)$sec;
		return $microtime;
	}
	
	function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
			switch($last) {
				// The 'G' modifier is available since PHP 5.1.0
				case 'g':
					$val *= 1024;
				case 'm':
					$val *= 1024;
				case 'k':
					$val *= 1024;
			}

		return $val;
	}
    
    /**
     * Return a set of product data to CU.
     */
    public function doRead($request) {
        $rangeFrom = (string) $request->RangeFrom;
        $rangeTo = (string) $request->RangeTo;
        $storeId = (string) $request->StoreviewId;
		
		$this->starttime = $this->microtime_float();
		$this->endtime = $this->microtime_float();
		$this->runtime = round($this->endtime - $this->starttime);
		$this->beforeMemory = memory_get_peak_usage();
		$this->maxMemory = ini_get('memory_limit');
		$this->maxMemoryChar = substr($this->maxMemory,strlen($this->maxMemory)-1,1);
	    
	        echo "<Products>\n";
        
				// get the highest product ID
				$collectionOfProduct = Mage::getModel($this->_collection)->getCollection(); 
				$collectionOfProduct->setOrder('entity_id','DESC');
				$collectionOfProduct->setPageSize(1);
				$collectionOfProduct->setCurPage(1);
				$totp = $collectionOfProduct->getFirstItem();
				$totp = $totp->getId();
		
				
		
				$collectionOfProduct = Mage::getModel($this->_collection)->getCollection(); 
	  $collectionOfProduct->addAttributeToFilter("entity_id", array('gteq' => $rangeFrom))
                ->setOrder('entity_id','ASC');
        
            $collectionOfProduct->addAttributeToFilter("entity_id", array('lt' => $rangeFrom + $this->upperLimit))
                ->setOrder('entity_id','ASC');

				//  monitor memory and max exec
				if($this->maxMemoryChar == "M"){
					  $this->maxMemory = str_replace("M","",$this->maxMemory);
					  $this->maxMemory = $this->maxMemory * 1024 * 1024;
				}else if($this->maxMemoryChar == "G"){
					  $this->maxMemory = str_replace("G","",$maxMemory);
					  $this->maxMemory = $this->maxMemory * 1024 * 1024 * 1024;
				}   
				
				if($this->maxMemory < 100){
					$this->maxMemory = 10000000000;
				}
				
				Mage::getSingleton('core/resource_iterator')->walk($collectionOfProduct->getSelect(), array(array($this, 'generateCuXmlForProduct')));
			
		// Let the cloud know where to start from the next time it calls
		//   for product data
		   
		if ($this->rangeNext <= $totp) {
			echo "<RangeNext>".$this->rangeNext."</RangeNext>\n";
		}else {
			// Start from beginning next time
			echo "<RangeNext>0</RangeNext>\n";
		}
			
        echo "</Products>\n";
    }
}

?>