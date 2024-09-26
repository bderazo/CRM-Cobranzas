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
use Models\Perfil;
use Models\UsuarioInstitucion;
use Models\Institucion;
use Models\UsuarioPerfil;
use Models\ProductoCampos;
use Models\Telefono;
use Models\Usuario;

class CargadorUsuariosPacifico
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
		print_r($extraInfo);
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
			$carga->tipo = 'usuarios pacifico';
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
			$usuarios_todos = Usuario::getTodos();
			$instituciones = Institucion::getInstituciones();
			$perfiles = Perfil::getCodigos();
			foreach ($it as $rowIndex => $values) {
				if (($rowIndex === 1)) {
					continue;
				}
				if ($values[0] == '')
					continue;

				$cliente_id = 0;
				$institucion_id = 0;
				foreach ($usuarios_todos as $cl) {
					$existe_cedula = array_search(trim($values[1]), $cl);

					if ($existe_cedula) {
						$cliente_id = $cl['id'];
						echo "Mensaje en la consola del servidor\n + $cliente_id";

						break;
					}
				}
				if ($cliente_id == 0) {
					$cliente = new Usuario();
					$cliente->username = trim($values[0]);
					$cliente->password = "Global@2024*";
					$cliente->fecha_creacion = date("Y-m-d H:i:s");
					$cliente->nombres = trim($values[0]); // para almacenar el valor riesgo
					$cliente->apellidos = trim($values[0]);
					// $cliente->email = trim($values[7]);
					$cliente->fecha_ultimo_cambio = date("Y-m-d H:i:s");
					$cliente->es_admin = 'no';
					$cliente->activo = 'si';
					$cliente->cambiar_password = 0;
					// $cliente->canal = \WebSecurity::getUserData('id');
					// $cliente->campana = \WebSecurity::getUserData('id');
					// $cliente->identificador = trim($values[1]);
					// $cliente->plaza = 0;
					// $cliente->equipo = 0;
					// $cliente->error_contraseña = 0;
					$cliente->save();
					$cliente_id = $cliente->id;
					// print_r($cliente);
				} else {
					// $cliente = Usuario::porId($cliente_id);
					// if ($values[0] != '') {
					// 	$cliente->nombres = trim($values[1]);
					// }
					// if ($values[1] != '') {
					// 	$cliente->cedula = trim($values[2]);
					// }
					// if ($values[2] != '') {
					// 	$cliente->profesion_id = trim($values[3]);
					// }
					// if ($values[4] != '') {
					// 	$cliente->tipo_referencia_id = trim($values[4]);
					// }
					// if ($values[5] != '') {
					// 	$cliente->zona = trim($values[5]);
					// }
					// if ($values[7] != '') {
					// 	$cliente->ciudad = trim($values[7]);
					// }
					// $cliente->fecha_modificacion = date("Y-m-d H:i:s");
					// $cliente->usuario_modificacion = \WebSecurity::getUserData('id');
					// $cliente->save();
				}
				foreach ($instituciones as $institucion) {
					if ($institucion['nombre'] == "Pacifico") {
						$institucion_id = $institucion['id'];
						break;
					}
				}
				if ($institucion_id !== 0) {
					$institucion = new UsuarioInstitucion();
					$institucion->usuario_id = $cliente_id;
					$institucion->institucion_id = $institucion_id;
					$institucion->fecha_ingreso = $hoytxt;
					$institucion->fecha_modificacion = $hoytxt;
					$institucion->save();
				}
				foreach ($perfiles as $perfil) {
					if (strcasecmp($perfil['identificador'], trim($values[2])) == 0) {
						print_r($perfil);
						$perfil_id = $perfil['id'];
						break;
					}
				}
				
				if ($perfil_id !== 0) {
					$perfil = new UsuarioPerfil();
					$perfil->usuario_id = $cliente_id;
					$perfil->perfil_id = $perfil_id;
					$perfil->fecha_ingreso = $hoytxt;
					$perfil->fecha_modificacion = $hoytxt;
					$perfil->save();
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
			\Auditor::info("Archivo '$nombreArchivo' cargado", "CargadorUsuariosPacifico");
		} catch (\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorUsuariosPacifico", $ex);
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