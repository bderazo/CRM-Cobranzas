<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\AplicativoDinersAsignaciones;
use Models\AplicativoDinersSaldos;
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

        //BUSCAR SEGUIMIENTOS
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->leftJoin('paleta_arbol pa ON pa.id = ps.nivel_3_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, cl.ciudad, u.canal, cl.zona,
                             DATE(ps.fecha_ingreso) AS fecha_ingreso_seguimiento,
                             pa.peso AS peso_paleta")
            ->where('ps.nivel_1_id IN (1855, 1839, 1847, 1799, 1861)')
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
        if (@$filtros['ciclo']){
            $fil = implode(',',$filtros['ciclo']);
            $q->where('addet.ciclo IN ('.$fil.')');
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
        $fil = '"' . implode('","',$clientes_asignacion) . '"';
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->orderBy('u.apellidos');
        $q->disableSmartJoin();
        $lista = $q->fetchAll();
        $data = [];
        $usuario_gestion = [];
        $verificar_duplicados = [];
        foreach($lista as $seg){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            $tarjeta_verificar = $seg['tarjeta'] == 'INTERDIN' ? 'VISA' : $seg['tarjeta'];
            if (isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$tarjeta_verificar])) {
                if (isset($saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']])) {
                    $saldos_arr = $saldos[$seg['cliente_id']][$seg['fecha_ingreso_seguimiento']];
                    $campos_saldos = json_decode($saldos_arr['campos'], true);
                    unset($saldos_arr['campos']);
                    $saldos_arr = array_merge($saldos_arr, $campos_saldos);

                    if (!isset($usuario_gestion[$seg['usuario_id']])) {
                        $meta_diaria = 0;
                        if (@$filtros['meta_diaria']) {
                            $meta_diaria = $filtros['meta_diaria'];
                        }
                        $usuario_gestion[$seg['usuario_id']] = [
                            'gestor' => $seg['gestor'],
                            'total_negociaciones' => 0,
                            'refinancia' => 0,
                            'notificado' => 0,
                            'cierre_efectivo' => 0,
                            'contactadas' => 0,
                            'seguimientos' => 0,
                            'contactabilidad' => 0,
                            'efectividad' => 0,
                            'meta_diaria' => $meta_diaria,
                            'meta_alcanzada' => 0,
                        ];
                    }
                    if ($seg['nivel_2_id'] == 1859) {
                        if (!isset($verificar_duplicados[$seg['cliente_id']][$seg['ciclo']])) {
                            $usuario_gestion[$seg['usuario_id']]['refinancia']++;

                            $verificar_duplicados[$seg['cliente_id']][$seg['ciclo']] = 1;
                        }
                    }
                    if ($seg['nivel_2_id'] == 1853) {
                        if (!isset($verificar_duplicados[$seg['cliente_id']][$seg['ciclo']])) {
                            $usuario_gestion[$seg['usuario_id']]['notificado']++;

                            $verificar_duplicados[$seg['cliente_id']][$seg['ciclo']] = 1;
                        }
                    }
                    if ($seg['nivel_1_id'] == 1855) {
                        $usuario_gestion[$seg['usuario_id']]['cierre_efectivo']++;
                    }
                    if (($seg['nivel_1_id'] == 1839) || ($seg['nivel_1_id'] == 1855)) {
                        $usuario_gestion[$seg['usuario_id']]['contactadas']++; //CIERRE EFECTIVO Y CIERRE NO EFECTIVO
                    }
                    if (($seg['nivel_1_id'] == 1839) || ($seg['nivel_1_id'] == 1855) ||
                        ($seg['nivel_1_id'] == 1847) || ($seg['nivel_1_id'] == 1799) ||
                        ($seg['nivel_1_id'] == 1861)) {
                        $usuario_gestion[$seg['usuario_id']]['seguimientos']++;
                    }
                }
            }
		}

        foreach ($usuario_gestion as $ug){
            $contactabilidad = $ug['seguimientos'] > 0 ? (($ug['contactadas'] / $ug['seguimientos']) * 100) : 0;
            $efectividad = $ug['contactadas'] > 0 ? (($ug['cierre_efectivo'] / $ug['contactadas']) * 100) : 0;
            $total_negociaciones = $ug['refinancia'] + $ug['notificado'];
            $meta_alcanzada = 0;
            if(($ug['meta_diaria'] > 0) && ($total_negociaciones > 0)){
                $meta_alcanzada =   ($total_negociaciones / $ug['meta_diaria']) * 100;
            }
            $ug['total_negociaciones'] = $total_negociaciones;
            $ug['contactabilidad'] = number_format($contactabilidad,2,'.',',');
            $ug['efectividad'] = number_format($efectividad,2,'.',',');
            $ug['meta_alcanzada'] = number_format($meta_alcanzada,2,'.',',');
            if($ug['seguimientos'] > 0){
                $data[] = $ug;
            }
        }
		$retorno['data'] = $data;
		$retorno['total'] = [];
		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


