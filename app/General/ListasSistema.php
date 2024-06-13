<?php
/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-28
 * Time: 14:09
 */

namespace General;

class ListasSistema {
	
	static $generos = [
		'M' => 'Masculino',
		'F' => 'Femenino',
	];
	
	static $meses = [
		1 => 'Enero',
		2 => 'Febrero',
		3 => 'Marzo',
		4 => 'Abril',
		5 => 'Mayo',
		6 => 'Junio',
		7 => 'Julio',
		8 => 'Agosto',
		9 => 'Septiembre',
		10 => 'Octubre',
		11 => 'Noviembre',
		12 => 'Diciembre',
	];
	
	// mapa de los dias en ingles a español, para datetime->format('D')
	
	static $diasMap = [
		'mon' => 'Lun',
		'tue' => 'Mar',
		'wed' => 'Mie',
		'thu' => 'Jue',
		'fri' => 'Vie',
		'sat' => 'Sab',
		'sun' => 'Dom',
	];
	
	static $diasMapLong = [
		'mon' => 'Lunes',
		'tue' => 'Martes',
		'wed' => 'Miércoles',
		'thu' => 'Jueves',
		'fri' => 'Viernes',
		'sat' => 'Sábado',
		'sun' => 'Domingo',
	];
	
	static $camposComunes = [
		'cedula' => 'Cédula',
		'codigo' => 'Código',
		'telefono' => 'Teléfono',
		'credito' => 'Crédito',
		'creditos' => 'Créditos',
		'interes' => 'Interés',
		'region' => 'Región',
		'extension' => 'Extensión',
		'anio' => 'Año',
	];
	
	static function simpleLabel($c) {
		if (!empty(self::$camposComunes[$c]))
			return self::$camposComunes[$c];
		$t = str_replace('_', ' ', $c);
		$t = str_replace('eion', 'eión', $t);
		$t = str_replace('cion', 'ción', $t);
		$t = str_replace('credito', 'crédito', $t);
		$t = str_replace('gestion', 'gestión', $t);
		$t = str_replace('anio ', 'año ', $t);
		$t = str_replace(' anio', ' año', $t);
		return ucfirst($t);
	}
	
	static function nombreDia(\DateTime $dt) {
		return @self::$diasMap[strtolower($dt->format('D'))];
	}
	
	static function nombreDiaLargo(\DateTime $dt) {
		return @self::$diasMapLong[strtolower($dt->format('D'))];
	}
	
	static function aniosSimple($anioFinal = null, $inverse = false) {
		$fin = $anioFinal ? $anioFinal : date('Y');
		$inicio = $fin - 5; // o consulta de la base
		//select distinct(anio) from subida order by anio
		$anios = [];
		for ($i = $inicio; $i <= $fin; $i++) {
			$anios[] = $i;
		}
		if ($inverse)
			$anios = array_reverse($anios);
		return array_combine($anios, $anios);
	}
	
	static function listaAnios() {
		$anios = [];
		foreach (range(date('Y') - 5, date('Y') + 1) as $y) {
			$anios[$y] = $y;
		}
		return $anios;
	}
	
	static function edadAnios($fechaNacimiento) {
		$from = new \DateTime($fechaNacimiento);
		$to = new \DateTime('today');
		return $from->diff($to)->y;
	}
	
	static function diferenciaSinTiempo($fechaMayor, $fechaMenor) {
		$mayor = new \DateTime((new \DateTime($fechaMayor))->format('Y-m-d'));
		$menor = new \DateTime((new \DateTime($fechaMenor))->format('Y-m-d'));
		return $mayor->diff($menor, true);
	}
	
	static function numero($num) {
		return number_format($num, 2, '.', '');
	}
	
	static function dineroTxt($num, $pref = '', $onEmpty = null) {
		if (!$num && $onEmpty !== null)
			return $onEmpty;
		return $pref . number_format($num, 2, '.', ',');
	}
	
	static function valoresEnLista(array $busqueda, array $comprobarCon) {
		$diff = array_intersect($busqueda, $comprobarCon);
		return count($diff) > 0;
	}
	
	static function listaSemanas($tope = 4) {
		$semanas = [];
		for ($i = 1; $i <= $tope; $i++)
			$semanas[] = "S-$i";
		return $semanas;
	}
	
	static function semanasGestion(\DateTime $fecha) {
		$pref = $fecha->format('Y-m-');
		$max = $fecha->format('t');
		
		$semanas = [];
		
		$semana = 1;
		$checkDia = 5; // viernes
		$last = 1;
		$ident = "S-1";
		for ($i = 1; $i <= $max; $i++) {
			$dia = new \DateTime($pref . $i);
			$weekday = $dia->format('N');
			
			if ($weekday == $checkDia && $i > 1) {
				$ident = "S-$semana";
				$semanas[$ident] = [$last, $i];
				$last = $i + 1;
				$semana++;
			}
			//echo $dia->format('D Y-m-d') . "\n";
		}
		$semanas[$ident][1] = $max;
		return $semanas;
	}
	
	static function encontrarSemanaDia($semanas, $diaCheck) {
		foreach ($semanas as $ident => $limites) {
			if ($diaCheck >= $limites[0] && $diaCheck <= $limites[1])
				return $ident;
		}
		return null;
	}
	
	static function concesionarios($lista) {
		$compacted = [];
		foreach ($lista as $l) {
			$compacted[$l['id']] = $l['nombre'];
		}
		return $compacted;
	}
	
	static function sucursales($sucursales) {
		$compacted = [];
		foreach ($sucursales as $items) {
			$compacted[$items['concesionario_id']][$items['id']] = $items['baccode'] . '-' . $items['nombre'];;
		}
		return $compacted;
	}
//	static function meses(){
//	    $meses =[];
//	    foreach($i=1;$i<=12;$i++){
//	        $m
//        }
//    }

}