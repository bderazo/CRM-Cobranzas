<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class Individual {
	/** @var \PDO */
	var $pdo;
	
	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }
	
	function calcular($filtros) {
		$lista = $this->consultaBase($filtros);
		return $lista;
	}
	
	function consultaBase($filtros) {
		$db = new \FluentPDO($this->pdo);

        //USUARIOS TELEFONIA TODOS
        $usuarios_telefonia = Usuario::getTodosTelefonia();
        $usuarios_telef = [];
        foreach ($usuarios_telefonia as $ut){
            $ut['7'] = 0;
            $ut['8'] = 0;
            $ut['9'] = 0;
            $ut['10'] = 0;
            $ut['11'] = 0;
            $ut['12'] = 0;
            $ut['13'] = 0;
            $ut['14'] = 0;
            $ut['15'] = 0;
            $ut['16'] = 0;
            $ut['17'] = 0;
            $usuarios_telef[$ut['id']] = $ut;
        }

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("u.id, CONCAT(u.apellidos,' ',u.nombres) AS gestor, HOUR(ps.fecha_ingreso) AS hora, 
							COUNT(ps.id) AS cantidad")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
        if(@$filtros['fecha_inicio']) {
            $q->where('DATE(ps.fecha_ingreso)',$filtros['fecha_inicio']);
        }else{
            $q->where('DATE(ps.fecha_ingreso)',date("Y-m-d"));
        }
        $q->groupBy('u.id, HOUR(ps.fecha_ingreso)');
        $q->orderBy('HOUR(ps.fecha_ingreso), u.apellidos');
//        printDie($q->getQuery());
		$lista = $q->fetchAll();
		$total_7 = 0;
		$total_8 = 0;
        $total_9 = 0;
        $total_10 = 0;
        $total_11 = 0;
        $total_12 = 0;
        $total_13 = 0;
        $total_14 = 0;
        $total_15 = 0;
        $total_16 = 0;
        $total_17 = 0;
        $total_general = 0;
        foreach($lista as $seg){
            if(isset($usuarios_telef[$seg['id']])) {
                $usuarios_telef[$seg['id']][$seg['hora']] = $seg['cantidad'];
            }
		}
        $data = [];
        foreach ($usuarios_telef as $ut){
            $total = $ut[7] + $ut[8] + $ut[9] + $ut[10] + $ut[11] + $ut[12] + $ut[13] + $ut[14] + $ut[15] + $ut[16] + $ut[17];
            $ut['total'] = $total;
            $ut['gestor'] = $ut['apellidos'] . ' ' . $ut['nombres'];

            $ut['hora_7'] = $ut[7];
            $ut['hora_8'] = $ut[8];
            $ut['hora_9'] = $ut[9];
            $ut['hora_10'] = $ut[10];
            $ut['hora_11'] = $ut[11];
            $ut['hora_12'] = $ut[12];
            $ut['hora_13'] = $ut[13];
            $ut['hora_14'] = $ut[14];
            $ut['hora_15'] = $ut[15];
            $ut['hora_16'] = $ut[16];
            $ut['hora_17'] = $ut[17];

            $total_7 = $total_7 + $ut[7];
            $total_8 = $total_8 + $ut[8];
            $total_9 = $total_9 + $ut[9];
            $total_10 = $total_10 + $ut[10];
            $total_11 = $total_11 + $ut[11];
            $total_12 = $total_12 + $ut[12];
            $total_13 = $total_13 + $ut[13];
            $total_14 = $total_14 + $ut[14];
            $total_15 = $total_15 + $ut[15];
            $total_16 = $total_16 + $ut[16];
            $total_17 = $total_17 + $ut[17];
            $total_general = $total_general + $total;
            $data[] = $ut;
        }
//		printDie($data);
		$retorno['data'] = $data;
		$retorno['total'] = [
			'total_7' => $total_7,
            'total_8' => $total_8,
            'total_9' => $total_9,
            'total_10' => $total_10,
            'total_11' => $total_11,
            'total_12' => $total_12,
            'total_13' => $total_13,
            'total_14' => $total_14,
            'total_15' => $total_15,
            'total_16' => $total_16,
            'total_17' => $total_17,
            'total_general' => $total_general,
		];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


