<?php

namespace Catalogos;

class CatalogoUsuarios extends CatalogoArrayBase {
	var $nombre = 'catalogo_usuarios';
	
	function areasTrabajo() {
		return $this->getByKey('areas');
	}
	
	function nombreArea($area) {
		return $this->valorSimple('areas', $area);
	}
	
}