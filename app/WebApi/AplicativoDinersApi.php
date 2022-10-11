<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use General\Seguridad\PermisosSession;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\AplicativoDiners;
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
 * Class AplicativoDinersApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de AplicativoDiners
 */
class AplicativoDinersApi extends BaseController {
	var $test = false;

	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}

	/**
	 * campos_aplicativo_diners
	 * @param $cliente_id
	 * @param $session
	 */
	function campos_aplicativo_diners() {
		if(!$this->isPost()) return "campos_aplicativo_diners";
		$res = new RespuestaConsulta();
		$producto_id = $this->request->getParam('producto_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);

		//DATA APLICATIVO DINERS
		$aplicativo_diners = AplicativoDiners::getAplicativoDiners($producto_id);
		$campos = [
			[
				'label' => 'CIUDAD DE GESTIÓN',
				'value' => $aplicativo_diners['ciudad_gestion'],
			],
			[
				'label' => 'FECHA DE ELABORACIÓN',
				'value' => $aplicativo_diners['fecha_elaboracion'],
			],
			[
				'label' => 'NEGOCIADO POR',
				'value' => $aplicativo_diners['negociado_por'],
			],
			[
				'label' => 'NUMERO DE CEDULA SOCIO',
				'value' => $aplicativo_diners['cedula_socio'],
			],
			[
				'label' => 'NOMBRE DEL SOCIO',
				'value' => $aplicativo_diners['nombre_socio'],
			],
			[
				'label' => 'DIRECCIÓN',
				'value' => $aplicativo_diners['direccion'],
			],
			[
				'label' => 'NÚMEROS DE CONTACTO',
				'value' => $aplicativo_diners['numero_contactos'],
			],
			[
				'label' => 'MAIL DE CONTACTO',
				'value' => $aplicativo_diners['mail_contacto'],
			],
			[
				'label' => 'CIUDAD DE LA CUENTA',
				'value' => $aplicativo_diners['ciudad_cuenta'],
			],
			[
				'label' => 'ZONA DE LA CUENTA',
				'value' => $aplicativo_diners['zona_cuenta'],
			],
		];

		$retorno['campos'] = $campos;
		return $this->json($res->conDatos($retorno));
	}

}
