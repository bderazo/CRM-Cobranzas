<?php

namespace WebApi;

use ApiRemoto\RespuestaConsulta;
use Controllers\BaseController;
use General\GeneralHelper;
use Models\Archivo;
use Models\Especialidad;
use Models\Membresia;
use Models\Pregunta;
use Models\UsuarioLogin;
use Models\UsuarioMembresia;
use Negocio\EnvioNotificacionesPush;
use upload;

/**
 * Class PreguntasApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de preguntas
 */
class PreguntasApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_preguntas_abogado_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_preguntas_abogado_list()
	{
		if(!$this->isPost()) return "get_preguntas_abogado_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$preguntas = Pregunta::getPreguntaAbogadoList($query, $page, $user, $config);
		return $this->json($res->conDatos($preguntas));
	}

	/**
	 * get_preguntas_cliente_list
	 * @param $query
	 * @param $page
	 * @param $session
	 */
	function get_preguntas_cliente_list()
	{
		if(!$this->isPost()) return "get_preguntas_cliente_list";
		$res = new RespuestaConsulta();
		$query = $this->request->getParam('query');
		$page = $this->request->getParam('page');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$preguntas = Pregunta::getPreguntaClienteList($query, $page, $user, $config);
		return $this->json($res->conDatos($preguntas));
	}

	/**
	 * get_preguntas_detalle
	 * @param $pregunta_id
	 * @param $session
	 */
	function get_preguntas_detalle()
	{
		if(!$this->isPost()) return "get_preguntas_detalle";
		$res = new RespuestaConsulta();
		$pregunta_id = $this->request->getParam('pregunta_id');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$config = $this->get('config');
		$pregunta = Pregunta::getPreguntaDetalle($pregunta_id, $config);
		return $this->json($res->conDatos($pregunta));
	}

	/**
	 * save_form_pregunta
	 * @param $session
	 * @param $pregunta_id
	 * @param $data
	 */
	function save_form_pregunta()
	{
		if(!$this->isPost()) return "save_form_pregunta";
		$res = new RespuestaConsulta();

		$pregunta_id = $this->request->getParam('pregunta_id');
		$data = $this->request->getParam('data');
		$session = $this->request->getParam('session');
		$user = UsuarioLogin::getUserBySession($session);
		$files = $_FILES;

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
		$recibir_archivo = false;
		if($pregunta_id > 0) {
			$pregunta = Pregunta::porId($pregunta_id);
			$pregunta->fecha_modificacion = date("Y-m-d H:i:s");
			$pregunta->usuario_modificacion = $user['id'];
		} else {
			$pregunta = new Pregunta();
			$pregunta->fecha_ingreso = date("Y-m-d H:i:s");
			$pregunta->usuario_ingreso = $user['id'];
			$pregunta->usuario_modificacion = $user['id'];
			$pregunta->fecha_modificacion = date("Y-m-d H:i:s");
			$pregunta->eliminado = 0;
			$pregunta->estado = 'pendiente';
			$pregunta->fecha_pregunta = date("Y-m-d H:i:s");

			//PARA VER EL TIPO DE PREGUNTA
			if(isset($data['usuario_membresia_id'])) {
				$usuario_membresia = UsuarioMembresia::porId($data['usuario_membresia_id']);
				if($usuario_membresia->tipo_pregunta == 'texto') {
					$pregunta->tipo_pregunta = 'texto';
				} else {
					$pregunta->tipo_pregunta = 'voz';
					$pregunta->archivo_pregunta = 'pregunta_voz_' . date("Y_m_d_H_i_s") . '.wav';
					$recibir_archivo = true;
				}
			} else {
				return $this->json($res->conError('ERROR AL CREAR PREGUNTA'));
			}
			$es_nuevo = true;
		}
		//ASIGNAR CAMPOS
		$fields = Pregunta::getAllColumnsNames();
		foreach($data as $k => $v) {
			if(in_array($k, $fields)) {
				$pregunta->$k = $v;
			}
		}
		if($pregunta->save()) {
			if($es_nuevo) {
				$envio_notificacion = EnvioNotificacionesPush::enviarPregunta($pregunta->id);
				//CREAR CODIGO DE LA PREGUNTA
				$pregunta_obj = Pregunta::porId($pregunta->id);
				if($pregunta_obj->tipo_pregunta == 'texto'){
					$tipo_pregunta = 'CT';
				}else{
					$tipo_pregunta = 'CV';
				}
				$especialidad_obj = Especialidad::porId($pregunta_obj->especialidad_id);
				$especialidad_txt = substr(strtoupper($especialidad_obj->nombre), 0, 3);
				$secuencial = GeneralHelper::format_numero($pregunta_obj->id,3);
				$pregunta_obj->codigo = $tipo_pregunta.'-'.$especialidad_txt.'-'.$secuencial.'-'.date("d").'-'.date("m").'-'.date("y");
				$pregunta_obj->save();
			}
			if($recibir_archivo) {
				if(isset($files["data"])) {
					//ARREGLAR ARCHIVOS
					$archivo['name'] = $pregunta->archivo_pregunta;
					$archivo['type'] = $files["data"]["type"]["pregunta"];
					$archivo['tmp_name'] = $files["data"]["tmp_name"]["pregunta"];
					$archivo['error'] = $files["data"]["error"]["pregunta"];
					$archivo['size'] = $files["data"]["size"]["pregunta"];
					$this->uploadFiles($pregunta, $archivo);
				}
			}
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL MODIFICAR PREGUNTA'));
		}
	}

	/**
	 * aceptar_pregunta
	 * @param $session
	 * @param $pregunta_id
	 */
	function aceptar_pregunta()
	{
		if(!$this->isPost()) return "aceptar_pregunta";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$pregunta_id = $this->request->getParam('pregunta_id');
		$user = UsuarioLogin::getUserBySession($session);

		$aceptarPregunta = Pregunta::aceptarPregunta($pregunta_id, $user['id']);
		if($aceptarPregunta) {
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('LA PREGUNTA HA SIDO ACEPTADA POR OTRO USUARIO'));
		}
	}

	/**
	 * get_form_pregunta
	 * @param $session
	 * @param $usuario_membresia_id
	 */
	function get_form_pregunta()
	{
		if(!$this->isPost()) return "get_form_pregunta";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$usuario_membresia_id = $this->request->getParam('usuario_membresia_id');
		$user = UsuarioLogin::getUserBySession($session);
		$form['form']['title'] = 'form';
		$form['form']['type'] = 'object';
		$form['form']['properties'] = $this->getFieldsPregunta($usuario_membresia_id);
		return $this->json($res->conDatos($form));
	}

	/**
	 * reenviar_preguntas_no_contestadas
	 */
	function reenviar_preguntas_no_contestadas()
	{
		$res = new RespuestaConsulta();
		$config = $this->get('config');
		$numero_dias_pasado = 3;
		$preguntas = Pregunta::getPreguntasNoContestadas($numero_dias_pasado);
		foreach($preguntas as $p){
			$envio_notificacion = EnvioNotificacionesPush::enviarPregunta($p['id']);
		}
		\Auditor::info("Reenvio de preguntas no contestadas por CRON JOB", 'Preguntas', $preguntas);
		return $this->json($res->conMensaje('OK'));
	}

	function getFieldsPregunta($usuario_membresia_id)
	{
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
			'empty_data' => ['id' => '', 'label' => 'Seleccionar'],
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

		$usuario_membresia = UsuarioMembresia::porId($usuario_membresia_id);
		if($usuario_membresia->tipo_pregunta == 'texto') {
			$voice_enabled = 0;
			$text_enabled = 1;
			$text_limit = $usuario_membresia->caracteres_preguntas_texto;
			$voice_limit = 0;
		} else {
			$voice_enabled = 1;
			$text_enabled = 0;
			$text_limit = 0;
			$voice_limit = $usuario_membresia->tiempo_preguntas_voz;
		}
		$form['pregunta'] = [
			'type' => 'string',
			'title' => 'Pregunta',
			'widget' => 'multimedia_message_widget',
			'full_name' => 'data[pregunta]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'mode' => 'FILE',
			'voice_enabled' => $voice_enabled,
			'text_enabled' => $text_enabled,
			'text_limit' => $text_limit,
			'voice_limit' => $voice_limit,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 2,
		];

		return $form;
	}

	public function uploadFiles($pregunta, $archivo)
	{
		$config = $this->get('config');

		//INSERTAR EN BASE EL ARCHIVO
		$arch = new Archivo();
		$arch->parent_id = $pregunta->id;
		$arch->parent_type = 'pregunta';
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

		$dir = $config['folder_archivos_audio_pregunta'];
		if(!is_dir($dir)) {
			\Auditor::error("Error API Carga Archivo: El directorio $dir de imagenes no existe", 'PreguntasApi', []);
			return false;
		}
		$upload = new Upload($archivo);
		if(!$upload->uploaded) {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'PreguntasApi', []);
			return false;
		}
		// save uploaded image with no changes
		$upload->Process($config['folder_archivos_audio_pregunta']);
		if($upload->processed) {
			\Auditor::info("API Carga Archivo " . $archivo['name'] . " cargada", 'PreguntasApi');
			return true;
		} else {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'PreguntasApi', []);
			return false;
		}
	}
}
