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
use Models\Membresia;
use Models\Paleta;
use Models\Pregunta;
use Models\Producto;
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
		$form['apellidos'] = [
			'type' => 'string',
			'title' => 'Apellidos',
			'widget' => 'text',
			'empty_data' => '',
			'full_name' => 'data[cl.apellidos]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 3,
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
	}

	/**
	 * get_preguntas_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_preguntas_list() {
		if (!$this->isPost()) return "get_preguntas_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$producto = Producto::getProductoList($query, $page, $user, $config);
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
		$producto = Producto::porId($producto_id);

		//DATA DE CLIENTES
		$cliente = Cliente::porId($producto['cliente_id']);
		$campos = [
			[
				'label' => 'Apellidos',
				'value' => $cliente['apellidos'],
			],
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
		foreach ($telefono as $tel){
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
		foreach ($direccion as $dir){
			$aux = [];
			$aux['tipo'] = $dir['tipo'];
			$aux['ciudad'] = $dir['ciudad'];
			$aux['direccion'] = $dir['direccion'];
			$dir_array[] = $aux;
		}

		//DATA DE REFERENCIAS
		$referencia = Referencia::porModulo('cliente', $producto['cliente_id']);
		$ref_array = [];
		foreach ($referencia as $ref){
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
		$producto = Producto::porId($producto_id);

		//DATA DE CLIENTES
		$campos = [
			[
				'label' => 'Producto adquirido',
				'value' => $producto['producto'],
			],
			[
				'label' => 'Subproducto',
				'value' => $producto['subproducto'],
			],
			[
				'label' => 'Agencia',
				'value' => $producto['agencia'],
			],
			[
				'label' => 'Estado',
				'value' => $producto['estado'],
			],
			[
				'label' => 'Estado operación',
				'value' => $producto['estado_operacion'],
			],
			[
				'label' => 'Tipo proceso',
				'value' => $producto['tipo_proceso'],
			],
			[
				'label' => 'Fecha adquisición',
				'value' => $producto['fecha_adquisicion'],
			],
			[
				'label' => 'Sector',
				'value' => $producto['sector'],
			],
			[
				'label' => 'Monto crédito',
				'value' => $producto['monto_credito'],
			],
			[
				'label' => 'Monto adeudado',
				'value' => $producto['monto_adeudado'],
			],
			[
				'label' => 'Monto riesgo',
				'value' => $producto['monto_riesgo'],
			],
			[
				'label' => 'Días mora',
				'value' => $producto['dias_mora'],
			],
			[
				'label' => 'Número de cuotas',
				'value' => $producto['numero_cuotas'],
			],
			[
				'label' => 'Fecha vencimiento',
				'value' => $producto['fecha_vencimiento'],
			],
			[
				'label' => 'Valor cuota',
				'value' => $producto['valor_cuota'],
			],
			[
				'label' => 'Valor cobrar',
				'value' => $producto['valor_cobrar'],
			],
			[
				'label' => 'Abono/pago',
				'value' => $producto['abono'],
			],
			[
				'label' => 'Nombre Garante',
				'value' => $producto['nombre_garante'],
			],
			[
				'label' => 'Cédula Garante',
				'value' => $producto['cedula_garante'],
			],
		];

		//DATA DE PAGOS
		$pagos_array = [];

		$retorno['campos'] = $campos;
		$retorno['pagos'] = $pagos_array;

		return $this->json($res->conDatos($retorno));
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
		$list = $this->request->getParam('list');
		$q = $this->request->getParam('q');
		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		if($list == 'nivel2') {
			$data = Paleta::getNivel2($q, $page, $data);
		} else {
			$data = [];
		}

		return $this->json($res->conDatos($data));
	}

	/**
	 * get_form_paleta
	 * @param $session
	 * @param $institucion_id
	 */
	function get_form_paleta() {
		if (!$this->isPost()) return "get_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$retorno = [];

		$retorno['form']['title'] = 'form';
		$retorno['form']['type'] = 'object';

		$paleta = Paleta::getNivel1();
		$nivel = [];
		foreach ($paleta as $p){
			$nivel[] = ['id' => $p['nivel1'], 'label' => $p['nivel1']];
		}

		$retorno['form']['properties']['Nivel1'] = [
			'type' => 'string',
			'title' => 'Nivel1',
			'widget' => 'choice',
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'nivel1',
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
			'title' => 'Nivel2',
			'widget' => 'picker-select2',
			'empty_data' => null,
			'full_name' => 'nivel2',
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
			'remote_path' => 'http://54.148.132.147/megacob/api/producto/buscar_listas',
			'remote_params' => [
				"list" => "nivel2"
			],
			'req_params' => [
				"nivel1" => "nivel1"
			],
		];
		$retorno['form']['properties']['Observaciones'] = [
			'type' => 'string',
			'title' => 'Observaciones',
			'widget' => 'textarea',
			'empty_data' => '',
			'full_name' => 'observaciones',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => [],
		];
		$retorno['form']['properties']['imagenes'] = [
			'type' => 'string',
			'title' => 'Imagen1',
			'widget' => 'file_widget',
			'empty_data' => '',
			'full_name' => 'imagenes',
			'constraints' => [],
			'mode' => 'IMAGEN',
			'multiple' => true,
			'required' => 0,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => [],
		];
		
		return $this->json($res->conDatos($retorno));
	}

	/**
	 * save_form_paleta
	 * @param $session
	 * @param $institucion_id
	 * @param $producto_id
	 * @param $data
	 * @param $_FILE
	 */
	function save_form_paleta() {
		if (!$this->isPost()) return "save_form_paleta";
		$res = new RespuestaConsulta();
		$institucion_id = $this->request->getParam('institucion_id');
		$producto_id = $this->request->getParam('producto_id');
		$data = $this->request->getParam('data');
		$files = $_FILES;
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$retorno = [];


		return $this->json($res->conMensaje('OK'));
	}
}
