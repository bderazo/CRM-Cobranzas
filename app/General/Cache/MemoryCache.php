<?php
namespace General\Cache;

class MemoryCache implements ICacheDatos {
	
	var $data = [];
	
	function get($key) {
		if (isset($this->data[$key])) return $this->data[$key];
		return null;
	}
	
	function set($key, $data, $ttl = 0) {
		$this->data[$key] = $data;
		return $data;
	}
}