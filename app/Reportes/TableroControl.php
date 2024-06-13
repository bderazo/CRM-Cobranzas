<?php
/**
 * Created by PhpStorm.
 * User: vegeta
 * Date: 5/15/2017
 * Time: 1:06 PM
 */

namespace Reportes;


use Catalogos\CatalogoCasospqr;
use General\ListasSistema;

class TableroControl {
	
	/** @var \PDO */
	var $pdo;
	
	function crear() {
		$db = new \FluentPDO($this->pdo);
		$todas = $db->from('casopqr')->count();
		$resueltos = $db->from('casopqr')->where('estado', 'resuelto')->count();
		$por = 0;
		if ($todas > 0)
			$por = ($resueltos / $todas) * 100;
		$data = [
			'cumplimiento' => floatval(ListasSistema::numero($por))
		];
		
		$sql = "SELECT con.nombre as concesionario, suc.nombre as sucursal, suc.zona, c.estado, count(c.*) as num
		FROM casopqr c
		join concesionario con on con.id = c.concesionario_id
		join sucursal suc on suc.id = c.sucursal_id
		group by con.nombre, suc.nombre, suc.zona, c.estado
		order by con.nombre, suc.nombre";
		
		$stmt = $this->pdo->query($sql, \PDO::FETCH_ASSOC);
		$all = $stmt->fetchAll();
		$tablaCumple = [];
		
		//TODO: zonas de algun lado?
		$cat = new CatalogoCasospqr(true);
		$listaZonas = $cat->getByKey('zonas');
		$zonas = [];
		foreach ($listaZonas as $key => $nombre) {
			$zonas[$key] = ['zona' => $nombre, 'total' => 0, 'por' => 0];
		}
		
		foreach ($all as $row) {
			$num = $row['num'];
			$zona = $row['zona'];
			$key = $row['concesionario'] . $row['sucursal'];
			if (empty($lista[$key]))
				$tablaCumple[$key] = ['con' => $row['concesionario'], 'suc' => $row['sucursal'], 'total' => 0, 'gestion' => 0, 'por' => null];
			$tablaCumple[$key]['total'] += $num;
			$zonas[$zona]['total'] += $num;
			if ($row['estado'] == 'resuelto')
				$tablaCumple[$key]['gestion'] += $num;
		}
		foreach ($tablaCumple as $key => &$row) {
			if ($row['gestion'] && $row['total']) {
				$por = ($row['gestion'] / $row['total']) * 100;
				$row['por'] = ListasSistema::numero($por) . '%';
			}
		}
		
		foreach ($zonas as $key => &$row) {
			if ($row['total']) {
				$por = ($row['total'] / $todas) * 100;
				$row['por'] = ListasSistema::numero($por) . '%';
			}
		}
		$zonas['total'] = ['zona' => 'TOTAL', 'total' => $todas, 'por' => '100%'];

		
		$data['tablaCumple'] = array_values($tablaCumple);
		$data['tablaZonas'] = array_values($zonas);
		return $data;
	}
	
	function calcularPor($total, $num) {
		return ($num * 100) / $total;
	}
	
	function reportesCSI($mes, $anio) {
		$db = new \FluentPDO($this->pdo);
		$conces = $db->from('concesionario')->where('nombre2 is not null')
			->select(null)->select('id, nombre, nombre2')->orderBy('nombre')->fetchAll('nombre2');
		$muestras = $db->from('muestra_periodo')->where('mes', $mes)->where('anio', $anio)->fetchAll('concesionario_id');
		$lista = [];
		$totales = [
			'muestras' => 0,
			'gestionados' => 0,
			'por' => 0,
		];
		foreach ($conces as $con) {
			$id = $con['id'];
			$dat = @$muestras[$id];
			$mue = $dat ? $dat['muestra'] : 0;
			$ges = $dat ? $dat['llamados'] : 0;
			$rec = [
				'concesionario' => $con['nombre'] . ' / ' . $con['nombre2'],
				'muestras' => $mue,
				'gestionados' => $ges,
				'por' => null
			];
			if ($mue && $ges) {
				$por = $this->calcularPor($mue, $ges);
				if ($por > 100) $por = 100;
				$rec['por'] = floatval(ListasSistema::numero($por));
			}
			if ($mue) $totales['muestras'] += $mue;
			if ($ges) $totales['gestionados'] += $ges;
			$lista[] = $rec;
		}
		if ($totales['muestras'] && $totales['gestionados']) {
			$por = $this->calcularPor($totales['muestras'], $totales['gestionados']);
			if ($por > 100) $por = 100;
			$totales['por'] = floatval(ListasSistema::numero($por));
		}
		
		return [
			'totales' => $totales,
			'lista' => $lista
		];
	}
	
}