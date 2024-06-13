<?php

namespace General\Excel;

/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-05-10
 * Time: 11:52
 */
class ExcelUtils {
	static $letras = [];
	
	static function rangoDesdeCoordenadas($rowstart, $colstart, $rowend, $colend) {
		$top = self::numToLetra($colstart) . $rowstart;
		$bot = self::numToLetra($colend) . $rowend;
		return $top . ':' . $bot;
	}
	
	static function loadLetras() {
		if (!self::$letras) {
			self::$letras = include_once('letras.php');
			// o de otra forma
		}
	}
	
	static function mapRow($array, $mapeo, $transformFunc = null) {
		self::loadLetras();
		$o = [];
		foreach ($array as $i => $val) {
			$letra = self::$letras[$i + 1];
			if (is_string($val)) $val = trim($val);
			if (!isset($mapeo[$letra]))
				continue;
			$campo = $mapeo[$letra];
			if (is_callable($transformFunc)) {
				$val = $transformFunc($val, $campo, $letra);
			}
			$o[$campo] = $val;
		}
		return $o;
	}
	
	static function arrayToRow($array, $transform = null) {
		self::loadLetras();
		$row = [];
		foreach ($array as $i => $val) {
			$letra = self::$letras[$i + 1];
			if (is_callable($transform))
				$val = $transform($val, $letra);
			if (is_string($val))
				$val = trim($val);
			$row[$letra] = $val;
		}
		return $row;
	}
	
	static function numToLetra($num) {
		self::loadLetras();
		return @self::$letras[$num];
	}
	
	static function letterToColumn($value) {
		$value = strtoupper($value);
		$num = 0;
		$tot = strlen($value) - 1;
		for ($i = 0; $i <= $tot; $i++) {
			$ch = ord($value[$i]) - 64;
			$num += $ch * pow(26, ($tot - $i));
		}
		return $num;
	}
}
