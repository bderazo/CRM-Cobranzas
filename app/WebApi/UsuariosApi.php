<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\Seguridad\PermisosSession;
use General\Validacion\Utilidades;
use Models\Actividad;
use Models\ApiUserTokenPushNotifications;
use Models\Archivo;
use Models\Banco;
use Models\Caso;
use Models\Especialidad;
use Models\Pregunta;
use Models\Respuesta;
use Models\Suscripcion;
use Models\Usuario;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Models\UsuarioProducto;
use Models\UsuarioSuscripcion;
use Slim\Container;
use upload;
use Notificaciones\AbstractEmailSender;
use Notificaciones\EmailMessage;
use Notificaciones\TemplateNotificacion;

/**
 * Class UsuariosApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de usuario
 */
class UsuariosApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_form_usuario_abogado
	 * @param $usuario_id
	 */
	function get_form_usuario_abogado()
	{
		if(!$this->isPost()) return "get_form_usuario_abogado";
		$res = new RespuestaConsulta();
		$usuario = [];
		$usuario_id = $this->request->getParam('usuario_id');
		if($usuario_id > 0) {
			$usuario = Usuario::porId($usuario_id);
		}
		$form['tab1']['form']['title'] = 'form';
		$form['tab1']['form']['type'] = 'object';
		$form['tab1']['form']['properties'] = $this->getTab1FieldsAbogados($usuario);
		$form['tab2']['form']['title'] = 'form';
		$form['tab2']['form']['type'] = 'object';
		$form['tab2']['form']['properties'] = $this->getTab2FieldsAbogados($usuario);
		$form['tab3']['form']['title'] = 'form';
		$form['tab3']['form']['type'] = 'object';
		$form['tab3']['form']['properties'] = $this->getTab3FieldsAbogados($usuario);
		if($usuario_id > 0) {
			$usuario_productos = UsuarioProducto::getUsuarioProducto($usuario_id);
			$form['productos'] = $usuario_productos;
		} else {
			$form['productos'] = [];
		}
		return $this->json($res->conDatos($form));
	}

	/**
	 * get_form_usuario_cliente
	 * @param $usuario_id
	 */
	function get_form_usuario_cliente()
	{
		if(!$this->isPost()) return "get_form_usuario_cliente";
		$res = new RespuestaConsulta();
		$usuario = [];
		if(isset($_POST['usuario_id'])) {
			$usuario_id = $this->request->getParam('usuario_id');
			if($usuario_id > 0) {
				$usuario = Usuario::porId($usuario_id);
			}
		}
		$form['tab1']['form']['title'] = 'form';
		$form['tab1']['form']['type'] = 'object';
		$form['tab1']['form']['properties'] = $this->getTab1FieldsClientes($usuario);
		$form['tab2']['form']['title'] = 'form';
		$form['tab2']['form']['type'] = 'object';
		$form['tab2']['form']['properties'] = $this->getTab2FieldsClientes($usuario);
		return $this->json($res->conDatos($form));
	}

	/**
	 * save_form_usuario
	 * @param $usuario_id
	 * @param $usuario_tipo //para saber si es abogado o cliente
	 * @param $data
	 * @param $productos
	 * @param $_FILES
	 */
	function save_form_usuario()
	{
		if(!$this->isPost()) return "save_form_usuario";
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$res = new RespuestaConsulta();

		$usuario_id = $this->request->getParam('usuario_id');
		$usuario_tipo = $this->request->getParam('usuario_tipo');
		$data = $this->request->getParam('data');
		$productos = $this->request->getParam('productos');
		$files = $_FILES;

		$existe = ($usuario_id > 0) ? Usuario::query()->where('username', '=', @$data['username'])->where('id', '!=', $usuario_id)->first() : Usuario::query()->where('username', '=', @$data['username'])->first();
		if ($existe) {
			return $this->json($res->conError('EL CORREO INGRESADO YA PERTENECE A OTRO USUARIO'));
		}

		// limpieza
		$keys = array_keys($data);
		foreach($keys as $key) {
			$val = $data[$key];
			if(is_string($val))
				$val = trim($val);
			if($val === null)
				unset($data[$key]);
			if($data['fecha_nacimiento'] == '')
				unset($data['fecha_nacimiento']);
		}
		$es_nuevo = false;
		$asignar_membresia_gratis = false;
		if($usuario_id > 0) {
			$usuario = Usuario::porId($usuario_id);
		} else {
			$usuario = new Usuario();
			$usuario->fecha_creacion = date("Y-m-d H:i:s");
			if($usuario_tipo == 'cliente') {
				$usuario->estado = 'aprobado';
				$usuario->tipo = 'cliente';
				$asignar_membresia_gratis = true;
			} else {
				$usuario->estado = 'pendiente';
				$usuario->tipo = 'abogado';
			}
			$usuario->activo = 1;
			$es_nuevo = true;
		}

		//ASIGNAR CAMPOS
		$fields = Usuario::getAllColumnsNames();
		$change_password = false;
		foreach($data as $k => $v) {
			if(in_array($k, $fields)) {
				$usuario->$k = $v;
			}
			if($k == 'password') {
				$change_password = true;
			}
		}
		if($usuario->save($change_password)) {
			//INSERTAR ARCHIVO DE PERFIL
			if(isset($files["data"])) {
				//ARREGLAR ARCHIVOS
				$archivo['name'] = date("Y_m_d_H_i_s") . '_' . $files["data"]["name"]["images"];
				$archivo['type'] = $files["data"]["type"]["images"];
				$archivo['tmp_name'] = $files["data"]["tmp_name"]["images"];
				$archivo['error'] = $files["data"]["error"]["images"];
				$archivo['size'] = $files["data"]["size"]["images"];
				$this->uploadFiles($usuario, $archivo);
			}

			//GUARDAR PRODUCTOS DE ABOGADOS
			if(count($productos) > 0) {
				foreach($productos as $p) {
					if(isset($p['id'])) {
						$prod = UsuarioProducto::porId($p['id']);
					} else {
						$prod = new UsuarioProducto();
						$prod->usuario_id = $usuario->id;
						$prod->usuario_ingreso = $usuario->id;
						$prod->fecha_ingreso = date("Y-m-d H:i:s");
						$prod->eliminado = 0;
					}
					$prod->usuario_modificacion = $usuario->id;
					$prod->fecha_modificacion = date("Y-m-d H:i:s");
					$prod->nombre = $p['nombre'];
					$prod->descripcion = $p['descripcion'];
					$prod->valor = $p['valor'];
					$prod->save();
				}
			}

			//ASIGNAR ROL
			if($es_nuevo) {
				if($usuario->tipo == 'abogado') {
					$db->insertInto('usuario_perfil', ['usuario_id' => $usuario->id, 'perfil_id' => 1])->execute();
				} elseif($usuario->tipo == 'cliente') {
					$db->insertInto('usuario_perfil', ['usuario_id' => $usuario->id, 'perfil_id' => 2])->execute();
				}
			}

			//ASIGNAR MEMBRESIAS GRATIS A LOS NUEVOS USUARIOS DE TIPO CLIENTE
			if($asignar_membresia_gratis) {
				$save_membresia_gratis = UsuarioMembresia::asignarMembresiasGratis($usuario->id);
			}

			$config = $this->get('config');
			$usuario_detalle = Usuario::getUsuarioDetalle($usuario->id, $config);
			return $this->json($res->conDatos($usuario_detalle));
		} else {
			return $this->json($res->conError('ERROR AL INGRESAR EL USUARIO'));
		}
	}

	/**
	 * set_user_token_push_notifications
	 * @param $session
	 * @param $token
	 * @param $dispositive
	 */
	function set_user_token_push_notifications()
	{
		if(!$this->isPost()) return "set_user_token_push_notifications";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$token = $this->request->getParam('token');
		$dispositive = $this->request->getParam('dispositive');
		$user = UsuarioLogin::getUserBySession($session);
		$verificar = ApiUserTokenPushNotifications::verificarPorToken($token, $user['id']);
		if(!$verificar) {
			$del_token_anterior = ApiUserTokenPushNotifications::deleteTokenAnterior($user['id'], $dispositive);
			$user_token = new ApiUserTokenPushNotifications();
			$user_token->usuario_id = $user['id'];
			$user_token->token = $token;
			$user_token->dispositive = $dispositive;
			$user_token->fecha_ingreso = date("Y-m-d H:i:s");
			$user_token->fecha_modificacion = date("Y-m-d H:i:s");
			$user_token->usuario_ingreso = $user['id'];
			$user_token->usuario_modificacion = $user['id'];
			$user_token->eliminado = 0;
			$user_token->save();
		}
		return $this->json($res->conMensaje('OK'));
	}

	/**
	 * get_membresias_disponibles
	 * @param $session
	 */
	function get_membresias_disponibles()
	{
		if(!$this->isPost()) return "get_membresias_disponibles";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$usuario_membresia = UsuarioMembresia::getMembresiasDisponibles($user['id']);
		return $this->json($res->conResults($usuario_membresia));
	}

	/**
	 * get_usuario_detalle
	 * @param $session
	 */
	function get_usuario_detalle()
	{
		if(!$this->isPost()) return "get_usuario_detalle";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$usuario = Usuario::getUsuarioDetalle($user['id'], $config);
		//PARA OBTENER LA SUSCRIPCION DEL ABOGADO
		$usuario_suscripcion = UsuarioSuscripcion::getSuscripcionDisponible($user['id']);
		$usuario['suscripcion'] = $usuario_suscripcion;
		$usuario['descripcion'] = '';
		return $this->json($res->conDatos($usuario));
	}

	/**
	 * home_abogado
	 * @param $session
	 */
	function home_abogado()
	{
		if(!$this->isPost()) return "home_abogado";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$retorno = [];

		//PARA SABER SI UN ABOGADO TIENE CONSULTAS PENDIENTES POR RESPONDER
		$pregunta_pendiente = Pregunta::getPreguntaPendienteRespuesta($user['id']);
		$retorno['pregunta_pendiente_id'] = null;
		if(isset($pregunta_pendiente['id'])){
			$retorno['pregunta_pendiente_id'] = $pregunta_pendiente['id'];
		}

		//PARA OBTENER LA SUSCRIPCION DEL ABOGADO
		$usuario_suscripcion = UsuarioSuscripcion::getSuscripcionDisponible($user['id']);
		$retorno['suscripcion'] = $usuario_suscripcion;

		//DATOS PARA EL GRAFICO DE PREGUNTAS Y RESPUESTAS
		$preguntas_especialidad = Pregunta::contarPreguntasPorEspecialidad($user['especialidad_id']);
		$respuestas_usuario = Pregunta::contarRespuestasPorUsuario($user['id']);
		$porcentaje = $preguntas_especialidad > 0 ? $respuestas_usuario * 100 / $preguntas_especialidad : 0;
		$preguntas_respuestas = [
			'consultas_especialidad' => [
				'label' => $preguntas_especialidad.' Consultas texto/voz a tu especialidad',
				'cantidad' => $preguntas_especialidad,
				'cantidad_formated' => $preguntas_especialidad,
			],
			'respuestas_usuario' => [
				'label' => $respuestas_usuario. ' ('.number_format($porcentaje,2,'.','').'%) Respuestas texto/voz',
				'cantidad' => $respuestas_usuario,
				'cantidad_formated' => $respuestas_usuario,
			],
			'porcentaje' => [
				'cantidad' => $porcentaje,
				'cantidad_formated' => number_format($porcentaje,2,'.','').'%',
			]
		];
		$retorno['preguntas_respuestas'] = $preguntas_respuestas;

		//DATOS PARA EL GRAFICO DE CASOS
		$casos_requeridos = Caso::contarCasosRequeridos($user['id']);
		$casos_aceptados = Caso::contarCasosAceptados($user['id']);
		$porcentaje = $casos_requeridos > 0 ? $casos_aceptados * 100 / $casos_requeridos : 0;
		$casos = [
			'casos_requeridos' => [
				'label' => $casos_requeridos.' Casos requeridos',
				'cantidad' => $casos_requeridos,
				'cantidad_formated' => $casos_requeridos,
			],
			'casos_aceptados' => [
				'label' => $casos_aceptados.' ('.number_format($porcentaje,2,'.','').'%) Casos aceptados',
				'cantidad' => $casos_aceptados,
				'cantidad_formated' => $casos_aceptados,
			],
			'porcentaje' => [
				'cantidad' => $porcentaje,
				'cantidad_formated' => number_format($porcentaje,2,'.','').'%',
			]
		];
		$retorno['casos'] = $casos;

		$promedio = Respuesta::getPromedioCalificacionRespuesta($user['id']);
		$promedio_calificacion = [
				'cantidad' => $promedio,
				'cantidad_formated' => number_format($promedio,2,'.',','),
		];
		$retorno['promedio_calificacion'] = $promedio_calificacion;

		return $this->json($res->conDatos($retorno));
	}

	/**
	 * home_cliente
	 * @param $session
	 */
	function home_cliente()
	{
		if(!$this->isPost()) return "home_cliente";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$retorno = [];

		//PARA OBTENER LA SUSCRIPCION DEL CLIENTE
		$usuario_suscripcion = UsuarioSuscripcion::getSuscripcionDisponible($user['id']);
		$retorno['suscripcion'] = $usuario_suscripcion;

		//MEMBRESIAS CONTRATADAS
		$membresias_contratadas = UsuarioMembresia::getMembresiasContratadas($user['id']);

		$retorno['membresias_contratadas'] = $membresias_contratadas;

		$promedio = Pregunta::getPromedioCalificacionPregunta($user['id']);
		$promedio_calificacion = [
			'cantidad' => $promedio,
			'cantidad_formated' => number_format($promedio,2,'.',','),
		];
		$retorno['promedio_calificacion'] = $promedio_calificacion;

		return $this->json($res->conDatos($retorno));
	}

	/**
	 * recuperar_contrasena
	 * @param $username
	 */
	function recuperar_contrasena()
	{
		if(!$this->isPost()) return "recuperar_contrasena";
		$respuesta_api = new RespuestaConsulta();
		$username = $this->request->getParam('username');
		$user = Usuario::porUsername($username);
		if(isset($user->id)) {
			$usuario = Usuario::porId($user->id);
			$newPass = Utilidades::generateRandomString(5);
			$usuario->password = $newPass;
			$usuario->save(true);

			//SINCRONIZAR LA CONTRASENA CON LA BIBLIOTECA
			$url = $this->get('config')['url_biblioteca'];
			$entryArgs = [
				'email' => $this->get('config')['usuario_admin_biblioteca']['email'],
				'password' => $this->get('config')['usuario_admin_biblioteca']['password'],
			];
			$result = $this->restRequestBiblioteca($url . '/api/login', $entryArgs);
			$token = $result['data']['token'];
			$entryArgs = [
				'email' => $user->username,
				'new_password' => $newPass,
			];
			$result = $this->restRequestWithTokenBiblioteca($url . '/api/change-pass', $entryArgs, $token);

			//ENVIO LA CLAVE
			$tpl = new TemplateNotificacion($this->container);
			$config = $this->get('config')['configuracion_email'];
			$data = [
				'clave' => $newPass,
				'nombres' => $user->nombres,
				'username' => $user->username
			];
			$body = $tpl->getTemplateSys('password.twig', $data);
			$sender = $this->get('emailSender');
			$sender->init();
			$sender->setSubject('SOLVIT Recuperar Contraseña');
			$sender->addAddress($user->username, $user->apellidos . ' ' . $user->nombres);
			$sender->setBody($body);
			$sender->isHtml(true);
			$res = $sender->enviar();
//			printDie($res);
			\Auditor::info('Recuperar contrasena', 'Usuarios', []);
			return $this->json($respuesta_api->conMensaje('OK'));
		}else{
			return $this->json($respuesta_api->conError('USUARIO NO ENCONTRADO'));
		}
	}

	/**
	 * get_abogados_suscripcion
	 * @param $session
	 * @param $query
	 * @param $page
	 */
	function get_abogados_suscripcion()
	{
		if(!$this->isPost()) return "get_abogados_suscripcion";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$user = UsuarioLogin::getUserBySession($session);
		$abogados = Usuario::getAbogadosSuscripcion($query,$page);
		return $this->json($res->conDatos($abogados));
	}

	/**
	 * get_abogados_disponibles
	 * @param $q
	 * @param $page
	 * @param $data
	 * @param $session
	 */
	function get_abogados_disponibles()
	{
		if(!$this->isPost()) return "get_abogados_disponibles";
		$res = new RespuestaConsulta();
		$q = $this->request->getParam('q');
		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$abogados = Usuario::getAbogadosDisponibles($q, $page, $data);
		return $this->json($res->conResults($abogados));
	}

	/**
	 * get_ciudades
	 * @param $q
	 * @param $page
	 * @param $data
	 * @param $session
	 */
	function get_ciudades()
	{
		if(!$this->isPost()) return "get_ciudades";
		$res = new RespuestaConsulta();
		$q = $this->request->getParam('q');
		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		if($page == 0){
			$ciudades = $this->getCiudadesListSelect2();
		}else{
			$ciudades = [];
		}
		return $this->json($res->conResults($ciudades));
	}

	function get_especialidad()
	{
		$res = new RespuestaConsulta();
		$especialidad = new Especialidad();

		$especialidad_data = $especialidad->getEspecialidades();
		$retorno = [];
		foreach($especialidad_data as $c) {
			$aux['id'] = $c['id'];
			$aux['text'] = $c['nombre'];
			$retorno[] = $aux;
		}
		return $this->json($res->conDatos($retorno));
	}

	/**
	 * get_especialidad_select2
	 * @param $q
	 * @param $page
	 * @param $data
	 * @param $session
	 */
	function get_especialidad_select2()
	{
		$res = new RespuestaConsulta();
		$q = $this->request->getParam('q');
		$page = $this->request->getParam('page');
		$data = $this->request->getParam('data');
		$especialidad_data = Especialidad::getEspecialidades($q, $page);
		$retorno = [];
		foreach($especialidad_data as $c) {
			$aux['id'] = $c['id'];
			$aux['text'] = $c['nombre'];
			$retorno[] = $aux;
		}
		return $this->json($res->conResults($retorno));
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

	function getCiudadesListSelect2()
	{
		$data = [
			[
				'id' => 'QUITO',
				'text' => 'QUITO'
			],
			[
				'id' => 'GUAYAQUIL',
				'text' => 'GUAYAQUIL'
			],
		];
		return $data;
	}

	function getTipoCuentaBancoList()
	{
		$data = [
			[
				'id' => 'AHORROS',
				'label' => 'AHORROS'
			],
			[
				'id' => 'CORRIENTE',
				'label' => 'CORRIENTE'
			],
		];
		return $data;
	}

	function getTab1FieldsAbogados($usuario)
	{
		if(!isset($usuario['id'])) {
			$form['images'] = [
				'type' => 'string',
				'title' => 'Foto',
				'widget' => 'file_widget',
				'empty_data' => '',
				'full_name' => 'data[images]',
				'constraints' => [],
				'mode' => 'IMAGEN',
				'crop_imagen_mode' => 'OVAL',
				'required' => 0,
				'disabled' => 0,
				'property_order' => 1,
				'choices' => [],
			];
		}

		$form['titulo Correo electrónico'] = [
			'title' => 'Correo electrónico',
			'widget' => 'readonly',
			'full_name' => 'data[title_1]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['username'] = [
			'type' => 'string',
			'title' => 'Correo electrónico',
			'widget' => 'text',
			'empty_data' => isset($usuario['username']) ? $usuario['username'] : '',
			'full_name' => 'data[username]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => [],
		];

		$form['titulo Contraseña'] = [
			'title' => 'Contraseña',
			'widget' => 'readonly',
			'full_name' => 'data[title_3]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		if(!isset($usuario['id'])) {
			$constrain = [[
				'name' => 'NotBlank',
				'message' => 'Este campo no puede estar vacío'
			]];
			$required = 1;
			$help = '';
		}else{
			$constrain = [];
			$required = 0;
			$help = 'Para cambiar la contraseña, debe ingresar la nueva contraseña';
		}
		$form['password_1'] = [
			'type' => 'string',
			'title' => 'Contraseña',
			'widget' => 'password',
			'empty_data' => '',
			'full_name' => 'data[password]',
			'constraints' => $constrain,
			'required' => $required,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => [],
			'help' => $help,
		];

		$form['titulo Contraseña 2'] = [
			'title' => 'Verificar contraseña',
			'widget' => 'readonly',
			'full_name' => 'data[title_4]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];
		$form['password_2'] = [
			'type' => 'string',
			'title' => 'Verificar contraseña',
			'widget' => 'password',
			'empty_data' => '',
			'full_name' => 'data[password_2]',
			'constraints' => $constrain,
			'required' => $required,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => [],
			'help' => $help,
		];

		$form['titulo Apellidos'] = [
			'title' => 'Apellidos',
			'widget' => 'readonly',
			'full_name' => 'data[title_5]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['apellidos'] = [
			'type' => 'string',
			'title' => 'Apellidos',
			'widget' => 'text',
			'empty_data' => isset($usuario['apellidos']) ? $usuario['apellidos'] : '',
			'full_name' => 'data[apellidos]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 5,
			'choices' => [],
		];

		$form['titulo Nombres'] = [
			'title' => 'Nombres',
			'widget' => 'readonly',
			'full_name' => 'data[title_6]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['nombres'] = [
			'type' => 'string',
			'title' => 'Nombres',
			'widget' => 'text',
			'empty_data' => isset($usuario['nombres']) ? $usuario['nombres'] : '',
			'full_name' => 'data[nombres]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 6,
			'choices' => [],
		];

		$form['titulo Cédula'] = [
			'title' => 'Cédula',
			'widget' => 'readonly',
			'full_name' => 'data[title_7]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['cedula'] = [
			'type' => 'string',
			'title' => 'Cédula',
			'widget' => 'text',
			'empty_data' => isset($usuario['cedula']) ? $usuario['cedula'] : '',
			'full_name' => 'data[cedula]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];

		$form['titulo Teléfono'] = [
			'title' => 'Teléfono',
			'widget' => 'readonly',
			'full_name' => 'data[title_8]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['telefono'] = [
			'type' => 'string',
			'title' => 'Teléfono',
			'widget' => 'text',
			'empty_data' => isset($usuario['telefono']) ? $usuario['telefono'] : '',
			'full_name' => 'data[telefono]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				],
				[
					'name' => 'Positive',
					'message' => 'Este campo debe ser un número válido'
				],
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 8,
			'choices' => [],
		];

		$form['titulo Fecha de nacimiento (opcional)'] = [
			'title' => 'Fecha de nacimiento (opcional)',
			'widget' => 'readonly',
			'full_name' => 'data[title_9]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['fecha_nacimiento'] = [
			'type' => 'string',
			'title' => 'Fecha de nacimiento (opcional)',
			'widget' => 'date',
			'empty_data' => isset($usuario['fecha_nacimiento']) ? $usuario['fecha_nacimiento'] : null,
			'full_name' => 'data[fecha_nacimiento]',
			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 9,
			'choices' => [],
		];

		$form['titulo Ciudad'] = [
			'title' => 'Ciudad',
			'widget' => 'readonly',
			'full_name' => 'data[title_10]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['ciudad'] = [
			'type' => 'string',
			'title' => 'Ciudad',
			'widget' => 'choice',
			'empty_data' => isset($usuario['ciudad']) ? ['id' => $usuario['ciudad'], 'label' => $usuario['ciudad']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[ciudad]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 10,
			'choices' => $this->getCiudadesList(),
		];

		return $form;
	}

	function getTab2FieldsAbogados($usuario)
	{
		$form['titulo Título de tercer nivel'] = [
			'title' => 'Título de tercer nivel',
			'widget' => 'readonly',
			'full_name' => 'data[title_11]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['titulo_tercer_nivel'] = [
			'type' => 'string',
			'title' => 'Título de tercer nivel',
			'widget' => 'text',
			'empty_data' => isset($usuario['titulo_tercer_nivel']) ? $usuario['titulo_tercer_nivel'] : '',
			'full_name' => 'data[titulo_tercer_nivel]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 1,
			'choices' => [],
		];

//		$form['numero_registro_senescyt_tercer_nivel'] = [
//			'type' => 'string',
//			'title' => 'Número de registro de Senescyt',
//			'widget' => 'text',
//			'empty_data' => isset($usuario['numero_registro_senescyt_tercer_nivel']) ? $usuario['numero_registro_senescyt_tercer_nivel'] : '',
//			'full_name' => 'data[numero_registro_senescyt_tercer_nivel]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
//			],
//			'required' => 1,
//			'disabled' => 0,
//			'property_order' => 2,
//			'choices' => [],
//		];

//		$form['fecha_graduacion_tercer_nivel'] = [
//			'type' => 'string',
//			'title' => 'Fecha de graduación',
//			'widget' => 'date',
//			'empty_data' => isset($usuario['fecha_graduacion_tercer_nivel']) ? $usuario['fecha_graduacion_tercer_nivel'] : '',
//			'full_name' => 'data[fecha_graduacion_tercer_nivel]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
//			],
//			'required' => 1,
//			'disabled' => 0,
//			'property_order' => 3,
//			'choices' => [],
//		];

		$form['titulo Título de cuarto nivel'] = [
			'title' => 'Título de cuarto nivel',
			'widget' => 'readonly',
			'full_name' => 'data[title_12]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['titulo_cuarto_nivel'] = [
			'type' => 'string',
			'title' => 'Título de cuarto nivel',
			'widget' => 'text',
			'empty_data' => isset($usuario['titulo_cuarto_nivel']) ? $usuario['titulo_cuarto_nivel'] : '',
			'full_name' => 'data[titulo_cuarto_nivel]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => [],
		];

//		$form['numero_registro_senescyt_cuarto_nivel'] = [
//			'type' => 'string',
//			'title' => 'Número de registro de Senescyt',
//			'widget' => 'text',
//			'empty_data' => isset($usuario['numero_registro_senescyt_cuarto_nivel']) ? $usuario['numero_registro_senescyt_cuarto_nivel'] : '',
//			'full_name' => 'data[numero_registro_senescyt_cuarto_nivel]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
//			],
//			'required' => 0,
//			'disabled' => 0,
//			'property_order' => 5,
//			'choices' => [],
//		];

//		$form['fecha_graduacion_cuarto_nivel'] = [
//			'type' => 'string',
//			'title' => 'Fecha de graduación',
//			'widget' => 'date',
//			'empty_data' => isset($usuario['fecha_graduacion_cuarto_nivel']) ? $usuario['fecha_graduacion_cuarto_nivel'] : '',
//			'full_name' => 'data[fecha_graduacion_cuarto_nivel]',
//			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
//			],
//			'required' => 0,
//			'disabled' => 0,
//			'property_order' => 6,
//			'choices' => [],
//		];

		$form['titulo Estudios adicionales'] = [
			'title' => 'Estudios adicionales',
			'widget' => 'readonly',
			'full_name' => 'data[title_13]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['estudios_adicionales'] = [
			'type' => 'string',
			'title' => 'Estudios adicionales',
			'widget' => 'textarea',
			'empty_data' => isset($usuario['estudios_adicionales']) ? $usuario['estudios_adicionales'] : '',
			'full_name' => 'data[estudios_adicionales]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];

		$form['titulo Especialidad'] = [
			'title' => 'Especialidad',
			'widget' => 'readonly',
			'full_name' => 'data[title_14]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$especialidad = new Especialidad();
		$especialidad_data = [];
		foreach($especialidad->getEspecialidades() as $c) {
			$aux['id'] = $c['id'];
			$aux['label'] = $c['nombre'];
			$especialidad_data[] = $aux;
		}
		$form['especialidad_id'] = [
			'type' => 'string',
			'title' => 'Especialidad',
			'widget' => 'choice',
			'empty_data' => isset($usuario['especialidad_id']) ? ['id' => $usuario['especialidad_id'], 'label' => $usuario['especialidad_id']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[especialidad_id]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 8,
			'choices' => $especialidad_data,
		];

		$form['titulo Actividad destacada'] = [
			'title' => 'Actividad destacada',
			'widget' => 'readonly',
			'full_name' => 'data[title_15]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$actividad = new Actividad();
		$actividad_data = [];
		foreach($actividad->getActividades() as $c) {
			$aux['id'] = $c['id'];
			$aux['label'] = $c['nombre'];
			$actividad_data[] = $aux;
		}
		$form['actividad_id'] = [
			'type' => 'string',
			'title' => 'Actividad destacada',
			'widget' => 'choice',
			'empty_data' => isset($usuario['actividad_id']) ? ['id' => $usuario['actividad_id'], 'label' => $usuario['actividad_id']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[actividad_id]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 9,
			'choices' => $actividad_data,
		];

		$form['titulo Tarifa referencial por hora'] = [
			'title' => 'Tarifa referencial por hora',
			'widget' => 'readonly',
			'full_name' => 'data[title_16]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['tarifa_referencial_hora'] = [
			'type' => 'string',
			'title' => 'Tarifa referencial por hora',
			'widget' => 'text',
			'empty_data' => isset($usuario['tarifa_referencial_hora']) ? $usuario['tarifa_referencial_hora'] : '',
			'full_name' => 'data[tarifa_referencial_hora]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				],
				[
					'name' => 'Positive',
					'message' => 'Este campo debe ser un número válido'
				],
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 10,
			'choices' => [],
		];
		return $form;
	}

	function getTab3FieldsAbogados($usuario)
	{
		$form['title_1'] = [
			'title' => 'Dirección para correspondencia',
			'widget' => 'readonly',
			'full_name' => 'data[title_1]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['titulo Dirección'] = [
			'title' => 'Dirección',
			'widget' => 'readonly',
			'full_name' => 'data[title_18]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['direccion_correspondencia'] = [
			'type' => 'string',
			'title' => 'Dirección',
			'widget' => 'textarea',
			'empty_data' => isset($usuario['direccion_correspondencia']) ? $usuario['direccion_correspondencia'] : '',
			'full_name' => 'data[direccion_correspondencia]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => [],
			'help' => 'Dirección donde SOLVIT enviará correspondencia',
		];

		$form['titulo Ciudad'] = [
			'title' => 'Ciudad',
			'widget' => 'readonly',
			'full_name' => 'data[title_19]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['ciudad_correspondencia'] = [
			'type' => 'string',
			'title' => 'Ciudad',
			'widget' => 'text',
			'empty_data' => isset($usuario['ciudad_correspondencia']) ? $usuario['ciudad_correspondencia'] : '',
			'full_name' => 'data[ciudad_correspondencia]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => [],
		];

		$form['title_2'] = [
			'title' => 'Datos Laborales Actuales',
			'widget' => 'readonly',
			'full_name' => 'data[title_2]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 4,
		];

		$form['titulo Empresa'] = [
			'title' => 'Empresa',
			'widget' => 'readonly',
			'full_name' => 'data[title_20]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['empresa'] = [
			'type' => 'string',
			'title' => 'Empresa',
			'widget' => 'text',
			'empty_data' => isset($usuario['empresa']) ? $usuario['empresa'] : '',
			'full_name' => 'data[empresa]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 5,
			'choices' => [],
		];

		$form['titulo Cargo'] = [
			'title' => 'Cargo',
			'widget' => 'readonly',
			'full_name' => 'data[title_21]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['cargo'] = [
			'type' => 'string',
			'title' => 'Cargo',
			'widget' => 'text',
			'empty_data' => isset($usuario['cargo']) ? $usuario['cargo'] : '',
			'full_name' => 'data[cargo]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 6,
			'choices' => [],
		];

		$form['titulo Tiempo en el cargo (años)'] = [
			'title' => 'Tiempo en el cargo (años)',
			'widget' => 'readonly',
			'full_name' => 'data[title_22]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['tiempo_cargo_anios'] = [
			'type' => 'string',
			'title' => 'Tiempo en el cargo (años)',
			'widget' => 'text',
			'empty_data' => isset($usuario['tiempo_cargo_anios']) ? $usuario['tiempo_cargo_anios'] : '',
			'full_name' => 'data[tiempo_cargo_anios]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];

		$form['title_3'] = [
			'title' => 'Datos Bancarios',
			'widget' => 'readonly',
			'full_name' => 'data[title_3]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 8,
		];

		$form['titulo Banco'] = [
			'title' => 'Banco',
			'widget' => 'readonly',
			'full_name' => 'data[title_23]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$banco = new Banco();
		$banco_data = [];
		foreach($banco->getBancos() as $c) {
			$aux['id'] = $c['id'];
			$aux['label'] = $c['nombre'];
			$banco_data[] = $aux;
		}
		$form['banco_id'] = [
			'type' => 'string',
			'title' => 'Banco',
			'widget' => 'choice',
			'empty_data' => isset($usuario['banco_id']) ? ['id' => $usuario['banco_id'], 'label' => $usuario['banco_id']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[banco_id]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 9,
			'choices' => $banco_data,
		];

		$form['titulo Tipo de cuenta'] = [
			'title' => 'Tipo de cuenta',
			'widget' => 'readonly',
			'full_name' => 'data[title_24]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['tipo_cuenta_banco'] = [
			'type' => 'string',
			'title' => 'Tipo de cuenta',
			'widget' => 'choice',
			'empty_data' => isset($usuario['tipo_cuenta_banco']) ? ['id' => $usuario['tipo_cuenta_banco'], 'label' => $usuario['tipo_cuenta_banco']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[tipo_cuenta_banco]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 10,
			'choices' => $this->getTipoCuentaBancoList(),
		];

		$form['titulo Número de cuenta'] = [
			'title' => 'Número de cuenta',
			'widget' => 'readonly',
			'full_name' => 'data[title_25]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['numero_cuenta_banco'] = [
			'type' => 'string',
			'title' => 'Número de cuenta',
			'widget' => 'text',
			'empty_data' => isset($usuario['numero_cuenta_banco']) ? $usuario['numero_cuenta_banco'] : '',
			'full_name' => 'data[numero_cuenta_banco]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 11,
			'choices' => [],
			'help' => 'Cuenta donde recibirá el usuario los pagos de SOLVIT',
		];

		$form['title_4'] = [
			'title' => 'Estado',
			'widget' => 'readonly',
			'full_name' => 'data[title_4]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 12,
		];

		$estado_usuario = isset($usuario['estado']) ? strtoupper($usuario['estado']) : 'PENDIENTE';
		$form['estado'] = [
			'type' => 'string',
			'title' => 'Estado',
			'widget' => 'text',
			'empty_data' => $estado_usuario,
			'full_name' => 'data[estado]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 1,
			'property_order' => 13,
			'choices' => [],
		];
		if($estado_usuario == 'PENDIENTE'){
			$form['estado']['help'] = 'Su solicitud de usuario estará pendiente hasta que SOLVIT verifique su identidad, este proceso puede tardar hasta 72 horas. Cuando se verifique su identidad recibirá un correo de confirmación de que su cuenta está activa.';
		}

		return $form;
	}

	public function uploadFiles($usuario, $archivo)
	{
		$config = $this->get('config');

		//INSERTAR EN BASE EL ARCHIVO
		$del_img_anteriores = Archivo::delImagenPerfil($usuario->id);
		$arch = new Archivo();
		$arch->parent_id = $usuario->id;
		$arch->parent_type = 'usuario';
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

		$dir = $config['folder_images_usuario'];
		if(!is_dir($dir)) {
			\Auditor::error("Error API Carga Archivo: El directorio $dir de imagenes no existe", 'UsuariosApi', []);
			return false;
		}
		$upload = new Upload($archivo);
		if(!$upload->uploaded) {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'UsuariosApi', []);
			return false;
		}
		// save uploaded image with no changes
		$upload->Process($dir);
		if($upload->processed) {
			\Auditor::info("API Carga Archivo " . $archivo['name'] . " cargada", 'UsuariosApi');
			return true;
		} else {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'UsuariosApi', []);
			return false;
		}
	}

	function getTab1FieldsClientes($usuario)
	{
		if(!isset($usuario['id'])) {
			$form['images'] = [
				'type' => 'string',
				'title' => 'Foto',
				'widget' => 'file_widget',
				'empty_data' => '',
				'full_name' => 'data[images]',
				'constraints' => [],
				'mode' => 'IMAGEN',
				'crop_imagen_mode' => 'OVAL',
				'required' => 0,
				'disabled' => 0,
				'property_order' => 1,
				'choices' => [],
			];
		}

		$form['titulo Correo electrónico'] = [
			'title' => 'Correo electrónico',
			'widget' => 'readonly',
			'full_name' => 'data[title_1]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['username'] = [
			'type' => 'string',
			'title' => 'Correo electrónico',
			'widget' => 'text',
			'empty_data' => isset($usuario['username']) ? $usuario['username'] : '',
			'full_name' => 'data[username]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => [],
		];

		$form['titulo Contraseña'] = [
			'title' => 'Contraseña',
			'widget' => 'readonly',
			'full_name' => 'data[title_2]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		if(!isset($usuario['id'])) {
			$constrain = [[
				'name' => 'NotBlank',
				'message' => 'Este campo no puede estar vacío'
			]];
			$required = 1;
			$help = '';
		}else{
			$constrain = [];
			$required = 0;
			$help = 'Para cambiar la contraseña, debe ingresar la nueva contraseña';
		}
		$form['password'] = [
			'type' => 'string',
			'title' => 'Contraseña',
			'widget' => 'password',
			'empty_data' => '',
			'full_name' => 'data[password]',
			'constraints' => $constrain,
			'required' => $required,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => [],
			'help' => $help,
		];

		$form['titulo Verificar contraseña'] = [
			'title' => 'Verificar contraseña',
			'widget' => 'readonly',
			'full_name' => 'data[title_2]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['password_2'] = [
			'type' => 'string',
			'title' => 'Verificar contraseña',
			'widget' => 'password',
			'empty_data' => '',
			'full_name' => 'data[password_2]',
			'constraints' => $constrain,
			'required' => $required,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => [],
			'help' => $help,
		];

		$form['titulo Apellidos'] = [
			'title' => 'Apellidos',
			'widget' => 'readonly',
			'full_name' => 'data[title_3]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['apellidos'] = [
			'type' => 'string',
			'title' => 'Apellidos',
			'widget' => 'text',
			'empty_data' => isset($usuario['apellidos']) ? $usuario['apellidos'] : '',
			'full_name' => 'data[apellidos]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 5,
			'choices' => [],
		];

		$form['titulo Nombres'] = [
			'title' => 'Nombres',
			'widget' => 'readonly',
			'full_name' => 'data[title_4]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['nombres'] = [
			'type' => 'string',
			'title' => 'Nombres',
			'widget' => 'text',
			'empty_data' => isset($usuario['nombres']) ? $usuario['nombres'] : '',
			'full_name' => 'data[nombres]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 6,
			'choices' => [],
		];

		$form['titulo Cédula'] = [
			'title' => 'Cédula',
			'widget' => 'readonly',
			'full_name' => 'data[title_5]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['cedula'] = [
			'type' => 'string',
			'title' => 'Cédula',
			'widget' => 'text',
			'empty_data' => isset($usuario['cedula']) ? $usuario['cedula'] : '',
			'full_name' => 'data[cedula]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];

		$form['titulo Teléfono'] = [
			'title' => 'Teléfono',
			'widget' => 'readonly',
			'full_name' => 'data[title_6]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['telefono'] = [
			'type' => 'string',
			'title' => 'Teléfono',
			'widget' => 'text',
			'empty_data' => isset($usuario['telefono']) ? $usuario['telefono'] : '',
			'full_name' => 'data[telefono]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				],
				[
					'name' => 'Positive',
					'message' => 'Este campo debe ser un número válido'
				],
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 8,
			'choices' => [],
		];

		$form['titulo Fecha de nacimiento (opcional)'] = [
			'title' => 'Fecha de nacimiento (opcional)',
			'widget' => 'readonly',
			'full_name' => 'data[title_6]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['fecha_nacimiento'] = [
			'type' => 'string',
			'title' => 'Fecha de nacimiento (opcional)',
			'widget' => 'date',
			'empty_data' => isset($usuario['fecha_nacimiento']) ? $usuario['fecha_nacimiento'] : '',
			'full_name' => 'data[fecha_nacimiento]',
			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 8,
			'choices' => [],
		];

		$form['titulo Ciudad'] = [
			'title' => 'Ciudad',
			'widget' => 'readonly',
			'full_name' => 'data[title_7]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['ciudad'] = [
			'type' => 'string',
			'title' => 'Ciudad',
			'widget' => 'choice',
			'empty_data' => isset($usuario['ciudad']) ? ['id' => $usuario['ciudad'], 'label' => $usuario['ciudad']] : ['id' => '', 'label' => 'Seleccionar'],
			'full_name' => 'data[ciudad]',
			'constraints' => [
//				[
//					'name' => 'NotBlank',
//					'message' => 'Este campo no puede estar vacío'
//				]
			],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 9,
			'choices' => $this->getCiudadesList(),
		];
		return $form;
	}

	function getTab2FieldsClientes($usuario)
	{
		$form['title_1'] = [
			'title' => 'Datos de Facturación',
			'widget' => 'readonly',
			'full_name' => 'data[title_1]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['titulo Apellidos Fact'] = [
			'title' => 'Apellidos',
			'widget' => 'readonly',
			'full_name' => 'data[title_8]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['apellidos_facturacion'] = [
			'type' => 'string',
			'title' => 'Apellidos',
			'widget' => 'text',
			'empty_data' => isset($usuario['apellidos_facturacion']) ? $usuario['apellidos_facturacion'] : '',
			'full_name' => 'data[apellidos_facturacion]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
			'choices' => [],
		];

		$form['titulo Nombres Fact'] = [
			'title' => 'Nombres',
			'widget' => 'readonly',
			'full_name' => 'data[title_9]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['nombres_facturacion'] = [
			'type' => 'string',
			'title' => 'Nombres',
			'widget' => 'text',
			'empty_data' => isset($usuario['nombres_facturacion']) ? $usuario['nombres_facturacion'] : '',
			'full_name' => 'data[nombres_facturacion]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'required' => 1,
			'disabled' => 0,
			'property_order' => 3,
			'choices' => [],
		];

		$form['titulo Cédula / RUC Fact'] = [
			'title' => 'Cédula / RUC',
			'widget' => 'readonly',
			'full_name' => 'data[title_10]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['cedula_facturacion'] = [
			'type' => 'string',
			'title' => 'Cédula / RUC',
			'widget' => 'text',
			'empty_data' => isset($usuario['cedula_facturacion']) ? $usuario['cedula_facturacion'] : '',
			'full_name' => 'data[cedula_facturacion]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 4,
			'choices' => [],
		];

		$form['titulo Correo electrónico Fact'] = [
			'title' => 'Correo electrónico',
			'widget' => 'readonly',
			'full_name' => 'data[title_11]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['mail_facturacion'] = [
			'type' => 'string',
			'title' => 'Correo electrónico',
			'widget' => 'text',
			'empty_data' => isset($usuario['mail_facturacion']) ? $usuario['mail_facturacion'] : '',
			'full_name' => 'data[mail_facturacion]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 5,
			'choices' => [],
		];

		$form['titulo Teléfono Fact'] = [
			'title' => 'Teléfono',
			'widget' => 'readonly',
			'full_name' => 'data[title_12]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['telefono_facturacion'] = [
			'type' => 'string',
			'title' => 'Teléfono',
			'widget' => 'text',
			'empty_data' => isset($usuario['telefono_facturacion']) ? $usuario['telefono_facturacion'] : '',
			'full_name' => 'data[telefono_facturacion]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 6,
			'choices' => [],
		];

		$form['titulo Dirección Fact'] = [
			'title' => 'Dirección',
			'widget' => 'readonly',
			'full_name' => 'data[title_13]',
			'constraints' => [],
			'type_content' => 'title',
			'required' => 0,
			'disabled' => 0,
			'property_order' => 1,
		];

		$form['direccion_facturacion'] = [
			'type' => 'string',
			'title' => 'Dirección',
			'widget' => 'text',
			'empty_data' => isset($usuario['direccion_facturacion']) ? $usuario['direccion_facturacion'] : '',
			'full_name' => 'data[direccion_facturacion]',
			'constraints' => [],
			'required' => 0,
			'disabled' => 0,
			'property_order' => 7,
			'choices' => [],
		];
		return $form;
	}

	function restRequestBiblioteca($url, $entryArgs)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$headers = [
			'Content-Type: application/json',
		];
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($entryArgs));
		$result = curl_exec($curl);
		curl_close($curl);
		return json_decode($result, 1);
	}

	function restRequestWithTokenBiblioteca($url, $entryArgs, $token)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token
		];
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($entryArgs));
		$result = curl_exec($curl);
		curl_close($curl);
		return json_decode($result, 1);
	}
}
