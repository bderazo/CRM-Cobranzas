<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\ProductoSeguimiento;
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

        $begin = new \DateTime($filtros['fecha_inicio']);
        $end = new \DateTime($filtros['fecha_fin']);
        $end->setTime(0, 0, 1);
        $daterange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $clientes_asignacion = [];
        $clientes_asignacion_detalle_marca = [];
        foreach ($daterange as $date) {
            $clientes_asignacion = array_merge($clientes_asignacion, AplicativoDinersAsignaciones::getClientes($campana_ece, $ciclo, $date->format("Y-m-d")));
            $clientes_asignacion_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca($campana_ece, $ciclo, $date->format("Y-m-d"));
            foreach ($clientes_asignacion_marca as $key => $val) {
                foreach ($val as $key1 => $val1) {
                    if (!isset($clientes_asignacion_detalle_marca[$key][$key1])) {
                        $clientes_asignacion_detalle_marca[$key][$key1] = $val1;
                    }
                }
            }
        }

        //OBTENER SALDOS
        $saldos = AplicativoDinersSaldos::getTodosRangoFecha($filtros['fecha_inicio'], $filtros['fecha_fin']);

        //OBTENER EL CICLO Y REFINANCIAS DEL CICLO EN ESE RANGO DE FECHAS PARA COMPARA Y NO MOSTRAR
        $refinancia_ciclo = ProductoSeguimiento::getRefinanciaCiclo($filtros['fecha_inicio']);
        $notificado_ciclo = ProductoSeguimiento::getNotificadoCiclo($filtros['fecha_inicio']);

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
        foreach ($usuarios_telefonia as $ut){
            $ut['hora_7'] = 0;
            $ut['hora_8'] = 0;
            $ut['hora_9'] = 0;
            $ut['hora_10'] = 0;
            $ut['hora_11'] = 0;
            $ut['hora_12'] = 0;
            $ut['hora_13'] = 0;
            $ut['hora_14'] = 0;
            $ut['hora_15'] = 0;
            $ut['hora_16'] = 0;
            $ut['hora_17'] = 0;
            $ut['hora_18'] = 0;
            $ut['hora_19'] = 0;
            $ut['total'] = 0;
            $usuarios_telefonia[$ut['id']] = $ut;
        }

        $totales_hora = [
            'total_7' => 0,
            'total_8' => 0,
            'total_9' => 0,
            'total_10' => 0,
            'total_11' => 0,
            'total_12' => 0,
            'total_13' => 0,
            'total_14' => 0,
            'total_15' => 0,
            'total_16' => 0,
            'total_17' => 0,
            'total_18' => 0,
            'total_19' => 0,
            'total' => 0,
        ];

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, cl.ciudad, u.canal, cl.zona,
                             HOUR(ps.fecha_ingreso) AS hora_ingreso_seguimiento,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
            ->where('ps.institucion_id', 1)
            ->where('ps.eliminado', 0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['campana_usuario']){
            $fil = '"' . implode('","',$filtros['campana_usuario']) . '"';
            $q->where('u.campana IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
            $q->where('u.canal IN ('.$fil.')');
        }
        if (@$filtros['ciclo']){
            $fil = implode(',',$filtros['ciclo']);
            $q->where('addet.ciclo IN ('.$fil.')');
        }
        if (@$filtros['resultado']){
            $fil = implode(',',$filtros['resultado']);
            $q->where('ps.nivel_1_id IN ('.$fil.')');
        }
        if (@$filtros['accion']){
            $fil = implode(',',$filtros['accion']);
            $q->where('ps.nivel_2_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion']){
            $fil = implode(',',$filtros['descripcion']);
            $q->where('ps.nivel_3_id IN ('.$fil.')');
        }
        if (@$filtros['motivo_no_pago']){
            $fil = implode(',',$filtros['motivo_no_pago']);
            $q->where('ps.nivel_1_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['descripcion_no_pago']){
            $fil = implode(',',$filtros['descripcion_no_pago']);
            $q->where('ps.nivel_2_motivo_no_pago_id IN ('.$fil.')');
        }
        if (@$filtros['gestor']){
            $fil = implode(',',$filtros['gestor']);
            $q->where('ps.usuario_ingreso IN ('.$fil.')');
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
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
//        printDie($q->getQuery());
        $resumen = [];
        $seguimientos_id = [];
        $lista = $q->fetchAll();
        $data = [];
        foreach($lista as $res) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $res['tarjeta'] == 'INTERDIN' ? 'VISA' : $res['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$res['cliente_id']][$res['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    if ($res['nivel_2_id'] == 1859) {
                        //A LOS REFINANCIA YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
                        if(!isset($refinancia_ciclo[$res['cliente_id']][$res['ciclo']])) {
                            $refinancia[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
                        }
                    }elseif ($res['nivel_2_id'] == 1853) {
                        //A LOS NOTIFICADO YA LES IDENTIFICO PORQ SE VALIDA DUPLICADOS
                        if(!isset($notificado_ciclo[$res['cliente_id']][$res['ciclo']])) {
                            $notificado[$res['cliente_id']][$res['fecha_ingreso_seguimiento']] = $res;
                        }
                    }else{
                        //OBTENGO LAS GESTIONES POR CLIENTE Y POR DIA
                        $data[$res['cliente_id']][$res['fecha_ingreso_seguimiento']][] = $res;
                    }
                }
            }
        }

        $data1 = [];
        foreach ($data as $cliente_id => $val) {
            foreach ($val as $fecha_seguimiento => $val1) {
                foreach ($val1 as $valf) {
                    $data1[] = $valf;
                }
            }
        }
        foreach ($refinancia as $val) {
            foreach ($val as $val1) {
                $data1[] = $val1;
            }
        }
        foreach ($notificado as $val) {
            foreach ($val as $val1) {
                $data1[] = $val1;
            }
        }

        foreach ($data1 as $res){
            if(isset($usuarios_telefonia[$res['usuario_id']]['hora_'.$res['hora_ingreso_seguimiento']])){
                $usuarios_telefonia[$res['usuario_id']]['hora_'.$res['hora_ingreso_seguimiento']]++;
                $usuarios_telefonia[$res['usuario_id']]['total']++;
            }

            if(isset($totales_hora['total_'.$res['hora_ingreso_seguimiento']])){
                $totales_hora['total_'.$res['hora_ingreso_seguimiento']]++;
                $totales_hora['total']++;
            }
            $resumen[] = $res;
        }


        usort($usuarios_telefonia, fn($a, $b) => $a['nombre_completo'] <=> $b['nombre_completo']);

		$retorno['data'] = $usuarios_telefonia;
        $retorno['resumen'] = $resumen;
		$retorno['total'] = $totales_hora;
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


