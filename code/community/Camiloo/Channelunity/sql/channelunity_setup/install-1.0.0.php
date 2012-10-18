<?php

$installer = $this;
$installer->startSetup();
/*
  $installer->addAttribute('quote_payment', 'channelunity_orderid', array());
  $installer->addAttribute('quote_payment', 'channelunity_remoteorderid', array());
  $installer->addAttribute('quote_payment', 'channelunity_remotechannelname', array());
  $installer->addAttribute('quote_payment', 'channelunity_subscriptionid', array());

  $installer->addAttribute('order_payment', 'channelunity_orderid', array());
  $installer->addAttribute('order_payment', 'channelunity_remoteorderid', array());
  $installer->addAttribute('order_payment', 'channelunity_remotechannelname', array());
  $installer->addAttribute('order_payment', 'channelunity_subscriptionid', array());
 */
$installer->endSetup();

$adminSession = Mage::getSingleton('admin/session');
$adminSession->unsetAll();
$adminSession->getCookie()->delete($adminSession->getSessionName());
?>