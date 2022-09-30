<?php

namespace Controllers\admin;

use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\UsuarioLogin;

class AccessLogController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('admin');
	}
	
	function index() {
		return $this->render('index');
	}
	
	function lista($page = 1) {
		$params = $_POST;
		$lista = UsuarioLogin::buscar($params, $page);
		$pag = new Paginator($lista->total(), 10, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	
}