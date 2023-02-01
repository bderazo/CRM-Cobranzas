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

				if($values[0] != ''){
					$paleta_arbol_n1 = new PaletaArbol();
					$paleta_arbol_n1->paleta_id = $paleta_id;
					$paleta_arbol_n1->nivel = 1;
					$paleta_arbol_n1->valor = $values[0];
					$paleta_arbol_n1->codigo = $values[2];
					$paleta_arbol_n1->peso = $values[1];
					$paleta_arbol_n1->save();
				}

				if($values[3] != ''){
					$paleta_arbol_n2 = new PaletaArbol();
					$paleta_arbol_n2->paleta_id = $paleta_id;
					$paleta_arbol_n2->nivel = 2;
					$paleta_arbol_n2->valor = $values[3];
					$paleta_arbol_n2->codigo = $values[5];
					$paleta_arbol_n2->peso = $values[4];
					$paleta_arbol_n2->padre_id = $paleta_arbol_n1->id;
					$paleta_arbol_n2->save();
				}

				if($values[6] != ''){
					$paleta_arbol_n3 = new PaletaArbol();
					$paleta_arbol_n3->paleta_id = $paleta_id;
					$paleta_arbol_n3->nivel = 3;
					$paleta_arbol_n3->valor = $values[6];
					$paleta_arbol_n3->codigo = $values[8];
					$paleta_arbol_n3->peso = $values[7];
					$paleta_arbol_n3->padre_id = $paleta_arbol_n2->id;
					$paleta_arbol_n3->save();
				}

				if($values[9] != ''){
					$paleta_arbol_n4 = new PaletaArbol();
					$paleta_arbol_n4->paleta_id = $paleta_id;
					$paleta_arbol_n4->nivel = 4;
					$paleta_arbol_n4->valor = $values[9];
					$paleta_arbol_n4->codigo = $values[11];
					$paleta_arbol_n4->peso = $values[10];
					$paleta_arbol_n4->padre_id = $paleta_arbol_n3->id;
					$paleta_arbol_n4->save();
				}
				$rep['total']++;
			}

			$time_end = microtime(true);

			$execution_time = ($time_end - $time_start)/60;
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