<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\AplicativoDinersSaldos;
use Models\AplicativoDinersSaldosCampos;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\Producto;
use Models\Telefono;

class CargadorSaldosDinersExcel
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
			$carga->tipo = 'saldos_diners';
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
			$email_todos = Email::getTodos();
			$saldos_todos = AplicativoDinersSaldos::getTodos();
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
				foreach($clientes_todos as $cl) {
					$existe_cedula = array_search($values[1], $cl);
					if($existe_cedula) {
						$cliente_id = $cl['id'];
						$cliente_cedula = $cl['cedula'];
						break;
					}
				}
				if($cliente_id == 0) {
					//CREAR CLIENTE
					$cliente = new Cliente();
					$cliente->cedula = $values[1];
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->eliminado = 0;
					$cliente->nombres = $values[0];
					$cliente->lugar_trabajo = $values[7];
					$cliente->ciudad = $values[9];
					$cliente->zona = $values[10];
                    $cliente->gestionar = 'si';
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->usuario_asignado = \WebSecurity::getUserData('id');
					$cliente->save();
					$cliente_id = $cliente->id;
					$cliente_cedula = $cliente->cedula;
				} else {
					//MODIFICAR CLIENTE
					$set = [
						'cedula' => $values[1],
						'nombres' => $values[0],
						'lugar_trabajo' => $values[7],
						'ciudad' => $values[9],
						'zona' => $values[10],
						'fecha_modificacion' => date("Y-m-d H:i:s"),
						'usuario_modificacion' => \WebSecurity::getUserData('id')
					];
					$query = $db->update('cliente')->set($set)->where('id', $cliente_id)->execute();
				}

				//PROCESO DE DIRECCIONES
				$direccion_id = 0;
				if(isset($direccion_todos[$cliente_id])) {
					foreach($direccion_todos[$cliente_id] as $dir) {
						$existe_direccion = array_search(trim($values[8]), $dir);
						if($existe_direccion) {
							$direccion_id = $dir['id'];
							break;
						}
					}
				}
				if($direccion_id == 0) {
					$direccion = new Direccion();
					$direccion->tipo = 'DOMICILIO';
					$direccion->origen = 'DINERS';
					$direccion->ciudad = $values[9];
					$direccion->direccion = trim($values[8]);
					$direccion->modulo_id = $cliente_id;
					$direccion->modulo_relacionado = 'cliente';
					$direccion->fecha_ingreso = date("Y-m-d H:i:s");
					$direccion->fecha_modificacion = date("Y-m-d H:i:s");
					$direccion->usuario_ingreso = \WebSecurity::getUserData('id');
					$direccion->usuario_modificacion = \WebSecurity::getUserData('id');
					$direccion->eliminado = 0;
					$direccion->save();
				}

				//PROCESO DE TELEFONOS
				if($values[2] != '') {
					$telefono_id = 0;
					if(isset($telefonos_todos[$cliente_id])) {
						foreach($telefonos_todos[$cliente_id] as $tel) {
							$existe_telefono = array_search($values[2], $tel);
							if($existe_telefono) {
								$telefono_id = $tel['id'];
								break;
							}
						}
					}
					if($telefono_id == 0) {
						$telefono = new Telefono();
						$telefono->tipo = 'CELULAR';
						$telefono->descripcion = 'TITULAR';
						$telefono->origen = 'DINERS';
						$telefono->telefono = $values[2];
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
				if($values[3] != '') {
					$telefono_id = 0;
					if(isset($telefonos_todos[$cliente_id])) {
						foreach($telefonos_todos[$cliente_id] as $tel) {
							$existe_telefono = array_search($values[3], $tel);
							if($existe_telefono) {
								$telefono_id = $tel['id'];
								break;
							}
						}
					}
					if($telefono_id == 0) {
						$telefono = new Telefono();
						$telefono->tipo = 'CELULAR';
						$telefono->descripcion = 'TITULAR';
						$telefono->origen = 'DINERS';
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
				if($values[4] != '') {
					$telefono_id = 0;
					if(isset($telefonos_todos[$cliente_id])) {
						foreach($telefonos_todos[$cliente_id] as $tel) {
							$existe_telefono = array_search($values[4], $tel);
							if($existe_telefono) {
								$telefono_id = $tel['id'];
								break;
							}
						}
					}
					if($telefono_id == 0) {
						$telefono = new Telefono();
						$telefono->tipo = 'CELULAR';
						$telefono->descripcion = 'TITULAR';
						$telefono->origen = 'DINERS';
						$telefono->telefono = $values[4];
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
				if($values[5] != '') {
					$telefono_id = 0;
					if(isset($telefonos_todos[$cliente_id])) {
						foreach($telefonos_todos[$cliente_id] as $tel) {
							$existe_telefono = array_search($values[5], $tel);
							if($existe_telefono) {
								$telefono_id = $tel['id'];
								break;
							}
						}
					}
					if($telefono_id == 0) {
						$telefono = new Telefono();
						$telefono->tipo = 'CELULAR';
						$telefono->descripcion = 'TITULAR';
						$telefono->origen = 'DINERS';
						$telefono->telefono = $values[5];
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

				//PROCESO DE EMAILS
				if($values[6] != '') {
					$email_id = 0;
					if(isset($email_todos[$cliente_id])) {
						foreach($email_todos[$cliente_id] as $ema) {
							$existe_email = array_search($values[6], $ema);
							if($existe_email) {
								$email_id = $ema['id'];
								break;
							}
						}
					}
					if($email_id == 0) {
						$mail = new Email();
						$mail->tipo = 'PERSONAL';
						$mail->descripcion = 'TITULAR';
						$mail->origen = 'DINERS';
						$mail->email = $values[6];
						$mail->bandera = 0;
						$mail->modulo_id = $cliente_id;
						$mail->modulo_relacionado = 'cliente';
						$mail->fecha_ingreso = date("Y-m-d H:i:s");
						$mail->fecha_modificacion = date("Y-m-d H:i:s");
						$mail->usuario_ingreso = \WebSecurity::getUserData('id');
						$mail->usuario_modificacion = \WebSecurity::getUserData('id');
						$mail->eliminado = 0;
						$mail->save();
					}
				}

				//PROCESO DE SALDOS
//				$saldos_id = 0;
//				foreach($saldos_todos as $sal) {
//					$existe_saldo = array_search($values[1], $sal);
//					if($existe_saldo) {
//						$saldos_id = $cl['id'];
//						break;
//					}
//				}
				//MAPEAR LOS CAMPOS PARA GUARDAR COMO CLAVE VALOR
				$cont = 0;
				$data_campos = [];
				for($i = 11; $i <= $ultima_posicion_columna; $i++) {
					if(isset($values[$i])) {
						$data_campos[$cabecera[$cont]] = $values[$i];
					}
					$cont++;
				}
//				if($saldos_id == 0){
					//CREAR SALDOS
					$saldos = new AplicativoDinersSaldos();
					$saldos->cliente_id = $cliente_id;
                    $saldos->fecha = @$extraInfo['fecha'];
					$saldos->campos = json_encode($data_campos,JSON_PRETTY_PRINT);
					$saldos->fecha_ingreso = date("Y-m-d H:i:s");
					$saldos->usuario_ingreso = \WebSecurity::getUserData('id');
					$saldos->fecha_modificacion = date("Y-m-d H:i:s");
					$saldos->usuario_modificacion = \WebSecurity::getUserData('id');
					$saldos->eliminado = 0;
					$saldos->save();
					$saldos_id = $saldos->id;
//				}else{
//					//MODIFICAR SALDOS
//					$set = [
//						'fecha_modificacion' => date("Y-m-d H:i:s"),
//						'usuario_modificacion' => \WebSecurity::getUserData('id'),
//						'campos' => json_encode($data_campos,JSON_PRETTY_PRINT),
//					];
//					$query = $db->update('aplicativo_diners_saldos')->set($set)->where('id', $saldos_id)->execute();
//
//					//ELIMINAR CAMPOS ANTERIORES
////					$query = $db->deleteFrom('aplicativo_diners_saldos_campos')->where('aplicativo_diners_saldos_id', $saldos_id)->execute();
//				}


				$rep['total']++;
			}

			$time_end = microtime(true);

			$execution_time = ($time_end - $time_start)/60;
			$rep['tiempo_ejecucion'] = $execution_time;

			$rep['idcarga'] = $carga->id;
			$carga->total_registros = $rep['total'];
			$carga->update();
			$pdo->commit();
			\Auditor::info("Archivo '$nombreArchivo'' cargado", "CargadorSaldosAplicativoDinersExcel");
		} catch(\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorSaldosAplicativoDinersExcel", $ex);
			$pdo->rollBack();
			$rep['errorSistema'] = $ex;
		}
		return $rep;
	}
}