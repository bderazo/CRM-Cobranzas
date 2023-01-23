<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class NegociacionesEjecutivo {
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

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'procesado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select("ps.*, CONCAT(u.apellidos,' ',u.nombres) AS gestor, addet.nombre_tarjeta, cl.cedula, 
							 addet.ciclo AS corte, u.canal, cl.nombres, addet.plazo_financiamiento, u.zona,
							 addet.saldo_actual_facturado_despues_abono,
							 addet.saldo_30_facturado_despues_abono, addet.saldo_60_facturado_despues_abono,
							 addet.saldo_90_facturado_despues_abono, addet.total_pendiente_facturado_despues_abono,
							 u.identificador,   
							 
							 
							 addet.saldo_30_facturado_despues_abono, addet.saldo_60_facturado_despues_abono,
							 addet.saldo_90_facturado_despues_abono")
			->where('ps.nivel_1_id',7)
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
		if (@$filtros['plaza_usuario']){
			$q->where('u.plaza',$filtros['plaza_usuario']);
		}
		if (@$filtros['fecha_inicio']){
			$hora = '00';
			if($filtros['hora_inicio'] != ''){
				$hora = $filtros['hora_inicio'];
			}
			$minuto = '00';
			if($filtros['minuto_inicio'] != ''){
				$minuto = $filtros['minuto_inicio'];
			}
			$fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso >= "'.$fecha.'"');
		}
		if (@$filtros['fecha_fin']){
			$hora = '00';
			if($filtros['hora_fin'] != ''){
				$hora = $filtros['hora_fin'];
			}
			$minuto = '00';
			if($filtros['minuto_fin'] != ''){
				$minuto = $filtros['minuto_fin'];
			}
			$fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
			$q->where('ps.fecha_ingreso <= "'.$fecha.'"');
		}
		$lista = $q->fetchAll();
		$data = [];
		//SUMAR TOTALES
		$total_cuentas = 0;
		$total_asignacion = 0;
		$total_porcentaje_productividad = 0;
		$total_contactadas = 0;
		$total_efectividad = 0;
		$total_porcentaje_cantactado = 0;
		$total_porcentaje_efectividad = 0;
		$total_negociaciones = 0;
		$total_porcentaje_produccion = 0;
		foreach($lista as $seg){

//			printDie($seg);

			$seg['asignacion'] = 0;
			if($seg['canal'] == 'TELEFONIA'){
				$seg['asignacion'] = 60;
			}
			if($seg['canal'] == 'CAMPO'){
				$seg['asignacion'] = 20;
			}
			$seg['porcentaje_productividad'] = ($seg['asignacion'] > 0) ? ($seg['cuentas'] / $seg['asignacion']) * 100 : 0;
			$seg['porcentaje_productividad'] = number_format($seg['porcentaje_productividad'],2,'.',',');
			$seg['observaciones'] = '';
			$seg['porcentaje_contactado'] = ($seg['cuentas'] > 0) ? ($seg['contactadas'] / $seg['cuentas']) * 100 : 0;
			$seg['porcentaje_contactado'] = number_format($seg['porcentaje_contactado'],2,'.',',');
			$seg['porcentaje_efectividad'] = ($seg['contactadas'] > 0) ? ($seg['efectividad'] / $seg['contactadas']) * 100 : 0;
			$seg['porcentaje_efectividad'] = number_format($seg['porcentaje_efectividad'],2,'.',',');
			$seg['porcentaje_produccion'] = ($seg['cuentas'] > 0) ? ($seg['negociaciones'] / $seg['cuentas']) * 100 : 0;
			$seg['porcentaje_produccion'] = number_format($seg['porcentaje_produccion'],2,'.',',');

			$total_cuentas = $total_cuentas + $seg['cuentas'];
			$total_asignacion = $total_asignacion + $seg['asignacion'];
			$total_contactadas = $total_contactadas + $seg['contactadas'];
			$total_efectividad = $total_efectividad + $seg['efectividad'];
			$total_negociaciones = $total_negociaciones + $seg['negociaciones'];
			$data[] = $seg;
		}
		$total_porcentaje_productividad = ($total_asignacion > 0) ? ($total_cuentas / $total_asignacion) * 100 : 0;
		$total_porcentaje_cantactado = ($total_cuentas > 0) ? ($total_contactadas / $total_cuentas) * 100 : 0;
		$total_porcentaje_efectividad = ($total_contactadas > 0) ? ($total_efectividad / $total_contactadas) * 100 : 0;
		$total_porcentaje_produccion = ($total_cuentas > 0) ? ($total_negociaciones / $total_cuentas) * 100 : 0;

//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [
			'total_cuentas' => $total_cuentas,
			'total_asignacion' => $total_asignacion,
			'total_porcentaje_productividad' => number_format($total_porcentaje_productividad,2,'.',','),
			'total_contactadas' => $total_contactadas,
			'total_efectividad' => $total_efectividad,
			'total_porcentaje_cantactado' => number_format($total_porcentaje_cantactado,2,'.',','),
			'total_porcentaje_efectividad' => number_format($total_porcentaje_efectividad,2,'.',','),
			'total_negociaciones' => $total_negociaciones,
			'total_porcentaje_produccion' => number_format($total_porcentaje_produccion,2,'.',','),
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


