<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class GestionesPorHora {
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

        $campana_ece = isset($filtros['campana_ece']) ? $filtros['campana_ece'] : [];
        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes($campana_ece,$ciclo);
        $clientes_asignacion_detalle = AplicativoDinersAsignaciones::getClientesDetalle($campana_ece,$ciclo);

        //USUARIOS TELEFONIA TODOS
		$plaza_usuario = [];
		$canal_usuario = [];
        $campana_usuario = [];
		if (@$filtros['plaza_usuario']){
			$plaza_usuario = $filtros['plaza_usuario'];
		}
		if (@$filtros['canal_usuario']){
			$canal_usuario = $filtros['canal_usuario'];
		}
        if (@$filtros['campana_usuario']){
            $campana_usuario = $filtros['campana_usuario'];
        }
        $usuarios_telefonia = Usuario::getTodosTelefonia($plaza_usuario, $canal_usuario, $campana_usuario);
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
            $ut['18'] = 0;
            $ut['19'] = 0;
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
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            if((count($filtros['canal_usuario']) == 1) && ($filtros['canal_usuario'][0] == 'TELEFONIA')){
                $q->where('u.canal',$filtros['canal_usuario'][0]);
                $q->where('u.campana','TELEFONIA');
                $q->where('u.identificador','MN');
            }else{
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
            }
        }
        if (@$filtros['resultado']){
            $fil = '"' . implode('","',$filtros['resultado']) . '"';
            $q->where('ps.nivel_1_id IN ('.$fil.')');
        }
        if (@$filtros['accion']){
            $fil = '"' . implode('","',$filtros['accion']) . '"';
            $q->where('ps.nivel_2_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion']){
            $fil = '"' . implode('","',$filtros['descripcion']) . '"';
            $q->where('ps.nivel_3_id IN ('.$fil.')');
        }
        if (@$filtros['motivo_no_pago']){
            $fil = '"' . implode('","',$filtros['motivo_no_pago']) . '"';
            $q->where('ps.nivel_1_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion_no_pago']){
            $fil = '"' . implode('","',$filtros['descripcion_no_pago']) . '"';
            $q->where('ps.nivel_2_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['fecha_inicio']){
            if(($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')){
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0'.$filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0'.$filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) >= "'.$filtros['fecha_inicio'].'"');
            }
        }
        if (@$filtros['fecha_fin']){
            if(($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')){
                $hora = strlen($filtros['hora_fin']) == 1 ? '0'.$filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0'.$filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                printDie($fecha);
                $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) <= "'.$filtros['fecha_fin'].'"');
            }
        }
        $q->where('ps.cliente_id',$clientes_asignacion);
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
        $total_18 = 0;
        $total_19 = 0;
        $total_general = 0;
        foreach($lista as $seg){
            if(isset($usuarios_telef[$seg['id']])) {
                $usuarios_telef[$seg['id']][$seg['hora']] = $seg['cantidad'];
            }
		}
        $data = [];
        foreach ($usuarios_telef as $ut){
            $total = $ut[7] + $ut[8] + $ut[9] + $ut[10] + $ut[11] + $ut[12] + $ut[13] + $ut[14] + $ut[15] + $ut[16] + $ut[17] + $ut[18] + $ut[19];
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
            $ut['hora_18'] = $ut[18];
            $ut['hora_19'] = $ut[19];

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
            $total_18 = $total_18 + $ut[18];
            $total_19 = $total_19 + $ut[19];
            $total_general = $total_general + $total;
            if($total > 0){
                $data[] = $ut;
            }
        }
//		printDie($data);


        //BUSCAR SEGUIMIENTOS RESUMEN
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, cl.cedula, 
                             cl.id AS id_cliente, HOUR(ps.fecha_ingreso) AS hora")
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            if((count($filtros['canal_usuario']) == 1) && ($filtros['canal_usuario'][0] == 'TELEFONIA')){
                $q->where('u.canal',$filtros['canal_usuario'][0]);
                $q->where('u.campana','TELEFONIA');
                $q->where('u.identificador','MN');
            }else{
                $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
                $q->where('u.canal IN ('.$fil.')');
            }
        }
        if (@$filtros['fecha_inicio']){
            if(($filtros['hora_inicio'] != '') && ($filtros['minuto_inicio'] != '')){
                $hora = strlen($filtros['hora_inicio']) == 1 ? '0'.$filtros['hora_inicio'] : $filtros['hora_inicio'];
                $minuto = strlen($filtros['minuto_inicio']) == 1 ? '0'.$filtros['minuto_inicio'] : $filtros['minuto_inicio'];
                $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) >= "'.$filtros['fecha_inicio'].'"');
            }
        }
        if (@$filtros['fecha_fin']){
            if(($filtros['hora_fin'] != '') && ($filtros['minuto_fin'] != '')){
                $hora = strlen($filtros['hora_fin']) == 1 ? '0'.$filtros['hora_fin'] : $filtros['hora_fin'];
                $minuto = strlen($filtros['minuto_fin']) == 1 ? '0'.$filtros['minuto_fin'] : $filtros['minuto_fin'];
                $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
                $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
            }else{
                $q->where('DATE(ps.fecha_ingreso) <= "'.$filtros['fecha_fin'].'"');
            }
        }
        $q->where('ps.cliente_id',$clientes_asignacion);
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $lista = $q->fetchAll();
        $resumen = [];
        foreach($lista as $res){
            $res['diners'] = '';
            $res['visa'] = '';
            $res['discover'] = '';
            $res['mastercard'] = '';
            $res['diners_ciclo'] = '';
            $res['visa_ciclo'] = '';
            $res['discover_ciclo'] = '';
            $res['mastercard_ciclo'] = '';
            if(isset($clientes_asignacion_detalle[$res['id_cliente']])) {
                foreach ($clientes_asignacion_detalle[$res['id_cliente']] as $cl) {
                    if (substr($cl['marca'], 0, 4) == 'DINE') {
                        $res['diners'] = 'SI';
                        $res['diners_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'VISA') {
                        $res['visa'] = 'SI';
                        $res['visa_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'DISC') {
                        $res['discover'] = 'SI';
                        $res['discover_ciclo'] = $cl['ciclo'];
                    }
                    if (substr($cl['marca'], 0, 4) == 'MAST') {
                        $res['mastercard'] = 'SI';
                        $res['mastercard_ciclo'] = $cl['ciclo'];
                    }
                }
            }
            $resumen[] = $res;
        }

		$retorno['data'] = $data;
        $retorno['resumen'] = $resumen;
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
            'total_18' => $total_18,
            'total_19' => $total_19,
            'total_general' => $total_general,
		];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


