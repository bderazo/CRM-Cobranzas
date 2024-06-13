<?php

/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-06
 * Time: 18:29
 */
class ModelHelper {
	/**
	 * Extrae y/o mapea campos de una fuente de datos a otro arreglo
	 * @param array $fieldList Lista de campos a extraer o si se usa como campoFuente => campoDestino, mapea el campo a otro
	 * @param object|array $source Fuente de datos
	 * @param bool $createEmpty
	 * @return array Arreglo mapeado
	 */
	public static function remap($fieldList, $source, $createEmpty = false) {
		$res = [];
		$rec = (array)$source;
		foreach ($fieldList as $key => $dest) {
			$extract = is_numeric($key) ? $dest : $key;
			if (!isset($rec[$extract])) {
				if ($createEmpty) {
					$res[$dest] = null;
				}
				continue;
			}
			$res[$dest] = $rec[$extract];
		}
		return $res;
	}

	public static function findPrefix(array $source, $prefix) {
		$data = [];
		$pref = $prefix . '_';
		foreach ($source as $key => $value) {
			if (strpos($key, $pref) !== 0) continue;
			$name = str_replace($pref, '', $key);
			$data[$name] = $value;
		}
		return $data;
	}

	public static function fillModel($model, array $source) {
		foreach ($source as $key => $value) {
			if (is_object($model))
				$model->$key = $value;
			if (is_array($model))
				$model[$key] = $value;
		}
		return $model;
	}

	static function toBoolDb($val, $default = null) {
		if (is_null($val)) return $default;
		if ($val == 'true') return 1;
		if ($val == 'false') return 0;
		if ($val == 0) return 0;
		if ($val == 1) return 1;
		return $val;
	}

	static function filterBoolVals(&$source, $keys) {
		foreach ($keys as $key) {
			if (isset($source[$key]))
				$source[$key] = self::toBoolDb($source[$key]);
		}
		return $source;
	}
}