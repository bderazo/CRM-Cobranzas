<?php

namespace Controllers\admin;

use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\Notificacion;
use Notificaciones\Notificador;

class NotificacionesController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::add('/admin/notificaciones', 'Notificaciones');
	}
	
	function index() {
		return $this->render('index');
	}
	
	function lista($page = 1) {
		$params = $_POST;
		$lista = Notificacion::buscar($params, $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$data['lista'] = $lista;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}
	
	function detalle($id) {
		$log = Notificacion::porId($id);
		
		$textos = [];
		$textos[] = 'Fecha Creacion: ' . $log->fecha_creacion;
		$textos[] = 'Fecha Envio: ' . $log->fecha_envio;
		$textos[] = 'Caso Id: ' . $log->caso_id;
		$textos[] = 'Destino: ' . $log->destino;
		$textos[] = 'Evento: ' . $log->evento;
		$textos[] = "Estado: <b>$log->estado</b>";
		$textos[] = 'Emails: ' . $log->emails;
		
		$data['textos'] = implode('<br>', $textos);
		if ($log->error) {
			$data['log_error'] = $log->error;
		}
		if ($log->data) {
			$data['log_data'] = $log->data;
		}
		if ($log->estado != 'enviado')
			$data['reenviar'] = true;
		$data['id'] = $id;
		$this->render('detalle', $data);
	}
	
	function eliminar($id) {
		$log = Notificacion::porId($id);
		$log->delete();
		$this->flash->addMessage('confirma', "Notificacion $id para caso $log->caso_id ELIMINADA");
		return $this->redirectToAction('index');
	}
	
	function reenviar($id) {
		$log = Notificacion::porId($id);
		/** @var Notificador $not */
		$not = $this->get('notificador');
		$not->metodo = 'local';
		$not->enviar($log);
		$this->flash->addMessage('confirma', "Notificacion $id para caso $log->caso_id REENVIADA");
		return $this->redirectToAction('detalle', ['id' => $id]);
	}
	
}