<?php

namespace Util;

use Exception;
use FastRoute\Dispatcher;
use Interop\Container\ContainerInterface;
use ReflectionMethod;
use Slim\Exception\MethodNotAllowedException;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Route;

/**
 * Requiere de determineRouteBeforeAppMiddleware = true en settings de slim para usar como middleware
 */
class ConventionHelper {
	var $namespace = '';
	var $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
	var $controllerFactory;

	/** @var  \Interop\Container\ContainerInterface */
	var $container;

	public function __construct(ContainerInterface $container) {
		$this->container = $container;
		$this->controllerFactory = function ($class) use ($container) {
			if ($container->has($class))
				return $container->get($class);
			$controller = new $class($container);
			if (method_exists($controller, 'init'))
				$controller->init();
			return $controller;
		};
	}

	function findClass($path) {
		$path = trim($path, '/');
		$p = explode('/', $path);
		$ns = $this->namespace ? [$this->namespace] : [];
		// begin test with a good'ol stack
		$action = 'index';
		$i = 0;
		while ($p) {
			$i++;
			$last = array_pop($p);
			$name = ucfirst($last) . 'Controller';
			$clase = implode('\\', array_merge($ns, $p, [$name]));
			$metodo = $action; // convention
			// tal vez un ltrim
			$mhay = method_exists($clase, $metodo); //this takes care of autoloading :)
			if ($mhay)
				return [$clase, $metodo, 'action' => $action, 'path' => $p ? implode('/', $p) : $last];
			if (!$mhay)
				$action = $last;
			//echo 'nuevo action ' . $action . "<br>";
			if ($i == 2)
				break;
		}
		return false;
	}

	function prepareDispatch($path, $httpMethod, $params, $extraArgs = []) {
		$classDef = $this->findClass($path);
		if (!$classDef)
			return [Dispatcher::NOT_FOUND];

		$ref = new ReflectionMethod($classDef[0], $classDef[1]);
		$comments = $ref->getDocComment();
		if (!empty($comments)) {
			$checks = [];
			foreach ($this->verbs as $verbCheck) {
				if (strpos($comments, '@' . $verbCheck) !== false)
					$checks[] = $verbCheck;
			}
			if ($checks && !in_array($httpMethod, $checks)) {
				return [Dispatcher::METHOD_NOT_ALLOWED, $checks];
			}
		}

		$methodParams = $ref->getParameters();
		$source = $params;
		if ($extraArgs)
			$source = array_merge($source, $extraArgs);
		$args = [];
		foreach ($methodParams as $par) {
			/** @var $par \ReflectionParameter */
			$name = $par->name;
			$existe = isset($source[$name]);
			// normal behaviour of Slim when a route argument is not found
			if (!$existe && !$par->isOptional())
				return [Dispatcher::NOT_FOUND];
			if ($existe)
				$args[$name] = $source[$name];
		}

		$factory = $this->controllerFactory;
		$container = $this->container;
		$handler = function () use ($factory, $classDef, $container, $args) {
			$method = $classDef[1];
			$controller = call_user_func_array($factory, $classDef);
			$container['controller'] = $controller;
			return call_user_func_array([$controller, $method], $args);
		};

		$this->container['controllerPath'] = $classDef['path'];
		$this->container['action'] = $classDef['action'];

		return [
			Dispatcher::FOUND,
			'handler' => $handler,
			'class' => $classDef[0],
			'action' => $classDef['action'],
			'controllerPath' => $classDef['path'],
		];
	}

	function processRequest(Request $request) {
		/** @var $r \Slim\Route */
		$route = $request->getAttribute('route');
		if ($route) {
			return $request;
		}

		$path = $request->getUri()->getPath();
		$method = $request->getMethod();
		$resolved = $this->prepareDispatch($path, $method, $request->getParams());
		/** @var \Slim\Router $router */
		$router = $this->container['router'];
		//if ( is_callable($resolved)) {
		if ($resolved[0] == Dispatcher::FOUND) {
			$route = $router->map([$method], $path, $resolved['handler']);
			$routeInfo = [Dispatcher::FOUND, $route->getIdentifier(), []];
			$request = $request->withAttribute('route', $route);
		} else {
			$routeInfo = $resolved;
		}
		$routeInfo['request'] = [$request->getMethod(), (string)$request->getUri()];
		return $request->withAttribute('routeInfo', $routeInfo);
	}

	function simpleDispatch($path, $extraArgs = []) {
		$self = $this;
		$handler = function (Request $request, $response, $args = []) use ($self, $path, $extraArgs) {
			$newpath = str_replace(':', '/', $path);
			$newpath = str_replace('.', '/', $newpath);
			$newpath = str_replace('Controller', '', $newpath);
			$params = $request->getParams();
			/** @var Route $route */
			$route = $request->getAttribute('route');
			$args = $route->getArguments();
			if ($extraArgs)
				$args = array_merge($args, $extraArgs);
			$resolved = $self->prepareDispatch($newpath, $request->getMethod(), $params, $args);
			if ($resolved[0] == Dispatcher::FOUND) {
				$handler = $resolved['handler'];
				return $handler();
			}
			$res = $self->container['response'];
			if ($resolved[0] == Dispatcher::NOT_FOUND) {
				throw new NotFoundException($request, $res);
			}
			if ($resolved[0] == Dispatcher::METHOD_NOT_ALLOWED) {
				throw new MethodNotAllowedException($request, $res, $resolved[1]);
			}
			throw new Exception("Unexpected errorresolving path: $path");
		};
		return $handler;
	}

	// utilitarios

	/**
	 * @return ConventionRouter
	 */
	function createRouter() {
		$router = new ConventionRouter();
		$router->conventionUtil = $this;
		return $router;
	}

	/**
	 * @return \Closure
	 */
	function createMiddleware() {
		return function (Request $request, $response, $next) {
			$request = $this->processRequest($request);
			$response = $next($request, $response);
			return $response;
		};
	}

}