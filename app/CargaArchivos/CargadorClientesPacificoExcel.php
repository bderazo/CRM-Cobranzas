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

class CargadorClientesPacificoExcel
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
		// echo "MensajeÑ \n + $nombreArchivo + $extraInfo";

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
			$productos_todos = Producto::porInstitucioVerificar(@$extraInfo['institucion_id']);
			$clientes_todos = Cliente::getTodos();
			$telefonos_todos = Telefono::getTodos();
			$direccion_todos = Direccion::getTodos();
			$email_todos = Email::getTodos();
			$institucion = Institucion::porId(@$extraInfo['institucion_id']);
			foreach ($it as $rowIndex => $values) {
				if (($rowIndex === 1)) {
					continue;
				}
				if ($values[0] == '')
					continue;

				$cliente_id = 0;
				$cliente_cedula = '';
				foreach ($clientes_todos as $cl) {
					$existe_cedula = array_search(trim($values[7]), $cl);

					if ($existe_cedula) {
						$cliente_id = $cl['id'];
						echo "Mensaje en la consola del servidor\n + $cliente_id";

						break;
					}
				}
				if ($cliente_id == 0) {
					$cliente = new Cliente();
					$cliente->apellidos = trim($values[2]); //numero de tarjeta
					$cliente->nombres = trim($values[6]); //nombre
					$cliente->cedula = trim($values[7]); //cedula
					// $cliente->sexo = 'M';
					// $cliente->estado_civil = 'SOLTERO';
					$cliente->lugar_trabajo = trim($values[13]); //ciudad domicilio
					$cliente->ciudad = trim($values[13]); //ciudad domicilio
					$cliente->zona = trim($values[16]); //seguro desgravamen
					$cliente->profesion_id = trim($values[4]); //tipo de cartera
					// $cliente->tipo_referencia_id = trim($values[4]);
					$cliente->gestionar = 'SI';
					$cliente->experiencia_crediticia = trim($values[5]); //motivo
					$cliente->fecha_ingreso = date("Y-m-d H:i:s");
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_ingreso = \WebSecurity::getUserData('id');
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->usuario_asignado = trim($values[17]); //codigo gestor
					$cliente->eliminado = 0;

					$cliente->save();
					$cliente_id = $cliente->id;
					$apellidos = $cliente->apellidos;
					// print_r($cliente);
				} else {
					$cliente = Cliente::porId($cliente_id);
					if ($values[2] != "") {
						$cliente->apellidos = trim($values[2]); ////numero de tarjeta
					}
					if ($values[4] != "") {
						$cliente->profesion_id = trim($values[4]); //tipo de cartera
					}
					if ($values[5] != "") {
						$cliente->experiencia_crediticia = trim($values[5]); //motivo
					}
					if ($values[6] != "") {
						$cliente->nombres = trim($values[6]); //nombre
					}
					if ($values[13] != "") {
						$cliente->lugar_trabajo = trim($values[13]); //ciudad domicilio
						$cliente->ciudad = trim($values[13]); //ciudad domicilio
					}
					if ($values[16] != "") {
						$cliente->zona = trim($values[16]); //seguro desgravamen
					}
					if ($values[17] != "") {
						$cliente->usuario_asignado = trim($values[17]); //codigo gestor
					}
					$cliente->fecha_modificacion = date("Y-m-d H:i:s");
					$cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					$cliente->save();
				}

				$producto_arr = explode(";", $values[2]);
				foreach ($producto_arr as $producto_txt) {
					$producto_id = 0;
					if (isset($productos_todos[$cliente_id])) {
						foreach ($productos_todos[$cliente_id] as $prod) {
							$existe = array_search(trim($producto_txt), $prod);
							if ($existe) {
								$producto_id = $prod['id'];
								break;
							}
						}
					}
					if ($producto_id == 0) {
						$producto = new Producto();
						$producto->institucion_id = @$extraInfo['institucion_id'];
						$producto->cliente_id = $cliente_id;

						$producto->campana_id = trim($values[2]); // numero de tarjeta
						$producto->producto = trim($values[1]); // codigo de abogado

						$producto->estado = 'asignado';

						$producto->fecha_gestionar = trim($values[0]); // fecha asignacion
						$producto->fecha_ingreso = date("Y-m-d H:i:s");

						$producto->fecha_modificacion = date("Y-m-d H:i:s");
						$producto->usuario_ingreso = \WebSecurity::getUserData('id');
						$producto->usuario_modificacion = \WebSecurity::getUserData('id');

						$producto->usuario_asignado = trim($values[17]); //codigo gestor

						$producto->eliminado = 0;

						$producto->save();
						$producto_id = $producto->id;

						$producto_campos = new ProductoCampos();
						$producto_campos->producto_id = $producto_id;

						$producto_campos->campo = trim($values[8]);
						$producto_campos->valor = trim($values[9]);

						$producto_campos->fecha_ingreso = date("Y-m-d H:i:s");
						$producto_campos->fecha_modificacion = date("Y-m-d H:i:s");
						$producto_campos->usuario_ingreso = \WebSecurity::getUserData('id');
						$producto_campos->usuario_modificacion = \WebSecurity::getUserData('id');
						$producto_campos->eliminado = 0;
						$producto_campos->save();


						$productos_procesados[] = $producto_id;
					} else {
						$set = [
							'estado' => 'asignado',
							'fecha_modificacion' => date("Y-m-d H:i:s"),
							'usuario_modificacion' => \WebSecurity::getUserData('id'),
							'usuario_asignado' => trim($values[17]), //codigo gestor,
							'fecha_gestionar' => trim($values[0]), // fecha asignacion,
						];
						$query = $db->update('producto')->set($set)->where('id',$producto_id)->execute();
						$productos_procesados[] = $producto_id;
					}
				}

				//PROCESO DE EMAILS
				if ($values[15] != '') {
					$email_arr = explode(";", $values[15]);
					foreach ($email_arr as $email_txt) {
						$email_id = 0;
						if (isset($email_todos[$cliente_id])) {
							foreach ($email_todos[$cliente_id] as $em) {
								$existe = array_search(trim($email_txt), $em);
								if ($existe) {
									$email_id = $em['id'];
									break;
								}
							}
						}
						if ($email_id == 0) {
							$email = new Email();
							$email->tipo = 'PERSONAL';
							$email->descripcion = 'TITULAR';
							$email->origen = 'IMPORTACION';
							$email->email = trim($email_txt);
							$email->bandera = 0;
							$email->modulo_id = $cliente_id;
							$email->modulo_relacionado = 'cliente';
							$email->fecha_ingreso = date("Y-m-d H:i:s");
							$email->fecha_modificacion = date("Y-m-d H:i:s");
							$email->usuario_ingreso = \WebSecurity::getUserData('id');
							$email->usuario_modificacion = \WebSecurity::getUserData('id');
							$email->eliminado = 0;
							$email->save();
						}
					}
				}

				//PROCESO DE TELEFONOS
				if ($values[10] != '') {
					$telefono_arr = explode(";", $values[10]);
					foreach ($telefono_arr as $telefono_txt) {
						$telefono_id = 0;
						if (isset($telefonos_todos[$cliente_id])) {
							foreach ($telefonos_todos[$cliente_id] as $tel) {
								$existe = array_search(trim($telefono_txt), $tel);
								if ($existe) {
									$telefono_id = $tel['id'];
									break;
								}
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

				if ($values[11] != '') {
					$telefono_arr = explode(";", $values[11]);
					foreach ($telefono_arr as $telefono_txt) {
						$telefono_id = 0;
						if (isset($telefonos_todos[$cliente_id])) {
							foreach ($telefonos_todos[$cliente_id] as $tel) {
								$existe = array_search(trim($telefono_txt), $tel);
								if ($existe) {
									$telefono_id = $tel['id'];
									break;
								}
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

				if ($values[12] != '') {
					$telefono_arr = explode(";", $values[12]);
					foreach ($telefono_arr as $telefono_txt) {
						$telefono_id = 0;
						if (isset($telefonos_todos[$cliente_id])) {
							foreach ($telefonos_todos[$cliente_id] as $tel) {
								$existe = array_search(trim($telefono_txt), $tel);
								if ($existe) {
									$telefono_id = $tel['id'];
									break;
								}
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
				if ($values[14] != '') {
					$direccion_arr = explode(";", $values[14]);
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
							$direccion->ciudad = trim($values[13]);
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

				$rep['total']++;
			}
			$time_end = microtime(true);
			$execution_time = ($time_end - $time_start) / 60;
			$rep['tiempo_ejecucion'] = $execution_time;
			$rep['idcarga'] = $carga->id;
			$carga->total_registros = $rep['total'];
			$carga->update();
			$pdo->commit();
			\Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorClientesPacificoExcel");
		} catch (\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorClientesPacificoExcel", $ex);
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