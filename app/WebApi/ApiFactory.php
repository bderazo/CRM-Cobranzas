<?php

namespace WebApi;

use Slim\Http\Request;

class ApiFactory {
	var $container;
	
	/**
	 * ApiFactory constructor.
	 * @param $container
	 */
	public function __construct($container) { $this->container = $container; }
	
	function createDispatch($class, $method, $initArgs = []) {
		$self = $this;
		// mantiene una estructura parecida a los controladores dinamicos pero mucho mas simple de llamar
		return function (Request $request, $response, $args = []) use ($self, $class, $method, $initArgs) {
			$params = $request->getParams();
			if ($args)
				$params = array_merge($params, $args);
			$c = new $class($self->container);
			if (method_exists($c, 'init'))
				$c->init($initArgs);
			return call_user_func_array([$c, $method], $params);
		};
		
	}
}