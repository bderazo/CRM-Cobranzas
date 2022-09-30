<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Egreso;
use Models\Email;
use Models\Cliente;
use Models\Paleta;
use Models\Producto;
use Models\Telefono;
use upload;

class ClienteController extends BaseController {

	function init() {
		\Breadcrumbs::add('/cliente', 'Cliente');
	}

	function index() {
		\WebSecurity::secure('cliente.lista');
		\Breadcrumbs::active('Cliente');
		$data['puedeCrear'] = $this->permisos->hasRole('cliente.crear');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('cliente.lista');
		$params = $this->request->getParsedBody();
		$lista = Cliente::buscar($params, 'cliente.apellidos', $page, 50);
		$pag = new Paginator($lista->total(), 50, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $listas) {
			$retorno[] = $listas;
		}
		$data['lista'] = $retorno;
		$data['pag'] = $pag;
		return $this->render('lista', $data);
	}

	function crear() {
		return $this->editar(0);
	}

	function editar($id) {
		\WebSecurity::secure('cliente.lista');

		$cat = new CatalogoCliente();
		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Cliente');
			$model = new ViewCliente();
			$telefono = [];
			$productos = [];
		} else {
			$model = Cliente::porId($id);
			\Breadcrumbs::active('Editar Cliente');
			$telefono = Telefono::porModulo('cliente', $model->id);
			$email = Email::porModulo('cliente', $model->id);
			$productos = Producto::porCliente($model->id);
		}

		$data['productos'] = json_encode($productos);
		$data['email'] = json_encode($email);
		$data['telefono'] = json_encode($telefono);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('cliente.modificar');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('cliente.modificar');
		$id = @$_POST['id'];
		$data = json_decode($json, true);
		// limpieza
		$keys = array_keys($data['model']);
		foreach ($keys as $key) {
			$val = $data['model'][$key];
			if (is_string($val))
				$val = trim($val);
			if ($val === '' || $val === null)
				unset($data['model'][$key]);
		}

		if ($id) {
			$con = Cliente::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Cliente modificado');
		} else {
			$con = new Cliente();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Cliente creado');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->usuario_asignado = \WebSecurity::getUserData('id');
		$con->save();

		//GUARDAR TELEFONO
		foreach ($data['telefono'] as $t) {
			if (isset($t['id'])) {
				$tel = Telefono::porId($t['id']);
				$tel->telefono = $t['telefono'];
			} else {
				$tel = new Telefono();
				$tel->telefono = $t['telefono'];
				$tel->modulo_id = $con->id;
				$tel->modulo_relacionado = 'cliente';
				$tel->usuario_ingreso = \WebSecurity::getUserData('id');
				$tel->eliminado = 0;
				$tel->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$tel->usuario_modificacion = \WebSecurity::getUserData('id');
			$tel->fecha_modificacion = date("Y-m-d H:i:s");
			$tel->save();
		}
		foreach ($data['del_telefono'] as $d) {
			$del = Telefono::eliminar($d);
		}

		//GUARDAR EMAIL
		foreach ($data['email'] as $e) {
			if (isset($e['id'])) {
				$ema = Email::porId($e['id']);
				$ema->email = $e['email'];
			} else {
				$ema = new Email();
				$ema->email = $e['email'];
				$ema->modulo_id = $con->id;
				$ema->modulo_relacionado = 'cliente';
				$ema->usuario_ingreso = \WebSecurity::getUserData('id');
				$ema->eliminado = 0;
				$ema->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$ema->usuario_modificacion = \WebSecurity::getUserData('id');
			$ema->fecha_modificacion = date("Y-m-d H:i:s");
			$ema->save();
		}
		foreach ($data['del_email'] as $d) {
			$del = Email::eliminar($d);
		}

		\Auditor::info("Cliente $con->apellidos actualizado", 'Cliente');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('cliente.eliminar');

		$eliminar = Cliente::eliminar($id);
		\Auditor::info("Cliente $eliminar->apellidos eliminado", 'Cliente');
		$this->flash->addMessage('confirma', 'Cliente eliminado');
		return $this->redirectToAction('index');
	}
}

class ViewCliente {
	var $id;
	var $apellidos;
	var $nombres;
	var $cedula;
	var $sexo;
	var $estado_civil;
	var $profesion_id;
	var $tipo_referencia_id;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
