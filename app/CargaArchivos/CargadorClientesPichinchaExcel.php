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
use Models\Institucion;
use Models\Producto;
use Models\ProductoCampos;
use Models\Telefono;
use Models\Usuario;

class CargadorClientesPichinchaExcel
{

	/** @var \PDO */
	var $pdo;

	/**
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
			$carga->tipo = 'clientes cedente';
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
			$telefonos_todos = Telefono::getTodos();
			$direccion_todos = Direccion::getTodos();
			$productos_todos = Producto::porInstitucioVerificar(@$extraInfo['institucion_id']);
			$institucion = Institucion::porId(@$extraInfo['institucion_id']);
			foreach ($it as $rowIndex => $values) {
				if (($rowIndex === 1)) {
					continue;
				}
				if ($values[0] == '')
					continue;

				$cliente_id = 0;
				foreach ($clientes_todos as $cl) {
					$existe_cedula = array_search(trim($values[0]), $cl);
					if ($existe_cedula) {
						$cliente_id = $cl['id'];
						break;
					}
				}
				if ($cliente_id == 0) {
					$cliente = new Cliente();
					$cliente->nombres = trim($values[1]);
					$cliente->cedula = $values[2];
					$cliente->profesion_id = trim($values[3]); // para almacenar el valor exigible
					$cliente->tipo_referencia_id = trim($values[4]); // para almacenar el valor riesgo
					$cliente->zona = trim($values[5]);
					$cliente->ciudad = trim($values[7]);
					$cliente->lugar_trabajo = 'Pichincha';
					$cliente->gestionar = 'si';
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->usuario_asignado = \WebSecurity::getUserData('id');
					$cliente->eliminado = 0;
					$cliente->save();
					$cliente_id = $cliente->id;
				} else {
					$cliente = Cliente::porId($cliente_id);
					if ($values[1] != '') {
						$cliente->nombres = trim($values[1]);
					}
					if ($values[2] != '') {
						$cliente->cedula = trim($values[2]);
					}
					if ($values[3] != '') {
						$cliente->profesion_id = trim($values[3]);
					}
					if ($values[4] != '') {
						$cliente->tipo_referencia_id = trim($values[4]);
					}
					if ($values[5] != '') {
						$cliente->zona = trim($values[5]);
					}
					if ($values[7] != '') {
						$cliente->ciudad = trim($values[7]);
					}
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->save();
				}

				//PROCESO DE TELEFONOS
				if ($values[8] != '') {
					$telefono_arr = explode(";", $values[8]);
					foreach ($telefono_arr as $telefono_txt) {

						$telefono_id = 0;
						foreach ($telefonos_todos as $tel) {
							$existe = array_search(trim($telefono_txt), $tel);
							if ($existe) {
								$telefono_id = $tel['id'];
								break;
							}
						}
						if ($telefono_id == 0) {
							$telefono = new Telefono();
							$telefono->tipo = 'DOMICILIO';
							$telefono->descripcion = 'TITULAR';
							$telefono->origen = 'IMPORTACION';
							$telefono->telefono = trim($telefono_txt);
							$telefono->bandera = 0;
							$telefono->modulo_id = $cliente_id;
							$telefono->modulo_relacionado = 'cliente';
							$telefono->fecha_ingreso = date("Y-m-d H:i:s");
							$telefono->fecha_modificacion = date("Y-m-d H:i:s");
							$telefono->usuario_ingreso = \WebSecurity::getUserData('id');
							$telefono->usuario_modificacion = \WebSecurity::getUserData('id');
							$telefono->eliminado = 0;
							$telefono->save();
						}
					}
				}

				//PROCESO DE DIRECCIONES
				if ($values[7] != '') {
					$direccion_arr = explode(";", $values[7]);
					foreach ($direccion_arr as $direccion_txt) {
						$direccion_id = 0;
						if (isset($direccion_todos[$cliente_id])) {
							foreach ($direccion_todos[$cliente_id] as $dir) {
								$existe_direccion = array_search(trim($direccion_txt), $dir);
								if ($existe_direccion) {
									$direccion_id = $dir['id'];
									break;
								}
							}
						}
						if ($direccion_id == 0) {
							$direccion = new Direccion();
							$direccion->tipo = 'DOMICILIO';
							$direccion->origen = 'IMPORTACION';
							$direccion->ciudad = trim($direccion_txt);
							$direccion->direccion = trim($direccion_txt);
							$direccion->modulo_id = $cliente_id;
							$direccion->modulo_relacionado = 'cliente';
							$direccion->fecha_ingreso = date("Y-m-d H:i:s");
							$direccion->fecha_modificacion = date("Y-m-d H:i:s");
							$direccion->usuario_ingreso = \WebSecurity::getUserData('id');
							$direccion->usuario_modificacion = \WebSecurity::getUserData('id');
							$direccion->eliminado = 0;
							$direccion->save();
						}
					}
				}

				$producto_id = 0;
				if (isset($productos_todos[$cliente_id])) {
					$producto_id = $productos_todos[$cliente_id]['id'];
				}
				if ($producto_id == 0) {
					if ($values[0] == 'CARTERA VENDIDA PY TDC') {
						$producto = new Producto();
						$producto->institucion_id = @$extraInfo['institucion_id'];
						$producto->cliente_id = $cliente_id;
						$producto->producto = 'CARTERA VENDIDA PY TDC';
						$producto->fecha_ingreso = date("Y-m-d H:i:s");
						$producto->usuario_ingreso = \WebSecurity::getUserData('id');
						$producto->eliminado = 0;
						$producto->estado = 'asignado';
						$producto->fecha_modificacion = date("Y-m-d H:i:s");
						$producto->usuario_modificacion = \WebSecurity::getUserData('id');
						$producto->usuario_asignado = 0;
						$producto->save();
						$producto_id = $producto->id;
						$productos_procesados[] = $producto_id;
					} elseif ($values[0] == 'CARTERA VENDIDA PY CONSUMO') {
						$producto = new Producto();
						$producto->institucion_id = @$extraInfo['institucion_id'];
						$producto->cliente_id = $cliente_id;
						$producto->producto = 'CARTERA VENDIDA PY CONSUMO';
						$producto->fecha_ingreso = date("Y-m-d H:i:s");
						$producto->usuario_ingreso = \WebSecurity::getUserData('id');
						$producto->eliminado = 0;
						$producto->estado = 'asignado';
						$producto->fecha_modificacion = date("Y-m-d H:i:s");
						$producto->usuario_modificacion = \WebSecurity::getUserData('id');
						$producto->usuario_asignado = 0;
						$producto->save();
						$producto_id = $producto->id;
						$productos_procesados[] = $producto_id;
					}
				} else {
					$set = [
						'estado' => 'asignado_' . $institucion->nombre,
						'fecha_modificacion' => date("Y-m-d H:i:s"),
						'usuario_modificacion' => \WebSecurity::getUserData('id'),
						'usuario_asignado' => 0,
						'fecha_gestionar' => null,
					];
					$query = $db->update('producto')->set($set)->where('id', $producto_id)->execute();
					$productos_procesados[] = $producto_id;
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
			\Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorClientesPichinchaExcel");
		} catch (\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorClientesPichinchaExcel", $ex);
			$pdo->rollBack();
			$rep['errorSistema'] = $ex;
		}
		return $rep;
	}

	public function searchArray($array, $search_list)
	{
		$result = [];
		foreach ($array as $key => $value) {
			foreach ($search_list as $k => $v) {
				if (!isset($value[$k]) || $value[$k] != $v) {
					continue 2;
				}
			}
			$result[] = $value;
		}
		return $result;
	}

	function getFecha($value, $default = null)
	{
		if ($value instanceof \DateTime)
			return $value->format('Y-m-d H:i:s');
		return $default;
	}
}