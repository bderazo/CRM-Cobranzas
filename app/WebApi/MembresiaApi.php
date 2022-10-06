<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use Models\Membresia;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Models\UsuarioSuscripcion;

/**
 * Class MembresiaApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de membresias
 */
class MembresiaApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_lista_membresias
	 * @param $session
	 */
	function get_lista_membresias()
	{
		if(!$this->isPost()) return "get_lista_membresias";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$membresia = Membresia::getMembresiasPago($user['id']);
		return $this->json($res->conDatos($membresia));
	}

	/**
	 * validar_codigo_promocional
	 * @param $session
	 * @param $membresia_id
	 * @param $codigo_promocional
	 */
	function validar_codigo_promocional()
	{
		if(!$this->isPost()) return "validar_codigo_promocional";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$membresia_id = $this->request->getParam('membresia_id');
		$codigo_promocional = $this->request->getParam('codigo_promocional');
		$user = UsuarioLogin::getUserBySession($session);
		$membresia = Membresia::getMembresiaConCodigoPromocional($membresia_id, $codigo_promocional, $user['id']);
		if($membresia) {
			return $this->json($res->conDatos($membresia));
		} else {
			return $this->json($res->conError('CÓDIGO INVÁLIDO'));
		}
	}

	/**
	 * save_compra_membresia
	 * @param $session
	 * @param $membresia_id
	 * @param $codigo_promocional_id
	 * @param $transaccion_paypal_id
	 * @param $valor
	 */
	function save_compra_membresia()
	{
		if(!$this->isPost()) return "save_compra_membresia";
		$res = new RespuestaConsulta();

		$membresia_id = $this->request->getParam('membresia_id');
		$codigo_promocional_id = $this->request->getParam('codigo_promocional_id');
		$transaccion_paypal_id = $this->request->getParam('transaccion_paypal_id');
		$valor = $this->request->getParam('valor');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		$membresia = Membresia::porId($membresia_id);
		$usuario_membresia = new UsuarioMembresia();
		$usuario_membresia->membresia_id = $membresia_id;
		$usuario_membresia->codigo_promocional_id = $codigo_promocional_id;
		$usuario_membresia->usuario_id = $user['id'];
		$usuario_membresia->fecha_inicio = date("Y-m-d");
		if($membresia->validez == 'anual') {
			$usuario_membresia->fecha_fin = date("Y-m-d", strtotime("$usuario_membresia->fecha_inicio +1 year"));
		} elseif($membresia->validez == 'mensual') {
			$usuario_membresia->fecha_fin = date("Y-m-d", strtotime("$usuario_membresia->fecha_inicio +1 month"));
		} else {
			$usuario_membresia->fecha_fin = $usuario_membresia->fecha_inicio;
		}
		$usuario_membresia->estado = 'disponible';
		$usuario_membresia->transaccion_paypal_id = $transaccion_paypal_id;
		$usuario_membresia->valor = $valor;
		$usuario_membresia->tipo_pregunta = $membresia->tipo_pregunta;
		if($membresia->tipo_pregunta == 'texto'){
			$usuario_membresia->numero_preguntas_texto = $membresia->numero_pregunta;
			$usuario_membresia->caracteres_preguntas_texto = $membresia->caracteres_pregunta;
		}elseif($membresia->tipo_pregunta == 'voz'){
			$usuario_membresia->numero_preguntas_voz = $membresia->numero_pregunta;
			$usuario_membresia->tiempo_preguntas_voz = $membresia->tiempo_pregunta;
		}
		$usuario_membresia->fecha_ingreso = date("Y-m-d H:i:s");
		$usuario_membresia->usuario_ingreso = $user['id'];
		$usuario_membresia->usuario_modificacion = $user['id'];
		$usuario_membresia->fecha_modificacion = date("Y-m-d H:i:s");
		$usuario_membresia->eliminado = 0;
		$suscripcion = UsuarioSuscripcion::getSuscripcionDisponible($user['id']);
		if(!is_null($suscripcion)){
			$usuario_membresia->suscripcion_id = $suscripcion['suscripcion_id'];
		}
		if($usuario_membresia->save()) {
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL COMPRAR SUSCRIPCIÓN'));
		}
	}

	/**
	 * caducar_membresia_vencida
	 */
	function caducar_membresia_vencida()
	{
		$res = new RespuestaConsulta();
		$caducar = UsuarioMembresia::caducarMembresiaVencida();
		\Auditor::info("Membresias caducadas por CRON JOB", 'Membresia', $caducar);
		return $this->json($res->conMensaje('OK'));
	}

	/**
	 * asignar_membresia_gratis
	 */
	function asignar_membresia_gratis()
	{
		$res = new RespuestaConsulta();
		$membresias = UsuarioMembresia::asignarMasivoMembresiasGratis();
		\Auditor::info("Membresias gratis creadas por CRON JOB", 'Membresia');
		return $this->json($res->conMensaje('OK'));
	}
}
