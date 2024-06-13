<?php
namespace General\Cache;

/**
 * Class SimpleMemcache
 * @package General\Cache
 */
class SimpleMemcache implements ICacheDatos {
	/** @var \Memcache */
	var $memcache;
	var $use = true;
	var $debug = false;
	
	var $server;
	var $port;
	var $connTimeout = 1;
	
	/**
	 * SimpleMemcache constructor.
	 * @param $server
	 * @param $port
	 */
	public function __construct($server = '127.0.0.1', $port = 11211) {
		$this->server = $server;
		$this->port = $port;
	}
	
	
	function init() {
		if ($this->memcache) return true;
		$this->memcache = new \Memcache();
		$res = @$this->memcache->connect($this->server, $this->port, $this->connTimeout);
		// get error, auditoria cache?
		if (!$res)
			$this->memcache = null;
		return $res;
	}
	
	function get($key) {
		if (!$this->use) return null;
		$this->init();
		if (!$this->memcache) return null;
		$data = $this->memcache->get($key);
		if ($this->debug) {
			$r = rand(20, 888);
			if ($data) header("cache_hit_$r: $key");
		}
		return $data;
	}
	
	function set($key, $data, $ttl = 0) {
		if (!$this->use) return false;
		$this->init();
		if (!$this->memcache) return false;
		//$this->memcache->set($key, $data, MEMCACHE_COMPRESSED, $ttl);
		$this->memcache->set($key, $data, $ttl);
		return $data;
	}
	
	
}