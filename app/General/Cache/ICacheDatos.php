<?php

namespace General\Cache;


interface ICacheDatos {
	
	function get($key);
	
	/**
	 * @param $key
	 * @param $data
	 * @param null $ttl
	 * @return mixed
	 */
	function set($key, $data, $ttl = null);
}



