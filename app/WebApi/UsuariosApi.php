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
	 * get_usuario_detalle
	 * @param $session
	 */
	function get_usuario_detalle()
	{
		if(!$this->isPost()) return "get_usuario_detalle";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

		$usuario_id = \WebSecurity::getUserData('id');
		$user = Usuario::porId($usuario_id);
		if(isset($user['id'])) {

			$config = $this->get('config');
			$usuario = Usuario::getUsuarioDetalle($user['id'], $config);


//		http_response_code(401);
//		die();

			return $this->json($res->conDatos($usuario));
		}else {
			http_response_code(401);
			die();
		}
	}

	/**
	 * save_form_usuario
	 * @param $session
	 * @param $data
	 * @param $_FILES
	 */
	function save_form_usuario()
	{
		if(!$this->isPost()) return "save_form_usuario";
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		$res = new RespuestaConsulta();

		$session = $this->request->getParam('session');
//		$user = UsuarioLogin::getUserBySession($session);

		$usuario_id = \WebSecurity::getUserData('id');
		$user = Usuario::porId($usuario_id);
		if(isset($user['id'])) {

			$data = $this->request->getParam('data');
			$files = $_FILES;

//		\Auditor::error("save_form_usuario API ", 'Files', $files);

			// limpieza
			$keys = array_keys($data);
			foreach($keys as $key) {
				$val = $data[$key];
				if(is_string($val))
					$val = trim($val);
				if($val === null)
					unset($data[$key]);
			}

			$usuario = Usuario::porId($user['id']);

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

				$config = $this->get('config');
				$usuario_detalle = Usuario::getUsuarioDetalle($usuario->id, $config);
				return $this->json($res->conDatos($usuario_detalle));
			} else {
				return $this->json($res->conError('ERROR AL MODIFICAR EL USUARIO'));
			}
		}else {
			http_response_code(401);
			die();
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
//		$user = UsuarioLogin::getUserBySession($session);
		$usuario_id = \WebSecurity::getUserData('id');
		$user = Usuario::porId($usuario_id);

		if(isset($user['id'])) {
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
		}else {
			http_response_code(401);
			die();
		}
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
			$sender->setSubject('MEGACOB Recuperar Contraseña');
			$sender->addAddress($user->email, $user->apellidos . ' ' . $user->nombres);
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
}
