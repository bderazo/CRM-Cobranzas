<?php

namespace Controllers;

use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Email;
use Models\FiltroBusqueda;
use Models\Institucion;
use Models\Paleta;
use Models\Telefono;
use upload;

class ContactoController extends BaseController {

	var $modulo = 'Contacto';

	function init() {
		\Breadcrumbs::add('/contacto', 'Contacto');
	}

	function index() {
		\WebSecurity::secure('contacto.lista');
		\Breadcrumbs::active('Contacto');
		$data['puedeCrear'] = $this->permisos->hasRole('contacto.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo,\WebSecurity::getUserData('id'));
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('contacto.lista');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo,\WebSecurity::getUserData('id'), $params);
		$lista = Contacto::buscar($params, 'contacto.nombres', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
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
		\WebSecurity::secure('contacto.lista');

		$catalogos = [
			'ciudades' => Catalogo::ciudades(),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Contacto');
			$model = new ViewContacto();
			$institucion_nombre = '';
		} else {
			$model = Contacto::porId($id);
			\Breadcrumbs::active('Editar Contacto');
			$institucion = Institucion::porId($model->institucion_id);
			$institucion_nombre = $institucion->nombre;
		}

		$data['catalogos'] = json_encode($catalogos, JSON_PRETTY_PRINT);
		$data['model'] = json_encode($model);
		$data['modelArr'] = $model;
		$data['institucion_nombre'] = $institucion_nombre;
		$data['permisoModificar'] = $this->permisos->hasRole('contacto.modificar');
		return $this->render('editar', $data);
	}

	function guardar($json) {
		\WebSecurity::secure('contacto.modificar');
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
			$con = Contacto::porId($id);
			$con->fill($data['model']);
			$this->flash->addMessage('confirma', 'Contacto modificado');
		} else {
			$con = new Contacto();
			$con->fill($data['model']);
			$con->usuario_ingreso = \WebSecurity::getUserData('id');
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$this->flash->addMessage('confirma', 'Contacto creado');
		}
		$con->usuario_modificacion = \WebSecurity::getUserData('id');
		$con->fecha_modificacion = date("Y-m-d H:i:s");
		$con->usuario_asignado = \WebSecurity::getUserData('id');
		$con->save();

		\Auditor::info("Contacto $con->nombre actualizado", 'Contacto');
		return $this->redirectToAction('editar', ['id' => $con->id]);

	}

	function eliminar($id) {
		\WebSecurity::secure('contacto.eliminar');

		$eliminar = Contacto::eliminar($id);
		\Auditor::info("Contacto $eliminar->nombre eliminada", 'Contacto');
		$this->flash->addMessage('confirma', 'Contacto eliminado');
		return $this->redirectToAction('index');
	}
}

class ViewContacto {
	var $id;
	var $institucion_id;
	var $nombres;
	var $apellidos;
	var $cargo;
	var $correo;
	var $telefono_oficina;
	var $telefono_celular;
	var $direccion;
	var $ciudad;
	var $descripcion;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
