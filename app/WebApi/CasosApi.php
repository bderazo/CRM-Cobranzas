<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use Models\Caso;
use Models\Especialidad;
use Models\Usuario;
use Models\UsuarioLogin;
use Models\UsuarioProducto;
use Negocio\EnvioNotificacionesPush;

/**
 * Class CasosApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de casos
 */
class CasosApi extends BaseController
{

	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_casos_abogado_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_casos_abogado_list()
	{
		if(!$this->isPost()) return "get_casos_abogado_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$casos = Caso::getCasoAbogadoList($query, $page, $user);
		return $this->json($res->conDatos($casos));
	}

	/**
	 * get_casos_cliente_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_casos_cliente_list()
	{
		if(!$this->isPost()) return "get_casos_cliente_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$casos = Caso::getCasoClienteList($query, $page, $user);
		return $this->json($res->conDatos($casos));
	}

	/**
	 * get_casos_detalle
	 * @param $caso_id
	 * @param $session
	 */
	function get_casos_detalle()
	{
		if(!$this->isPost()) return "get_casos_detalle";
		$res = new RespuestaConsulta();
		$caso_id = $this->request->getParam('caso_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$caso = Caso::getCasoDetalle($caso_id);
		return $this->json($res->conDatos($caso));
	}

	/**
	 * save_form_caso
	 * @param $session
	 * @param $caso_id
	 * @param $data
	 */
	function save_form_caso()
	{
		if(!$this->isPost()) return "save_form_caso";
		$res = new RespuestaConsulta();

		$caso_id = $this->request->getParam('caso_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		// limpieza
		$keys = array_keys($data);
		foreach($keys as $key) {
			$val = $data[$key];
			if(is_string($val))
				$val = trim($val);
			if($val === null)
				unset($data[$key]);
		}

		$es_nuevo = false;
		if($caso_id > 0) {
			$caso = Caso::porId($caso_id);
			$caso->fecha_modificacion = date("Y-m-d H:i:s");
			$caso->usuario_modificacion = $user['id'];
			$estado_anterior = $caso->estado;
		} else {
			$caso = new Caso();
			$caso->fecha_ingreso = date("Y-m-d H:i:s");
			$caso->usuario_ingreso = $user['id'];
			$caso->eliminado = 0;
			$es_nuevo = true;
		}

		//ASIGNAR CAMPOS
		$fields = Caso::getAllColumnsNames();
		foreach($data as $k => $v) {
			if(in_array($k, $fields)) {
				$caso->$k = $v;
			}
		}
		//CALCULAR EL VALOR DEL ANTICIPO
		$porcentaje_anticipo = $this->getPorcentajeAnticipo($caso->presupuesto);
		$anticipo = $caso->presupuesto * $porcentaje_anticipo / 100;
		$caso->anticipo = number_format($anticipo, 2, '.', '');

		if($caso->save()) {
			if($es_nuevo) {
				$envio_notificacion = EnvioNotificacionesPush::enviarCaso($caso->id, $caso->usuario_asignado);

				//CREAR CODIGO DEL CASO
				$caso_obj = Caso::porId($caso->id);
				$especialidad_obj = Especialidad::porId($caso_obj->especialidad_id);
				$especialidad_txt = substr(strtoupper($especialidad_obj->nombre), 0, 3);
				$secuencial = GeneralHelper::format_numero($caso_obj->id, 3);
				$caso_obj->codigo = 'C-' . $especialidad_txt . '-' . $secuencial . '-' . date("d") . '-' . date("m") . '-' . date("y");
				$caso_obj->save();
			} else {
				if($estado_anterior != $caso->estado) {
					$envio_notificacion = EnvioNotificacionesPush::enviarCambioEstadoCaso($caso->id);
				}
			}
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL MODIFICAR EL CASO'));
		}
	}

	/**
	 * get_form_aceptar_caso
	 * @param $caso_id
	 */
	function get_form_aceptar_caso()
	{
		if(!$this->isPost()) return "get_form_aceptar_caso";
		$res = new RespuestaConsulta();
		$caso = [];
		if(isset($_POST['caso_id'])) {
			$caso_id = $this->request->getParam('caso_id');
			if($caso_id > 0) {
				$caso = Caso::porId($caso_id);
			}
		}
		$form['form']['title'] = 'form';
		$form['form']['type'] = 'object';
		$form['form']['properties'] = $this->getFieldsAceptarCaso($caso);
		return $this->json($res->conDatos($form));
	}

	/**
	 * get_form_rechazar_caso
	 * @param $caso_id
	 */
	function get_form_rechazar_caso()
	{
		if(!$this->isPost()) return "get_form_rechazar_caso";
		$res = new RespuestaConsulta();
		$caso = [];
		if(isset($_POST['caso_id'])) {
			$caso_id = $this->request->getParam('caso_id');
			if($caso_id > 0) {
				$caso = Caso::porId($caso_id);
			}
		}
		$form['form']['title'] = 'form';
		$form['form']['type'] = 'object';
		$form['form']['properties'] = $this->getFieldsRechazarCaso($caso);
		return $this->json($res->conDatos($form));
	}

	/**
	 * get_form_caso
	 * @param $session
	 * @param $usuario_id
	 * @param $producto_id
	 */
	function get_form_caso()
	{
		if(!$this->isPost()) return "get_form_caso";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$usuario_id = $this->request->getParam('usuario_id');
		$producto_id = $this->request->getParam('producto_id');
		$usuario = [];
		if($usuario_id > 0) {
			$usuario = Usuario::porId($usuario_id);
		}
		$producto = [];
		if($producto_id > 0) {
			$producto = UsuarioProducto::porId($producto_id);
		}
		$form['form']['title'] = 'form';
		$form['form']['type'] = 'object';
		$form['form']['properties'] = $this->getFieldsCaso($usuario, $producto);
		return $this->json($res->conDatos($form));
	}

	/**
	 * get_productos_disponibles
	 * @param $session
	 * @param $q
	 * @param $page
	 * @param $data
	 */
	function get_productos_disponibles()
	{
		if(!$this->isPost()) return "get_productos_disponibles";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$q = $this->request->getParam('q');
		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$user = UsuarioLogin::getUserBySession($session);
		$usuario_producto = UsuarioProducto::getProductoFiltros($q, $page, $data);
		return $this->json($res->conResults($usuario_producto));
	}

	/**
	 * save_form_nuevo_caso
	 * @param $session
	 * @param $data
	 */
	function save_form_nuevo_caso()
	{
		if(!$this->isPost()) return "save_form_nuevo_caso";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$data = $this->request->getParam('data');
		$user = UsuarioLogin::getUserBySession($session);

		if($data['productos_disponibles'] > 0) {
			$producto = UsuarioProducto::porId($data['productos_disponibles']);
			$caso = new Caso();
			$caso->fecha_ingreso = date("Y-m-d H:i:s");
			$caso->usuario_ingreso = $user['id'];
			$caso->fecha_modificacion = date("Y-m-d H:i:s");
			$caso->usuario_modificacion = $user['id'];
			$caso->eliminado = 0;
			$caso->usuario_producto_id = $producto->id;
			$caso->estado = 'pendiente';
			$caso->detalle_cliente = $data['detalle_cliente'];
			$caso->especialidad_id = $data['especialidad_id'];
			$caso->ciudad = $data['ciudad'];
			$caso->calificaciones = $data['calificaciones'];
//			$caso->valor_referencia = $data['valor_referencia'];
			$caso->presupuesto = $producto->valor;
			//CALCULAR EL VALOR DEL ANTICIPO
			$porcentaje_anticipo = $this->getPorcentajeAnticipo($producto->valor);
			$anticipo = $producto->valor * $porcentaje_anticipo / 100;
			$caso->anticipo = number_format($anticipo, 2, '.', '');
			$caso->usuario_asignado = $producto->usuario_id;
			$caso->save();
			if($caso->save()) {
				$envio_notificacion = EnvioNotificacionesPush::enviarCaso($caso->id, $caso->usuario_asignado);

				//CREAR CODIGO DEL CASO
				$caso_obj = Caso::porId($caso->id);
				$especialidad_obj = Especialidad::porId($caso_obj->especialidad_id);
				$especialidad_txt = substr(strtoupper($especialidad_obj->nombre), 0, 3);
				$secuencial = GeneralHelper::format_numero($caso_obj->id, 3);
				$caso_obj->codigo = 'C-' . $especialidad_txt . '-' . $secuencial . '-' . date("d") . '-' . date("m") . '-' . date("y");
				$caso_obj->save();
				return $this->json($res->conMensaje('OK'));
			} else {
				return $this->json($res->conError('ERROR AL MODIFICAR EL CASO'));
			}
		} else {
			return $this->json($res->conError('PRODUCTO NO VÁLIDO'));
		}
	}

	function getCiudadesList()
	{
		$data = [
			[
				'id' => 'QUITO',
				'label' => 'QUITO'
			],
			[
				'id' => 'GUAYAQUIL',
				'label' => 'GUAYAQUIL'
			],
		];
		return $data;
	}

	function getCalificacionesList()
	{
		$data = [
			[
				'id' => 1,
				'label' => '1'
			],
			[
				'id' => 2,
				'label' => '2'
			],
			[
				'id' => 3,
				'label' => '3'
			],
			[
				'id' => 4,
				'label' => '4'
			],
			[
				'id' => 5,
				'label' => '5'
			],
		];
		return $data;
	}

	function getRangoValorList()
	{
		$data = [
			[
				'id' => '0 - 100',
				'label' => '0 - 100',
			],
			[
				'id' => '101 - 300',
				'label' => '101 - 300',
			],
			[
				'id' => '301 - 500',
				'label' => '301 - 500',
			],
			[
				'id' => '> 500',
				'label' => '> 500',
			],
		];
		return $data;
	}

	function getPorcentajeAnticipo($valor)
	{
		if($valor < 500) {
			$porcentaje = 60;
		} elseif(($valor >= 500) && ($valor < 1000)) {
			$porcentaje = 50;
		} elseif(($valor >= 1000) && ($valor < 5000)) {
			$porcentaje = 40;
		} else {
			$porcentaje = 30;
		}
		return $porcentaje;
	}

	function getFieldsAceptarCaso($caso)
	{
		$form['detalle_abogado'] = [
			'type' => 'string',
			'title' => 'Detalle',
			'widget' => 'multimedia_message_widget',
			'empty_data' => '',
			'full_name' => 'data[detalle_abogado]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'mode' => 'FILE',
			'voice_enabled' => false,
			'text_enabled' => true,
			'text_limit' => 1000,
			'voice_limit' => 0,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 1,
			'choices' => [],
		];


		$form['tiempo_estimado'] = [
			'type' => 'string',
			'title' => 'Tiempo estimado (días)',
			'widget' => 'text',
			'empty_data' => isset($caso['tiempo_estimado']) ? $caso['tiempo_estimado'] : '',
			'full_name' => 'data[tiempo_estimado]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				],
				[
					'name' => 'PositiveInteger',
					'message' => 'Este campo debe ser un número válido'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => [],
		];
		return $form;
	}

	function getFieldsRechazarCaso($caso)
	{
		$form['detalle_abogado'] = [
			'type' => 'string',
			'title' => 'Detalle',
			'widget' => 'multimedia_message_widget',
			'empty_data' => '',
			'full_name' => 'data[detalle_abogado]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'mode' => 'FILE',
			'voice_enabled' => false,
			'text_enabled' => true,
			'text_limit' => 1000,
			'voice_limit' => 0,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 1,
			'choices' => [],
		];
		return $form;
	}

	function getFieldsCaso($usuario, $producto)
	{
		$especialidad = Especialidad::getEspecialidades();
		$especialidad_data = [];
		foreach($especialidad as $c) {
			$aux['id'] = $c['id'];
			$aux['label'] = $c['nombre'];
			$especialidad_data[] = $aux;
		}
		if(isset($usuario['especialidad_id'])){
			$especialidad_arr = Especialidad::porId($usuario['especialidad_id']);
		}
		$form['especialidad_id'] = [
			'type' => 'string',
			'title' => 'Especialidad',
			'widget' => 'choice',
//			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'empty_data' => isset($usuario['especialidad_id']) ? ['id' => $usuario['especialidad_id'], 'label' => $especialidad_arr['nombre']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[especialidad_id]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 1,
			'choices' => $especialidad_data,
		];

		$form['ciudad'] = [
			'type' => 'string',
			'title' => 'Ciudad',
			'widget' => 'choice',
//			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'empty_data' => isset($usuario['ciudad']) ? ['id' => $usuario['ciudad'], 'label' => $usuario['ciudad']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[ciudad]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => $this->getCiudadesList(),
		];

		$form['calificaciones'] = [
			'type' => 'string',
			'title' => 'Calificaciones',
			'widget' => 'choice',
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[calificaciones]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => $this->getCalificacionesList(),
		];

//		$form['valor_referencia'] = [
//			'type' => 'string',
//			'title' => 'Valor máximo de referencia',
//			'widget' => 'text',
//			'empty_data' => '',
//			'full_name' => 'data[valor_referencia]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				],
//				[
//					'name' => 'Positive',
//					'message' => 'Este campo debe ser un número válido'
//				]
//			],
//			'required' => 1,
//			'disabled' => 0,
//			'property_order' => 4,
//			'choices' => [],
//		];

		$form['valor_referencia'] = [
			'type' => 'string',
			'title' => 'Valor máximo de referencia',
			'widget' => 'choice',
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[valor_referencia]',
			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				],
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => $this->getRangoValorList(),
		];

//		$form['abogados_disponibles'] = [
//			'type' => 'string',
//			'title' => 'Abogados disponibles',
//			'widget' => 'picker-select2',
//			'full_name' => 'data[abogados_disponibles]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
//			],
//			'remote_path' => 'solvit/api/usuario/get_abogados_disponibles',
//			'req_params' => [
//				'data[especialidad_id]' => 'data[especialidad_id]',
//				'data[ciudad]' => 'data[ciudad]',
//				'data[calificaciones]' => 'data[calificaciones]',
//			],
//			'minimum_input_length' => 0,
//			'required' => 1,
//			'disabled' => 0,
//			'property_order' => 5,
//		];

		$form['productos_disponibles'] = [
			'type' => 'string',
			'title' => 'Productos disponibles',
			'widget' => 'picker-select2',
			'empty_data' => isset($producto['id']) ? ['id' => $producto['id'], 'text' => $producto['nombre']] : ['id' => '', 'text' => ''],
			'full_name' => 'data[productos_disponibles]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'remote_path' => 'api/casos/get_productos_disponibles',
			'req_params' => [
				'data[especialidad_id]' => 'data[especialidad_id]',
				'data[ciudad]' => 'data[ciudad]',
				'data[calificaciones]' => 'data[calificaciones]',
//				'data[abogados_disponibles]' => 'data[abogados_disponibles]',
				'data[valor_referencia]' => 'data[valor_referencia]',
			],
			'minimum_input_length' => 0,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 6,
			'placeholder' => 'Selecceionar'
		];

		$form['detalle_cliente'] = [
			'type' => 'string',
			'title' => 'Detalle',
			'widget' => 'multimedia_message_widget',
			'empty_data' => '',
			'full_name' => 'data[detalle_cliente]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'mode' => 'FILE',
			'voice_enabled' => false,
			'text_enabled' => true,
			'text_limit' => 1000,
			'voice_limit' => 0,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];
		return $form;
	}
}
