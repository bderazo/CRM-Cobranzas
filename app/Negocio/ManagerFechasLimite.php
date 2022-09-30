<?php

namespace Negocio;

use Models\Casopqr;

/**
 * Componente para resolver las fechas limite de un caso por escalamiento o por lo que sea, tambien toma en cuenta los feriados
 * @package Negocio
 */
class ManagerFechasLimite {
	/** @var  \PDO */
	var $pdo;
	
	var $cacheFeriados = [];
	
	function loadFeriados() {
		if (!$this->cacheFeriados) {
			$db = new \FluentPDO($this->pdo);
			$this->cacheFeriados = $db->from('feriado')->orderBy('fecha')->fetchAll('fecha');
		}
		return $this->cacheFeriados;
	}
	
	function calcularDia($inicio, $dias) {
		$feriados = $this->loadFeriados();
		$f = new \DateTime($inicio);
		$objetivo = 0;
		while ($objetivo < $dias) {
			$f->modify('+1 day');
			$dia = $f->format('N');
			if ($dia == 6 or $dia == 7)
				continue;
			$iso = $f->format('Y-m-d');
			if (!empty($feriados[$iso]))
				continue;
			$objetivo++;
		}
		//return $f->format('Y-m-d');
		return $f;
	}
	
	function resolverLimites(Casopqr $caso, $fechaCambio = null) {
		$sumarDias = $caso->nivel_escalamiento < 3 ? 2 : 1;
		// ojo con estado de repuestos
		if ($caso->tipo == 'repuestos' && $caso->estado != 'abierto') {
			$maxETA = $this->maxETARepuestos($caso->id);
			if ($maxETA) return $this->calcularDia($maxETA, 2); // 48 horas para repuestos
		}
		// si el caso fue escalado, tomar esto como fecha limite
		if ($fechaCambio) {
			$fecha = $fechaCambio;
			if ($caso->cita) {
				$fcambio = new \DateTime($fechaCambio);
				$fcita = new \DateTime($caso->cita);
				$fecha = $fcita > $fcambio ? $caso->cita : $fechaCambio;
			}
			return $this->calcularDia($fecha, $sumarDias);
		}
		
		if ($caso->fecha_escalamiento) {
			return $this->calcularDia($caso->fecha_escalamiento, $sumarDias);
		}
		$fecha = $caso->cita ? $caso->cita : $caso->fecha_creacion;
		return $this->calcularDia($fecha, $sumarDias);
	}
	
	function maxETARepuestos($id) {
		$db = new \FluentPDO($this->pdo);
		$row = $db->from('casopqr_repuesto')->where('caso_id', $id)->select(null)
			->select('max(fecha_llegada) as eta')->fetch();
		if ($row)
			return $row['eta'] ?? null;
		return null;
	}
	
	function actualizarCaso(Casopqr $caso, $fechaCambio = null) {
		$fecha = $this->resolverLimites($caso, $fechaCambio);
		$caso->fecha_limite = $fecha->format('Y-m-d');
		return $caso;
	}
}