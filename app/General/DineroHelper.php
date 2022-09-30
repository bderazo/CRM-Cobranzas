<?php

namespace General;

class DineroHelper {
	
	/**
	 * Replica la funcion PAGO del Excel, en la practica le saca la madre a las cuotas
	 * @param $interes
	 * @param $periodos
	 * @param $valorPresente
	 * @param int $valorFuturo
	 * @param int $type
	 * @return float
	 */
	static function pagoExcel($interes, $periodos, $valorPresente, $valorFuturo = 0, $type = 0) {
		// http://stackoverflow.com/questions/31088264/how-to-calculate-excel-pmt-using-php
		$PMT = (-$valorFuturo - $valorPresente * pow(1 + $interes, $periodos)) /
			(1 + $interes * $type) /
			((pow(1 + $interes, $periodos) - 1) / $interes);
		return $PMT;
	}
	
	static function montoMax($interes, $periodos, $cuota, $valorFuturo = 0, $type = 0) {
		// ecuacion reversa de pagoExcel para resolver $valorPresente
		// el signo negativo no es necesario
		$ter1 = ((pow(1 + $interes, $periodos) - 1) / $interes) * $cuota;
		$ter1 = $ter1 * (1 + $interes * $type) + $valorFuturo;
		$ter1 = $ter1 / pow(1 + $interes, $periodos);
		return $ter1;
	}
	
	/**
	 * Funcion mas simple de pago utilizada en las estimaciones de capacidad de pago, etc.,...no se
	 * @param $interes
	 * @param $plazo
	 * @return float|int
	 */
	static function pago2($interes, $plazo) {
		return (1 - pow((1 + $interes), -$plazo)) / $interes;
	}
	
	/**
	 * Formatea un numero a dos decimales pero retorna un flotante para calculos, etc
	 * @param $num
	 * @return float
	 */
	static function numero($num) {
		return number_format($num, 2, '.', '') + 0;
	}
	
	/**
	 * Dormatea un numero con dos decimales y separador de miles (,) retorna string
	 * @param $num
	 * @return string
	 */
	static function formatDinero($num) {
		return number_format($num, 2, '.', ',');
	}
	
	
}