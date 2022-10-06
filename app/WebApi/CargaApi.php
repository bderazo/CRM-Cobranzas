<?php

namespace WebApi;

use ApiRemoto\RespuestaPendientes;
use Carga\ReporteCargaFilas;
use Carga\Retencion\CargadorRemoto;
use Controllers\BaseController;
use Models\Concesionario;
use Notificaciones\Notificador;
use Slim\Http\UploadedFile;

/**
 * Class CargaController
 * @package Controllers\api
 * Aqui se ejecuta la logica de carga de datos remotos
 */
class CargaApi extends BaseController {
	
	var $test = false;
	
	function init($p = []) {
		if (@$p['test']) $this->test = true;
	}
	
	function recibir() {
		if (!$this->isPost()) return "carga/recibir";
		
		/** @var Notificador $notifcador */
		$notifcador = $this->get('notificador');
		
		$reporte = new ReporteCargaFilas();
		$reporte->fecha = date('Y-m-d H:i:s');
		$reporte->estado = 'ERROR';
		$header = json_decode(@$_POST['data'], true);
		$codigos = $header['concesionario'];
		if (!is_array($codigos))
			$codigos = [$codigos];
		
		$listaIds = Concesionario::idsPorCodigoApi($codigos);
		$errorApi = '';
		foreach ($codigos as $codigo) {
			if (!@$listaIds[$codigo]) {
				if ($errorApi) $errorApi .= ', ';
				$errorApi .= "No se encuentra concesionario con codigo $codigo";
			}
		}
		if ($errorApi) {
			\Auditor::info("Peticion Carga fallida", 'CargaApi', $errorApi);
			$reporte->mensaje = $errorApi;
			return $this->json($reporte, JSON_PRETTY_PRINT);
		}
		
		$uploads = $this->request->getUploadedFiles();
		if (empty($uploads['ot'])) {
			$msg = "Archivo 'ot' no recibido";
			\Auditor::info("Peticion Carga fallida", 'CargaApi', $msg);
			$reporte->mensaje = $msg;
			return $this->json($reporte, JSON_PRETTY_PRINT);
		}
		
		/** @var UploadedFile $up */
		$up = $uploads['ot'];
		$tempPath = realpath($this->get('config')['tempPath']);
		$path = $tempPath . '/' . $up->getClientFilename();
		
		/** @var \PDO $pdo */
		$pdo = $this->get('pdo');
		$pdo->beginTransaction();
		$msg = $this->test ? ' TEST' : '';
		$cargador = new CargadorRemoto($pdo);
		try {
			$up->moveTo($path);
			$cargador->test = $this->test;
			$reporte = $cargador->procesar($path, array_values($listaIds));
			$pdo->commit();
			\Auditor::info("Carga recibida$msg", 'CargaApi', $reporte);
		} catch (\Exception $ex) {
			$pdo->rollBack();
			$data = [$ex, $cargador->lastRecord, $ex->getTraceAsString()];
			\Auditor::error("Error API Carga $msg: " . $ex->getMessage(), 'CargaApi', $data);
			$reporte->estado = 'ERROR';
			$reporte->mensaje = $ex->getMessage();
		}
		
		// notificar cada carga
		if (!$this->test && !empty($cargador->ultimasCargas)) {
			foreach ($cargador->ultimasCargas as $carga)
				$notifcador->enviarCargaRemota($carga);
		}
		
		// borrar archivo con condicion o algo
		$keepFile = true;
		if (!$keepFile && file_exists($path))
			@unlink($path);
		$reporte->otrosIds = [];
		return $this->json($reporte, JSON_PRETTY_PRINT);
	}
	
	function pendientes() {
		if (!$this->isPost()) return "carga/pendientes";
		
		$res = new RespuestaPendientes();
		$header = json_decode(@$_POST['data'], true);
		if (empty($header)) {
			return $this->json($res->withError('Datos de peticion invalidos'), JSON_PRETTY_PRINT);
		}
		$pdo = $this->get('pdo');
		$db = new \FluentPDO($pdo);
		try {
			$codigos = $header['concesionario'];
			$listaIds = Concesionario::idsPorCodigoApi($codigos);
			$errorApi = '';
			foreach ($codigos as $codigo) {
				if (!@$listaIds[$codigo]) {
					if ($errorApi) $errorApi .= ', ';
					$errorApi .= "No se encuentra concesionario con codigo $codigo";
				}
			}
			if ($errorApi)
				return $this->json($res->withError($errorApi), JSON_PRETTY_PRINT);
			$ids = array_values($listaIds);
			
			//TODO sacar de configuracion
			$tiempoDiasErrores = 10;
			
			$hoy = new \DateTime();
			$int = 'P' . $tiempoDiasErrores . 'D';
			$pasado = $hoy->sub(new \DateInterval($int));
			$lista = $db->from('retencion_errores')->where('concesionario_id', $ids)
				->where("fecha_original >= ?", $pasado->format('Y-m-d'));
			$res->desde = $pasado->format('Y-m-d');
			foreach ($lista as $row) {
				$res->lista[] = [
					'ot' => $row['ot'],
					'baccode' => $row['baccode'],
					'vin' => $row['vin'],
					'error' => $row['error'],
					'fuente' => $row['fuente']
				];
			}
		} catch (\Exception $ex) {
			\Auditor::error("Error API Pendientes: " . $ex->getMessage(), 'CargaApi', $ex);
			$res->withError("Ocurrio un error al consultar los datos");
		}
		return $this->json($res, JSON_PRETTY_PRINT);
	}
	
}
