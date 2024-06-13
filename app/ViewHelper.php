<?php

class ViewHelper {
	/** @var Slim\App */
	var $app;

	static $uri;
	protected static $container;

	/**
	 * SlimFunctions constructor.
	 * @param \Slim\App $app
	 */
	public function __construct(\Slim\App $app) {
		$this->app = $app;
	}

	static function setContainer($container) {
		self::$container = $container;
	}

	static function render($tpl, array $vars = []) {
		global $container;
		if (strpos($tpl, '.twig') === false)
			$tpl .= '.twig';
		return $container['view']->render($container['response'], $tpl, $vars);
	}

	static function root() {
		global $container;
		if (is_string(self::$uri))
			return self::$uri;
		self::$uri = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
		return self::$uri;
	}

	static function unauthorized($mensaje = 'Se requiere autenticación') {
		// TODO esto arreglar la referencia global o quitar o hacer de alguna otra forma
		global $container;
		/** @var \Slim\Http\Request $req */
		$req = $container['request'];
		/** @var \Slim\Http\Response $res */
		$res = $container['response'];
		if ($req->isXhr()) {
			unset($_SESSION['urlRedirect']);
			$res = $res->withStatus(401);
			throw new \Slim\Exception\SlimException($req, $res);
		}

		/** @var \Slim\Router $router */
		$router = $container['router'];
		/** @var Slim\Flash\Messages $flash */
		$flash = $container['flash'];
		$_SESSION['urlRedirect'] = $req->getUri()->__toString();
		$flash->addMessage('error', $mensaje);
		$res = $res->withRedirect($router->pathFor('login'));
		throw new \Slim\Exception\SlimException($req, $res);
	}

	static function negateCache() {
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
}