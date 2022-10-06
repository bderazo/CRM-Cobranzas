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
use Models\Producto;
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
	function get_preguntas_list()
	{
//		if(!$this->isPost()) return "get_preguntas_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$producto = Producto::getProductoList($query, $page, $user, $config);
		return $this->json($res->conDatos($producto));
	}
}
