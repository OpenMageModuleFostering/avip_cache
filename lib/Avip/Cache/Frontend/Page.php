<?php
/**
 * @category   Avip
 * @package    Avip_Cache
 * @subpackage Avip_Cache_Frontend
 * @copyright  Copyright (c) 2010
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


/**
 * @see Zend_Cache_Core
 */
#require_once 'Zend/Cache/Core.php';


/**
 * @package    Avip_Cache
 * @subpackage Avip_Cache_Frontend
 * @copyright  Copyright (c) 2010
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Avip_Cache_Frontend_Page extends Zend_Cache_Frontend_Page
{
    /**
     * @var array options
     */
    protected $_specificOptions = array(
        'http_conditional' => false,
        'debug_header' => false,
        'content_type_memorization' => true,
        'memorize_headers' => array(),
        'default_options' => array(
            'cache_with_get_variables' => true,
            'cache_with_post_variables' => true,
            'cache_with_session_variables' => true,
            'cache_with_files_variables' => false,
            'cache_with_cookie_variables' => false,
            'make_id_with_get_variables' => true,
            'make_id_with_post_variables' => true,
            'make_id_with_session_variables' => true,
            'make_id_with_files_variables' => true,
            'make_id_with_cookie_variables' => true,
            'cache' => true,
            'specific_lifetime' => false,
            'tags' => array(),
            'priority' => null
        ),
        'regexps' => array()
    );

    /**
     * Start the cache
     *
     * @param  string  $id       (optional) A cache id (if you set a value here, maybe you have to use Output frontend instead)
     * @param  boolean $doNotDie For unit testing only !
     * @return boolean True if the cache is hit (false else)
     */
    public function start($id = false, $doNotDie = false)
    {
        
        $this->_cancel = false;
        $lastMatchingRegexp = null;
        foreach ($this->_specificOptions['regexps'] as $regexp => $conf) {
            if (preg_match("`$regexp`", $_SERVER['REQUEST_URI'])) {
                $lastMatchingRegexp = $regexp;
            }
        }
        
        $this->_activeOptions = $this->_specificOptions['default_options'];
        if (!is_null($lastMatchingRegexp)) {
            $conf = $this->_specificOptions['regexps'][$lastMatchingRegexp];
            foreach ($conf as $key=>$value) {
                $this->_activeOptions[$key] = $value;
            }
        }
        if (!($this->_activeOptions['cache'])) {
            return false;
        }
        
        if (!$id) {
            $id = $this->_makeId();
            if (!$id) {
                return false;
            }
        }
        
    	if($this->getSession()->isLoggedIn())
        {
        	$this->_activeOptions['tags'][] = 'customer_' . $this->getSession()->getId();	
        }
        
        $array = $this->load($id);
        if ($array !== false) {
            $data = $array['data'];
            $headers = $array['headers'];
            if ($this->_specificOptions['debug_header']) {
                echo "DEBUG HEADER : This is a cached page $id!";
            }
            if (!headers_sent()) {
                foreach ($headers as $key=>$headerCouple) {
                    $name = $headerCouple[0];
                    $value = $headerCouple[1];
                    header("$name: $value");
                }
            }
            echo $data;
            if ($doNotDie) {
                return true;
            }
            die();
        }
        ob_start(array($this, '_flush'));
        ob_implicit_flush(false);
        return false;
    }
  
	private function getSession()
	{
		return Mage::getSingleton('customer/session');
	}
  
	public function addTags($tags)
	{
		if(!is_array($tags) && !in_array($tags, $this->_activeOptions['tags']))
		{
			$this->_activeOptions['tags'][] = $tags;	
		}	
		
		if(is_array($tags))
		{
			foreach($tags as $tag)
			{
				if(!in_array($tag, $this->_activeOptions['tags']))
				{
					$this->_activeOptions['tags'][] = $tag;	
				}	
			}
		}
	}
	
    /**
     * callback for output buffering
     * (shouldn't really be called manually)
     *
     * @param  string $data Buffered output
     * @return string Data to send to browser
     */
    public function _flush($data)
    {
        if ($this->_cancel) {
            return $data;
        }
        $contentType = null;
        $storedHeaders = array();
        $headersList = headers_list();
        foreach($this->_specificOptions['memorize_headers'] as $key=>$headerName) {
            foreach ($headersList as $headerSent) {
                $tmp = split(':', $headerSent);
                $headerSentName = trim(array_shift($tmp));
                if (strtolower($headerName) == strtolower($headerSentName)) {
                    $headerSentValue = trim(implode(':', $tmp));
                    $storedHeaders[] = array($headerSentName, $headerSentValue);
                }
            }
        }       
        $array = array(
            'data' => $data,
            'headers' => $storedHeaders
        );
        $this->save($array, null, $this->_activeOptions['tags'], $this->_activeOptions['specific_lifetime'], $this->_activeOptions['priority']);
        return $data;
    }
	
	/**
     * Make an id depending on REQUEST_URI and superglobal arrays (depending on options)
     *
     * @return mixed|false a cache id (string), false if the cache should have not to be used
     */
    protected function _makeId()
    {
        $tmp = $_SERVER['REQUEST_URI'];
        foreach (array('Get', 'Post', 'Session', 'Files', 'Cookie') as $arrayName) {
            $tmp2 = $this->_makePartialId($arrayName, $this->_activeOptions['cache_with_' . strtolower($arrayName) . '_variables'], $this->_activeOptions['make_id_with_' . strtolower($arrayName) . '_variables']);
            $tmp = $tmp . $tmp2;
        }
        return md5($tmp);
    }
    
    /**
     * Make a partial id depending on options
     *
     * @param  string $arrayName Superglobal array name
     * @param  bool   $bool1     If true, cache is still on even if there are some variables in the superglobal array
     * @param  bool   $bool2     If true, we have to use the content of the superglobal array to make a partial id
     * @return mixed|false Partial id (string) or false if the cache should have not to be used
     */
    protected function _makePartialId($arrayName, $bool1, $bool2)
    {
        switch ($arrayName) {
        case 'Get':
            $var = $_GET;
            break;
        case 'Post':
            $var = $_POST;
            break;
        case 'Session':
            if ($this->getSession()->isLoggedIn()) {
                $var = $this->getSession()->getId();
            } else {
                $var = null;
            }
            break;
        case 'Cookie':
            if (isset($_COOKIE) && isset($_COOKIE['PHPSESSID'])) {
                $var = $_COOKIE['PHPSESSID'];
            } else {
                $var = null;
            }
            break;
        case 'Files':
            $var = $_FILES;
            break;
        default:
            return false;
        }
        if ($bool1) {
            if ($bool2) {
                return serialize($var);
            }
            return '';
        }
        if (count($var) > 0) {
            return false;
        }
        return '';
    }
	
    public function clean($mode = 'all', $tags = array())
    {
    	if($this->getSession()->isLoggedIn())
        {
        	$tags[] = $this->getSession()->getId();	
        }
        
        Mage::log('ici_clean_step_1');
    	parent::clean($mode, $tags);
    }
}
