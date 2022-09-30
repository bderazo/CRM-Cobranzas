<?php

namespace Catalogos;

use General\ListasSistema;

abstract class CatalogoArrayBase {
	var $tipos = [];
	var $nombre = '';
	
	var $basepath = __DIR__ . '/';
	
	public function __construct($cargarTipos = true) {
		if ($cargarTipos)
			$this->getTipos();
	}
	
	function cargarArchivo($nombre) {
		$file = $this->basepath . $nombre . '.php';
		if (!file_exists($file))
			throw new \Exception("El archivo $file no existe en catalogos");
		return include($file);
	}
	
	function getTipos() {
		if (empty($this->tipos))
			$this->tipos = $this->cargarArchivo($this->nombre);
		return $this->tipos;
	}
	
	function getCatalogo() {
		return $this->getTipos();
	}
	
	function getByKey($key) {
		$this->getCatalogo();
		return isset($this->tipos[$key]) ? $this->tipos[$key] : [];
	}
	
	function valorSimple($catalogo, $key, $default = null) {
		if (!$catalogo || !$key) return null;
		$this->getCatalogo();
		if (!empty($this->tipos[$catalogo][$key]))
			return $this->tipos[$catalogo][$key];
		return $default ? $default : ListasSistema::simpleLabel($key);
	}
}