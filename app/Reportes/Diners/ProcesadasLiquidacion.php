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

class ProcesadasLiquidacion
{
	/** @var \PDO */
	var $pdo;

	/**
	 *
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);

        $ciclo = isset($filtros['ciclo']) ? $filtros['ciclo'] : [];

        $clientes_asignacion = AplicativoDinersAsignaciones::getClientes([],$ciclo);
        $clientes_asignacion_detalle_marca = AplicativoDinersAsignaciones::getClientesDetalleMarca([],$ciclo);

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
            ->innerJoin('aplicativo_diners_detalle addet ON ps.id = addet.producto_seguimiento_id AND addet.eliminado = 0')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, cl.cedula, 
							 addet.ciclo AS corte, u.canal AS canal_usuario, cl.nombres, addet.plazo_financiamiento, 
							 u.identificador AS area_usuario, u.plaza AS zona, cl.id AS id_cliente,
							 addet.edad_cartera, cl.zona AS zona_cuenta,
							 addet.nombre_tarjeta AS tarjeta, addet.ciclo")
            ->where('ps.nivel_3_id IN (1860)')
			->where('ps.institucion_id', 1)
			->where('ps.eliminado', 0);
        if (@$filtros['plaza_usuario']){
            $fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
            $q->where('u.plaza IN ('.$fil.')');
        }
        if (@$filtros['canal_usuario']){
            $fil = '"' . implode('","',$filtros['canal_usuario']) . '"';
            $q->where('u.canal IN ('.$fil.')');
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
        $fil = implode(',',$clientes_asignacion);
        $q->where('ps.cliente_id IN ('.$fil.')');
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		foreach($lista as $seg) {
            //VERIFICO SI EL CLIENTE Y LA TARJETA ESTAN ASIGNADAS
            if(isset($clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']])){
                $seg['inicio'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['fecha_inicio'];
                $seg['fin'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['fecha_fin'];
                $seg['fecha_envio'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['fecha_asignacion'];
                $seg['negociacion_asignacion'] = '';
                $seg['campana'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['campana'];
                $seg['campana_ece'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['campana_ece'];
                $seg['cuenta'] = $seg['tarjeta'] . $seg['cedula'];
                $seg['fecha_asignacion'] = $clientes_asignacion_detalle_marca[$seg['cliente_id']][$seg['tarjeta']]['fecha_asignacion'];

                $data[] = $seg;
            }
		}

//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [];

		return $retorno;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


