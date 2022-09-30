<?php

if (!class_exists('Memcached')) {
	/**
	 * En caso de que la clase memcached no exista, crear un repuesto barato usando 'memcache'
	 */
	class Memcached {
		/** @var \Memcache */
		var $memcache;
		
		/**
		 * Memcached constructor.
		 */
		public function __construct() {
			$this->memcache = new \Memcache();
		}
		
		function addServer($server, $port = 11211) {
			return @$this->memcache->connect($server, $port);
		}
		
		function set($key, $value, $expiration = 0) {
			return $this->memcache->set($key, $value, MEMCACHE_COMPRESSED, $expiration);
		}
		
		function get($key) {
			return $this->memcache->get($key);
		}
	}
}