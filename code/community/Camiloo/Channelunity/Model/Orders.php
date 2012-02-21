<?php

class Camiloo_Channelunity_Model_Orders extends Camiloo_Channelunity_Model_Abstract
{
	
	protected $_collection = 'sales/order';

    
    
    /*

     RequestType	OrderStatusUpdate
     
     Payload	XML Message
     <SubscriptionID />  The corresponding ChannelUnity subscription ID
     <OrderID />  The channel specific order ID being shipped
     <OrderStatus /> The new order status
     
     If being shipped / completed:
     <ShipmentDate />  The date and time the item was shipped
     <CarrierName />  The name of the delivery company
     <ShipmentMethod /> The shipping method used
     <TrackingNumber />  The tracking number for the shipment
     */
    public function generateCuXmlForOrderStatus($order) {
        $orderStatus = $order->getState();
        
        if ($orderStatus == 'canceled') {
            $orderStatus = "Cancelled";
        }
        else if ($orderStatus == 'closed') {
            $orderStatus = "Cancelled";
        }
        else if ($orderStatus == 'complete') {
            $orderStatus = "Complete";
        }
        else if ($orderStatus == 'processing') {
            $orderStatus = "Processing";
        }
        else if ($orderStatus == 'holded') {
            $orderStatus = "OnHold";
        }
        else if ($orderStatus == 'new') {
            $orderStatus = "Processing";
        }
        else if ($orderStatus == 'payment_review') {
            $orderStatus = "OnHold";
        }
        else if ($orderStatus == 'pending_payment') {
            $orderStatus = "OnHold";
        }
        else if ($orderStatus == 'fraud') {
            $orderStatus = "OnHold";
        }
        else {
            $orderStatus = "Processing";
        }
        
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->setOrderFilter($order);
        
        $orderXml = "";
        
        foreach ($collection as $txn) {
            $infoArray = $txn->getAdditionalInformation();
            $orderXml .= "<SubscriptionID>{$infoArray['SubscriptionId']}</SubscriptionID>\n";
            $orderXml .= "<OrderID>{$infoArray['RemoteOrderID']}</OrderID>\n";
        
            break;
        }
        $orderXml .= "<OrderStatus>$orderStatus</OrderStatus>\n";
    
        return $orderXml;
    }
    
    public function generateCuXmlForOrderShip($order,
                                              $carrierName,
                                              $shipMethod,
                                              $trackNumber) {
        
        $orderXml =  $this->generateCuXmlForOrderStatus($order);
        
        $orderXml .= "<ShipmentDate>".date("c")."</ShipmentDate>\n";
        $orderXml .= "<CarrierName>$carrierName</CarrierName>\n";
        $orderXml .= "<ShipmentMethod>$shipMethod</ShipmentMethod>\n";
        $orderXml .= "<TrackingNumber>$trackNumber</TrackingNumber>\n";
        
        return $orderXml;
    }
    
	public function fixEncoding($in_str)
	{
		 if (function_exists('mb_strlen')) {
		 	
		    $cur_encoding = mb_detect_encoding($in_str);
		   	if ($cur_encoding == "UTF-8" && mb_check_encoding($in_str, "UTF-8")) {
		  		
		   	} else {
		  		$in_str = utf8_encode($in_str);
		   }
		 }
		
		return $in_str;
	}

	public function doCreate($dataArray) {
	
        
		/**********
		*	TODO: Make this handle the HasTax flags and calculate ex tax as necessary.
		****
         
         <MerchantName />	The unique merchant name	
         <SubscriptionID />	The subscription ID to which this message relates	
         <ServiceSKU />	The channel subscribed to	CU_AMZ_UK
         <Orders>	Group containing one or more orders	
         + <Order>	Top level order element	
         + + <OrderId />	Channel Specific order ID	203-7998859-0936342
         + + <PurchaseDate />	Date and time on which the order was placed on the Channel	2011-05-24T22:40:24+00:00
         + + <Currency />	The currency the order was purchased in	GBP
         + + <OrderItems>	Group of one or more ordered items	
         + + + <Item>	Information about a line item	
         + + + + <SKU />	Stock keeping unit	ITEM2424
         + + + + <Name />	Name of the product	Chocolate Bar
         + + + + <Quantity />	The quantity purchased	1
         + + + + <Price />	The price of each individual item including any applicable taxes	1.99
         + + + + <Tax />	The amount of the price which is tax	0.22
         + + + </Item>		
         + + </OrderItems>		
         + + <ShippingInfo>	Parent element for the shipping information	
         + + + <RecipientName />	Name of the recipient for delivery	
         + + + <Address1 />	Line 1 of delivery address	
         + + + <Address2 />	Line 2 of delivery address	
         + + + <Address3 />	Line 3 of delivery address	
         + + + <City />	Delivery city	Manchester
         + + + <State />	Delivery state (or county for UK)	Lancs
         + + + <PostalCode />	Delivery postal code or ZIP code	M1 1AA
         + + + <Country />	Two letter delivery country code	GB
         + + + <PhoneNumber />	Phone number for the delivery location	01619321015
         + + + <ShippingPrice />	Price paid for delivery including tax	1.99
         + + + <ShippingTax />	The amount of the shipping price which was tax	0.22
         + + + <Service />	The delivery service (e.g. first class, recorded delivery, expedited, etc.)	Standard
         + + + <DeliveryInstructions />	Instructions for the delivery driver	
         + + + <GiftWrapPrice />	Price paid for gift wrapping including tax	
         + + + <GiftWrapTax />	Amount of the gift wrap price which was tax	
         + + + <GiftWrapType />	Channel Specific gift wrap type	
         + + + <GiftMessage />	Gift message for the item	
         + + </ShippingInfo>	
         
         + </Order>		
         </Orders>		
         
         *******/		
	
		// this method takes an array of correct structure and creates a valid order creation
		// request within Magento.
		
		foreach ($dataArray->Orders->Order as $order) {
		
          echo "<Info>Next order: {$order->OrderId} Create Quote</Info>";
            
          try {
		
			$quote = Mage::getModel('sales/quote')->setStoreId((string) $dataArray->StoreviewId);
			
			// we need to verify (from our XML) that we can create customer accounts
			// and that we can contact the customer.
			
            
            echo "<Info>Create Customer</Info>";

            
			$customer = Mage::getModel('customer/customer')
							->setWebsiteId((string) $dataArray->WebsiteId)
							->loadByEmail((string) $order->BillingInfo->Email);
					
			if ($customer->getId() > 0) {
				$quote->assignCustomer($customer);
			} else {
				if ((string) $order->customer->canCreateCustomer) {
					// customer does not exist, but we can create one.
					// however. if we can't email the customer their password
					// there's no point continuing.
					if ((string) $order->customer->canEmailCustomer) {
						
						// we can create a customer, and can email them a random password
						// within a welcome email. So lets do this.
						
						$customer = Mage::getModel('customer/customer');
                        
						$customerData = array(
							"firstname" => $this->fixEncoding((string) $order->customer->firstname),
							"lastname" => $this->fixEncoding((string) $order->customer->surname),
							"email" => (string) $order->customer->email,
							"website_id" => (string) $order->websiteId,
						);
                        
						$customer->addData($customerData);
						$customer->save();
						$customer->setPassword($customer->generatePassword(8))->save();
						$customer->sendNewAccountEmail();
						$customer->save();
						
						// and now to assign the customer onto the quote.
						$quote->assignCustomer($customer);	
                        
					} else {
						// create the order as a guest.
						$quote->setCustomerFirstname($this->fixEncoding((string) $order->customer->firstname));
						$quote->setCustomerLastname($this->fixEncoding((string) $order->customer->surname));
						$quote->setCustomerEmail((string) $order->customer->email);
						$quote->setCustomerIsGuest(1);
					}
                
                } else {
					// create the order as a guest.
					$quote->setCustomerFirstname($this->fixEncoding($this->getFirstName((string) $order->BillingInfo->Name)));
					$quote->setCustomerLastname($this->fixEncoding($this->getLastName((string) $order->BillingInfo->Name)));
					$quote->setCustomerEmail((string) $order->BillingInfo->Email);
					$quote->setCustomerIsGuest(1);
                }
            }
			
            echo "<Info>Order currency {$order->Currency}</Info>";
            
            $quote->getStore()->setCurrentCurrencyCode((string) $order->Currency);
			
            $storeCurrency = $quote->getStore()->getBaseCurrencyCode();
              
            echo "<Info>Store currency $storeCurrency</Info>";
            
            $currencyObject = Mage::getModel('directory/currency');
            $reverseRate = $currencyObject->getResource()->getRate($storeCurrency, (string) $order->Currency);
            
              if ($reverseRate == "") {
                  $reverseRate = 1.0;
              }
              
            echo "<ConversionRate>$reverseRate</ConversionRate>";
            
			// add product(s)
			foreach ($order->OrderItems->Item as $orderitem) {
				$product = Mage::getModel('catalog/product')->loadByAttribute('sku', (string) $orderitem->SKU);
                
                
                // TODO check what SKU is mapped to - or is this taken care of at CU end?
                
                if (is_object($product)) {
                    
                    $product->setPrice(((string) $orderitem->Price) / $reverseRate);
                    
                    $item = Mage::getModel('sales/quote_item');
                    $item->setQuote($quote)->setProduct($product);
                    $item->setData('qty', (string) $orderitem->Quantity);
                    $quote->addItem($item);
                }
                else {
                    echo "<Info>Can't find SKU to add to quote ".(string) $orderitem->SKU.", trying to create stub</Info>";
                    
                    // Create stub if needed
					$this->createStubProduct((string) $orderitem->SKU, (string) $orderitem->Name, 
                                             (string) $dataArray->WebsiteId, 
                                             (string) $order->OrderId, (string) $orderitem->Price, 
                                             (string) $orderitem->Quantity);
                    
                    // Try once again to add our item to the quote
                    $product = Mage::getModel('catalog/product')->loadByAttribute('sku', 
                                                                                  (string) $orderitem->SKU);
                    
                    if (is_object($product)) {
                        
                        $product->setPrice(((string) $orderitem->Price) / $reverseRate);
                        
                        $item = Mage::getModel('sales/quote_item');
                        $item->setQuote($quote)->setProduct($product);
                        $item->setData('qty', (string) $orderitem->Quantity);
                        $quote->addItem($item);
                    }
                    else {
                        echo "<Info>Can't find SKU to add to quote ".(string) $orderitem->SKU."</Info>";
                    }
                }
			}
            
            
            echo "<Info>Set Billing Address</Info>";
            
            $postcode = $this->fixEncoding((string) $order->ShippingInfo->PostalCode);
            $postcode = str_replace("-", "_", $postcode); // can throw exception if - in postcode
		
			// set the billing address
			$billingAddressData = array(
                'firstname' => $this->fixEncoding($this->getFirstName((string) $order->BillingInfo->Name)),
                'lastname' => $this->fixEncoding($this->getLastName((string) $order->BillingInfo->Name)),
                'email' =>  (string) $order->BillingInfo->Email,
                'telephone' =>  (string) $order->BillingInfo->PhoneNumber,
            
            'street' =>  (string) $this->fixEncoding((string) $order->ShippingInfo->Address1."\n"
                .(string) $order->ShippingInfo->Address2
                ."\n".(string) $order->ShippingInfo->Address3),
			'city' =>  $this->fixEncoding((string) $order->ShippingInfo->City),
			'postcode' =>  $postcode,
			'region' =>  (string) $order->ShippingInfo->State,
			'region_id' =>  (string) $order->ShippingInfo->State,
			'country_id' =>  (string) $order->ShippingInfo->Country
			);
            
	 		// add the billing address to the quote.
			$billingAddress = $quote->getBillingAddress()->addData($billingAddressData);
              
              
              echo "<Info>Set Shipping Address</Info>";
			
			// set the shipping address
			$shippingAddressData = array(
				'firstname' => $this->fixEncoding($this->getFirstName((string) $order->ShippingInfo->RecipientName)),
				'lastname' => $this->fixEncoding($this->getLastName((string) $order->ShippingInfo->RecipientName)),
				'street' =>  (string) $this->fixEncoding((string) $order->ShippingInfo->Address1
                     ."\n".(string) $order->ShippingInfo->Address2
                     ."\n".(string) $order->ShippingInfo->Address3),
				'city' =>  $this->fixEncoding((string) $order->ShippingInfo->City),
				'postcode' => $postcode,
				'region' =>  (string) $order->ShippingInfo->State,
				'region_id' =>  (string) $order->ShippingInfo->State,
				'country_id' =>  (string) $order->ShippingInfo->Country,
			//	'email' =>  (string) $order->ShippingInfo->email,
				'telephone' =>  (string) $order->ShippingInfo->PhoneNumber
			);
            
            Mage::getSingleton('core/session')->setShippingPrice(((string) $order->ShippingInfo->ShippingPrice) / $reverseRate);
	
			// add the shipping address to the quote.
			$shippingAddress = $quote->getShippingAddress()->addData($shippingAddressData);
            /////////////////////////////////////////////
              $method = Mage::getModel('shipping/rate_result_method');
              $method->setCarrier('channelunitycustomrate');
              $method->setCarrierTitle('ChannelUnity Shipping');
              $method->setMethod('channelunitycustomrate');
              $method->setMethodTitle('ChannelUnity Rate');
              
              $shipPrice = Mage::getSingleton('core/session')->getShippingPrice();
              
              $method->setPrice($shipPrice);
              $method->setCost($shipPrice);
              
              
              $rate = Mage::getModel('sales/quote_address_rate')
                ->importShippingRate($method);
              
              $shippingAddress->addShippingRate($rate);
              
            /////////////////////////////////////////////
			$shippingAddress->setShippingMethod('channelunitycustomrate_channelunitycustomrate');
			$shippingAddress->setShippingDescription((string) $order->ShippingInfo->Service);
			$shippingAddress->setPaymentMethod('channelunitypayment');
                          
              $quote->getPayment()->importData(array(
                                                     'method' => 'channelunitypayment'
                                                     ));
            $quote->collectTotals()->save();
            
			if (version_compare(Mage::getVersion(), "1.4.0.0", ">=")) {
				$service = Mage::getModel('sales/service_quote', $quote);
			} else {
				$service = Mage::getModel('channelunity/ordercreatebackport', $quote);
			}
			
			$service->submitAll();
			$newOrder = $service->getOrder(); // returns full order object.
            
              
            if (!is_object($newOrder)) {
                echo "<NotImported>".((string) $order->OrderId)."</NotImported>";
                continue;
            }
            else {
                echo "<Imported>".((string) $order->OrderId)."</Imported>";
            }
              
          } catch (Exception $x) {
              echo "<Exception><![CDATA[".$x->getMessage()."]]></Exception>";
              echo "<NotImported>".((string) $order->OrderId)."</NotImported>";
              continue;
          }
            
            $newOrder->setState('processing', 'processing', 'Order imported from ChannelUnity', false);
            
            // This order will have been paid for, otherwise it won't have imported
            
            $invoiceId = Mage::getModel('sales/order_invoice_api')
                ->create($newOrder->getIncrementId(), array());
            
            $invoice = Mage::getModel('sales/order_invoice')
                ->loadByIncrementId($invoiceId);
            
            /**
             * Pay invoice
             * i.e. the invoice state is now changed to 'Paid'
             */
            $invoice->capture()->save();
            
            $newOrder->setTotalPaid($newOrder->getGrandTotal());
            $newOrder->setBaseTotalPaid($newOrder->getBaseGrandTotal());
            
            /** Make a transaction to store CU info */
            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($newOrder->getPayment());
            $transaction->setOrder($newOrder);
            $transaction->setTxnType('capture');
            $transaction->setAdditionalInformation('SubscriptionId', (string) $dataArray->SubscriptionId);
            $transaction->setAdditionalInformation('RemoteOrderID', (string) $order->OrderId);
            
            $serviceType = (string) $order->ServiceSku;
            switch ($serviceType) {
                case "CU_AMZ_UK":
                    $serviceType = "Amazon.co.uk";
                    break;
                case "CU_AMZ_COM":
                    $serviceType = "Amazon.com";
                    break;
                case "CU_AMZ_DE":
                    $serviceType = "Amazon.de";
                    break;
                case "CU_AMZ_FR":
                    $serviceType = "Amazon.fr";
                    break;
                case "CU_AMZ_CA":
                    $serviceType = "Amazon.ca";
                    break;
                case "CU_AMZ_IT":
                    $serviceType = "Amazon.it";
                    break;
                case "CU_AMZ_ES":
                    $serviceType = "Amazon.es";
                    break;
                case "CU_AMZ_JP":
                    $serviceType = "Amazon.co.jp";
                    break;
            }
            
            $transaction->setAdditionalInformation('ServiceType', $serviceType);
            
            // get order flags so we know whether it's an FBA order
            if (isset($order->OrderFlags) && ( ((string) $order->OrderFlags) == "AMAZON_FBA")) {
                
                $transaction->setAdditionalInformation('AmazonFBA', 'Yes');
                // TODO - can't set 'complete' state manually - ideally import tracking info and create shipment in Mage
      //          $newOrder->setState('complete', 'complete', 'Order was fulfilled by Amazon', false);
            }
            $transaction->save();
            
            /** Add gift message */
            if (isset($order->ShippingInfo->GiftMessage)) {
                
				$message = Mage::getModel('giftmessage/message');
                
              	// $gift_sender = $message->getData('sender');
                // $gift_recipient = $message->getData('recipient');
                
                $message->setMessage($order->ShippingInfo->GiftMessage);
				$message->save();
                
                $gift_message_id = $message->getId();
                
                $newOrder->setData('gift_message_id', $gift_message_id);
			}
            
            $newOrder->setCreatedAt(strtotime((string) $order->PurchaseDate));
            $newOrder->save();
		}
		
	}
    
    private function createStubProduct($missingSku, $productTitle, $websiteID, $keyorder, $price, $qty) {
        $product = new Mage_Catalog_Model_Product();
        
        $db = Mage::getSingleton("core/resource")->getConnection("core_write");
        $table_prefix = Mage::getConfig()->getTablePrefix();
        $sql = "SELECT entity_type_id FROM  {$table_prefix}eav_entity_type WHERE entity_type_code='catalog_product'";
        $result = $db->query($sql);
        $row = $result->fetch();
        
        $sql = "SELECT attribute_set_id FROM {$table_prefix}eav_attribute_set WHERE entity_type_id='".$row['entity_type_id']."' ORDER BY attribute_set_id ASC";
        $result = $db->query($sql);
        $row = $result->fetch();	
        $attributeSetId = $row['attribute_set_id'];
        
        // Build the product
        $product->setSku($missingSku);
        $product->setAttributeSetId($attributeSetId);
        $product->setTypeId('simple');
        $product->setName($productTitle);
        //TODO set the attribute marked as the SKU attribute
        
        $product->setWebsiteIDs(array($websiteID)); # derive website ID from store.
        $product->setDescription('Product missing from imported order ID '.$keyorder);
        $product->setShortDescription('Product missing from imported order ID '.$keyorder);
        $product->setPrice($price); # Set some price    
        
        // Default Magento attribute
        $product->setWeight('0.01');
        
        $product->setVisibility(1); // not visible
        $product->setStatus(1);	// status = enabled, other price shows as 0.00 in the order
        $product->setTaxClassId(0); # My default tax class
        $product->setStockData(array(
                                     'is_in_stock' => 1,
                                     'qty' => $qty
                                     ));
        
        $product->setCreatedAt(strtotime('now'));
        
        $product->save();
    }
    
    private function getFirstName($name) {
        $lastSpacePos = strrpos($name, " ");
        if ($lastSpacePos !== FALSE) {
            
            return substr($name, 0, $lastSpacePos);
        }
        else {
            
            return $name;
        }
    }
	
	private function getLastName($name) {
        $exp = explode(" ", $name);
        if (count($exp) > 0) {
            
            return $exp[count($exp) - 1];
        }
        else {
            
            return "___";
        }
    }
	
	public function doUpdate($dataArray) {
		
	}	
	


}

?>