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
        
        $orderXml =  "<SubscriptionID>{$order->getData('channelunity_subscriptionid')}</SubscriptionID>\n";
        $orderXml .= "<OrderID>{$order->getData('channelunity_orderid')}</OrderID>\n";
        $orderXml .= "<OrderStatus>{$orderStatus}</OrderStatus>\n";
    
        return $orderXml;
    }
    
    public function generateCuXmlForOrderShip($order,
                                              $carrierName,
                                              $shipMethod,
                                              $trackNumber) {
        
        $orderXml =  $this->generateCuXmlForOrderStatus($order);
        
        $orderXml .= "<ShipmentDate>{}</ShipmentDate>\n"; //TODO
        $orderXml .= "<CarrierName>$carrierName</CarrierName>\n";
        $orderXml .= "<ShipmentMethod>$shipMethod</ShipmentMethod>\n";
        $orderXml .= "<TrackingNumber>$trackNumber</TrackingNumber>\n";
        
        return $orderXml;
    }
    
	public function fixEncoding($in_str)
	{
		 if (function_exists('mb_strlen')) {
		 	
		    $cur_encoding = mb_detect_encoding($in_str);
		   	if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8")){
		  		
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
		
			$quote = Mage::getModel('sales/quote')->setStoreId(1  /*(string) $order->storeId TODO */);
			
			// we need to verify (from our XML) that we can create customer accounts
			// and that we can contact the customer.
			
			$customer = Mage::getModel('customer/customer')
							->setWebsiteId((string) $order->websiteId)
							->loadByEmail((string) $order->customer->email);
					
			if ($customer->getId() > 0){
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
							"firstname"=>$this->fixEncoding((string) $order->customer->firstname),
							"lastname"=>$this->fixEncoding((string) $order->customer->surname),
							"email"=>(string) $order->customer->email,
							"website_id"=>(string) $order->websiteId,
						);
				
						$customer->addData($customerData);
						$customer->save();
						$customer->setPassword($customer->generatePassword(8))->save();
						$customer->sendNewAccountEmail();	
						$customer->save();				
						
						// and now to assign the customer onto the quote.
						$quote->assignCustomer($customer);	
			
					}else{
						// create the order as a guest.
						$quote->setCustomerFirstname($this->fixEncoding((string) $order->customer->firstname));
						$quote->setCustomerLastname($this->fixEncoding((string) $order->customer->surname));
						$quote->setCustomerEmail((string) $order->customer->email);
						$quote->setCustomerIsGuest(1);
					}
                
			} else {
					// create the order as a guest.
					$quote->setCustomerFirstname($this->fixEncoding((string) $order->customer->firstname));
					$quote->setCustomerLastname($this->fixEncoding((string) $order->customer->surname));
					$quote->setCustomerEmail((string) $order->customer->email);
					$quote->setCustomerIsGuest(1);
			}
            }
			
			
			// add product(s)
			foreach ($order->OrderItems->Item as $orderitem) {
				$product = Mage::getModel('catalog/product')->loadByAttribute('sku', (string) $orderitem->SKU);
                
                // TODO create stub if needed
                // TODO check what SKU is mapped to - or is this taken care of at CU end?
                
				$item = Mage::getModel('sales/quote_item');
				$item->setQuote($quote)->setProduct($product);
				$item->setData('qty', (string) $orderitem->itemQuantityOrdered);
				$item->setData('custom_price', (string) $orderitem->itemPriceEach);
				$quote->addItem($item);
			}
		
			// set the billing address
			$billingAddressData = array(
                'firstname' => $this->fixEncoding((string) $order->BillingInfo->Name),
                'lastname' => $this->fixEncoding((string) $order->BillingInfo->Name),
                'email' =>  (string) $order->BillingInfo->Email,
                'telephone' =>  (string) $order->BillingInfo->PhoneNumber,
            /*
            'street' =>  (string) $this->fixEncoding((string) $order->billingAddress->addressLine1."\n"
                .(string) $order->billingAddress->addressLine2
                ."\n".(string) $order->billingAddress->addressLine3),
			'city' =>  $this->fixEncoding((string) $order->billingAddress->addressCity),
			'postcode' =>  $this->fixEncoding((string) $order->billingAddress->addressZip),
			'region' =>  (string) $order->billingAddress->addressState,
			'country_id' =>  (string) $order->billingAddress->addressCountry,*/
			);
			 
	 		// add the billing address to the quote.
			$billingAddress = $quote->getBillingAddress()->addData($billingAddressData);
			
			// set the shipping address
			$shippingAddressData = array(
				'firstname' => $this->fixEncoding((string) $order->ShippingInfo->RecipientName),
				'lastname' => $this->fixEncoding((string) $order->ShippingInfo->RecipientName),
				'street' =>  (string) $this->fixEncoding((string) $order->ShippingInfo->Address1
                     ."\n".(string) $order->ShippingInfo->Address2
                     ."\n".(string) $order->ShippingInfo->Address3),
				'city' =>  $this->fixEncoding((string) $order->ShippingInfo->City),
				'postcode' =>  $this->fixEncoding((string) $order->ShippingInfo->PostalCode),
				'region' =>  (string) $order->ShippingInfo->State,
				'country_id' =>  (string) $order->ShippingInfo->Country,
			//	'email' =>  (string) $order->ShippingInfo->email,
				'telephone' =>  (string) $order->ShippingInfo->PhoneNumber
			);
	
			// add the billing address to the quote.
			$shippingAddress = $quote->getShippingAddress()->addData($shippingAddressData);
			 
			$shippingAddress->setShippingMethod('ChannelUnity');
			$shippingAddress->setShippingDescription((string) $order->ShippingInfo->Service);
			$shippingAddress->setShippingPrice((string) $order->ShippingInfo->ShippingPrice);
			$shippingAddress->setPaymentMethod('channelunitypayment');
			 
			$quote->getPayment()->importData(array(
												   'method' => 'channelunitypayment',
												   'channelunity_orderid' => (string) $order->channelunityId,
												   'channelunity_remoteorderid' => (string) $order->channelunityRemoteOrderId,
												   'channelunity_remotechannelname' => (string) $order->channelunityChannelName,
												   'channelunity_remoteusername' => (string) $order->channelunityRemoteUsername,
												   ));
			 
			$quote->collectTotals()->save();
			 
			if (version_compare(Mage::getVersion(), "1.4.0.0", ">=")){
				$service = Mage::getModel('sales/service_quote', $quote);
			}else{
				$service = Mage::getModel('channelunity/ordercreatebackport', $quote);
			}
			
			$service->submitAll();
			$newOrder = $service->getOrder(); // returns full order object.
		
			
			
		}
		
	}
	
	
	
	public function doUpdate($dataArray){
		
	}	
	


}

?>