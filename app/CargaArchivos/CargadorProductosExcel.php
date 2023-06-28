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

class CargadorProductosExcel
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

	function cargar($path, $extraInfo, $institucion_id)
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
			$carga->tipo = 'productos';
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
			$cabecera = [];
			$clientes_todos = Cliente::getTodos();
			$telefonos_todos = Telefono::getTodos();
			$direccion_todos = Direccion::getTodos();
			$email_todos = Email::getTodos();
			$productos_todos = Producto::porInstitucion($institucion_id);
			$institucion = Institucion::porId($institucion_id);
			foreach($it as $rowIndex => $values) {
				if(($rowIndex === 1)) {
					$ultima_posicion_columna = array_key_last($values);
					for($i = 5; $i <= $ultima_posicion_columna; $i++) {
						$cabecera[] = $values[$i];
					}
					continue;
				}
				if($values[0] == '')
					continue;

				$cliente_id = 0;
				foreach($clientes_todos as $cl) {
					$existe_cedula = array_search($values[1], $cl);
					if($existe_cedula) {
						$cliente_id = $cl['id'];
						break;
					}
				}
				if($cliente_id == 0) {
					$cliente = new Cliente();
					$cliente->cedula = $values[1];
					$cliente->nombres = $values[2];
                    $cliente->gestionar = 'si';
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->usuario_asignado = \WebSecurity::getUserData('id');
					$cliente->eliminado = 0;
					$cliente->save();
					$cliente_id = $cliente->id;
				}

				//PROCESO DE DIRECCIONES
				if($values[4] != '') {
					$direccion_id = 0;
					if(isset($direccion_todos[$cliente_id])) {
						foreach($direccion_todos[$cliente_id] as $dir) {
							$existe_direccion = array_search(trim($values[4]), $dir);
							if($existe_direccion) {
								$direccion_id = $dir['id'];
								break;
							}
						}
					}
					if($direccion_id == 0) {
						$direccion = new Direccion();
						$direccion->tipo = 'DOMICILIO';
						$direccion->origen = strtoupper($institucion['nombre']);
						$direccion->ciudad = $values[5];
						$direccion->direccion = trim($values[4]);
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

				//PROCESO DE TELEFONOS
				if($values[3] != '') {
					$telefono_id = 0;
					foreach($telefonos_todos as $tel) {
						$existe = array_search($values[3], $tel);
						if($existe) {
							$telefono_id = $tel['id'];
							break;
						}
					}
					if($telefono_id == 0) {
						$telefono = new Telefono();
						$telefono->tipo = 'DOMICILIO';
						$telefono->descripcion = 'TITULAR';
						$telefono->origen = strtoupper($institucion['nombre']);
						$telefono->telefono = $values[3];
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

//			if($values[12] != '') {
//				$mail = new Email();
//				$mail->tipo = 'PERSONAL';
//				$mail->descripcion = 'TITULAR';
//				$mail->origen = 'DINERS';
//				$mail->email = $values[12];
//				$mail->bandera = 0;
//				$mail->modulo_id = $cliente->id;
//				$mail->modulo_relacionado = 'cliente';
//				$mail->fecha_ingreso = date("Y-m-d H:i:s");
//				$mail->fecha_modificacion = date("Y-m-d H:i:s");
//				$mail->usuario_ingreso = \WebSecurity::getUserData('id');
//				$mail->usuario_modificacion = \WebSecurity::getUserData('id');
//				$mail->eliminado = 0;
//				$mail->save();
//			}

				//PROCESO DE PRODUCTOS
				$producto_id = 0;
				foreach($productos_todos as $prod) {
					$existe = array_search($values[0], $prod);
					if($existe) {
						$producto_id = $prod['id'];
						break;
					}
				}
				if($producto_id == 0) {
					$producto = new Producto();
					$producto->institucion_id = $institucion_id;
					$producto->cliente_id = $cliente_id;
					$producto->producto = $values[0];
					$producto->fecha_ingreso = date("Y-m-d H:i:s");
					$producto->usuario_ingreso = \WebSecurity::getUserData('id');
					$producto->eliminado = 0;
					$producto->estado = 'no_asignado';
					$producto->fecha_modificacion = date("Y-m-d H:i:s");
					$producto->usuario_modificacion = \WebSecurity::getUserData('id');
					$producto->usuario_asignado = 0;
					$producto->save();
					$producto_id = $producto->id;
				}
				$query = $db->deleteFrom('producto_campos')->where('producto_id', $producto_id)->execute();
				$cont = 0;
				for($i = 7; $i <= $ultima_posicion_columna; $i++) {
					$producto_campos = new ProductoCampos();
					$producto_campos->producto_id = $producto_id;
					$producto_campos->campo = $cabecera[$cont];
					$producto_campos->valor = $values[$i];
					$producto_campos->fecha_ingreso = date("Y-m-d H:i:s");
					$producto_campos->fecha_modificacion = date("Y-m-d H:i:s");
					$producto_campos->usuario_ingreso = \WebSecurity::getUserData('id');
					$producto_campos->usuario_modificacion = \WebSecurity::getUserData('id');
					$producto_campos->eliminado = 0;
					$producto_campos->save();
					$cont++;
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