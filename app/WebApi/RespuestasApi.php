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

/**
 * Class RespuestasApi
 * @package Controllers\api
 * Aqui se ejecuta la logica de respuestas
 */
class RespuestasApi extends BaseController
{
	var $test = false;

	function init($p = [])
	{
		if(@$p['test']) $this->test = true;
	}

	/**
	 * get_form_respuesta
	 * @param $session
	 * @param $pregunta_id
	 */
	function get_form_respuesta()
	{
		if(!$this->isPost()) return "get_form_respuesta";
		$res = new RespuestaConsulta();
		$session = $this->request->getParam('session');
		$pregunta_id = $this->request->getParam('pregunta_id');
		$user = UsuarioLogin::getUserBySession($session);
		$form['form']['title'] = 'form';
		$form['form']['type'] = 'object';
		$form['form']['properties'] = $this->getFieldsRespuesta($pregunta_id);

		$config = $this->get('config');
		$pregunta = Pregunta::getPreguntaDetalle($pregunta_id, $config);
		$form['question'] = $pregunta;

		return $this->json($res->conDatos($form));
	}

	/**
	 * save_form_respuesta
	 * @param $session
	 * @param $pregunta_id
	 * @param $respuesta_id
	 * @param $data
	 */
	function save_form_respuesta()
	{
		if(!$this->isPost()) return "save_form_respuesta";
		$res = new RespuestaConsulta();

		$pregunta_id = $this->request->getParam('pregunta_id');
		$respuesta_id = $this->request->getParam('respuesta_id');
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
		if($respuesta_id > 0) {
			$respuesta = Respuesta::porId($respuesta_id);
			$respuesta->fecha_modificacion = date("Y-m-d H:i:s");
			$respuesta->usuario_modificacion = $user['id'];
		} else {
			$verificar_respuesta_pregunta = Respuesta::getRespuestaPorPregunta($pregunta_id);
			if(count($verificar_respuesta_pregunta) == 0) {
				$respuesta = new Respuesta();
				$respuesta->fecha_ingreso = date("Y-m-d H:i:s");
				$respuesta->usuario_ingreso = $user['id'];
				$respuesta->usuario_modificacion = $user['id'];
				$respuesta->fecha_modificacion = date("Y-m-d H:i:s");
				$respuesta->eliminado = 0;
				$respuesta->pregunta_id = $pregunta_id;
				$respuesta->fecha_respuesta = date("Y-m-d H:i:s");
				//PARA VER EL TIPO DE RESPUESTA
				if(isset($data['respuesta'])) {
					$respuesta->tipo_respuesta = 'texto';
				} else {
					$respuesta->tipo_respuesta = 'voz';
					$respuesta->archivo_respuesta = 'respuesta_voz_' . date("Y_m_d_H_i_s") . '.wav';
				}
				$es_nuevo = true;
			}else{
				return $this->json($res->conError('LA PREGUNTA YA HA SIDO RESPONDIDA POR OTRO USUARIO'));
			}
		}
		//ASIGNAR CAMPOS
		$fields = Respuesta::getAllColumnsNames();
		foreach($data as $k => $v) {
			if(in_array($k, $fields)) {
				$respuesta->$k = $v;
			}
		}
		if($respuesta->save()) {
			if($es_nuevo) {
				//ENVIO DE NOTIFICACION PUSH DE LA RESPUESTA
				$envio_notificacion = new EnvioNotificacionesPush();
				$envio_notificacion->respuestaEnviada($pregunta_id);
				//PONER COMO RESPONDIDA A LA PREGUNTA
				$pregunta = Pregunta::porId($pregunta_id);
				$pregunta->estado = 'respondida';
				$pregunta->usuario_modificacion = $user['id'];
				$pregunta->fecha_modificacion = date("Y-m-d H:i:s");
				$pregunta->save();
			}
			if(isset($files["data"])) {
				//ARREGLAR ARCHIVOS
				$archivo['name'] = $respuesta->archivo_respuesta;
				$archivo['type'] = $files["data"]["type"]["respuesta"];
				$archivo['tmp_name'] = $files["data"]["tmp_name"]["respuesta"];
				$archivo['error'] = $files["data"]["error"]["respuesta"];
				$archivo['size'] = $files["data"]["size"]["respuesta"];
				$this->uploadFiles($respuesta, $archivo);
			}
			return $this->json($res->conMensaje('OK'));
		} else {
			return $this->json($res->conError('ERROR AL MODIFICAR PREGUNTA'));
		}
	}

	function getFieldsRespuesta($pregunta_id)
	{
		$form['respuesta'] = [
			'type' => 'string',
			'title' => 'Respuesta',
			'widget' => 'multimedia_message_widget',
			'full_name' => 'data[respuesta]',
			'constraints' => [
				[
					'name' => 'NotBlank',
					'message' => 'Este campo no puede estar vacío'
				]
			],
			'mode' => 'FILE',
			'voice_enabled' => true,
			'text_enabled' => true,
			'text_limit' => 800,
			'voice_limit' => 300,
			'required' => 1,
			'disabled' => 0,
			'property_order' => 1,
		];

		return $form;
	}

	public function uploadFiles($respuesta, $archivo)
	{
		$config = $this->get('config');

		//INSERTAR EN BASE EL ARCHIVO
		$arch = new Archivo();
		$arch->parent_id = $respuesta->id;
		$arch->parent_type = 'respuesta';
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

		$dir = $config['folder_archivos_audio_respuesta'];
		if(!is_dir($dir)) {
			\Auditor::error("Error API Carga Archivo: El directorio $dir de imagenes no existe", 'RespuestasApi', []);
			return false;
		}
		$upload = new Upload($archivo);
		if(!$upload->uploaded) {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'RespuestasApi', []);
			return false;
		}
		// save uploaded image with no changes
		$upload->Process($config['folder_archivos_audio_respuesta']);
		if($upload->processed) {
			\Auditor::info("API Carga Archivo " . $archivo['name'] . " cargada", 'RespuestasApi');
			return true;
		} else {
			\Auditor::error("Error API Carga Archivo: " . $upload->error, 'RespuestasApi', []);
			return false;
		}
	}
}
