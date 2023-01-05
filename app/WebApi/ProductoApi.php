<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\Archivo;
use Models\Banco;
use Models\Caso;
use Models\Cliente;
use Models\Direccion;
use Models\Especialidad;
use Models\Institucion;
use Models\Membresia;
use Models\Paleta;
use Models\PaletaArbol;
use Models\Pregunta;
use Models\Producto;
use Models\ProductoSeguimiento;
use Models\Referencia;
use Models\Suscripcion;
use Models\Telefono;
use Models\Usuario;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Models\UsuarioProducto;
use Models\UsuarioSuscripcion;
use Negocio\EnvioNotificacionesPush;
use Slim\Container;
use upload;

/**
 * Class ProductoApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de productos
 */
class ProductoApi extends BaseController {
	var $test = false;

	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}

	/**
	 * get_form_busqueda_producto
	 * @param $session
	 */
	function get_form_busqueda_producto() {
		if (!$this->isPost()) return "get_form_busqueda_producto";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$retorno = [];

			$retorno['form']['title'] = 'form';
			$retorno['form']['type'] = 'object';

			$form['institucion'] = [
				'type' => 'string',
				'title' => 'Institución',
				'widget' => 'text',
				'empty_data' => '',
				'full_name' => 'data[i.nombre]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 1,
				'choices' => [],
			];
			$form['cedula'] = [
				'type' => 'string',
				'title' => 'Cédula',
				'widget' => 'text',
				'empty_data' => '',
				'full_name' => 'data[cl.cedula]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 2,
				'choices' => [],
			];
			$form['nombres'] = [
				'type' => 'string',
				'title' => 'Nombres',
				'widget' => 'text',
				'empty_data' => '',
				'full_name' => 'data[cl.nombres]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 4,
				'choices' => [],
			];
			$form['producto'] = [
				'type' => 'string',
				'title' => 'Producto',
				'widget' => 'text',
				'empty_data' => '',
				'full_name' => 'data[p.producto]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 5,
				'choices' => [],
			];

			$retorno['form']['properties'] = $form;

			return $this->json($res->conDatos($retorno));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_preguntas_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_productos_list() {
		if (!$this->isPost()) return "get_productos_list";
		$res = new RespuestaConsulta();

		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$config = $this->get('config');
			$producto = Producto::getProductoList($data, $page, $user, $config);
//		\Auditor::error("get_preguntas_list API ", 'Producto', $producto);
			return $this->json($res->conDatos($producto));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	function get_preguntas_list() {
		if (!$this->isPost()) return "get_productos_list";
		$res = new RespuestaConsulta();

		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$producto = Producto::getProductoList($data, $page, $user, $config);
//		\Auditor::error("get_preguntas_list API ", 'Producto', $producto);
		return $this->json($res->conDatos($producto));
	}

	/**
	 * get_producto_cliente
	 * @param $producto_id
	 * @param $session
	 */
	function get_producto_cliente() {
		if(!$this->isPost()) return "get_producto_cliente";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$producto = Producto::porId($producto_id);

			//DATA DE CLIENTES
			$cliente = Cliente::porId($producto['cliente_id']);
			$campos = [
				[
					'label' => 'Nombres',
					'value' => $cliente['nombres'],
				],
				[
					'label' => 'Cédula',
					'value' => $cliente['cedula'],
				],
			];

			//DATA DE TELEFONOS
			$telefono = Telefono::porModulo('cliente', $producto['cliente_id']);
			$tel_array = [];
			foreach($telefono as $tel) {
				$aux = [];
				$aux['numero_oro'] = $tel['bandera'];
				$aux['tipo'] = $tel['tipo'];
				$aux['descripcion'] = $tel['descripcion'];
				$aux['telefono'] = $tel['telefono'];
				$tel_array[] = $aux;
			}

			//DATA DE DIRECCIONES
			$direccion = Direccion::porModulo('cliente', $producto['cliente_id']);
			$dir_array = [];
			foreach($direccion as $dir) {
				$aux = [];
				$aux['tipo'] = substr($dir['tipo'], 0, 3);
				$aux['ciudad'] = $dir['ciudad'];
				$aux['direccion'] = $dir['direccion'];
				$aux['latitud'] = null;
				$aux['longitud'] = null;
				$dir_array[] = $aux;
			}

			//DATA DE REFERENCIAS
			$referencia = Referencia::porModulo('cliente', $producto['cliente_id']);
			$ref_array = [];
			foreach($referencia as $ref) {
				$aux = [];
				$aux['tipo'] = $ref['tipo'];
				$aux['descripcion'] = $ref['descripcion'];
				$aux['nombre'] = $ref['nombre'];
				$aux['telefono'] = $ref['telefono'];
				$aux['ciudad'] = $ref['ciudad'];
				$aux['direccion'] = $ref['direccion'];
				$ref_array[] = $aux;
			}

			$retorno['campos'] = $campos;
			$retorno['telefonos'] = $tel_array;
			$retorno['direcciones'] = $dir_array;
			$retorno['referencias'] = $ref_array;

			return $this->json($res->conDatos($retorno));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_producto_producto
	 * @param $producto_id
	 * @param $session
	 */
	function get_producto_producto() {
		if(!$this->isPost()) return "get_producto_producto";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$producto = Producto::porId($producto_id);

			//DATA DE CLIENTES
			$campos = [
				[
					'label' => 'Producto adquirido',
					'value' => $producto['producto'],
				],
				[
					'label' => 'Estado',
					'value' => strtoupper($producto['estado']),
				],
			];

			//DATA DE PAGOS
			$pagos_array = [];

			$retorno['campos'] = $campos;
			$retorno['pagos'] = $pagos_array;

			return $this->json($res->conDatos($retorno));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * buscar_listas
	 * @param $session
	 * @param $list
	 * @param $q
	 * @param $page
	 * @param $data
	 */
	function buscar_listas() {
		if(!$this->isPost()) return "buscar_listas";
		$res = new RespuestaConsulta();

		$q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas q: '.$q, 'API', $q);
		$page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas page: '.$page, 'API', $page);
		$data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas data: '.$data, 'API', $data);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$respuesta = PaletaArbol::getNivel2ApiQuery($q, $page, $data);
		$retorno['results'] = $respuesta;
		$retorno['pagination'] = ['more' => true];

		return $this->json($retorno);
	}

	/**
	 * get_form_paleta
	 * @param $session
	 * @param $institucion_id
	 * @param $producto_id
	 */
	function get_form_paleta() {
		if (!$this->isPost()) return "get_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$retorno = [];

			$retorno['form']['title'] = 'form';
			$retorno['form']['type'] = 'object';

			$institucion = Institucion::porId($institucion_id);
			$paleta_nivel1 = PaletaArbol::getNivel1($institucion->paleta_id);
			$nivel = [];
			foreach($paleta_nivel1 as $key => $val) {
				$nivel[] = ['id' => $key, 'label' => $val];
			}
			$retorno['form']['properties']['Nivel1'] = [
				'type' => 'string',
				'title' => 'Resultado Gestión',
				'widget' => 'choice',
				'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
				'full_name' => 'data[nivel1]',
				'constraints' => [
					[
						'name' => 'NotBlank',
						'message' => 'Este campo no puede estar vacío'
					]
				],
				'required' => 1,
				'disabled' => 0,
				'property_order' => 1,
				'choices' => $nivel,
			];
			$retorno['form']['properties']['Nivel2'] = [
				'type' => 'string',
				'title' => 'Descripción',
				'widget' => 'picker-select2',
				'empty_data' => null,
				'full_name' => 'data[nivel2]',
				'constraints' => [
					[
						'name' => 'Count',
						'Min' => 1,
						'MinMessage' => "Debe seleccionar por lo menos una opción."
					],
				],
				'required' => 1,
				'disabled' => 0,
				'property_order' => 2,
				'choices' => [],
				"multiple" => false,
				'remote_path' => 'api/producto/buscar_listas',
				'remote_params' => [
					"list" => "nivel2"
				],
				'req_params' => [
					"data[nivel1]" => "data[nivel1]"
				],
			];
			$producto = Producto::porId($producto_id);
			$direcciones = Direccion::porModulo('cliente', $producto['cliente_id']);
			$dir = [];
			foreach($direcciones as $d) {
				$dir[] = ['id' => $d['id'], 'label' => substr($d['direccion'], 0, 40)];
			}
			$retorno['form']['properties']['Direccion'] = [
				'type' => 'string',
				'title' => 'Dirección Visita',
				'widget' => 'choice',
				'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
				'full_name' => 'data[direccion_visita]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 3,
				'choices' => $dir,
			];
			$retorno['form']['properties']['Observaciones'] = [
				'type' => 'string',
				'title' => 'Observaciones',
				'widget' => 'textarea',
				'empty_data' => 'MEGACOB ' . date("Ymd") . ' ',
				'full_name' => 'data[observaciones]',
				'constraints' => [],
				'required' => 0,
				'disabled' => 0,
				'property_order' => 4,
				'choices' => [],
			];
			$retorno['form']['properties']['imagenes'] = [
				'type' => 'string',
				'title' => 'Imagen1',
				'widget' => 'file_widget',
				'empty_data' => '',
				'full_name' => 'data[imagenes]',
				'constraints' => [],
				'mode' => 'IMAGEN',
				'multiple' => true,
				'required' => 0,
				'disabled' => 0,
				'property_order' => 5,
				'choices' => [],
			];

			return $this->json($res->conDatos($retorno));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * save_form_paleta
	 * @param $session
	 * @param $institucion_id
	 * @param $producto_id
	 * @param $lat
	 * @param $long
	 * @param $data
	 * @param $_FILE
	 */
	function save_form_paleta() {
		if (!$this->isPost()) return "save_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
		\Auditor::info('save_form_paleta institucion_id: '.$institucion_id, 'API', []);
		$producto_id = $this->request->getParam('producto_id');
		\Auditor::info('save_form_paleta producto_id: '.$producto_id, 'API', []);
		$lat = $this->request->getParam('lat');
		\Auditor::info('save_form_paleta lat: '.$lat, 'API', []);
		$long = $this->request->getParam('long');
		\Auditor::info('save_form_paleta long: '.$long, 'API', []);
		$data = $this->request->getParam('data');
		\Auditor::info('save_form_paleta data: ', 'API', $data);
		$files = $_FILES;
		\Auditor::info('save_form_paleta files: ', 'API', $files);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$institucion = Institucion::porId($institucion_id);
			$producto = Producto::porId($producto_id);
			$producto->estado = 'procesado';
			$producto->save();

			$con = new ProductoSeguimiento();
			$con->institucion_id = $institucion_id;
			$con->cliente_id = $producto->cliente_id;
			$con->producto_id = $producto->id;
			$con->paleta_id = $institucion['paleta_id'];
			if(isset($data['nivel1'])) {
				$con->nivel_1_id = $data['nivel1'];
			}
			if(isset($data['nivel2'])) {
				$con->nivel_2_id = $data['nivel2'];
			}
			if(isset($data['nivel3'])) {
				$con->nivel_3_id = $data['nivel3'];
			}
			if(isset($data['nivel4'])) {
				$con->nivel_4_id = $data['nivel4'];
			}
			$con->observaciones = $data['observaciones'];
			if($data['direccion_visita'] > 0) {
				$con->direccion_id = $data['direccion_visita'];
				$direccion_update = Direccion::porId($data['direccion_visita']);
				$direccion_update->lat = $lat;
				$direccion_update->long = $long;
				$direccion_update->save();
			}
			$con->lat = $lat;
			$con->long = $long;
			$con->observaciones = $data['observaciones'];
			$con->usuario_ingreso = $user['id'];
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$con->usuario_modificacion = $user['id'];
			$con->fecha_modificacion = date("Y-m-d H:i:s");
			$con->save();

			if(isset($files["data"])) {
				//ARREGLAR ARCHIVOS
				$archivo = [];
				$i = 0;
				foreach($files['data']['name']['imagenes'] as $f) {
					$archivo[$i]['name'] = $f;
					$i++;
				}
				$i = 0;
				foreach($files['data']['type']['imagenes'] as $f) {
					$archivo[$i]['type'] = 'image/jpeg';
					$i++;
				}
				$i = 0;
				foreach($files['data']['tmp_name']['imagenes'] as $f) {
					$archivo[$i]['tmp_name'] = $f;
					$i++;
				}
				$i = 0;
				foreach($files['data']['error']['imagenes'] as $f) {
					$archivo[$i]['error'] = $f;
					$i++;
				}
				$i = 0;
				foreach($files['data']['size']['imagenes'] as $f) {
					$archivo[$i]['size'] = $f;
					$i++;
				}

				\Auditor::info('save_form_paleta archivo: ', 'API', $archivo);
				foreach($archivo as $f) {
					$this->uploadFiles($con, $f);
				}
			}

			return $this->json($res->conDatos($con->toArray()));
		}else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	public function uploadFiles($seguimiento, $archivo)
	{
		$config = $this->get('config');

		//INSERTAR EN BASE EL ARCHIVO
		$arch = new Archivo();
		$arch->parent_id = $seguimiento->id;
		$arch->parent_type = 'seguimiento';
		$arch->nombre = $archivo['name'];
		$arch->nombre_sistema = $archivo['name'];
		$arch->longitud = $archivo['size'];
		$arch->tipo_mime = $archivo['type'];
		$arch->descripcion = 'imagen ingresada desde la app';
		$arch->fecha_ingreso = date("Y-m-d H:i:s");
		$arch->fecha_modificacion = date("Y-m-d H:i:s");
		$arch->usuario_ingreso = 1;
		$arch->usuario_modificacion = 1;
		$arch->eliminado = 0;
		$arch->save();

		$dir = $config['folder_images_seguimiento'];
		if(!is_dir($dir)) {
			\Auditor::error("Error API Carga Archivo: El directorio $dir de imagenes no existe", 'ProductoApi', []);
			return false;
		}
		$upload = new Upload($archivo);
		if(!$upload->uploaded) {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'ProductoApi', []);
			return false;
		}
		// save uploaded image with no changes
		$upload->Process($dir);
		if($upload->processed) {
			\Auditor::info("API Carga Archivo " . $archivo['name'] . " cargada", 'ProductoApi');
			return true;
		} else {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'ProductoApi', []);
			return false;
		}
	}
}
