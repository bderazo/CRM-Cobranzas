<?php

namespace PagosPacifico;

use Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser;
use Models\PagosPacificio;

class CargaArchivoPagos
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
        $it = $book->createRowIterator(0); // Asume que la hoja correcta es la primera
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

            foreach ($it as $rowIndex => $values) {
                // Omitir la primera fila si es cabecera
                if ($rowIndex === 1) {
                    continue;
                }

                // Si la fila no tiene un valor en la primera columna, saltar
                if ($values[0] == '') {
                    continue;
                }

                // Asumimos que las columnas del archivo de Excel son las siguientes:
                // 0 - ID de pago
                // 1 - Monto
                // 2 - Fecha de pago
                // 3 - Descripción

                $pago = new PagosPacificio();
                $pago->pago_id = trim($values[0]);
                $pago->monto = trim($values[1]);
                $pago->fecha_pago = trim($values[2]);
                $pago->descripcion = trim($values[3]);
                $pago->fecha_ingreso = $hoytxt;
                $pago->fecha_modificacion = $hoytxt;
                $pago->usuario_ingreso = \WebSecurity::getUserData('id');
                $pago->usuario_modificacion = \WebSecurity::getUserData('id');
                $pago->save();

                $rep['total']++;
            }

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start) / 60;
            $rep['tiempo_ejecucion'] = $execution_time;

            $pdo->commit();
            \Auditor::info("Archivo '$nombreArchivo' cargado en pagos_pacificio", "CargaArchivoPagos");
        } catch (\Exception $ex) {
            \Auditor::error("Error en la carga de pagos", "CargaArchivoPagos", $ex);
            $pdo->rollBack();
            $rep['errorSistema'] = $ex;
        }

        return $rep;
    }
}
