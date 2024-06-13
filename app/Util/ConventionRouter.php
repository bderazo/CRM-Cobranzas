<?php
namespace Util;

use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Router;

class ConventionRouter extends Router {

	/** @var ConventionHelper */
	var $conventionUtil;

	var $middlewareResolver;

	protected function resolveMiddleware($controller, $action) {
		if (is_callable($this->middlewareResolver))
			return call_user_func($this->middlewareResolver, $controller, $action);
		return [];
	}

	public function dispatch(ServerRequestInterface $request) {
		$dis = parent::dispatch($request);

		// if route actually exists, do nothing
		if ($dis[0] == Dispatcher::FOUND) {
			return $dis;
		}
		$path = $request->getUri()->getPath();
		$resolved = $this->conventionUtil->prepareDispatch($path, $request->getMethod(), $request->getParams());

		if ($resolved[0] != Dispatcher::FOUND)
			return $resolved;

		/** @var $route \Slim\Route */
		$route = $this->map([$request->getMethod()], $path, $resolved['handler']);
		$id = $route->getIdentifier();
		// TODO: test middleware resolution
//		$middlewares = $this->resolveMiddleware($resolved['controller'], $resolved['action']);
//		foreach ($middlewares as $mid) {
//			$route->add($mid);
//		}
		$ret = [Dispatcher::FOUND, $id, []]; // sacar los args?
		return $ret;
	}


}