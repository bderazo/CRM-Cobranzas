<?php

namespace Reportes\Desperdicio;

class BodegaDesperdicio
{
	/** @var \PDO */
	var $pdo;

	/**
	 * constructor.
	 * @param \PDO $pdo
	 */
	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	function calcular($filtros)
	{
		$lista = $this->consultaBase($filtros);
		return $lista;
	}

	function consultaBase($filtros)
	{
		$db = new \FluentPDO($this->pdo);
		$pdo = $this->pdo;

		if($filtros['anio'] == '') {
			$lista['data'] = [];
			$lista['total'] = [];
			return $lista;
		}else{
			$anio = (int)$filtros['anio'];
			$anio_anterior = $anio - 1;
		}

		//SALDO ANIO ANTERIOR
		//INGRESOS
		$q = $db->from('desperdicio d')
			->select(null)
			->select('SUM(d.peso) peso_neto')
			->where('d.eliminado', 0)
			->where('EXTRACT(YEAR FROM d.fecha_ingreso) < '.$anio);
		$d = $q->fetch();
		$ingreso_anio_anterior = $d['peso_neto'];
		//EGRESOS
		$q = $db->from('desperdicio d')
			->select(null)
			->select('SUM(d.peso) peso_neto')
			->where('d.eliminado', 0)
			->where('EXTRACT(YEAR FROM d.fecha_modificacion) < '.$anio)
			->where("d.estado <> 'disponible'");
		$d = $q->fetch();
		$egreso_anio_anterior = $d['peso_neto'];
		$saldo_anio_anterior = $ingreso_anio_anterior - $egreso_anio_anterior;

		//DATOS INICIALES
		$data[] = [
			'fecha'=> 'SALDO '.$anio_anterior,
			'ingreso' => $saldo_anio_anterior,
			'ingreso_format' => number_format($saldo_anio_anterior,2,'.',','),
			'egreso' => 0,
			'egreso_format' => number_format(0,2,'.',','),
			'disponible' => $saldo_anio_anterior,
			'disponible_format' => number_format($saldo_anio_anterior,2,'.',','),
			'historico' => $saldo_anio_anterior,
			'historico_format' => number_format($saldo_anio_anterior,2,'.',','),
			'anio' => 0,
			'mes' => 0,
		];

		//DATOS DEL ANIO
		$total_ingreso = 0;
		$total_egreso = 0;
		$total_disponible = 0;
		$total_historico = 0;
		$indice_anterior = 0;
		for($mes = 1; $mes <= 12; $mes++){
			//INGRESOS
			$q = $db->from('desperdicio d')
				->select(null)
				->select('SUM(d.peso) peso_neto')
				->where('d.eliminado', 0)
				->where('EXTRACT(YEAR FROM d.fecha_ingreso) = '.$anio)
				->where('EXTRACT(MONTH FROM d.fecha_ingreso) = '.$mes);
			$d = $q->fetch();
			$ingreso = $d['peso_neto'] > 0 ? $d['peso_neto'] : 0;
			$total_ingreso = $total_ingreso + $ingreso;
			//EGRESOS
			$q = $db->from('desperdicio d')
				->select(null)
				->select('SUM(d.peso) peso_neto')
				->where('d.eliminado', 0)
				->where('EXTRACT(YEAR FROM d.fecha_modificacion) = '.$anio)
				->where('EXTRACT(MONTH FROM d.fecha_modificacion) = '.$mes)
				->where("d.estado <> 'disponible'");
			$d = $q->fetch();
			$egreso =  $d['peso_neto'] > 0 ? $d['peso_neto'] : 0;
			$total_egreso = $total_egreso + $egreso;
			$disponible = $data[$indice_anterior]['disponible'] + $ingreso - $egreso;
			$historico = $data[$indice_anterior]['historico'] + $ingreso;
			$total_disponible = $disponible;
			$total_historico = $historico;
			$aux = [
				'fecha'=> '1 '.$this->getNombreMes($mes).' - '.$this->getUltimoDiaMes($mes,$anio).' '.$this->getNombreMes($mes),
				'ingreso' => $ingreso,
				'ingreso_format' => number_format($ingreso,2,'.',','),
				'egreso' => $egreso,
				'egreso_format' => number_format($egreso,2,'.',','),
				'disponible' => $disponible,
				'disponible_format' => number_format($disponible,2,'.',','),
				'historico' => $historico,
				'historico_format' => number_format($historico,2,'.',','),
				'anio' => $anio,
				'mes' => $mes,
			];
			$data[] = $aux;
			$indice_anterior++;
		}
		$lista['data'] = $data;
		$lista['total'] = [
			'total_ingreso' => number_format($total_ingreso,2,'.',','),
			'total_egreso' => number_format($total_egreso,2,'.',','),
			'total_disponible' => number_format($total_disponible,2,'.',','),
			'total_historico' => number_format($total_historico,2,'.',','),
		];
		return $lista;
	}

	function exportar($filtros)
	{
		$q = $this->consultaBase($filtros);
		return $q;
	}

	function getNombreMes($numero_mes){
		if($numero_mes == 1)
			return 'ene';
		elseif($numero_mes == 2)
			return 'feb';
		elseif($numero_mes == 3)
			return 'mar';
		elseif($numero_mes == 4)
			return 'abr';
		elseif($numero_mes == 5)
			return 'may';
		elseif($numero_mes == 6)
			return 'jun';
		elseif($numero_mes == 7)
			return 'jul';
		elseif($numero_mes == 8)
			return 'ago';
		elseif($numero_mes == 9)
			return 'sep';
		elseif($numero_mes == 10)
			return 'oct';
		elseif($numero_mes == 11)
			return 'nov';
		elseif($numero_mes == 12)
			return 'dic';
		else
			return '';
	}

	function getUltimoDiaMes($numero_mes, $anio){
		$ultimo_dia = cal_days_in_month(CAL_GREGORIAN, $numero_mes, $anio);
		return $ultimo_dia;
	}
}

