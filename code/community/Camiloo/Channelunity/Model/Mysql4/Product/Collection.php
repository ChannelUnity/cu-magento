<?php

class Camiloo_Price_Model_Mysql4_Product_Collection extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
{
	protected function _construct()
	{
	        parent::_init('catalog/product');
	    parent::_initTables();
	}
}