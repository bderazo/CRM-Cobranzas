<?php

namespace Reportes;


class PetitionsClaimsReport {
	/** @var \PDO */
	var $pdo;
	
	var $rangos = [
		[0, 15],
		[16, 30],
		[31, 60],
		[61, 90],
		[91, null],
	];
	
	var $empresas = [];
	
	function petitionsClaims($filtros) {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('casopqr')->orderBy('fecha_creacion desc')
			->where("estado not in ('cerrado', 'anulado')")
			->select(null)->select('id, fecha_creacion, estado');
		if ($this->empresas) {
			$q->where('concesionario_id', $this->empresas);
		}
		// filtros, etc.
		$lista = $q->fetchAll();
		$grupos = [];
		foreach ($this->rangos as $rango) {
			$key = $this->keyRango($rango);
			$nombre = $rango[0];
			if ($rango[1]) $nombre .= '-' . $rango[1];
			else $nombre = '> ' . $rango[0];
			$nombre .= ' dias';
			$grupos[$key] = ['min' => $rango[0], 'max' => $rango[1], 'total' => 0, 'pasado' => 0, 'comp' => null, 'rango' => $nombre];
		}
		$hoy = new \DateTime();
		$pasadoFormat = date('Y-m-d', strtotime('last sunday')); // sunday
//		echo $pasadoFormat . "\n";
		$pasado = new \DateTime($pasadoFormat);
		
		echo '<pre>';
		foreach ($lista as $row) {
			$fecha = new \DateTime($row['fecha_creacion']);
			$diff = $hoy->diff($fecha);
			
			
			$key = $this->encontrarRango($diff->days);
			if ($key) $grupos[$key]['total'] += 1;

//			echo $row['id'] . ' - ';
//			echo $row['fecha_creacion'] . ' - ';
//			echo 'dias ' . $diff->days . ' - ' . $key;
//			echo "\n";
			
			// semana pasada
			$diff2 = $pasado->diff($fecha);
			$key = $this->encontrarRango($diff2->days);
			if ($key) $grupos[$key]['pasado'] += 1;
		}
//		die();
		
		$items = array_values($grupos);
		foreach ($items as &$item) {
			if ($item['total'] > $item['pasado'])
				$item['comp'] = 'up';
			if ($item['total'] < $item['pasado'])
				$item['comp'] = 'down';
			if ($item['total'] == $item['pasado'])
				$item['comp'] = 'same';
		}
		return $items;
		
	}
	
	function encontrarRango($numero) {
		foreach ($this->rangos as $rango) {
			$check = $numero >= $rango[0];
			if ($rango[1])
				$check = $check && ($numero <= $rango[1]);
			if ($check)
				return $this->keyRango($rango);
		}
		return false;
	}
	
	protected function keyRango($rango) {
		$k = $rango[0];
		if ($rango[1]) $k .= '-' . $rango[1];
		return $k;
	}
	
	//// ---------------
	
	function top10CasosPorModelo() {
		$db = new \FluentPDO($this->pdo);
		$lista = $db->from('casopqr')->select(null)
			->select('modelo, familia, count(*) as cuenta')
			->where("estado not in ('cerrado', 'anulado')")
			->groupBy('modelo, familia')
			->orderBy('cuenta desc, modelo')
			->limit(10)->fetchAll();
		
		foreach ($lista as &$row) {
			if (!$row['modelo'])
				$row['modelo'] = $row['familia'];
			unset($row['familia']);
		}
		
		return $lista;
	}
	
	function top10Fallas() {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('casopqr')->select(null)
			->select('count(*) total, familia, falla_sistema,falla_componente,falla_detalle')
			->where("estado not in ('cerrado', 'anulado')")
			->where('tipo', 'falla_tecnica')
			->where('falla_detalle is not null')// solo los que tienen registrada la falla
			->groupBy('familia,falla_sistema,falla_componente,falla_detalle')->orderBy('count(*) desc')->limit(10);
		if ($this->empresas)
			$q->where('concesionario_id', $this->empresas);
		$tabla = $q->fetchAll();
		return $tabla;
	}
}