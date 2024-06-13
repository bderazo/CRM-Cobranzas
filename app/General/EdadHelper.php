<?php
/**
 * Created by PhpStorm.
 * User: vegeta
 * Date: 3/22/2017
 * Time: 12:39 AM
 */

namespace General;


class EdadHelper {
	
	static function crearIntervalo($maximos) {
		$intervalo['Y'] = $maximos['anios'];
		if (@$maximos['meses'])
			$intervalo['M'] = $maximos['meses'];
		if (@$maximos['dias'])
			$intervalo['D'] = $maximos['dias'];
		$txtintervalo = 'P';
		foreach ($intervalo as $tipo => $value) {
			$txtintervalo .= $value . $tipo;
		}
		return new \DateInterval($txtintervalo);
	}
	
	/**
	 * Verifica si una fecha se pasa de un maximo configurado como intervalo
	 * @param \DateTime $nacimiento
	 * @param $maximos array arreglo con los indices 'anios', 'meses', 'dias'
	 * @return bool
	 */
	static function sobrepasaEdad(\DateTime $nacimiento, $maximos) {
		$hoy = new \DateTime();
		$edadMaxima = self::crearIntervalo($maximos);
		$minimo = (new \DateTime())->sub($edadMaxima);
		$diffMinimo = date_diff($minimo, $hoy);
		$maximoDias = $diffMinimo->days;
		
		$diferencia = date_diff($nacimiento, $hoy);
		$diasPersona = $diferencia->days;
		
		return $diasPersona > $maximoDias;
	}
	
	static function edadExacta($fechatxt) {
		if (!($fechatxt instanceof \DateTime))
			$fechatxt = new \DateTime($fechatxt);
		$diff = date_diff(date_create(), $fechatxt);
		$anios = $diff->format('%y');
		$meses = $diff->format('%m');
		$dias = $diff->format('%d');
		$totalDias = $diff->days;
		return compact('anios', 'meses', 'dias', 'totalDias');
	}
	
}