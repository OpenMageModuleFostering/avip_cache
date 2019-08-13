<?php
      class Avip_Cache_Model_Product_Observer
      {
      	  protected $cache;
      	  private $stores = array();
      	  
          public function __construct()
          {
          	$this->cache = Avip_Cache::getInstance(); 		
          }
          
	      private function getSession()
		  {
		  	return Mage::getSingleton('customer/session');
		  }
          
      	  /**
            * Launch Page Caching
            * @param   Varien_Event_Observer $observer
            * @return  Avip_Cache_Model_Product_Observer
          */
          public function start($observer)
          {
          	$cache_update = Mage::app()->getRequest()->getParam('cache_update', false);
          	$this->cache->start($cache_update); 
            return $this;
          }
          
          /**
            * Clean before Add product
            * @param   Varien_Event_Observer $observer
            * @return  Avip_Cache_Model_Product_Observer
          */
          public function add_clean($observer)
          {
            $event = $observer->getEvent();
            $product = $event->getProduct(); 
            
			if($this->cache && $this->cache->isStarted()){
            	$this->cache->clean($product->getId());	
            	
            	$session = $this->getSession();
            	if($session->isLoggedIn())
            	{
            		$this->cache->clean('customer_' . $session->getId());
            	}
            }
            
            return $this;
          }
          
      	  /**
            * Add product id to current tags
            * @param   Varien_Event_Observer $observer
            * @return  Avip_Cache_Model_Product_Observer
          */
          public function add($observer)
          {
            $event = $observer->getEvent();
            $products = $event->getCollection(); 
            
			if($this->cache && $this->cache->isStarted()){
            	$tags = array();
            	foreach ($products as $product){           	
            		$tags[] = $product->getId();
            	}
            	if(!empty($tags))
            	{
            		$this->cache->addTags($tags);
            	}
            }
            
            return $this;
          }
          
      	  /**
            * remove cache page for given product
            * @param   Varien_Event_Observer $observer
            * @return  Avip_Cache_Model_Product_Observer
          */
          public function clean($observer)
          {
            $event = $observer->getEvent();
            $item = $event->getQuoteItem();   

          	if($this->cache && $this->cache->isStarted()){
          		$this->cache->clean($item->getData('product_id'));	

          		$session = $this->getSession();
            	if($session->isLoggedIn())
            	{
            		$this->cache->clean('customer_' . $session->getId());
            	}
            }
            
            return $this;
          }
          
      	  /**
            * Generate cache based upon url
          */
          public function dailyUrlCacheUpdate()
          {            
          		$collection = Mage::getResourceModel('core/url_rewrite_collection');
          		foreach($collection as $url)
          		{
          			$store = $this->getStore($url->getStoreId());
          			if($store)
          			{
          				$url = Mage::getBaseUrl('web') . $store->getCode() . '/' . $url->getRequestPath() . '?cache_update=true';
          				Mage::log($url);
          				get_headers($url);
          			}	
          		}
          }
          
          private function getStore($id)
          {
          	if(!in_array($id, array_keys($stores)))
          	{
          		$store = Mage::getModel('store')->load($id);	
          		$this->stores[$id] = $store;
          	}else{
          		$store = $this->stores[$id];
          	}
          	
          	return $store;
          } 
      }
?>