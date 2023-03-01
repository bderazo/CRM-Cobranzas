<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;

class CargadorAsignacionesDinersExcel
{

	/** @var \PDO */
	var $pdo;

	/**
	 * CargadorAplicativoDinersExcel constructor.
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	function cargar($path, $extraInfo)
	{
		$book = XlsxParser::open($path);
		$it = $book->createRowIterator(0);
		$nombreArchivo = $extraInfo['name'];
		$rep = [
			'total' => 0,
			'errores' => 0,
			'errorSistema' => null,
			'archivo' => $nombreArchivo,
			'idcarga' => null,
			'tiempo_ejecucion' => 0,
		];

		$hoy = new \DateTime();
		$hoytxt = $hoy->format('Y-m-d H:i:s');

		$pdo = $this->pdo;
		$pdo->beginTransaction();
		try {
			$time_start = microtime(true);

			$carga = new CargaArchivo();
			$carga->tipo = 'asignaciones_diners';
			$carga->estado = 'cargado';
			$carga->observaciones = @$extraInfo['observaciones'];
			$carga->archivo_real = $nombreArchivo;
			$carga->longitud = @$extraInfo['size'];
			$carga->tipomime = @$extraInfo['mime'];
			$carga->fecha_ingreso = $hoytxt;
			$carga->fecha_modificacion = $hoytxt;
			$carga->usuario_ingreso = \WebSecurity::getUserData('id');
			$carga->usuario_modificacion = \WebSecurity::getUserData('id');
			$carga->usuario_asignado = \WebSecurity::getUserData('id');
			$carga->eliminado = 0;
			$carga->save();

			$db = new \FluentPDO($pdo);
			$clientes_todos = Cliente::getTodos();
			$asignaciones_todos = AplicativoDinersAsignaciones::getTodos();
			$productos_todos = Producto::porInstitucioVerificar(1);
			$aplicativo_diners_todos = AplicativoDiners::porInstitucioVerificar(1);
			foreach($it as $rowIndex => $values) {
				if(($rowIndex === 1)) {
					$ultima_posicion_columna = array_key_last($values);
					for($i = 11; $i <= $ultima_posicion_columna; $i++) {
						$cabecera[] = $values[$i];
					}
					continue;
				}
				if($values[0] == '')
					continue;

				//PROCESO DE CLIENTES
				$cliente_id = 0;
				$cliente_cedula = '';
				$cliente_arr = [];
				foreach($clientes_todos as $cl) {
					$existe_cedula = array_search($values[8], $cl);
					if($existe_cedula) {
						$cliente_id = $cl['id'];
						$cliente_cedula = $cl['cedula'];
						$cliente_arr = $cl;
						break;
					}
				}

				if($cliente_id == 0) {
					//CREAR CLIENTE
					$cliente = new Cliente();
					$cliente->cedula = $values[8];
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->eliminado = 0;
					$cliente->nombres = $values[7];
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->usuario_asignado = \WebSecurity::getUserData('id');
					$cliente->save();
					$cliente_id = $cliente->id;
					$cliente_cedula = $cliente->cedula;
					$cliente_arr = $cliente->toArray();
				}

				//PROCESO DE PRODUCTOS
				$producto_id = 0;
				if(isset($productos_todos[$cliente_id])) {
					$producto_id = $productos_todos[$cliente_id]['id'];
				}
				if($producto_id == 0) {
					$producto = new Producto();
					$producto->institucion_id = 1;
					$producto->cliente_id = $cliente_id;
					$producto->producto = $values[5];
					$producto->fecha_ingreso = date("Y-m-d H:i:s");
					$producto->usuario_ingreso = \WebSecurity::getUserData('id');
					$producto->usuario_asignado = 0;
					$producto->eliminado = 0;
					$producto->estado = 'asignado_megacob';
					$producto->fecha_modificacion = date("Y-m-d H:i:s");
					$producto->usuario_modificacion = \WebSecurity::getUserData('id');
					$producto->save();
					$producto_id = $producto->id;
				}else{
					//CAMBIAR ESTADO APLICATIVO DINERS
					$set = [
						'estado' => 'asignado_megacob',
					];
					$query = $db->update('producto')->set($set)->where('id', $producto_id)->execute();
				}

				//CAMBIAR EL ESTADO DE APLICACION DINERS
				$aplicativo_diners_id = 0;
				if(isset($aplicativo_diners_todos[$cliente_id])) {
					$aplicativo_diners_id = $aplicativo_diners_todos[$cliente_id]['id'];
				}
				if($aplicativo_diners_id == 0) {
					$aplicativo_diners = new AplicativoDiners();
					$aplicativo_diners->cliente_id = $cliente_id;
					$aplicativo_diners->institucion_id = 1;
					$aplicativo_diners->producto_id = $producto_id;
					$aplicativo_diners->estado = 'asignado_megacob';
					$aplicativo_diners->fecha_elaboracion = date("Y-m-d H:i:s");
					$aplicativo_diners->cedula_socio = $cliente_cedula;
					$aplicativo_diners->nombre_socio = $cliente_arr['nombres'];
					$aplicativo_diners->ciudad_cuenta = $cliente_arr['ciudad'];
					$aplicativo_diners->zona_cuenta = $cliente_arr['zona'];
					$aplicativo_diners->fecha_ingreso = date("Y-m-d H:i:s");
					$aplicativo_diners->fecha_modificacion = date("Y-m-d H:i:s");
					$aplicativo_diners->usuario_ingreso = \WebSecurity::getUserData('id');
					$aplicativo_diners->usuario_modificacion = \WebSecurity::getUserData('id');
					$aplicativo_diners->usuario_asignado = 0;
					$aplicativo_diners->eliminado = 0;
					$aplicativo_diners->save();
					$aplicativo_diners_id = $aplicativo_diners->id;
				} else {
					//CAMBIAR ESTADO APLICATIVO DINERS
					$set = [
						'estado' => 'asignado_megacob',
					];
					$query = $db->update('aplicativo_diners')->set($set)->where('id', $aplicativo_diners_id)->execute();
				}

				//PROCESO DE ASIGNACIONES
				$asignacion_id = 0;
				if(isset($asignaciones_todos[$aplicativo_diners_id])) {
					$asignacion_id = $asignaciones_todos[$aplicativo_diners_id]['id'];
				}
				if($asignacion_id == 0) {

				}
//				$asignacion_id = 0;
//				$buscar_items = [
//					'mes' => $values[2],
//					'anio' => $values[3],
//					'campana' => $values[4],
//					'marca' => $values[5],
//					'ciclo' => $values[6],
//					'cedula_socio' => $values[8],
//				];
//				$existe_asignacion = $this->searchArray($asignaciones_todos, $buscar_items);
//				if(count($existe_asignacion) > 0) {
//					$asignacion_id = $existe_asignacion[0]['id'];
//				}
				//MAPEAR LOS CAMPOS PARA GUARDAR COMO CLAVE VALOR
				$cont = 0;
				$data_campos = [];
				for($i = 9; $i <= $ultima_posicion_columna; $i++) {
					if(isset($values[$i])) {
						$data_campos[$cabecera[$cont]] = $values[$i];
					}
					$cont++;
				}
				if($asignacion_id == 0) {
					//CREAR ASIGNACION
					$asignaciones = new AplicativoDinersAsignaciones();
					$asignaciones->cliente_id = $cliente_id;
					$asignaciones->aplicativo_diners_id = $aplicativo_diners_id;
					$asignaciones->fecha_inicio = $this->getFecha($values[0]);
					$asignaciones->fecha_fin = $this->getFecha($values[1]);
					$asignaciones->mes = $values[2];
					$asignaciones->anio = $values[3];
					$asignaciones->campana = $values[4];
					$asignaciones->marca = $values[5];
					$asignaciones->ciclo = $values[6];
					$asignaciones->nombre_socio = $values[7];
					$asignaciones->cedula_socio = $values[8];
					$asignaciones->campana_ece = $values[9];
					$asignaciones->condonacion_interes = $values[10];
					$asignaciones->segregacion = $values[11];
					$asignaciones->campos = json_encode($data_campos, JSON_PRETTY_PRINT);
					$asignaciones->fecha_ingreso = date("Y-m-d H:i:s");
					$asignaciones->usuario_ingreso = \WebSecurity::getUserData('id');
					$asignaciones->fecha_modificacion = date("Y-m-d H:i:s");
					$asignaciones->usuario_modificacion = \WebSecurity::getUserData('id');
					$asignaciones->eliminado = 0;
					$asignaciones->save();
				} else {
					//MODIFICAR SALDOS
					$set = [
						'fecha_modificacion' => date("Y-m-d H:i:s"),
						'usuario_modificacion' => \WebSecurity::getUserData('id'),
						'campos' => json_encode($data_campos, JSON_PRETTY_PRINT),
						'fecha_inicio' => $this->getFecha($values[0]),
						'fecha_fin' => $this->getFecha($values[1]),
					];
					$query = $db->update('aplicativo_diners_asignaciones')->set($set)->where('id', $asignacion_id)->execute();
				}
				$rep['total']++;
			}

			$time_end = microtime(true);

			$execution_time = ($time_end - $time_start) / 60;
			$rep['tiempo_ejecucion'] = $execution_time;

			$rep['idcarga'] = $carga->id;
			$carga->total_registros = $rep['total'];
			$carga->update();
			$pdo->commit();
			\Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorAsignacionesAplicativoDinersExcel");
		} catch(\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorAsignacionesAplicativoDinersExcel", $ex);
			$pdo->rollBack();
			$rep['errorSistema'] = $ex;
		}
		return $rep;
	}

	public function searchArray($array, $search_list)
	{
		$result = [];
		foreach($array as $key => $value) {
			foreach($search_list as $k => $v) {
				if(!isset($value[$k]) || $value[$k] != $v) {
					continue 2;
				}
			}
			$result[] = $value;
		}
		return $result;
	}

	function getFecha($value, $default = null)
	{
		if($value instanceof \DateTime)
			return $value->format('Y-m-d H:i:s');
		return $default;
	}
}