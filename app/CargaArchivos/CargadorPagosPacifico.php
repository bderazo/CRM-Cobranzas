<?php

namespace CargaArchivos;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\PagosPacifico;

class CargadorPagosPacifico
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
	
		// Inicializa $rep aquí
		$rep = [
			'total' => 0,
			'errores' => 0,
			'errorSistema' => null,
			'errorDatos' => [],
			'archivo' => $nombreArchivo,
			'idcarga' => null,
			'tiempo_ejecucion' => 0,
		];
	
		$hoytxt = (new \DateTime())->format('Y-m-d H:i:s');
		$pdo = $this->pdo;
		$pdo->beginTransaction();
		try {
			foreach ($it as $rowIndex => $values) {
				if ($rowIndex === 0 || empty($values[0])) {
					continue; // Saltar la fila de encabezado y filas vacías
				}
	
				// Asignar valores solo para la tabla pagos_pacifico
				$pagosPacifico = new PagosPacifico();
				$pagosPacifico->numero_tarjeta = trim($values[0]);
				$pagosPacifico->valor_pagado = trim($values[1]);
				$pagosPacifico->fecha_pago = trim($values[2]);
				$pagosPacifico->pago1 = trim($values[3]);
				$pagosPacifico->pago2 = trim($values[4]);
				$pagosPacifico->pago3 = trim($values[5]);
				$pagosPacifico->fecha_carga = $hoytxt; // Fecha actual
	
				$pagosPacifico->save(); // Solo guarda lo necesario
				$rep['total']++;
			}
	
			$pdo->commit();
		} catch (\Exception $ex) {
			$pdo->rollBack();
			$rep['errorSistema'] = $ex->getMessage();
		}
	
		return $rep;
	}
	}