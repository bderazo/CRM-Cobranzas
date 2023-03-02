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
use Models\Usuario;

class CargadorAsignacionesGestorDinersExcel
{

	/** @var \PDO */
	var $pdo;

	/**
	 * CargadorAsignacionesGestorDinersExcel constructor.
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
			'errorDatos' => [],
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
			$carga->tipo = 'asignaciones_gestor_diners';
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
			$usuarios_todos = Usuario::getTodos();
			$productos_todos = Producto::porInstitucioVerificar(1);
			$aplicativo_diners_todos = AplicativoDiners::porInstitucioVerificar(1);
			foreach($it as $rowIndex => $values) {
				if(($rowIndex === 1)) {
					continue;
				}
				if($values[0] == '')
					continue;

				//PROCESO DE CLIENTES
				$cliente_id = 0;
				foreach($clientes_todos as $cl) {
					$existe_cedula = array_search($values[0], $cl);
					if($existe_cedula) {
						$cliente_id = $cl['id'];
						break;
					}
				}
				if($cliente_id == 0) {
					//ERROR CLIENTE NO ENCONTRADO
					$rep['errorDatos'][] = 'CLIENTE NO ENCONTRADO EN LA FILA: ' . $rowIndex;
					break;
				}

				//PROCESO DE USUARIOS
				$usuario_id = 0;
				if(isset($usuarios_todos[$values[1]])) {
					$usuario_id = $usuarios_todos[$values[1]]['id'];
				}
				if($usuario_id == 0) {
					//ERROR USUARIO NO ENCONTRADO
					$rep['errorDatos'][] = 'USUARIO NO ENCONTRADO EN LA FILA: ' . $rowIndex;
					break;
				}

				//PROCESO DE PRODUCTOS
				$producto_id = 0;
				$estado_producto = 'asignado_megacob';
				if(isset($productos_todos[$cliente_id])) {
					$producto_id = $productos_todos[$cliente_id]['id'];
					$estado_producto = $productos_todos[$cliente_id]['estado'];
				}
				if($producto_id == 0) {
					//ERROR PRODUCTO NO ENCONTRADO
					$rep['errorDatos'][] = 'CLIENTE NO TIENE UN PRODUCTO ASIGNADO EN LA FILA: ' . $rowIndex;
					break;
				}

				//CAMBIAR EL ESTADO DE APLICACION DINERS
				$aplicativo_diners_id = 0;
				$estado_aplicativo_diners = 'no_asignado';
				if(isset($aplicativo_diners_todos[$cliente_id])) {
					$aplicativo_diners_id = $aplicativo_diners_todos[$cliente_id]['id'];
					$estado_aplicativo_diners = $aplicativo_diners_todos[$cliente_id]['estado'];
				}
				if($aplicativo_diners_id == 0) {
					//ERROR APLICATIVO NO ENCONTRADO
					$rep['errorDatos'][] = 'APLICATIVO DINERS NO TIENE EL CLIENTE ASIGNADO EN LA FILA: ' . $rowIndex;
					break;
				}

				$set = [
					'fecha_modificacion' => date("Y-m-d H:i:s"),
					'usuario_modificacion' => \WebSecurity::getUserData('id'),
					'usuario_asignado' => $usuario_id,
					'estado' => 'asignado_usuario',
				];
				$query = $db->update('producto')->set($set)->where('id', $producto_id)->execute();
				$set = [
					'fecha_modificacion' => date("Y-m-d H:i:s"),
					'usuario_modificacion' => \WebSecurity::getUserData('id'),
					'usuario_asignado' => $usuario_id,
					'estado' => 'asignado_usuario',
				];
				$query = $db->update('aplicativo_diners')->set($set)->where('id', $aplicativo_diners_id)->execute();


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