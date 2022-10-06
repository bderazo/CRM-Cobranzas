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
use Models\Especialidad;
use Models\Membresia;
use Models\Pregunta;
use Models\Suscripcion;
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
			'widget' => 'choice',
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[institucion]',
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
	 * validar_codigo_promocional
	 * @param $session
	 * @param $suscripcion_id
	 * @param $codigo_promocional
	 */
	function validar_codigo_promocional() {
		if (!$this->isPost()) return "validar_codigo_promocional";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$suscripcion_id = $this->request->getParam('suscripcion_id');
		$codigo_promocional = $this->request->getParam('codigo_promocional');
		$user = UsuarioLogin::getUserBySession($session);
		$suscripcion = Suscripcion::getSuscripcionConCodigoPromocional($suscripcion_id, $codigo_promocional);
		if ($suscripcion) {
			return $this->json($res->conDatos($suscripcion));
		} else {
			return $this->json($res->conError('CÓDIGO INVÁLIDO'));
		}

	}

	/**
	 * save_compra_suscripcion
	 * @param $session
	 * @param $suscripcion_id
	 * @param $codigo_promocional_id
	 * @param $transaccion_paypal_id
	 * @param $valor
	 */
	function save_compra_suscripcion() {
		if (!$this->isPost()) return "save_form_pregunta";
		$res = new RespuestaConsulta();

		$suscripcion_id = $this->request->getParam('suscripcion_id');
		$codigo_promocional_id = $this->request->getParam('codigo_promocional_id');
		$transaccion_paypal_id = $this->request->getParam('transaccion_paypal_id');
		$valor = $this->request->getParam('valor');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$verificar_suscripcion = UsuarioSuscripcion::getSuscripcionDisponible($user['id']);
		if (count($verificar_suscripcion) == 0) {
			$suscripcion = Suscripcion::porId($suscripcion_id);
			$usuario_suscripcion = new UsuarioSuscripcion();
			$usuario_suscripcion->suscripcion_id = $suscripcion_id;
			$usuario_suscripcion->codigo_promocional_id = $codigo_promocional_id;
			$usuario_suscripcion->usuario_id = $user['id'];
			$usuario_suscripcion->fecha_inicio = date("Y-m-d");
			if ($suscripcion->validez == 'anual') {
				$usuario_suscripcion->fecha_fin = date("Y-m-d", strtotime("$usuario_suscripcion->fecha_inicio +1 year"));
			} elseif ($suscripcion->validez == 'mensual') {
				$usuario_suscripcion->fecha_fin = date("Y-m-d", strtotime("$usuario_suscripcion->fecha_inicio +1 month"));
			} else {
				$usuario_suscripcion->fecha_fin = $usuario_suscripcion->fecha_inicio;
			}
			$usuario_suscripcion->estado = 'disponible';
			$usuario_suscripcion->transaccion_paypal_id = $transaccion_paypal_id;
			$usuario_suscripcion->valor = $valor;
			$usuario_suscripcion->fecha_ingreso = date("Y-m-d H:i:s");
			$usuario_suscripcion->usuario_ingreso = $user['id'];
			$usuario_suscripcion->usuario_modificacion = $user['id'];
			$usuario_suscripcion->fecha_modificacion = date("Y-m-d H:i:s");
			$usuario_suscripcion->eliminado = 0;
			if ($usuario_suscripcion->save()) {
				if ($user['tipo'] == 'cliente') {
					$save_membresia_gratis = UsuarioMembresia::asignarMembresiasGratis($user['id']);
				}
				return $this->json($res->conMensaje('OK'));
			} else {
				return $this->json($res->conError('ERROR AL COMPRAR SUSCRIPCIÓN'));
			}
		} else {
			return $this->json($res->conError('EL USUARIO YA TIENE UNA SUSCRIPCIÓN ACTIVA'));
		}
	}

	/**
	 * caducar_suscripcion_vencida
	 */
	function caducar_suscripcion_vencida() {
		$res = new RespuestaConsulta();
		$caducar = UsuarioSuscripcion::caducarSuscripcionVencida();
		\Auditor::info("Suscripciones caducadas por CRON JOB", 'Suscripcion', $caducar);
		return $this->json($res->conMensaje('OK'));
	}
}
