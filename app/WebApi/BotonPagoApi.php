<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use Models\Archivo;
use Models\MembresiaPregunta;
use Models\Pregunta;
use Models\Respuesta;
use Models\UsuarioLogin;
use Negocio\EnvioNotificacionesPush;
use upload;
use Braintree;

include "app/WebApi/braintree_php-master/lib/Braintree.php";

/**
 * Class RespuestasApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de respuestas
 */
class BotonPagoApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_token
	 * @param $session
	 */
	function get_token()
	{
		if(!$this->isPost()) return "get_token";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		// Instantiate a Braintree Gateway either like this:
		$gateway = new Braintree\Gateway([
			'environment' => 'sandbox',
			'merchantId' => '9jdqrzkzs922w6zw',
			'publicKey' => 'rm2d7ktybs76c7pm',
			'privateKey' => 'ac22f4f1de924985e0257bad598b5c1e'
		]);
		$clientToken = $gateway->clientToken()->generate();
		return $this->json($res->conDatos($clientToken));
	}
}
