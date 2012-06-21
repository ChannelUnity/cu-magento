<?php
/**
 * ChannelUnity connector for Magento Commerce 
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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
        private $upperLimit = 250;
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
        
        public function generateCuXmlForSingleProduct($productId, $storeId) {
            $productXml = "";
            $bNeedCustomOptionProducts = false; // custom options needed?
            $skuList = array(); // SKUs of the custom option child products
            $customOptionAttrs = array();
            $customOptionsData = array();
            
            $product = Mage::getModel('catalog/product');
            $product->setStoreId($storeId)->load($productId);
            
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
            
            $attributeSetModel = Mage::getModel("eav/entity_attribute_set");
            $attributeSetModel->load($product->getData('attribute_set_id'));
            $attributeSetName = $attributeSetModel->getAttributeSetName();
            
            $productXml = "<Product>\n";
            $productXml .= "  <RemoteId>".$product->getId()."</RemoteId>\n";
            $productXml .= "  <ProductType><![CDATA[".$attributeSetName." ]]></ProductType>\n";
            $productXml .= "  <Title><![CDATA[{$product->getData('name')} ]]></Title>\n";
            $productXml .= "  <Description><![CDATA[{$product->getData('description')} ]]></Description>\n";
            $productXml .= "  <SKU><![CDATA[{$product->getData('sku')}]]></SKU>\n";
            $productXml .= "  <Price>{$product->getData('price')}</Price>\n";
            $productXml .= "  <Quantity>{$qty}</Quantity>\n";
            $productXml .= "  <Category>{$catids}</Category>\n";
            $productXml .= "  <CategoryName><![CDATA[{$catnames} ]]></CategoryName>\n";
            $productXml .= "  <Image><![CDATA[{$imageUrl}]]></Image>\n";
            
            // Add associated/child product references if applicable
            $productXml .= "  <RelatedSKUs>\n";
            
            $variationXml = "  <Variations>\n";
            
            if ($product->getData("type_id") == 'configurable') {
                
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
                
                // Do we need to do variations?
                
                $childProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                foreach ($childProducts as $cp) {
                    
                    $productXml .= "  <SKU><![CDATA[{$cp->getData('sku')}]]></SKU>\n";
                }
            }
            else if ($product->getData('has_options') == 1) {
                $bNeedCustomOptionProducts = true;
                
                // Product has custom options
               
                foreach ($product->getOptions() as $o) {
                    $optionType = $o->getType();
                    
                    // Look at only drop down boxes or radio buttons
                    
                    if (($optionType == 'drop_down' || $optionType == 'radio') 
                        && $o->getData("is_require") == 1) {
                        
                        $optTitle = $o->getData('title');
                        $optTitle = "custom_".ereg_replace("[^A-Za-z0-9_]", "", str_replace(" ", "_", $optTitle));
                        
                        $customOptionsData[$optTitle] = array();
                        
                        $variationXml .= "    <Variation><![CDATA[{$optTitle}]]></Variation>\n";
                        $customOptionAttrs[] = $optTitle;
                        
                        $values = $o->getValues();
                        
                        if (count($skuList) == 0) {
                            
                            foreach ($values as $k => $v) {
                                
                                $skuList[] = $product->getData('sku')."-".$v->getData('sku');
                                
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')] = array();
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["title"] = $v->getData('title');
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["price"] = $v->getData('price');
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["price_type"] = $v->getData('price_type');
                            }
                            
                        }
                        else {
                            // Take a copy of the current SKU list
                            // append all the combinations
                            
                            $tempSkuList = array();
                            foreach ($values as $k => $v) {
                                
                                $tempSkuList[] = $v->getData('sku');
                                
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')] = array();
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["title"] = $v->getData('title');
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["price"] = $v->getData('price');
                                $customOptionsData[count($customOptionAttrs)][$v->getData('sku')]["price_type"] = $v->getData('price_type');
                            }
                            
                            $newSkuList = array();
                            
                            foreach ($skuList as $oldSku) {
                                
                                foreach ($tempSkuList as $newSku) {
                                    
                                    $newSkuList[] = $oldSku."-".$newSku;
                                }
                            }
                            
                            $skuList = $newSkuList;
                        }
                        
                    }
                }
                
                // Build up the SKU combinations for each combination of options
                foreach ($skuList as $relsku) {
                    
                    $productXml .= "    <SKU><![CDATA[{$relsku}]]></SKU>\n";
                }
                
            }
            
            $variationXml .= "  </Variations>\n";
            $productXml .= "  </RelatedSKUs>\n";
            $productXml .= $variationXml;
            
            $productXml .= "  <Custom>\n";
            
            // Enumerate all other attribute values
            $productXml .= $this->enumerateCustomAttributesForProduct($product);
            
            $productXml .= "  </Custom>\n";
            $productXml .= "</Product>\n";
            
            // ============ Now generate product elements for all possible custom options ============
            $idIncrement = 1000000;
            
            if ($bNeedCustomOptionProducts) {
                
                foreach ($skuList as $customSku) {
                    
                    $skuParts = explode("-", $customSku);
                    
                    $productXml .= "<Product>\n";
                    $productXml .= "  <RemoteId>".(($idIncrement++) + $product->getId())."</RemoteId>\n";
                    $productXml .= "  <ProductType><![CDATA[".$attributeSetName." ]]></ProductType>\n";
                    $productXml .= "  <Title><![CDATA[{$product->getData('name')} ]]></Title>\n";
                    $productXml .= "  <Description><![CDATA[{$product->getData('description')} ]]></Description>\n";
                    $productXml .= "  <SKU><![CDATA[{$customSku}]]></SKU>\n";
                    $productXml .= "  <Quantity>{$qty}</Quantity>\n";
                    $productXml .= "  <Category>{$catids}</Category>\n";
                    $productXml .= "  <CategoryName><![CDATA[{$catnames} ]]></CategoryName>\n";
                    $productXml .= "  <Image><![CDATA[{$imageUrl}]]></Image>\n";
                    $productXml .= "  <RelatedSKUs>   </RelatedSKUs>  <Variations>   </Variations>\n";
                    $productXml .= "  <Custom>\n";
                    
                    // Enumerate all other attribute values
                    $productXml .= $this->enumerateCustomAttributesForProduct($product);
                    
                    $basePrice = $product->getData('price');
                    $extraPrice = 0.00;
                    
                    $indexTemp = 1;
                    for ( ; $indexTemp < count($skuParts); ) {
                        $part = $skuParts[$indexTemp];
                        
                        $keycust = $customOptionAttrs[$indexTemp-1];
                        
                        $custValue = $customOptionsData[$indexTemp][$part]['title'];
                        
                        $priceExtra = $customOptionsData[$indexTemp][$part]['price'];
                        $priceType = $customOptionsData[$indexTemp][$part]['price_type'];
                        
                        if ($priceType == "fixed") {
                            
                            $extraPrice += (double)$priceExtra;
                        }
                        else if ($priceType == "percent") {
                            
                            $extraPrice += $basePrice * (100.0 + $priceExtra)/100.0;
                        }
                        
                        $productXml .= "    <$keycust><![CDATA[".$custValue."]]></$keycust>\n";
                        
                        $indexTemp++;
                    }
                    
                    $basePrice += $extraPrice; // custom options have prices attached
                    
                    $productXml .= "  </Custom>\n";
                    
                    
                    $productXml .= "  <Price>$basePrice</Price>\n";
                    
                    $productXml .= "</Product>\n";
                }
            }
            // =======================================================================================
            unset($product);
            
            return $productXml;
        }
        
        public function enumerateCustomAttributesForProduct($product) {
            $productXml = "";
            
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
                        
                        if (is_object($attribute)) {
                            
                            $prodDataValue = $attribute->getSource()
                                ->getOptionText($myval);
                            
                            if ($prodDataValue == '') {
                                
                                $prodDataValue = $myval;
                            }
                        }
                        else {
                            $prodDataValue = $myval;
                        }
                    } else {
                        $prodDataValue = $product->getData($attr);
                    }
                    
                    if (is_object($prodDataValue)) {
                        if ('Varien_Object' == get_class($prodDataValue)) {
                            
                            $prodDataValue = $prodDataValue->toXml();
                        }
                    }
                    if (is_array($prodDataValue)) {
                        
                        $prodDataValue = $product->getData($attr);
                        $productXml .= "    <$attr><![CDATA[$prodDataValue]]></$attr>\n";
                    }
                    else {
                        
                        if (!is_object($prodDataValue)) {
                            $productXml .= "    <$attr><![CDATA[".$prodDataValue."]]></$attr>\n";
                        } else if ('Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Type_Configurable_Attribute_Collection' 
                                   == get_class($prodDataValue)) {
                            $productXml .= "    <$attr><![CDATA[Mage_Core_Model_Mysql4_Collection_Abstract]]></$attr>\n";
                        }
                    }
                }
            }
            return $productXml;
        }
        
        public function generateCuXmlForProductEcho($args) {
            echo $this->generateCuXmlForProduct($args);
        }
    	
        public function generateCuXmlForProduct($args) {
            $productXml = '';
            $this->countCurr++;
            
            $this->maxMemory = $this->return_bytes(ini_get('memory_limit'));
            
            if ($this->runtime < $this->maxruntime 
                && (memory_get_peak_usage() + $this->changeMemory) < $this->maxMemory
                && $this->countCurr <= $this->upperLimit) 
            {
                $row = $args['row'];
                $this->rangeNext = $row["entity_id"] + 1;
                
                $productXml .= $this->generateCuXmlForSingleProduct($row["entity_id"], $args["storeId"]);
                
                // after....
                $this->endtime = $this->microtime_float();
                $this->runtime = round($this->endtime - $this->starttime);
                
                $tmpval = memory_get_peak_usage() - $this->beforeMemory;
                if ($tmpval > $this->changeMemory) {
                    $this->changeMemory = $tmpval;
                }
                
            } else {
                
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
        
        public function doSetValue($request) {
            $storeId = (string) $request->StoreviewId;
            $fieldName = (string) $request->FieldName;
            $fieldValue = (string) $request->FieldValue;
            $sku = (string) $request->SKU;
            
            $collectionOfProduct = Mage::getModel($this->_collection)->getCollection()->addStoreFilter($storeId);
            $collectionOfProduct->setPageSize(1);
            $collectionOfProduct->setCurPage(1);
            $collectionOfProduct->addFieldToFilter('sku', $sku);
            $product = $collectionOfProduct->getFirstItem();
            
            // set an attribute for the product
            $product->setData($fieldName, $fieldValue);
            $product->save();
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
            
            try {
                
                // get the highest product ID
                if (version_compare(Mage::getVersion(), "1.6.0.0", ">=")
                    && class_exists("Mage_Catalog_Model_Resource_Product_Collection")) {
                    $collectionOfProduct = Mage::getModel('channelunity/collection')->addStoreFilter($storeId);
                }
                else {
                    $collectionOfProduct = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($storeId);
                }
                $collectionOfProduct->setOrder('entity_id', 'DESC');
                $collectionOfProduct->setPageSize(1);
                $collectionOfProduct->setCurPage(1);
                $totp = $collectionOfProduct->getFirstItem();
                $totp = $totp->getEntityId();
                
                if (version_compare(Mage::getVersion(), "1.6.0.0", ">=")
                    && class_exists("Mage_Catalog_Model_Resource_Product_Collection")) {
                    $collectionOfProduct = Mage::getModel('channelunity/collection')->addStoreFilter($storeId);
                }
                else {
                    $collectionOfProduct = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($storeId);
                }
                $totalNumProducts = $this->executeQueryScalar(str_replace("SELECT", "SELECT count(*) as count_cu, ", $collectionOfProduct->getSelect()), 'count_cu');
                
                $collectionOfProduct->addAttributeToFilter("entity_id", array('gteq' => $rangeFrom))
                    ->setOrder('entity_id', 'ASC');
                
                // monitor memory and max exec
                if ($this->maxMemoryChar == "M") {
                    $this->maxMemory = str_replace("M", "", $this->maxMemory);
                    $this->maxMemory = $this->maxMemory * 1024 * 1024;
                } else if ($this->maxMemoryChar == "G") {
                    $this->maxMemory = str_replace("G", "", $this->maxMemory);
                    $this->maxMemory = $this->maxMemory * 1024 * 1024 * 1024;
                }   
                
                if ($this->maxMemory < 100) {
                    $this->maxMemory = 10000000000;
                }
                
                $query = str_replace("INNER JOIN", "LEFT JOIN", $collectionOfProduct->getSelect());
                
                echo "<Query><![CDATA[$query]]></Query>\n";
                
                try {
                    
                    Mage::getSingleton('core/resource_iterator')->walk($query, 
                                                                       array(array($this, 'generateCuXmlForProductEcho')), 
                                                                       array('storeId' => $storeId),
                                                                       $collectionOfProduct->getSelect()->getAdapter());
                    
                }
                catch (Exception $x1) {
                    Mage::getSingleton('core/resource_iterator')->walk($collectionOfProduct->getSelect(), 
                                                                       array(array($this, 'generateCuXmlForProductEcho')), 
                                                                       array('storeId' => $storeId));
                }
                
                // Let the cloud know where to start from the next time it calls
                //   for product data
                
                if ($this->rangeNext <= $totp) {
                    echo "<RangeNext>".$this->rangeNext."</RangeNext>\n";
                } else {
                    // Start from beginning next time
                    echo "<RangeNext>0</RangeNext>\n";
                }
                
                echo "<TotalProducts>$totalNumProducts</TotalProducts>\n";
            }
            catch (Exception $x) {
                echo "<Error><![CDATA[".$x->getTraceAsString()."]]></Error>\n";
            }
            echo "</Products>\n";
        }
        
        private function executeQuery($sql) {
            $db = Mage::getSingleton("core/resource")->getConnection("core_write");
            
            $result = $db->query($sql);
            
            $resultArray = array();
            
            foreach ($result as $row) {
                
                $resultArray[] = $row;
            }
            
            return $resultArray;
        }
        private function executeQueryScalar($sql, $column) {
            $result = $this->executeQuery($sql);
            
            foreach ($result as $row) {
                return $row[$column];
            }
            return -1;
        }
    }
    
?>