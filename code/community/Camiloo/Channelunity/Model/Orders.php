<?php

/**
 * ChannelUnity connector for Magento Commerce
 *
 * @category   Camiloo
 * @package    Camiloo_Channelunity
 * @copyright  Copyright (c) 2012 Camiloo Limited (http://www.camiloo.co.uk)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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

    public function generateCuXmlForOrderStatus($order)
    {
        $orderStatus = $order->getState();

        if ($orderStatus == 'canceled') {
            $orderStatus = "Cancelled";
        } else if ($orderStatus == 'closed') {
            $orderStatus = "Cancelled";
        } else if ($orderStatus == 'complete') {
            $orderStatus = "Complete";
        } else if ($orderStatus == 'processing') {
            $orderStatus = "Processing";
        } else if ($orderStatus == 'holded') {
            $orderStatus = "OnHold";
        } else if ($orderStatus == 'new') {
            $orderStatus = "Processing";
        } else if ($orderStatus == 'payment_review') {
            $orderStatus = "OnHold";
        } else if ($orderStatus == 'pending_payment') {
            $orderStatus = "OnHold";
        } else if ($orderStatus == 'fraud') {
            $orderStatus = "OnHold";
        } else {
            $orderStatus = "Processing";
        }

        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                ->setOrderFilter($order);

        $orderXml = "";
        $isCu = false;

        foreach ($collection as $txn) {
            $infoArray = $txn->getAdditionalInformation();
            if (isset($infoArray['SubscriptionId'])) {
                $orderXml .= "<SubscriptionID>{$infoArray['SubscriptionId']}</SubscriptionID>\n";
                $isCu = true;
            }
            if (isset($infoArray['RemoteOrderID'])) {
                $orderXml .= "<OrderID>{$infoArray['RemoteOrderID']}</OrderID>\n";
                $isCu = true;
            }
            break;
        }

        if (!$isCu)
            return false;

        $orderXml .= "<OrderStatus>$orderStatus</OrderStatus>\n";

        return $orderXml;
    }

    public function generateCuXmlForOrderShip($order, $carrierName, $shipMethod, $trackNumber)
    {

        $orderXml = $this->generateCuXmlForOrderStatus($order);

        if (!empty($orderXml)) {

            $orderXml .= "<ShipmentDate>" . date("c") . "</ShipmentDate>\n";
            $orderXml .= "<CarrierName>$carrierName</CarrierName>\n";
            $orderXml .= "<ShipmentMethod>$shipMethod</ShipmentMethod>\n";
            $orderXml .= "<TrackingNumber>$trackNumber</TrackingNumber>\n";
        }

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

    public function doCreate($dataArray, $order)
    {
        // this method takes an array of the normal structure and creates an
        // order creation request within Magento.

        echo "<Info>Next order: {$order->OrderId} Create Quote</Info>";
        Mage::register('cu_order_in_progress', 1);
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

                    $customerEmail = (string) $order->BillingInfo->Email;
                    $bConvertBack = false; // change to true to get marketplace emails back

                    if ($bConvertBack) {
                        $serviceType = (string) $order->ServiceSku;
                        switch ($serviceType) {
                            case "CU_AMZ_UK":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.co.uk", $customerEmail);
                                break;
                            case "CU_AMZ_COM":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.com", $customerEmail);
                                break;
                            case "CU_AMZ_DE":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.de", $customerEmail);
                                break;
                            case "CU_AMZ_FR":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.fr", $customerEmail);
                                break;
                            case "CU_AMZ_CA":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.ca", $customerEmail);
                                break;
                            case "CU_AMZ_IT":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.it", $customerEmail);
                                break;
                            case "CU_AMZ_ES":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.es", $customerEmail);
                                break;
                            case "CU_AMZ_JP":
                                $customerEmail = str_replace("@channelunity.com", "@marketplace.amazon.co.jp", $customerEmail);
                                break;
                        }
                    }

                    $quote->setCustomerEmail($customerEmail);
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
            $itemOptions = array();

            echo "<Info>Set Billing Address</Info>";

            $postcode = $this->fixEncoding((string) $order->ShippingInfo->PostalCode);

            $regionModel = Mage::getModel('directory/region')->loadByCode((string) $order->ShippingInfo->State, (string) $order->ShippingInfo->Country);
            $regionId = is_object($regionModel) ? $regionModel->getId() : ((string) $order->ShippingInfo->State);

            
            if (!empty($order->ShippingInfo->Address1)
                    && !empty($order->ShippingInfo->Address2)
                    && ((string) $order->ServiceSku) == "CU_AMZ_DE") {

                // set the billing address
                $billingAddressData = array(
                    'firstname' => $this->fixEncoding($this->getFirstName((string) $order->BillingInfo->Name)),
                    'lastname' => $this->fixEncoding($this->getLastName((string) $order->BillingInfo->Name)),
                    'email' => (string) $order->BillingInfo->Email,
                    'telephone' => ( (string) $order->BillingInfo->PhoneNumber == "" ?
                            (string) $order->ShippingInfo->PhoneNumber :
                            (string) $order->BillingInfo->PhoneNumber),
                    'company' => (string) $this->fixEncoding((string) $order->ShippingInfo->Address1),
                    'street' => (string) $this->fixEncoding(
                            (string) $order->ShippingInfo->Address2
                            . "\n" . (string) $order->ShippingInfo->Address3),
                    'city' => $this->fixEncoding((string) $order->ShippingInfo->City),
                    'postcode' => $postcode,
                    'region' => (string) $order->ShippingInfo->State,
                    'region_id' => $regionId,
                    'country_id' => (string) $order->ShippingInfo->Country,
                    'should_ignore_validation' => true
                );

                // add the billing address to the quote.
                $billingAddress = $quote->getBillingAddress()->addData($billingAddressData);

                echo "<Info>Set Shipping Address</Info>";

                // set the shipping address
                $shippingAddressData = array(
                    'firstname' => $this->fixEncoding($this->getFirstName((string) $order->ShippingInfo->RecipientName)),
                    'lastname' => $this->fixEncoding($this->getLastName((string) $order->ShippingInfo->RecipientName)),
                    'company' => (string) $this->fixEncoding((string) $order->ShippingInfo->Address1),
                    'street' => (string) $this->fixEncoding(
                            (string) $order->ShippingInfo->Address2
                            . "\n" . (string) $order->ShippingInfo->Address3),
                    'city' => $this->fixEncoding((string) $order->ShippingInfo->City),
                    'postcode' => $postcode,
                    'region' => (string) $order->ShippingInfo->State,
                    'region_id' => $regionId,
                    'country_id' => (string) $order->ShippingInfo->Country,
                    'telephone' => (string) $order->ShippingInfo->PhoneNumber,
                    'should_ignore_validation' => true
                );
            } else {

                // set the billing address
                $billingAddressData = array(
                    'firstname' => $this->fixEncoding($this->getFirstName((string) $order->BillingInfo->Name)),
                    'lastname' => $this->fixEncoding($this->getLastName((string) $order->BillingInfo->Name)),
                    'email' => (string) $order->BillingInfo->Email,
                    'telephone' => ( (string) $order->BillingInfo->PhoneNumber == "" ?
                            (string) $order->ShippingInfo->PhoneNumber :
                            (string) $order->BillingInfo->PhoneNumber),
                    'street' => (string) $this->fixEncoding((string) $order->ShippingInfo->Address1 . "\n"
                            . (string) $order->ShippingInfo->Address2
                            . "\n" . (string) $order->ShippingInfo->Address3),
                    'city' => $this->fixEncoding((string) $order->ShippingInfo->City),
                    'postcode' => $postcode,
                    'region' => (string) $order->ShippingInfo->State,
                    'region_id' => $regionId,
                    'country_id' => (string) $order->ShippingInfo->Country,
                    'should_ignore_validation' => true
                );


                // add the billing address to the quote.
                $billingAddress = $quote->getBillingAddress()->addData($billingAddressData);

                echo "<Info>Set Shipping Address</Info>";

                // set the shipping address
                $shippingAddressData = array(
                    'firstname' => $this->fixEncoding($this->getFirstName((string) $order->ShippingInfo->RecipientName)),
                    'lastname' => $this->fixEncoding($this->getLastName((string) $order->ShippingInfo->RecipientName)),
                    'street' => (string) $this->fixEncoding((string) $order->ShippingInfo->Address1
                            . "\n" . (string) $order->ShippingInfo->Address2
                            . "\n" . (string) $order->ShippingInfo->Address3),
                    'city' => $this->fixEncoding((string) $order->ShippingInfo->City),
                    'postcode' => $postcode,
                    'region' => (string) $order->ShippingInfo->State,
                    'region_id' => $regionId,
                    'country_id' => (string) $order->ShippingInfo->Country,
                    'telephone' => (string) $order->ShippingInfo->PhoneNumber,
                    'should_ignore_validation' => true
                );
            }

            Mage::getSingleton('core/session')->setShippingPrice(
                    $this->getDeTaxPrice((string) $order->ShippingInfo->ShippingPrice) / $reverseRate);

            // add the shipping address to the quote.
            $shippingAddress = $quote->getShippingAddress()->addData($shippingAddressData);
            
            // add product(s)
            foreach ($order->OrderItems->Item as $orderitem) {
                $product = Mage::getModel('catalog/product')->loadByAttribute(
                        (string) $dataArray->SkuAttribute, (string) $orderitem->SKU);

                // First check if this is a custom option
                if (!is_object($product)) {
                    $skuparts = explode("-", (string) $orderitem->SKU);

                    if (count($skuparts) > 1) {
                        $parentsku = $skuparts[0];

                        $product = Mage::getModel('catalog/product')->loadByAttribute(
                                (string) $dataArray->SkuAttribute, $parentsku);

                        for ($i = 1; $i < count($skuparts); $i++) {
                            $itemOptions[$parentsku][] = $skuparts[$i];
                        }
                    }
                }
                // ------------------------------------------------------

                if (is_object($product)) {

                    $product->setPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress) / $reverseRate);

                    $item = Mage::getModel('sales/quote_item');
                    $item->setQuote($quote)->setProduct($product);
                    $item->setData('qty', (string) $orderitem->Quantity);
                    $item->setCustomPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress));
                    $item->setOriginalCustomPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress));
                    //     $item->setQtyInvoiced((string) $orderitem->Quantity);

                    $quote->addItem($item);

                    $quote->save();
                    $item->save();
                } else {
                    echo "<Info>Can't find SKU to add to quote " . ((string) $orderitem->SKU)
                    . ", trying to create stub</Info>";

                    $prodIdToLoad = 0;

                    try {
                        // Create stub if needed
                        $this->createStubProduct((string) $orderitem->SKU, (string) $orderitem->Name, (string) $dataArray->WebsiteId, (string) $order->OrderId, (string) $orderitem->Price, (string) $orderitem->Quantity, (string) $dataArray->SkuAttribute);
                    } catch (Exception $e) {
                        echo "<Info><![CDATA[Stub create error - " . $e->getMessage() . "]]></Info>";

                        if (strpos($e->getMessage(), "Duplicate entry")) {

                            $msgParts = explode("Duplicate entry '", $e->getMessage());

                            if (isset($msgParts[1])) {

                                $msgParts = $msgParts[1];
                                $msgParts = explode("-", $msgParts);
                                $prodIdToLoad = $msgParts[0];
                            }
                        }
                    }

                    if ($prodIdToLoad > 0) {

                        echo "<Info>Load by ID $prodIdToLoad</Info>";
                        $product = Mage::getModel('catalog/product')->load($prodIdToLoad);
                    } else {

                        // Try once again to add our item to the quote
                        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', (string) $orderitem->SKU);
                    }

                    if (is_object($product)) {

                        $product->setPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress) / $reverseRate);

                        $item = Mage::getModel('sales/quote_item');
                        $item->setQuote($quote)->setProduct($product);
                        $item->setData('qty', (string) $orderitem->Quantity);
                        $item->setCustomPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress));
                        $item->setOriginalCustomPrice($this->getDeTaxPrice((string) $orderitem->Price, $shippingAddress));
                        // $item->setQtyInvoiced((string) $orderitem->Quantity);
                        $quote->addItem($item);

                        $quote->save();
                        $item->save();
                    } else {
                        echo "<Info>Can't find SKU to add to quote " . ((string) $orderitem->SKU) . "</Info>";
                    }
                }
            }

            $quote->getShippingAddress()->setData('should_ignore_validation', true);
            $quote->getBillingAddress()->setData('should_ignore_validation', true);
            /////////////////////////////////////////////
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier('channelunitycustomrate');
            $method->setCarrierTitle('ChannelUnity Shipping');
            $method->setMethod('channelunitycustomrate');
            $method->setMethodTitle((string) $order->ShippingInfo->Service);

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

            $currentstore = Mage::app()->getStore()->getId();
            // upgrade to admin permissions to avoid item qty not available issue
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

            $service->submitAll();
            $newOrder = $service->getOrder(); // returns full order object.
            // we're done; sign out of admin permission
            Mage::app()->setCurrentStore($currentstore);

            if (!is_object($newOrder)) {
                echo "<NotImported>" . ((string) $order->OrderId) . "</NotImported>";
                return;
            } else {
                echo "<Imported>" . ((string) $order->OrderId) . "</Imported>";
            }
        } catch (Exception $x) {
            echo "<Exception><![CDATA[" . $x->getMessage() . " " . $x->getTraceAsString() . "]]></Exception>";
            echo "<NotImported>" . ((string) $order->OrderId) . "</NotImported>";
            Mage::unregister('cu_order_in_progress');
            if (isset($newOrder) && is_object($newOrder)) {
                $newOrder->delete();
            }
            return;
        }

        $ordStatus = $this->CUOrderStatusToMagentoStatus((string) $order->OrderStatus);

        try {
            $newOrder->setData('state', $ordStatus);
            $newOrder->setStatus($ordStatus);
            $history = $newOrder->addStatusHistoryComment(
                    'Order imported from ChannelUnity', false);
            $history->setIsCustomerNotified(false);
        } catch (Exception $x1) {

            try {
                $newOrder->setState('closed', 'closed', 'Order imported from ChannelUnity', false);
            } catch (Exception $x2) {

            }
        }

        try {

            // This order will have been paid for, otherwise it won't
            // have imported

            $invoiceId = Mage::getModel('sales/order_invoice_api')
                    ->create($newOrder->getIncrementId(), array());

            $invoice = Mage::getModel('sales/order_invoice')
                    ->loadByIncrementId($invoiceId);

            /**
             * Pay invoice
             * i.e. the invoice state is now changed to 'Paid'
             */
            $invoice->capture()->save();
            Mage::dispatchEvent('sales_order_invoice_pay', array('invoice' => $invoice));

            $newOrder->setTotalPaid($newOrder->getGrandTotal());
            $newOrder->setBaseTotalPaid($newOrder->getBaseGrandTotal());

            /** Make a transaction to store CU info */
            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($newOrder->getPayment());
            $transaction->setOrder($newOrder);
            $transaction->setTxnType('capture');
            $transaction->setTxnId((string) $order->OrderId);
            $transaction->setAdditionalInformation('SubscriptionId', (string) $dataArray->SubscriptionId);
            $transaction->setAdditionalInformation('RemoteOrderID', (string) $order->OrderId);
            $transaction->setAdditionalInformation('ShippingService', (string) $order->ShippingInfo->Service);

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
                // Can't set 'complete' state manually - ideally import tracking info and create shipment in Mage


                $newOrder->setData('state', 'complete');
                $newOrder->setStatus('complete');
                $history = $newOrder->addStatusHistoryComment('Order was fulfilled by Amazon', false);
                $history->setIsCustomerNotified(false);
            }
            $transaction->save();

            /** Add gift message */
            if (isset($order->ShippingInfo->GiftMessage)) {

                $message = Mage::getModel('giftmessage/message');

                $message->setMessage($order->ShippingInfo->GiftMessage);
                $message->save();

                $gift_message_id = $message->getId();

                $newOrder->setData('gift_message_id', $gift_message_id);
            }

            $newOrder->setCreatedAt((string) $order->PurchaseDate);

            //================ Add custom options where applicable ============
            $allItems = $newOrder->getAllItems();
            foreach ($allItems as $item) {
                if (isset($itemOptions[$item->getSku()])) {
                    $optionsToAdd = $itemOptions[$item->getSku()];

                    $optionArray = array();

                    foreach ($optionsToAdd as $customSkuToAdd) {
                        $productTemp = Mage::getModel('catalog/product')->load($item->getProductId());

                        $tempOption = $productTemp->getOptions();
                        foreach ($tempOption as $option) {
                            $temp = $option->getData();

                            $values = $option->getValues();
                            if (count($values) > 0) {
                                foreach ($values as $value) {

                                    if ($value["sku"] == $customSkuToAdd) {

                                        echo "<Info>Add custom option: $customSkuToAdd</Info>";

                                        $optionArray[count($optionArray)]
                                                = array(
                                            'label' => $temp["default_title"],
                                            'value' => $value["title"],
                                            'print_value' => $value["title"],
                                            'option_type' => 'radio',
                                            'custom_view' => false,
                                            'option_id' => $temp["option_id"],
                                            'option_value' => $value->getId()
                                        );
                                    }
                                }
                            }
                        }
                    }

                    $item->setProductOptions(array('options' => $optionArray
                    ));

                    $item->save();
                }
            }
            $newOrder->save();
        } catch (Exception $e) {
            if (is_object($newOrder)) {
                $newOrder->delete();
            }
        }

        Mage::unregister('cu_order_in_progress');
    }

    private function createStubProduct($missingSku, $productTitle, $websiteID, $keyorder, $price, $qty, $skuAttribute)
    {
        $product = new Mage_Catalog_Model_Product();

        $db = Mage::getSingleton("core/resource")->getConnection("core_write");
        $table_prefix = Mage::getConfig()->getTablePrefix();
        $sql = "SELECT entity_type_id FROM  {$table_prefix}eav_entity_type WHERE entity_type_code='catalog_product'";
        $result = $db->query($sql);
        $row = $result->fetch();

        $sql = "SELECT attribute_set_id FROM {$table_prefix}eav_attribute_set WHERE entity_type_id='" . $row['entity_type_id'] . "' ORDER BY attribute_set_id ASC";
        $result = $db->query($sql);
        $row = $result->fetch();
        $attributeSetId = $row['attribute_set_id'];

        // Build the product
        $product->setSku($missingSku);
        $product->setAttributeSetId($attributeSetId);
        $product->setTypeId('simple');
        $product->setName($productTitle);
        $product->setData($skuAttribute, $missingSku); // set the attribute marked as the SKU attribute

        $product->setWebsiteIDs(array($websiteID)); # derive website ID from store.
        $product->setDescription('Product missing from imported order ID ' . $keyorder);
        $product->setShortDescription('Product missing from imported order ID ' . $keyorder);
        $product->setPrice($price); # Set some price
        // Default Magento attribute
        $product->setWeight('0.01');
        $product->setVisibility(1); // not visible
        $product->setStatus(1); // status = enabled, otherwise price shows as 0.00 in the order
        $product->setTaxClassId(0); # My default tax class
        $product->setStockData(array(
            'is_in_stock' => 1,
            'qty' => $qty
        ));

        $product->setCreatedAt(strtotime('now'));

        $product->save();
    }

    private function getFirstName($name)
    {
        $lastSpacePos = strrpos($name, " ");
        if ($lastSpacePos !== FALSE) {

            return substr($name, 0, $lastSpacePos);
        } else {

            return $name;
        }
    }

    private function getLastName($name)
    {
        $exp = explode(" ", $name);
        if (count($exp) > 0) {

            return $exp[count($exp) - 1];
        } else {

            return "___";
        }
    }

    public function CUOrderStatusToMagentoStatus($orderStatus)
    {
        if ($orderStatus == 'Processing') {
            $orderStatus = "processing";
        } else if ($orderStatus == 'OnHold') {
            $orderStatus = "holded";
        } else if ($orderStatus == 'Complete') {
            $orderStatus = "complete";
        } else {
            $orderStatus = "canceled";
        }

        return $orderStatus;
    }

    private function doSingleOrder($singleOrder, $newOrder)
    {

        // 3. Update order status
        $ordStatus = $this->CUOrderStatusToMagentoStatus((string) $singleOrder->OrderStatus);

        try {
            $newOrder->setData('state', $ordStatus);
            $newOrder->setData('status', $ordStatus);
        } catch (Exception $x1) {

            try {

                $newOrder->setData('state', 'closed');
                $newOrder->setData('status', 'closed');
            } catch (Exception $x2) {

            }
        }

        $newOrder->save();
    }

    public function reserveStock($dataArray, $order)
    {
        foreach ($order->OrderItems->Item as $orderitem) {

            $product = Mage::getModel('catalog/product')->loadByAttribute(
                    (string) $dataArray->SkuAttribute, (string) $orderitem->SKU);

            if (is_object($product)) {
                $qty = (string) $orderitem->Quantity;

                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId()); // Load the stock for this product
                $stock->setQty($stock->getQty() - $qty); // Set to new Qty
                $stock->save(); // Save
            }
        }
    }

    public function releaseStock($dataArray, $order)
    {
        foreach ($order->OrderItems->Item as $orderitem) {

            $product = Mage::getModel('catalog/product')->loadByAttribute(
                    (string) $dataArray->SkuAttribute, (string) $orderitem->SKU);

            if (is_object($product)) {
                $qty = (string) $orderitem->Quantity;

                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId()); // Load the stock for this product
                $stock->setQty($stock->getQty() + $qty); // Set to new Qty
                $stock->save(); // Save
            }
        }
    }
    
    public function getSalesTaxRate($country, $region, $postcode) {
        
        $quote = Mage::getModel('sales/quote');
        $shippingAddressData = array(
                                     'country_id' => $country,
                                     'region' => $region,
                                     'postcode' => $postcode,
                                     );
        $shippingAddress = $quote->getShippingAddress()->addData($shippingAddressData);
        
        $calc = Mage::getSingleton('tax/calculation');
        $rr = $calc->getRateRequest($shippingAddress); 
        
        $rate = -1;
        
        $rates = $calc->getRatesForAllProductTaxClasses($rr);
        
        foreach ($rates as $class => $rate) {
            
            break;
            
        }
        
        return $rate;
    }

    public function getDeTaxPrice($price, $address = null)
    {
        $priceIncTax = Mage::getStoreConfig('channelunityint/generalsettings/priceinctax');
           
        if ($priceIncTax == 1) {
            return $price;
        }
        
        if ($address != null) {
            
            $cid = $address->getData('country_id');
            $rgn = $address->getData('region');
            $pcd = $address->getPostcode();
            
            if ($cid == 'US') {
                // Look at state-specific sales taxes
                
                $rate = $this->getSalesTaxRate($cid, $rgn, $pcd);
                
                if ($rate > 1) {
                    return $price / (100.0 + $rate) * 100.0;
                }
                
            }
        }
        
        $taxRate = 1;
        $calc = Mage::getSingleton('tax/calculation');
        $rates = $calc->getRatesForAllProductTaxClasses($calc->getRateRequest());

        foreach ($rates as $class => $rate) {
            $taxRate = $rate;
            break;
        }

        if ($taxRate == 0) {
            return $price;
        } else {
            return $price / (100.0 + $taxRate) * 100.0;
        }
    }

    public function doUpdate($dataArray)
    {
        foreach ($dataArray->Orders->Order as $order) {

            $orderId = trim((string) $order->OrderId);

            $bOrderExisted = false;

            try {
                $transaction = Mage::getModel('sales/order_payment_transaction')
                        ->loadByTxnId();

                $newOrder = $transaction->getOrder();
                if (is_object($newOrder)) {
                    $this->doSingleOrder($order, $newOrder);
                    $bOrderExisted = true;
                }
            } catch (Exception $x1) {

            }
            // Additional information good for failsafe
            if (!$bOrderExisted) {
                $oid = $orderId;

                $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
                                ->addFieldToFilter('additional_information', array('like' => '%s:13:"RemoteOrderID";s:' . strlen($oid) . ':"' . $oid . '"%'))->getFirstItem();

                $newOrder = $transaction->getOrder();
                if (is_object($newOrder)) {
                    $this->doSingleOrder($order, $newOrder);
                    $bOrderExisted = true;
                }
            }

            $table_prefix = Mage::getConfig()->getTablePrefix();

            // See if the order has been imported by the old Amazon module
            if (!$bOrderExisted && $this->table_exists("{$table_prefix}amazonimport_flatorders")) {
                $oid = $orderId;
                $db = Mage::getSingleton("core/resource")->getConnection("core_write");

                $_sql = "SELECT * FROM {$table_prefix}amazonimport_flatorders WHERE amazon_order_id='$oid'";

                $result = $db->query($_sql);

                if ($result->rowCount() > 0) {
                    $bOrderExisted = true;
                }
            }

            //=======================================================
            if (!$bOrderExisted) {
                $orderIsFba = isset($order->OrderFlags) && (((string) $order->OrderFlags) == 'AMAZON_FBA');
                $ignoreQty  = Mage::getStoreConfig('channelunityint/generalsettings/ignorefbaqty');

                if (((string) $order->OrderStatus) == "Processing") {
                    // if the stock isn't already decreased, decrease it

                    if (!isset($order->StockReservedCart) || ((string) $order->StockReservedCart) == "0") {
                        echo "<StockReserved>" . $orderId . "</StockReserved>";

                        if (!$orderIsFba || !$ignoreQty) {
                            $this->reserveStock($dataArray, $order);
                        }
                    }

                    $this->doCreate($dataArray, $order);
                } else if (((string) $order->OrderStatus) == "OnHold") {
                    // Reserve the stock
                    echo "<Imported>" . $orderId . "</Imported>";
                    echo "<StockReserved>" . $orderId . "</StockReserved>";

                    if (!$orderIsFba || !$ignoreQty) {
                        $this->reserveStock($dataArray, $order);
                    }
                } else {
                    // Let's not create cancelled orders !!! We don't have all the details
                    if ("Cancelled" != ((string) $order->OrderStatus)) {
                        // Just create the order (e.g. previously completed)
                        $this->doCreate($dataArray, $order);
                    } else {
                        // Have this order marked as imported anyway
                        echo "<Imported>" . $orderId . "</Imported>";
                    }
                }
            }

            if ($bOrderExisted) {
                if (((string) $order->OrderStatus) == "Cancelled") {
                    // Put back our stock
                    if (!$orderIsFba || !$ignoreQty) {
                        $this->releaseStock($dataArray, $order);
                    }
                }
                echo "<Imported>" . $orderId . "</Imported>";
            }
        }
    }

    public function table_exists($tablename)
    {
        $db = Mage::getSingleton("core/resource")->getConnection("core_write");
        $_sql = "SHOW TABLES LIKE '$tablename';";
        $result = $db->query($_sql);
        return $result->rowCount() > 0;
    }

}
