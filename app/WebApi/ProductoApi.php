<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDinersDetalle;
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
use Models\PaletaMotivoNoPago;
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
class ProductoApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_form_busqueda_producto
	 * @param $session
	 */
	function get_form_busqueda_producto()
	{
		if(!$this->isPost()) return "get_form_busqueda_producto";
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
		} else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_preguntas_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_productos_list()
	{
		if(!$this->isPost()) return "get_productos_list";
		$res = new RespuestaConsulta();

		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if(isset($user['id'])) {
			$config = $this->get('config');

			//ELIMINAR APLICACIONES DINERS DETALLE SIN ID DE SEGUIMIENTO CREADAS POR EL USUARIO DE LA SESION
			$detalle_sin_seguimiento = AplicativoDinersDetalle::getSinSeguimiento($user['id']);
			foreach($detalle_sin_seguimiento as $ss) {
				$mod = AplicativoDinersDetalle::porId($ss['id']);
				$mod->eliminado = 1;
				$mod->save();
			}

			$producto = Producto::getProductoList($data, $page, $user, $config);
			return $this->json($res->conDatos($producto));
		} else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_producto_cliente
	 * @param $producto_id
	 * @param $session
	 */
	function get_producto_cliente()
	{
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
		} else {
			return $this->json($res->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_producto_producto
	 * @param $producto_id
	 * @param $session
	 */
	function get_producto_producto()
	{
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
		} else {
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
	function buscar_listas()
	{
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

	function buscar_listas_n3()
	{
		if(!$this->isPost()) return "buscar_listas_n3";
		$res = new RespuestaConsulta();

		$q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas_n3 q: '.$q, 'API', $q);
		$page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas_n3 page: '.$page, 'API', $page);
		$data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas_n3 data: '.$data, 'API', $data);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$respuesta = PaletaArbol::getNivel3ApiQuery($q, $page, $data);
		$retorno['results'] = $respuesta;
		$retorno['pagination'] = ['more' => true];

		return $this->json($retorno);
	}

	function buscar_listas_n4()
	{
		if(!$this->isPost()) return "buscar_listas_n4";
		$res = new RespuestaConsulta();

		$q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas_n4 q: '.$q, 'API', $q);
		$page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas_n4 page: '.$page, 'API', $page);
		$data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas_n4 data: '.$data, 'API', $data);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$respuesta = PaletaArbol::getNivel4ApiQuery($q, $page, $data);
		$retorno['results'] = $respuesta;
		$retorno['pagination'] = ['more' => true];

		return $this->json($retorno);
	}

	/**
	 * buscar_listas_motivo_no_pago
	 * @param $session
	 * @param $list
	 * @param $q
	 * @param $page
	 * @param $data
	 */
	function buscar_listas_motivo_no_pago()
	{
		if(!$this->isPost()) return "buscar_listas_motivo_no_pago";
		$res = new RespuestaConsulta();

		$q = $this->request->getParam('q');
//		\Auditor::info('buscar_listas q: '.$q, 'API', $q);
		$page = $this->request->getParam('page');
//		\Auditor::info('buscar_listas page: '.$page, 'API', $page);
		$data = $this->request->getParam('data');
//		\Auditor::info('buscar_listas data: '.$data, 'API', $data);
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$respuesta = PaletaMotivoNoPago::getNivel2ApiQuery($q, $page, $data);
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
	function get_form_paleta()
	{
		if(!$this->isPost()) return "get_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
		$producto_id = $this->request->getParam('producto_id');

//		$institucion_id = 1;
//		$producto_id = 12596;

		\Auditor::error("get_form_paleta institucion_id: $institucion_id ", 'Producto', $institucion_id);
		\Auditor::error("get_form_paleta producto_id: $producto_id ", 'Producto', $producto_id);

		if($institucion_id > 0 && $producto_id > 0) {
			$session = $this->request->getParam('session');
			$user = UsuarioLogin::getUserBySession($session);
			if(isset($user['id'])) {
				$retorno = [];

				$retorno['form']['title'] = 'form';
				$retorno['form']['type'] = 'object';

				$institucion = Institucion::porId($institucion_id);
				$paleta = Paleta::porId($institucion->paleta_id);

				$retorno['form']['properties']['title_5'] = [
					'title' => 'REGISTRO DE SEGUIMIENTO',
					'widget' => 'readonly',
					'full_name' => 'data[title_5]',
					'constraints' => [],
					'type_content' => 'title',
					'required' => 0,
					'disabled' => 0,
					'property_order' => 1,
				];

				$paleta_nivel1 = PaletaArbol::getNivel1($institucion->paleta_id);
				$nivel = [];
				foreach($paleta_nivel1 as $key => $val) {
					$nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
				}
				$retorno['form']['properties']['Nivel1'] = [
					'type' => 'string',
					'title' => $paleta['titulo_nivel1'],
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
				if($paleta['titulo_nivel2'] != '') {
					$retorno['form']['properties']['Nivel2'] = [
						'type' => 'string',
						'title' => $paleta['titulo_nivel2'],
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
				}
				if($paleta['titulo_nivel3'] != '') {
					$retorno['form']['properties']['Nivel3'] = [
						'type' => 'string',
						'title' => $paleta['titulo_nivel3'],
						'widget' => 'picker-select2',
						'empty_data' => null,
						'full_name' => 'data[nivel3]',
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
						'remote_path' => 'api/producto/buscar_listas_n3',
						'remote_params' => [
							"list" => "nivel3"
						],
						'req_params' => [
							"data[nivel2]" => "data[nivel2]"
						],
					];
				}
				if($paleta['titulo_nivel4'] != '') {
					$retorno['form']['properties']['Nivel4'] = [
						'type' => 'string',
						'title' => $paleta['titulo_nivel4'],
						'widget' => 'picker-select2',
						'empty_data' => null,
						'full_name' => 'data[nivel4]',
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
						'remote_path' => 'api/producto/buscar_listas_n4',
						'remote_params' => [
							"list" => "nivel4"
						],
						'req_params' => [
							"data[nivel3]" => "data[nivel3]"
						],
					];
				}

				if($institucion_id == 1){
					$retorno['form']['properties']['fecha_compromiso_pago'] = [
						'type' => 'string',
						'title' => 'FECHA COMPROMISO DE PAGO',
						'widget' => 'date',
						'empty_data' => null,
						'full_name' => 'data[fecha_compromiso_pago]',
						'constraints' => [],
						'required' => 0,
						'disabled' => 0,
						'property_order' => 2,
						'choices' => [],
					];
					$retorno['form']['properties']['valor_comprometido'] = [
						'type' => 'string',
						'title' => 'VALOR COMPROMETIDO',
						'widget' => 'text',
						'empty_data' => 0,
						'full_name' => 'data[valor_comprometido]',
						'constraints' => [
							[
								'name' => 'Positive',
								'message' => 'Este campo debe ser un número válido'
							],
						],
						'required' => 0,
						'disabled' => 0,
						'property_order' => 2,
						'choices' => [],
					];
				}

				if($paleta['titulo_motivo_no_pago_nivel1'] != '') {
					$retorno['form']['properties']['title_1'] = [
						'title' => 'MOTIVO DE NO PAGO',
						'widget' => 'readonly',
						'full_name' => 'data[title_1]',
						'constraints' => [],
						'type_content' => 'title',
						'required' => 0,
						'disabled' => 0,
						'property_order' => 1,
					];

					$paleta_nivel1 = PaletaMotivoNoPago::getNivel1($institucion->paleta_id);
					$nivel = [];
					foreach($paleta_nivel1 as $key => $val) {
//					$nivel[] = ['id' => $key, 'label' => $val];
						$nivel[] = ['id' => $val['nivel1_id'], 'label' => $val['nivel1']];
					}
					$retorno['form']['properties']['Nivel1MotivoNoPago'] = [
						'type' => 'string',
						'title' => $paleta['titulo_motivo_no_pago_nivel1'],
						'widget' => 'choice',
						'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
						'full_name' => 'data[nivel_1_motivo_no_pago_id]',
						'constraints' => [
							[
								'name' => 'NotBlank',
								'message' => 'Este campo no puede estar vacío'
							]
						],
						'required' => 0,
						'disabled' => 0,
						'property_order' => 3,
						'choices' => $nivel,
					];
				}
				if($paleta['titulo_motivo_no_pago_nivel2'] != '') {
					$retorno['form']['properties']['Nivel2MotivoNoPago'] = [
						'type' => 'string',
						'title' => $paleta['titulo_motivo_no_pago_nivel2'],
						'widget' => 'picker-select2',
						'empty_data' => null,
						'full_name' => 'data[nivel_2_motivo_no_pago_id]',
						'constraints' => [
							[
								'name' => 'Count',
								'Min' => 1,
								'MinMessage' => "Debe seleccionar por lo menos una opción."
							],
						],
						'required' => 0,
						'disabled' => 0,
						'property_order' => 4,
						'choices' => [],
						"multiple" => false,
						'remote_path' => 'api/producto/buscar_listas_motivo_no_pago',
						'remote_params' => [
							"list" => "nivel_2_motivo_no_pago_id"
						],
						'req_params' => [
							"data[nivel_1_motivo_no_pago_id]" => "data[nivel_1_motivo_no_pago_id]"
						],
					];
				}

				$producto = Producto::porId($producto_id);
				$direcciones = Direccion::porModulo('cliente', $producto['cliente_id']);
				if(count($direcciones) > 0) {
					$dir = [];
					foreach($direcciones as $d) {
						$dir[] = ['id' => $d['id'], 'label' => substr($d['direccion'], 0, 40)];
					}
					$retorno['form']['properties']['title_2'] = [
						'title' => 'DIRECCIÓN DE VISITA',
						'widget' => 'readonly',
						'full_name' => 'data[title_2]',
						'constraints' => [],
						'type_content' => 'title',
						'required' => 0,
						'disabled' => 0,
						'property_order' => 1,
					];
					$retorno['form']['properties']['Direccion'] = [
						'type' => 'string',
						'title' => 'Dirección Visita',
						'widget' => 'choice',
						'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
						'full_name' => 'data[direccion_visita]',
						'constraints' => [],
						'required' => 0,
						'disabled' => 0,
						'property_order' => 5,
						'choices' => $dir,
					];
				}
				$retorno['form']['properties']['title_3'] = [
					'title' => 'OBSERVACIONES',
					'widget' => 'readonly',
					'full_name' => 'data[title_3]',
					'constraints' => [],
					'type_content' => 'title',
					'required' => 0,
					'disabled' => 0,
					'property_order' => 1,
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
					'property_order' => 6,
					'choices' => [],
				];
				$retorno['form']['properties']['title_4'] = [
					'title' => 'IMÁGENES',
					'widget' => 'readonly',
					'full_name' => 'data[title_4]',
					'constraints' => [],
					'type_content' => 'title',
					'required' => 0,
					'disabled' => 0,
					'property_order' => 1,
				];
				$retorno['form']['properties']['imagenes'] = [
					'type' => 'string',
					'title' => 'Imágenes',
					'widget' => 'file_widget',
					'empty_data' => '',
					'full_name' => 'data[imagenes]',
					'constraints' => [],
					'mode' => 'IMAGEN',
					'multiple' => true,
					'required' => 0,
					'disabled' => 0,
					'property_order' => 7,
					'choices' => [],
				];

				return $this->json($res->conDatos($retorno));
			} else {
				return $this->json($res->conError('PARAMETROS INCORRECTOS'));
			}
		} else {
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
	function save_form_paleta()
	{
		if(!$this->isPost()) return "save_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
//		\Auditor::info('save_form_paleta institucion_id: ' . $institucion_id, 'API', []);
		$producto_id = $this->request->getParam('producto_id');
//		\Auditor::info('save_form_paleta producto_id: ' . $producto_id, 'API', []);
		$lat = $this->request->getParam('lat');
//		\Auditor::info('save_form_paleta lat: ' . $lat, 'API', []);
		$long = $this->request->getParam('long');
//		\Auditor::info('save_form_paleta long: ' . $long, 'API', []);
		$data = $this->request->getParam('data');
//		\Auditor::info('save_form_paleta data: ', 'API', $data);
		$files = $_FILES;
//		\Auditor::info('save_form_paleta files: ', 'API', $files);
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
			$con->canal = 'CAMPO';
			if(isset($data['nivel1'])) {
				$con->nivel_1_id = $data['nivel1'];
				$paleta_arbol = PaletaArbol::porId($data['nivel1']);
				$con->nivel_1_texto = $paleta_arbol['valor'];
			}
			if(isset($data['nivel2'])) {
				$con->nivel_2_id = $data['nivel2'];
				$paleta_arbol = PaletaArbol::porId($data['nivel2']);
				$con->nivel_2_texto = $paleta_arbol['valor'];
			}
			if(isset($data['nivel3'])) {
				$con->nivel_3_id = $data['nivel3'];
				$paleta_arbol = PaletaArbol::porId($data['nivel3']);
				$con->nivel_3_texto = $paleta_arbol['valor'];
			}
			if(isset($data['nivel4'])) {
				$con->nivel_4_id = $data['nivel4'];
				$paleta_arbol = PaletaArbol::porId($data['nivel4']);
				$con->nivel_4_texto = $paleta_arbol['valor'];
			}

			if(isset($data['nivel_1_motivo_no_pago_id'])) {
				$con->nivel_1_motivo_no_pago_id = $data['nivel_1_motivo_no_pago_id'];
				$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_1_motivo_no_pago_id']);
				$con->nivel_1_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
			}
			if(isset($data['nivel_2_motivo_no_pago_id'])) {
				$con->nivel_2_motivo_no_pago_id = $data['nivel_2_motivo_no_pago_id'];
				$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_2_motivo_no_pago_id']);
				$con->nivel_2_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
			}
			if(isset($data['nivel_3_motivo_no_pago_id'])) {
				$con->nivel_3_motivo_no_pago_id = $data['nivel_3_motivo_no_pago_id'];
				$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_3_motivo_no_pago_id']);
				$con->nivel_3_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
			}
			if(isset($data['nivel_4_motivo_no_pago_id'])) {
				$con->nivel_4_motivo_no_pago_id = $data['nivel_4_motivo_no_pago_id'];
				$paleta_motivo_no_pago = PaletaMotivoNoPago::porId($data['nivel_4_motivo_no_pago_id']);
				$con->nivel_4_motivo_no_pago_texto = $paleta_motivo_no_pago['valor'];
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
			$con->usuario_ingreso = $user['id'];
			$con->eliminado = 0;
			$con->fecha_ingreso = date("Y-m-d H:i:s");
			$con->usuario_modificacion = $user['id'];
			$con->fecha_modificacion = date("Y-m-d H:i:s");
			$con->save();

			//ASIGNAR APLICACIONES DINERS DETALLE SIN ID DE SEGUIMIENTO CREADAS POR EL USUARIO DE LA SESION
			$detalle_sin_seguimiento = AplicativoDinersDetalle::getSinSeguimiento($user['id']);
			foreach($detalle_sin_seguimiento as $ss) {
				$mod = AplicativoDinersDetalle::porId($ss['id']);
				$mod->producto_seguimiento_id = $con->id;
				$mod->save();
			}

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
		} else {
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
