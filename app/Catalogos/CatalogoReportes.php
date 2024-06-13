<?php

namespace Catalogos;

class CatalogoReportes extends CatalogoArrayBase {
	var $nombre = 'catalogo_reportes';
	
	function listaPQR() {
		return $this->getByKey('pqr');
	}
	
	function listaCSI() {
		return $this->getByKey('csi');
	}
}

