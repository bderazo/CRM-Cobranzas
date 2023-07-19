<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class InformeJornada {
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

//		//BUSCAR USUARIOS DINERS CON ROL DE GESTOR
//		$plaza = '';
//		if (@$filtros['plaza_usuario']){
//			$plaza = $filtros['plaza_usuario'];
//		}
//		$usuarios_gestores = Usuario::getUsuariosGestoresDiners($plaza);

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select("u.plaza, CONCAT(u.apellidos,' ',u.nombres) AS gestor, u.canal,
							COUNT(*) 'cuentas',
							COUNT(IF(ps.nivel_1_id = 1839 OR ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861, 1, NULL)) 'contactadas',
							COUNT(IF(ps.nivel_1_id = 1855, 1, NULL)) 'efectividad',
							COUNT(IF(ps.nivel_1_id = 1855 OR ps.nivel_1_id = 1861, 1, NULL)) 'negociaciones'")
			->where('ps.institucion_id',1)
			->where('ps.eliminado',0);
		if (@$filtros['plaza_usuario']){
			$fil = '"' . implode('","',$filtros['plaza_usuario']) . '"';
			$q->where('u.plaza IN ('.$fil.')');
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
            $hora = '00';
            if($filtros['hora_inicio'] != ''){
                $hora = $filtros['hora_inicio'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_inicio'] != ''){
                $minuto = $filtros['minuto_inicio'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_inicio'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso >= "'.$fecha.'"');
        }
        if (@$filtros['fecha_fin']){
            $hora = '00';
            if($filtros['hora_fin'] != ''){
                $hora = $filtros['hora_fin'];
            }
            $hora = strlen($hora) == 1 ? '0'.$hora : $hora;
            $minuto = '00';
            if($filtros['minuto_fin'] != ''){
                $minuto = $filtros['minuto_fin'];
            }
            $minuto = strlen($minuto) == 1 ? '0'.$minuto : $minuto;
            $fecha = $filtros['fecha_fin'] . ' ' . $hora . ':' . $minuto . ':00';
            $q->where('ps.fecha_ingreso <= "'.$fecha.'"');
        }
        $q->groupBy('u.id');
        $q->orderBy('u.plaza, u.apellidos');
        $q->disableSmartJoin();
		$lista = $q->fetchAll();
		$data = [];
		//SUMAR TOTALES
		$total_cuentas = 0;
		$total_asignacion = 0;
		$total_contactadas = 0;
		$total_efectividad = 0;
		$total_negociaciones = 0;
        $total_ejecutivos = 0;
		foreach($lista as $seg){
			$seg['asignacion'] = 0;
			if($seg['canal'] == 'TELEFONIA'){
				$seg['asignacion'] = 60;
			}
			if(($seg['canal'] == 'CAMPO') || ($seg['canal'] == 'AUXILIAR TELEFONIA')){
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

            $total_ejecutivos++;

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
            'total_ejecutivos' => $total_ejecutivos,
            'canal' => 'DOMICILIO EXTERNO',
            'empresa' => 'MEGACOB',
            'portafolio' => '',
		];

		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


