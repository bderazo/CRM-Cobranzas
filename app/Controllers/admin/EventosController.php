<?php

namespace Controllers\admin;

use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\LogEvento;

class EventosController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::add('/admin/eventos', 'Eventos del sistema');
	}
	
	function index() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::active('Eventos del sistema');
		$data['niveles'] = [
			\Auditor::INFO => 'INFO',
			\Auditor::WARN => 'WARNING',
			\Auditor::DEBUG => 'DEBUG',
			\Auditor::ERROR => 'ERROR',
		];
		return $this->render('index', $data);
	}
	
	function lista($page = 1) {
		$params = $_POST;
		$lista = LogEvento::buscar($params, $page);
		$pag = new Paginator($lista->total(), 10, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	function detalle($id) {
		\Breadcrumbs::active('Detalle de envento');
		/** @var LogEvento $log */
		$log = LogEvento::query()->findOrFail($id);
		$data['e'] = $log;
		$data['hayDatos'] = !empty($log->datos);
		return $this->render('detalle', $data);
	}
	
	function decode($json) {
		$f = json_decode($json, true);
		print_r($f);
		exit();
	}
	
}