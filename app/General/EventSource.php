<?php

namespace General;

use Interop\Container\ContainerInterface;

class EventSource {
	
	static $listeners = [];
	static $onError;
	
	/** @var  ContainerInterface */
	static $container;
	
	static function subscribe($event, $listenerName, $handler) {
		if (empty(self::$listeners[$event]))
			self::$listeners[$event] = [];
		self::$listeners[$event][$listenerName] = $handler;
	}
	
	static function fireEvent($event, $data) {
		if (empty(self::$listeners[$event]))
			return;
		foreach (self::$listeners[$event] as $nombre => $handler) {
			try {
				if (is_string($handler)) {
					if (!self::$container) throw new \Exception("Container no inicializado para clave $handler");
					$handler = self::$container[$handler];
				}
				if (is_callable($handler))
					call_user_func($handler, $data);
				if ($handler instanceof EventHandler)
					$handler->handle($event, $data);
			} catch (\Exception $ex) {
				if (self::$onError)
					call_user_func(self::$onError, $ex, $event, $nombre, $data);
			}
		}
	}
}

