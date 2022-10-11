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
			'type' => 'integer',
			'title' => 'Institución',
			'widget' => 'choice',
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[i.id]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
			'choices' => [
				[
					'id' => '1',
					'label' => 'DINERS'
				],
			],
		];

		$form['cedula'] = [
			'type' => 'string',
			'title' => 'Cédula',
			'widget' => 'text',
			'empty_data' => '',
			'full_name' => 'data[cedula]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 2,
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
}
