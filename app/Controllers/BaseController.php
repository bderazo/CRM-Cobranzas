<?php

namespace Controllers;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;
use Util\SlimFlash2;

abstract class BaseController {
	/** @var Container */
	var $container;
	/** @var Request */
	var $request;
	/** @var Response */
	var $response;
	/** @var \SlimSession\Helper */
	var $session;

	/** @var SlimFlash2 */
	var $flash;

	/** @var \Breadcrumbs */
	var $crumbs;

	/** @var \General\Seguridad\IPermissionCheck */
	var $permisos;

	var $area;

	var $title;

	/**
	 * BaseController constructor.
	 * @param Container $container
	 */
	public function __construct(Container $container) {
		$this->container = $container;
		$this->request = $container['request'];
		$this->response = $container['response'];
		$this->session = $container['session'];
		$this->flash = $container['flash'];
		$this->crumbs = $container['breadcrumbs'];
		$this->permisos = $container['permisosCheck'];
	}

	function init() {
	}

	protected function controllerRoute() {
		// o hacer una llamada al router para obtener bien el path, no se
		// porque la convencion se mete por aqui
		$clase = (new \ReflectionClass($this))->getShortName();
		$base = str_replace('Controller', '', $clase);
		$base = lcfirst($base);
		$area = '';
		if ($this->area)
			$area = $this->area . '/';
		return $area . $base;
	}

	protected function render($tpl, $vars = []) {
		if (strpos($tpl, '/') !== 0) {
			$ruta = $this->controllerRoute();
			$tpl = $ruta . '/' . $tpl;
		}
		if (strpos($tpl, '.twig') === false)
			$tpl .= '.twig';
		/** @var Twig $view */
		$view = $this->container['view'];
		if ($this->title)
			$vars['title'] = $this->title;
		return $view->render($this->container['response'], $tpl, $vars);
	}



	protected function get($id) {
		return $this->container->get($id);
	}

	protected function isPost() {
		return $this->request->isPost();
	}

	protected function redirect($uri, $status = null) {
		// poner mas opciones para redireccion
		return $this->response->withRedirect($uri, $status);
	}

	protected function redirectToAction($action, $data = [], $status = null) {
		$root = $this->container['root']; // hmmmm
		if (strpos($action, '/') === false) // es una accion local
			$ruta = $this->controllerRoute() . '/' . $action;
		else
			$ruta = $action;
		$url = $root . '/' . $ruta;
		if (!empty($data))
			$url .= '?' . http_build_query($data);
		return $this->response->withRedirect($url, $status);
	}

	protected function json($data, $jsonOptions = 0) {
		return $this->response->withJson($data, null, $jsonOptions);
	}


}