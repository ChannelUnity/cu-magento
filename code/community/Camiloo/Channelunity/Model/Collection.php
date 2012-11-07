<?php

if (version_compare(Mage::getVersion(), "1.6.0.0", ">=")
        && class_exists("Mage_Catalog_Model_Resource_Product_Collection")) {

    class Camiloo_Channelunity_Model_Collection extends Mage_Catalog_Model_Resource_Product_Collection
    {

        public function isEnabledFlat()
        {
            return false;
        }

    }

}