<?php
namespace General\Validacion;

/**
 * Class Utilidades
 * Funciones generales
 */
class Utilidades {

	const ISOFORMAT = 'Y-m-d';
	const ISO_DATETIME = 'Y-m-d H:i:s';

	static function errorString($errores) {
		$l = [];
		foreach ($errores as $campo => $lista) {
			$vals = array_values($lista);
			$t = implode(', ', $vals);
			$l[] = sprintf('[%s] %s', $campo, $t);
		}
		return implode('; ', $l);
	}

	static function validateEmail($email) {
		if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
			return true;
		}
		return false;
	}

	static $charset = array(
		'Š' => 'S', 'š' => 's', 'Ð' => 'Dj', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
		'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I',
		'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U',
		'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
		'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i',
		'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u',
		'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y', 'ƒ' => 'f'
	);

	static $cedulaTipo = [
		'privada' => 'CORPORATIVO',
		'publica' => 'GOBIERNO',
		'natural' => 'RETAIL'
	];

	public static function normalizeString($string) {
		if (empty($string)) return $string;
		return strtr($string, self::$charset);
	}

	static function limpiaTelefonos($string) {
		$numero = str_replace("+593", "", $string);
		//print $numero."  sin 593 \n\r";
		$numero1 = str_replace("(01)", "", $numero);
		// print $numero." sin 01 \n\r";
		return preg_replace('/\s+/', '', $numero1);
		// return $numero;
	}

	static function resolverTipoPorCedula($tipoCedula) {
		if (!isset(self::$cedulaTipo[$tipoCedula]))
			return null;
		return self::$cedulaTipo[$tipoCedula];
	}

	static function fixCedula($c) {
		if ($c == '' || $c == null) return null;
		if (strlen($c) == 9 || strlen($c) == 12)
			$c = '0' . $c;
		return '' . $c;
	}

	static function convertirMinutosEnHoras($time, $format = '%02d:%02d') {
		if ($time >= 0) {
			$hours = floor($time / 60);
			$minutes = ($time % 60);
			return sprintf($format, $hours, $minutes);
		}else
			return;
	}

	public static function generateRandomString($length = 5) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}