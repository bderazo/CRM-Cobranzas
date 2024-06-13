<?php
namespace Reportes;

class ConditionHelper {
	var $filtros = [];
	
	/**
	 * ConditionHelper constructor.
	 * @param array $filtros
	 */
	public function __construct(array $filtros = []) {
		$this->filtros = $filtros;
	}
	
	function tieneFiltro($key) {
		if (!isset($this->filtros[$key]) || empty($this->filtros[$key]))
			return false;
		return true;
	}
	
	function algunFiltro($lista) {
		foreach ($lista as $key) {
			if ($this->tieneFiltro($key))
				return true;
		}
		return false;
	}
	
	static function quoteList($items, $char = "'") {
		$list = [];
		foreach ($items as $item) {
			$list[] = $char . $item . $char;
		}
		return $list;
	}
	
	static function quoteListTxt($items, $char = "'") {
		$list = self::quoteList($items, $char);
		return implode(',', $list);
	}

// helpers
	
	/**
	 * Saca un set de diferencias entre dos seta, expresado como porcentaje
	 * @param $setActual
	 * @param $setPasado
	 * @param $campo
	 * @return array
	 */
	static function calcDiff($setActual, $setPasado, $campo) {
		$diffset = [];
		foreach ($setActual as $ix => $actual) {
			$past = @$setPasado[$ix];
			if (!$actual || !$past) {
				$diffset[$ix] = null;
				continue;
			}
			$per = self::porcentajeDiff($actual[$campo], $past[$campo]);
			$diffset[$ix] = $per;
		}
		return $diffset;
	}
	
	/**
	 * Calcula el porcentaje de diferencia entre dos valores
	 * @param $actual
	 * @param $pasado
	 * @return float|int
	 */
	static function porcentajeDiff($actual, $pasado) {
		if ($actual == $pasado) return 0;
		if ($pasado == 0) return 0;
		$resta = $actual - $pasado;
		$per = (abs($resta) * 100) / $pasado;
		if ($resta < 0) $per *= -1;
		return $per;
	}
	
}

//['code' => 'tendencias', 'name' => 'Tendencias de Crecimiento y Decrecimiento'],
//			['code' => 'promFactura', 'name' => 'Promedio de Facturación'],
//			['code' => 'winners', 'name' => 'Winners & Losers'],
//			['code' => 'retencionGeneral', 'name' => 'Retención General de Vehículos'],
//			['code' => 'retencionCamiones', 'name' => 'Retención General de Camiones'],
//			['code' => 'retencionKilometraje', 'name' => 'Retención por Kilometraje'],

