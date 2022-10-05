<?php

namespace Controllers;

use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Cliente;
use Models\Contacto;
use Models\Egreso;
use Models\Email;
use Models\Paleta;
use Models\Producto;
use Models\Telefono;
use upload;

class ProductoController extends BaseController {

	function init() {
		\Breadcrumbs::add('/producto', 'Producto');
	}

	function index() {
		\WebSecurity::secure('producto.lista');
		\Breadcrumbs::active('Producto');
		$data['puedeCrear'] = $this->permisos->hasRole('producto.crear');
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('producto.lista');
		$params = $this->request->getParsedBody();
		$lista = Producto::buscar($params, 'producto.fecha_ingreso', $page, 50);
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
		\WebSecurity::secure('producto.lista');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		$model = Producto::porId($id);
		\Breadcrumbs::active('Registrar Seguimiento');
		$telefono = Telefono::porModulo('cliente', $model->cliente_id);
		$email = Email::porModulo('cliente', $model->cliente_id);
		$cliente = Cliente::porId($model->cliente_id);
		$aplicativo_diners = Producto::getAplicativoDiners($model->cliente_id);
		$aplicativo_diners_tarjeta_diners = Producto::getAplicativoDinersDetalle('DINERS',$aplicativo_diners['id']);
		$aplicativo_diners_tarjeta_discover = Producto::getAplicativoDinersDetalle('DISCOVER',$aplicativo_diners['id']);
		$aplicativo_diners_tarjeta_interdin = Producto::getAplicativoDinersDetalle('INTERDIN',$aplicativo_diners['id']);
		$aplicativo_diners_porcentaje_interes = Producto::getAplicativoDinersPorcentajeInteres();

		$data['aplicativo_diners_porcentaje_interes'] = json_encode($aplicativo_diners_porcentaje_interes);
		$data['aplicativo_diners'] = json_encode($aplicativo_diners);
		$data['aplicativo_diners_tarjeta_diners'] = json_encode($aplicativo_diners_tarjeta_diners);
		$data['aplicativo_diners_tarjeta_discover'] = json_encode($aplicativo_diners_tarjeta_discover);
		$data['aplicativo_diners_tarjeta_interdin'] = json_encode($aplicativo_diners_tarjeta_interdin);
		$data['cliente'] = json_encode($cliente);
		$data['email'] = json_encode($email);
		$data['telefono'] = json_encode($telefono);
		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['permisoModificar'] = $this->permisos->hasRole('producto.modificar');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('producto.modificar');
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
			$con = Producto::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Producto modificado');
		} else {
			$con = new Producto();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Producto creado');
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
				$tel->modulo_relacionado = 'producto';
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
				$ema->modulo_relacionado = 'producto';
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

		\Auditor::info("Producto $con->producto actualizado", 'Producto');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('producto.eliminar');

		$eliminar = Producto::eliminar($id);
		\Auditor::info("Producto $eliminar->producto eliminado", 'Producto');
		$this->flash->addMessage('confirma', 'Producto eliminado');
		return $this->redirectToAction('index');
	}
}

class ViewProducto {
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
