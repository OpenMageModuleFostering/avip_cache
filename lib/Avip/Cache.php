<?php
class Avip_Cache
{
	// Hold an instance of the class
    private static $instance;
    private $cache = null;
    private $started = false;
    
    // A private constructor; prevents direct creation of object
    private function __construct()
    { 
    	
    }
	
	// Prevent users to clone the instance
    public function __clone()
    {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }
    
    // The singleton method
    public static function getInstance() 
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
            self::$instance->init();
        }

        return self::$instance;
    }
    
	/**
     * Use Page cache
     */
    private function  init()
    {
    	if(!isset($_SESSION))session_start() ;
    	
    	$this->loadCache();
    }
    
    public function start()
    {
    	if(!is_null($this->cache))
    	{
    		if(!$this->started){
    			$this->cache->start();
    			$this->started = true;
    		}
    	}
    	else
    	{
    		throw new Exception("Aucun moteur de cache n'est chargé");	
    	}
    }
    
    public function addTags($tags)
    {
    	if($this->started){
    		$this->cache->addTags($tags);
    	}
    }
    
	public function clean($tags)
    {
    	if($this->started){
    		if(!is_array($tags))
	    	{
	    		$tags = array(0=>$tags);
	    	}
    		Mage::log('ici_clean_step_0');
    		$this->cache->clean('matchingTag', $tags);
    		$this->cache->cancel();
    	}
    }
    
    public function isStarted()
    {
    	return $this->started;
    }
    
    protected function loadCache()
    {
    	$frontendOptions = array(
            'lifetime' => 3600,
            'default_options' => array(
                'cache' => false
            ),
            'debug_header' => false,
            'regexps' => array(
                '^/$' => array('cache' => true),
                '^/fr_bp/customer' => array('cache' => false),
                '^/fr_bp/boutiques' => array('cache' => true),
                '^/catalog/category' => array('cache' => true),
                '^/catalog/vente' => array('cache' => true),
            )
        );
        
        $backendOptions = array(
          'cache_dir' => Mage::getBaseDir('var') . DIRECTORY_SEPARATOR . 'tmp',
        );
        
        $frontend = new Avip_Cache_Frontend_Page($frontendOptions);
        
        $this->cache = Zend_Cache::factory(
            $frontend,
            'File',
            $frontendOptions,
            $backendOptions
        );
    }
	
}