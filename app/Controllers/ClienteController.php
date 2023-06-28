<?php

namespace Controllers;

use Catalogos\CatalogoCliente;
use General\GeneralHelper;
use General\Validacion\Utilidades;
use JasonGrimes\Paginator;
use Models\Archivo;
use Models\Catalogo;
use Models\Contacto;
use Models\Direccion;
use Models\Egreso;
use Models\Cliente;
use Models\Email;
use Models\FiltroBusqueda;
use Models\Paleta;
use Models\Producto;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Telefono;
use upload;

class ClienteController extends BaseController {

	var $modulo = 'Cliente';

	function init() {
		\Breadcrumbs::add('/cliente', 'Cliente');
	}

	function index() {
		\WebSecurity::secure('cliente.lista');
		\Breadcrumbs::active('Cliente');
		$data['puedeCrear'] = $this->permisos->hasRole('cliente.crear');
		$data['filtros'] = FiltroBusqueda::porModuloUsuario($this->modulo,\WebSecurity::getUserData('id'));
		return $this->render('index', $data);
	}

	function lista($page) {
		\WebSecurity::secure('cliente.lista');
		$params = $this->request->getParsedBody();
		$saveFiltros = FiltroBusqueda::saveModuloUsuario($this->modulo,\WebSecurity::getUserData('id'), $params);
		$lista = Cliente::buscar($params, 'cliente.nombres', $page, 20);
		$pag = new Paginator($lista->total(), 20, $page, "javascript:cargar((:num));");
		$retorno = [];
		foreach ($lista as $listas) {
			$retorno[$listas['id']] = $listas;
		}
		$data['lista'] = $retorno;
		$data['pag'] = $pag;
//		printDie($pag);
		return $this->render('lista', $data);
	}

	function crear() {
		return $this->editar(0);
	}

	function editar($id) {
		\WebSecurity::secure('cliente.lista');

		$cat = new CatalogoCliente();
		$catalogos = [
			'sexo' => $cat->getByKey('sexo'),
			'estado_civil' => $cat->getByKey('estado_civil'),
			'tipo_telefono' => $cat->getByKey('tipo_telefono'),
			'tipo_email' => $cat->getByKey('tipo_email'),
			'descripcion_telefono' => $cat->getByKey('descripcion_telefono'),
			'descripcion_email' => $cat->getByKey('descripcion_email'),
			'origen_telefono' => $cat->getByKey('origen_telefono'),
			'tipo_direccion' => $cat->getByKey('tipo_direccion'),
			'tipo_referencia' => $cat->getByKey('tipo_referencia'),
			'descripcion_referencia' => $cat->getByKey('descripcion_referencia'),
			'ciudades' => Catalogo::ciudades(),
		];

		if ($id == 0) {
			\Breadcrumbs::active('Crear Cliente');
			$model = new ViewCliente();
            $model->gestionar = 'si';
			$telefono = [];
			$email = [];
			$direccion = [];
			$referencia = [];
			$productos = [];
		} else {
			$model = Cliente::porId($id);
			\Breadcrumbs::active('Editar Cliente');
			$telefono = Telefono::porModulo('cliente', $model->id);
			$email = Email::porModulo('cliente', $model->id);
//			printDie($email);
			$direccion = Direccion::porModulo('cliente', $model->id);
			$referencia = Referencia::porModulo('cliente', $model->id);
			$productos = ProductoSeguimiento::getUltimoSeguimientoPorCliente($model->id);

//			printDie($productos);
		}

		$data['productos'] = json_encode($productos);
		$data['referencia'] = json_encode($referencia);
		$data['direccion'] = json_encode($direccion);
		$data['telefono'] = json_encode($telefono);
		$data['email'] = json_encode($email);
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
			} else {
				$tel = new Telefono();
				$tel->modulo_id = $con->id;
				$tel->modulo_relacionado = 'cliente';
				$tel->usuario_ingreso = \WebSecurity::getUserData('id');
				$tel->eliminado = 0;
				$tel->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$tel->telefono = $t['telefono'];
			$tel->tipo = $t['tipo'];
			$tel->descripcion = $t['descripcion'];
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
			} else {
				$ema = new Email();
				$ema->modulo_id = $con->id;
				$ema->modulo_relacionado = 'cliente';
				$ema->usuario_ingreso = \WebSecurity::getUserData('id');
				$ema->eliminado = 0;
				$ema->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$ema->email = $e['email'];
			$ema->tipo = $e['tipo'];
			$ema->descripcion = $e['descripcion'];
			$ema->usuario_modificacion = \WebSecurity::getUserData('id');
			$ema->fecha_modificacion = date("Y-m-d H:i:s");
			$ema->save();
		}
		foreach ($data['del_email'] as $d) {
			$del = Email::eliminar($d);
		}

		//GUARDAR DIRECCION
		foreach ($data['direccion'] as $d) {
			if (isset($d['id'])) {
				$dir = Direccion::porId($d['id']);
				$dir->tipo = $d['tipo'];
				$dir->ciudad = $d['ciudad'];
				$dir->direccion = $d['direccion'];
			} else {
				$dir = new Direccion();
				$dir->tipo = $d['tipo'];
				$dir->ciudad = $d['ciudad'];
				$dir->direccion = $d['direccion'];
				$dir->modulo_id = $con->id;
				$dir->modulo_relacionado = 'cliente';
				$dir->usuario_ingreso = \WebSecurity::getUserData('id');
				$dir->eliminado = 0;
				$dir->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$dir->usuario_modificacion = \WebSecurity::getUserData('id');
			$dir->fecha_modificacion = date("Y-m-d H:i:s");
			$dir->save();
		}
		foreach ($data['del_direccion'] as $d) {
			$del = Direccion::eliminar($d);
		}

		//GUARDAR REFERENCIA
		foreach ($data['referencia'] as $r) {
			if (isset($r['id'])) {
				$ref = Referencia::porId($r['id']);
				$ref->tipo = $r['tipo'];
				$ref->descripcion = $r['descripcion'];
				$ref->nombre = $r['nombre'];
				$ref->telefono = $r['telefono'];
				$ref->ciudad = $r['ciudad'];
				$ref->direccion = $r['direccion'];
			} else {
				$ref = new Referencia();
				$ref->tipo = $r['tipo'];
				$ref->descripcion = $r['descripcion'];
				$ref->nombre = $r['nombre'];
				$ref->telefono = $r['telefono'];
				$ref->ciudad = $r['ciudad'];
				$ref->direccion = $r['direccion'];
				$ref->modulo_id = $con->id;
				$ref->modulo_relacionado = 'cliente';
				$ref->usuario_ingreso = \WebSecurity::getUserData('id');
				$ref->eliminado = 0;
				$ref->fecha_ingreso = date("Y-m-d H:i:s");
			}
			$ref->usuario_modificacion = \WebSecurity::getUserData('id');
			$ref->fecha_modificacion = date("Y-m-d H:i:s");
			$ref->save();
		}
		foreach ($data['del_referencia'] as $d) {
			$del = Referencia::eliminar($d);
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

	//BUSCADORES
	function buscador() {
//		$db = new \FluentPDO($this->get('pdo'));
		$data = [];
		return $this->render('buscador', $data);
	}

	function buscar($nombres, $cedula) {
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$likeNombres = $pdo->quote('%' . strtoupper($nombres) . '%');
		$likeCedula = $pdo->quote('%' . strtoupper($cedula) . '%');
		$db = new \FluentPDO($pdo);

		$qpro = $db->from('cliente c')
			->select(null)
			->select('c.*')
			->where('c.eliminado', 0);
		if ($nombres != '') {
			$qpro->where("(upper(c.nombres) like $likeNombres )");
		}
		if ($cedula != '') {
			$qpro->where("(c.cedula like $likeCedula )");
		}
		$qpro->orderBy('c.nombres')->limit(50);
		$lista = $qpro->fetchAll();
		$cliente = [];
		foreach ($lista as $l) {
			$cliente[] = $l;
		}
		return $this->json(compact('cliente'));
	}
}

class ViewCliente {
	var $id;
	var $nombres;
	var $cedula;
	var $sexo;
	var $estado_civil;
	var $lugar_trabajo;
	var $ciudad;
	var $zona;
	var $profesion_id;
	var $tipo_referencia_id;
    var $gestionar;
	var $fecha_ingreso;
	var $fecha_modificacion;
	var $usuario_ingreso;
	var $usuario_modificacion;
	var $usuario_asignado;
	var $eliminado;
}
