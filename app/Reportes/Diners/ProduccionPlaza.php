<?php

namespace Reportes\Diners;

use General\ListasSistema;
use Models\GenerarPercha;
use Models\OrdenExtrusion;
use Models\OrdenCB;
use Models\TransformarRollos;
use Models\Usuario;

class ProduccionPlaza {
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

		//BUSCAR USUARIOS DINERS CON ROL DE GESTOR
		$usuarios_gestores = Usuario::getUsuariosGestoresDiners();

		//BUSCAR SEGUIMIENTOS
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select('ps.*, u.id AS id_usuario, addet.nombre_tarjeta, addet.saldo_actual_facturado_despues_abono,
							 addet.saldo_30_facturado_despues_abono, addet.saldo_60_facturado_despues_abono,
							 addet.saldo_90_facturado_despues_abono')
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
		$data_contar = [];
		//CONTAR LOS USUARIOS QUE HICIERON SEGUIMIENTOS
		foreach($lista as $seg){
			if(isset($data_contar[$seg['id_usuario']][$seg['canal']][$seg['nombre_tarjeta']])){
				$data_contar[$seg['id_usuario']][$seg['canal']][$seg['nombre_tarjeta']]++;
			}else{
				$data_contar[$seg['id_usuario']][$seg['canal']]['DINERS'] = 0;
				$data_contar[$seg['id_usuario']][$seg['canal']]['INTERDIN'] = 0;
				$data_contar[$seg['id_usuario']][$seg['canal']]['DISCOVER'] = 0;
				$data_contar[$seg['id_usuario']][$seg['canal']]['MASTERCARD'] = 0;
			}
		}

		//UNIR CON LOS ASESORES QUE NO REALIZARON GESTIONES
		$data = [];
		foreach($usuarios_gestores as $ug){
			if(isset($data_contar[$ug['id']])){
				foreach($data_contar[$ug['id']] as $k => $v){
					$d['plaza'] = $ug['plaza'];
					$d['ejecutivo'] = $ug['nombres'];
					$d['canal'] = $k;
					$d['diners'] = $v['DINERS'];
					$d['interdin'] = $v['INTERDIN'];
					$d['discover'] = $v['DISCOVER'];
					$d['mastercard'] = $v['MASTERCARD'];
					$d['total_general'] = $v['DINERS'] + $v['INTERDIN'] + $v['DISCOVER'] + $v['MASTERCARD'];
					$data[] = $d;
				}
			}else{
				if (@$filtros['plaza_usuario']){
					if ($filtros['plaza_usuario'] == $ug['plaza']){
						$d['plaza'] = $ug['plaza'];
						$d['ejecutivo'] = $ug['nombres'];
						$d['canal'] = $ug['canal'];
						$d['diners'] = 0;
						$d['interdin'] = 0;
						$d['discover'] = 0;
						$d['mastercard'] = 0;
						$d['total_general'] = 0;
						$data[] = $d;
					}
				}else {
					$d['plaza'] = $ug['plaza'];
					$d['ejecutivo'] = $ug['nombres'];
					$d['canal'] = $ug['canal'];
					$d['diners'] = 0;
					$d['interdin'] = 0;
					$d['discover'] = 0;
					$d['mastercard'] = 0;
					$d['total_general'] = 0;
					$data[] = $d;
				}
			}
		}

		//AGRUPAR POR PLAZA DEL USUARIO
		$data_plaza = [];
		foreach($data as $d){
			$data_plaza[$d['plaza']][] = $d;
		}
		ksort($data_plaza);

		//TOTALES POR PLAZA
		$total_diners = 0;
		$total_interdin = 0;
		$total_discover = 0;
		$total_mastercard = 0;
		$total_general = 0;
		$data_totales = [];
		foreach($data_plaza as $k => $v){
			$total_plaza_diners = 0;
			$total_plaza_interdin = 0;
			$total_plaza_discover = 0;
			$total_plaza_mastercard = 0;
			$total_plaza_general = 0;
			foreach($v as $vd){
				$total_diners = $total_diners + $vd['diners'];
				$total_interdin = $total_interdin + $vd['interdin'];
				$total_discover = $total_discover + $vd['discover'];
				$total_mastercard = $total_mastercard + $vd['mastercard'];
				$total_general = $total_general + $vd['total_general'];

				$total_plaza_diners = $total_plaza_diners + $vd['diners'];
				$total_plaza_interdin = $total_plaza_interdin + $vd['interdin'];
				$total_plaza_discover = $total_plaza_discover + $vd['discover'];
				$total_plaza_mastercard = $total_plaza_mastercard + $vd['mastercard'];
				$total_plaza_general = $total_plaza_general + $vd['total_general'];
			}
			$data_totales[$k]['total_plaza_diners'] = $total_plaza_diners;
			$data_totales[$k]['total_plaza_interdin'] = $total_plaza_interdin;
			$data_totales[$k]['total_plaza_discover'] = $total_plaza_discover;
			$data_totales[$k]['total_plaza_mastercard'] = $total_plaza_mastercard;
			$data_totales[$k]['total_plaza_general'] = $total_plaza_general;
			$data_totales[$k]['plaza'] = $k;
			$data_totales[$k]['data'] = $v;
		}

		//ORDENAR EL ARRAY PARA IMPRIMIR
		$data = [];
		foreach($data_totales as $dt){
			$numItems = count($dt['data']);
			$i = 0;
			foreach($dt['data'] as $dat){
				if(++$i === $numItems) {
					$aux['plaza'] = 'TOTAL '.$dt['plaza'];
					$aux['ejecutivo'] = '';
					$aux['canal'] = '';
					$aux['diners'] = $dt['total_plaza_diners'];
					$aux['interdin'] = $dt['total_plaza_interdin'];
					$aux['discover'] = $dt['total_plaza_discover'];
					$aux['mastercard'] = $dt['total_plaza_mastercard'];
					$aux['total_general'] = $dt['total_plaza_general'];
					$data[] = $aux;
				}else{
					$data[] = $dat;
				}
			}
		}
//		printDie($data);

		$retorno['data'] = $data;
		$retorno['total'] = [
			'total_diners' => $total_diners,
			'total_interdin' => $total_interdin,
			'total_discover' => $total_discover,
			'total_mastercard' => $total_mastercard,
			'total_general' => $total_general,
		];

		//BUSCAR RECUPERO AL REFINANCIAR
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id AND p.eliminado = 0')
			->innerJoin('aplicativo_diners ad ON p.id = ad.producto_id AND ad.eliminado = 0')
			->innerJoin("aplicativo_diners_detalle addet ON ad.id = addet.aplicativo_diners_id AND addet.eliminado = 0 AND addet.tipo = 'gestionado'")
			->innerJoin('usuario u ON u.id = ps.usuario_ingreso')
			->select(null)
			->select('addet.nombre_tarjeta, addet.ciclo, COUNT(ps.id) AS cuentas, 
							 SUM(addet.saldo_actual_facturado_despues_abono) AS actuales,
							 SUM(addet.saldo_30_facturado_despues_abono) AS d30, 
							 SUM(addet.saldo_60_facturado_despues_abono) AS d60,
							 SUM(addet.saldo_90_facturado_despues_abono) AS d90, u.plaza, u.id')
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
		$q->orderBy('addet.nombre_tarjeta, addet.ciclo');
		$q->groupBy('addet.nombre_tarjeta, addet.ciclo');
		$lista = $q->fetchAll();
		$total_cuentas = 0;
		$total_actuales = 0;
		$total_d30 = 0;
		$total_d60 = 0;
		$total_d90 = 0;
		$data_grupo_tarjeta = [];
//		printDie($lista);
		foreach($lista as $l){
			$data_grupo_tarjeta[$l['nombre_tarjeta']][] = $l;
			$total_cuentas = $total_cuentas + $l['cuentas'];
			$total_actuales = $total_actuales + $l['actuales'];
			$total_d30 = $total_d30 + $l['d30'];
			$total_d60 = $total_d60 + $l['d60'];
			$total_d90 = $total_d90 + $l['d90'];
		}

		//TOTALES POR TARJETA
		$data_recupero = [];
		foreach($data_grupo_tarjeta as $key => $val){
			$total_cuentas_tarjeta = 0;
			$total_actuales_tarjeta = 0;
			$total_d30_tarjeta = 0;
			$total_d60_tarjeta = 0;
			$total_d90_tarjeta = 0;
			foreach($val as $v){
				$total_cuentas_tarjeta = $total_cuentas_tarjeta + $v['cuentas'];
				$total_actuales_tarjeta = $total_actuales_tarjeta + $v['actuales'];
				$total_d30_tarjeta = $total_d30_tarjeta + $v['d30'];
				$total_d60_tarjeta = $total_d60_tarjeta + $v['d60'];
				$total_d90_tarjeta = $total_d90_tarjeta + $v['d90'];
				$v['marca'] = $v['ciclo'];
				$v['actuales'] = number_format($v['actuales'],2,'.',',');
				$v['d30'] = number_format($v['d30'],2,'.',',');
				$v['d60'] = number_format($v['d60'],2,'.',',');
				$v['d90'] = number_format($v['d90'],2,'.',',');
				$data_recupero[] = $v;
			}
			$data_recupero[] = [
				'marca' => $key,
				'cuentas' => $total_cuentas_tarjeta,
				'actuales' => number_format($total_actuales_tarjeta,2,'.',','),
				'd30' => number_format($total_d30_tarjeta,2,'.',','),
				'd60' => number_format($total_d60_tarjeta,2,'.',','),
				'd90' => number_format($total_d90_tarjeta,2,'.',','),
			];
		}

//		printDie($data_recupero);

		$retorno['data_recupero'] = $data_recupero;
		$retorno['total_recupero'] = [
			'total_cuentas' => $total_cuentas,
			'total_actuales' => number_format($total_actuales,2,',','.'),
			'total_d30' => number_format($total_d30,2,',','.'),
			'total_d60' => number_format($total_d60,2,',','.'),
			'total_d90' => number_format($total_d90,2,',','.'),
		];




		return $retorno;
	}
	
	function exportar($filtros) {
		$q = $this->consultaBase($filtros);
		return $q;
	}
}


