<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\AplicativoDiners;
use Models\AplicativoDinersDetalle;
use Models\CargaArchivo;
use Models\Cliente;
use Models\Direccion;
use Models\Email;
use Models\PaletaArbol;
use Models\Producto;
use Models\Telefono;

class CargadorPaletaExcel
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

	function cargar($path, $extraInfo, $paleta_id)
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
			$carga->tipo = 'paleta';
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

			foreach($it as $rowIndex => $values) {
				if(($rowIndex === 1))
					continue;
				if($values[0] == '')
					continue;

				$nivel_1_todos = PaletaArbol::getNivel1Paleta($paleta_id);
				$nivel_2_todos = PaletaArbol::getNivel2Paleta($paleta_id);
				$nivel_3_todos = PaletaArbol::getNivel3Paleta($paleta_id);
				$nivel_4_todos = PaletaArbol::getNivel4Paleta($paleta_id);

				$nivel1_id = 0;
				foreach($nivel_1_todos as $niv) {
					if (array_search(trim($values[0]), $niv) !== FALSE ) {
						$nivel1_id = $niv['nivel1_id'];
						break;
					}
				}
				if($nivel1_id == 0) {
					$paleta_arbol_n1 = new PaletaArbol();
					$paleta_arbol_n1->paleta_id = $paleta_id;
					$paleta_arbol_n1->nivel = 1;
					$paleta_arbol_n1->valor = trim($values[0]);
					$paleta_arbol_n1->codigo = trim($values[1]);
					$paleta_arbol_n1->peso = trim($values[2]);
					$paleta_arbol_n1->mostrar_motivo_no_pago = trim($values[3]) == '' ? '' : 'si';
					$paleta_arbol_n1->mostrar_fecha_compromiso_pago = trim($values[4]) == '' ? '' : 'si';
					$paleta_arbol_n1->mostrar_valor_comprometido = trim($values[5]) == '' ? '' : 'si';
					$paleta_arbol_n1->save();
					$nivel1_id = $paleta_arbol_n1->id;
				}else{
					$paleta_arbol_n1 = PaletaArbol::porId($nivel1_id);
					$paleta_arbol_n1->codigo = trim($values[1]);
					$paleta_arbol_n1->peso = trim($values[2]);
					$paleta_arbol_n1->mostrar_motivo_no_pago = trim($values[3]) == '' ? '' : 'si';
					$paleta_arbol_n1->mostrar_fecha_compromiso_pago = trim($values[4]) == '' ? '' : 'si';
					$paleta_arbol_n1->mostrar_valor_comprometido = trim($values[5]) == '' ? '' : 'si';
					$paleta_arbol_n1->save();
				}


				if($values[6] != '') {
					$nivel2_id = 0;
					foreach($nivel_2_todos as $niv) {
						$existe = array_search(trim($values[6]), $niv);
						if($existe) {
							$nivel2_id = $niv['nivel2_id'];
							break;
						}
					}
					if($nivel2_id == 0) {
						$paleta_arbol_n2 = new PaletaArbol();
						$paleta_arbol_n2->paleta_id = $paleta_id;
						$paleta_arbol_n2->nivel = 2;
						$paleta_arbol_n2->valor = trim($values[6]);
						$paleta_arbol_n2->codigo = trim($values[7]);
						$paleta_arbol_n2->peso = trim($values[8]);
						$paleta_arbol_n2->mostrar_motivo_no_pago = trim($values[9]) == '' ? '' : 'si';
						$paleta_arbol_n2->mostrar_fecha_compromiso_pago = trim($values[10]) == '' ? '' : 'si';
						$paleta_arbol_n2->mostrar_valor_comprometido = trim($values[11]) == '' ? '' : 'si';
						$paleta_arbol_n2->padre_id = $nivel1_id;
						$paleta_arbol_n2->save();
						$nivel2_id = $paleta_arbol_n2->id;
					}else{
						$paleta_arbol_n2 = PaletaArbol::porId($nivel2_id);
						$paleta_arbol_n2->codigo = trim($values[7]);
						$paleta_arbol_n2->peso = trim($values[8]);
						$paleta_arbol_n2->mostrar_motivo_no_pago = trim($values[9]) == '' ? '' : 'si';
						$paleta_arbol_n2->mostrar_fecha_compromiso_pago = trim($values[10]) == '' ? '' : 'si';
						$paleta_arbol_n2->mostrar_valor_comprometido = trim($values[11]) == '' ? '' : 'si';
						$paleta_arbol_n2->save();
					}

					if($values[12] != '') {
						$nivel3_id = 0;
						foreach($nivel_3_todos as $niv) {
							$existe = array_search(trim($values[12]), $niv);
							if($existe) {
								$nivel3_id = $niv['nivel3_id'];
								break;
							}
						}
						if($nivel3_id == 0) {
							$paleta_arbol_n3 = new PaletaArbol();
							$paleta_arbol_n3->paleta_id = $paleta_id;
							$paleta_arbol_n3->nivel = 3;
							$paleta_arbol_n3->valor = trim($values[12]);
							$paleta_arbol_n3->codigo = trim($values[13]);
							$paleta_arbol_n3->peso = trim($values[14]);
							$paleta_arbol_n3->mostrar_motivo_no_pago = trim($values[15]) == '' ? '' : 'si';
							$paleta_arbol_n3->mostrar_fecha_compromiso_pago = trim($values[16]) == '' ? '' : 'si';
							$paleta_arbol_n3->mostrar_valor_comprometido = trim($values[17]) == '' ? '' : 'si';
							$paleta_arbol_n3->padre_id = $nivel2_id;
							$paleta_arbol_n3->save();
							$nivel3_id = $paleta_arbol_n3->id;
						} else {
							$paleta_arbol_n3 = PaletaArbol::porId($nivel3_id);
							$paleta_arbol_n3->codigo = trim($values[13]);
							$paleta_arbol_n3->peso = trim($values[14]);
							$paleta_arbol_n3->mostrar_motivo_no_pago = trim($values[15]) == '' ? '' : 'si';
							$paleta_arbol_n3->mostrar_fecha_compromiso_pago = trim($values[16]) == '' ? '' : 'si';
							$paleta_arbol_n3->mostrar_valor_comprometido = trim($values[17]) == '' ? '' : 'si';
							$paleta_arbol_n3->save();
						}

						if($values[18] != '') {
							$nivel4_id = 0;
							foreach($nivel_4_todos as $niv) {
								$existe = array_search(trim($values[18]), $niv);
								if($existe) {
									$nivel4_id = $niv['nivel4_id'];
									break;
								}
							}
							if($nivel4_id == 0) {
								$paleta_arbol_n4 = new PaletaArbol();
								$paleta_arbol_n4->paleta_id = $paleta_id;
								$paleta_arbol_n4->nivel = 4;
								$paleta_arbol_n4->valor = trim($values[18]);
								$paleta_arbol_n4->codigo = trim($values[19]);
								$paleta_arbol_n4->peso = trim($values[20]);
								$paleta_arbol_n4->mostrar_motivo_no_pago = trim($values[21]) == '' ? '' : 'si';
								$paleta_arbol_n4->mostrar_fecha_compromiso_pago = trim($values[22]) == '' ? '' : 'si';
								$paleta_arbol_n4->mostrar_valor_comprometido = trim($values[23]) == '' ? '' : 'si';
								$paleta_arbol_n4->padre_id = $nivel3_id;
								$paleta_arbol_n4->save();
								$nivel4_id = $paleta_arbol_n4->id;
							} else {
								$paleta_arbol_n4 = PaletaArbol::porId($nivel4_id);
								$paleta_arbol_n4->codigo = trim($values[19]);
								$paleta_arbol_n4->peso = trim($values[20]);
								$paleta_arbol_n4->mostrar_motivo_no_pago = trim($values[21]) == '' ? '' : 'si';
								$paleta_arbol_n4->mostrar_fecha_compromiso_pago = trim($values[22]) == '' ? '' : 'si';
								$paleta_arbol_n4->mostrar_valor_comprometido = trim($values[23]) == '' ? '' : 'si';
								$paleta_arbol_n4->save();
							}
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
			\Auditor::info("Archivo '$nombreArchivo'' cargado", "CargadorPaletaExcel");
		} catch(\Exception $ex) {
			\Auditor::error("Ingreso de carga", "CargadorPaletaExcel", $ex);
			$pdo->rollBack();
			$rep['errorSistema'] = $ex;
		}
		return $rep;
	}

	function procesarFila($rownum, $values, $file)
	{
		$data = [
			'hoja' => $values[0],
			'asesor_servicio' => $values[1],
			'cedula' => $values[2],
			'concesionario' => $values[3],
			'punto_servicio' => $values[4],
			'chasis' => $values[5],
			'modelo' => $values[6],
			'familia_modelo' => $values[7],
			'kilometraje' => $values[8],
			'centro_costo' => $values[9],
			'fecha_ingreso' => $this->getFecha($values[10]),
			'fecha_facturacion' => $this->getFecha($values[11]),
			'cliente' => $values[12],
			'direccion' => $this->getFecha($values[13], $values[13]), // WUUUT M8?
			'telefono' => $values[14],
			'ciudad' => $values[15],
			'status' => $values[16],
			'sub_status' => $values[17],
			'servicio' => $values[18],
			'f_uno' => $values[19],
			'f_dos' => $values[20],
			'f_tres' => $values[21],
			'f_uno_uno' => $values[22],
			'f_dos_uno' => $values[23],
			'q_uno' => $values[24],
			'q_dos' => $values[25],
			'q_tres' => $values[26],
			'q_cuatro' => $values[27],
			'q_cinco' => $values[28],
			'q_seis' => $values[29],
			'q_siete' => $values[30],
			's_tres' => $values[31],
			'verbalizacion' => $values[32],
			'fecha_atencion' => $this->getFecha($values[33]),
			'fecha_gestion' => $this->getFecha($values[34]),
			'fecha_divulgacion' => $this->getFecha($values[35]),
			'categoria' => $values[36],
			'usrgestion' => $values[37],
			'num_fila' => $rownum,
			'nombre_archivo' => $file
		];
		// trim all the things!
		foreach($data as $key => $val) {
			if($val && is_string($val))
				$data[$key] = trim($val);
		}

		$cedula = $data['cedula'];
		if($cedula) {
			if(strlen($cedula) == 9 || strlen($cedula) == 12)
				$data['cedula'] = '0' . $cedula;
		}
		// de aqui validar, o algo
		return $data;
	}

	function getFecha($value, $default = null)
	{
		if($value instanceof \DateTime)
			return $value->format('Y-m-d H:i:s');
		return $default;
	}
}