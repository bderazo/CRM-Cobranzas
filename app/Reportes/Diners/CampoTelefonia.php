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

class CampoTelefonia {
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

        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes();
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca();

        //OBTENER SALDOS
//        $saldos = AplicativoDinersSaldos::getTodosFecha();

		//BUSCAR SEGUIMIENTOS
//		$q = $db->from('producto_seguimiento ps')
//			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
//			->select(null)
//			->select("u.id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor,
//			                COUNT(IF(ps.nivel_2_id = 1859, 1, NULL)) 'refinancia',
//							COUNT(IF(ps.nivel_2_id = 1853, 1, NULL)) 'notificado',
//							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'cierre_efectivo',
//							COUNT(IF(ps.nivel_1_id = 1839, 1, NULL)) 'cierre_no_efectivo',
//							COUNT(IF(ps.nivel_1_id = 1847, 1, NULL)) 'mensaje_tercero',
//							COUNT(IF(ps.nivel_1_id = 1799, 1, NULL)) 'no_ubicado',
//							COUNT(IF(ps.nivel_1_id = 1873, 1, NULL)) 'regularizacion'")
//			->where('ps.institucion_id',1)
//			->where('ps.eliminado',0);
        $q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
            ->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
            ->innerJoin('cliente cl ON cl.id = ps.cliente_id')
            ->select(null)
            ->select("ps.*, u.id AS usuario_id, u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.nombres, 
                             cl.cedula, addet.nombre_tarjeta AS tarjeta, addet.ciclo, u.canal, cl.zona")
//            ->where('ps.nivel_1_id IN (1855, 1839, 1861)')
            ->where('ps.institucion_id',1)
            ->where('ps.eliminado',0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
            $q->where('u.canal IN ('.$fil.')');
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
        $lista = $q->fetchAll();
		$data = [];
		//SUMAR TOTALES
		$total_refinancia = 0;
		$total_notificado = 0;
		$total_cierre_efectivo = 0;
		$total_cierre_no_efectivo = 0;
		$total_mensaje_tercero = 0;
		$total_no_ubicado = 0;
		$total_regularizacion = 0;
		$total_total = 0;
		foreach($lista as $res){
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$res['cliente_id']][$res['tarjeta']])) {
                if (!isset($usuario_gestion[$res['usuario_id']])) {
                    $usuario_gestion[$res['usuario_id']] = [
                        'plaza' => $res['plaza'],
                        'gestor' => $res['gestor'],
                        'refinancia' => 0,
                        'notificado' => 0,
                        'cierre_efectivo' => 0,
                        'cierre_no_efectivo' => '',
                        'mensaje_tercero' => 0,
                        'no_ubicado' => 0,
                        'regularizacion' => 0,
                        'total' => 0,
                    ];
                }
                if ($res['nivel_2_id'] == 1859) {
                    $usuario_gestion[$res['usuario_id']]['refinancia']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_refinancia++;
                    $total_total++;
                }
                if ($res['nivel_2_id'] == 1853) {
                    $usuario_gestion[$res['usuario_id']]['notificado']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_notificado++;
                    $total_total++;
                }
                if ($res['nivel_1_id'] == 1855) {
                    $usuario_gestion[$res['usuario_id']]['cierre_efectivo']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_cierre_efectivo++;
                    $total_total++;
                }
                if ($res['nivel_1_id'] == 1839) {
                    $usuario_gestion[$res['usuario_id']]['cierre_no_efectivo']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_cierre_no_efectivo++;
                    $total_total++;
                }
                if ($res['nivel_1_id'] == 1847) {
                    $usuario_gestion[$res['usuario_id']]['mensaje_tercero']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_mensaje_tercero++;
                    $total_total++;
                }
                if ($res['nivel_1_id'] == 1799) {
                    $usuario_gestion[$res['usuario_id']]['no_ubicado']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_no_ubicado++;
                    $total_total++;
                }
                if ($res['nivel_1_id'] == 1873) {
                    $usuario_gestion[$res['usuario_id']]['regularizacion']++;
                    $usuario_gestion[$res['usuario_id']]['total']++;
                    $total_regularizacion++;
                    $total_total++;
                }
            }
//			$data[] = $res;
		}


//		printDie($usuario_gestion);

		$retorno['data'] = $usuario_gestion;
		$retorno['total'] = [
			'total_refinancia' => $total_refinancia,
			'total_notificado' => $total_notificado,
			'total_cierre_efectivo' => $total_cierre_efectivo,
			'total_cierre_no_efectivo' => $total_cierre_no_efectivo,
			'total_mensaje_tercero' => $total_mensaje_tercero,
			'total_no_ubicado' => $total_no_ubicado,
			'total_regularizacion' => $total_regularizacion,
			'total_total' => $total_total,
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


