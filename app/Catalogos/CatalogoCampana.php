<?php

namespace Catalogos;

use General\ListasSistema;

class CatalogoCampana extends CatalogoArrayBase {
	var $nombre = 'catalogo_campana';
	
	function labelsAcciones($transiciones) {
		$nombres = $this->getByKey('transiciones');
		$items = [];
		foreach ($transiciones as $accion => $estado) {
			$nombre = @$nombres[$accion] ?? ListasSistema::simpleLabel($accion);
			$items[$accion] = ['label' => $nombre, 'destino' => $estado];
		}
		return $items;
	}
	
	function nombreOperacion($accion) {
		return $this->valorSimple('textos_historico', $accion);
	}
	
	function nombreEstado($estado) {
		return $this->valorSimple('estados', $estado);
	}
	
	function nombreTipo($tipo) {
		return $this->valorSimple('tipos', $tipo);
	}
	
	function nombreOrigen($origen) {
		return $this->valorSimple('origenes', $origen);
	}
	
	function nombreMedio($medio) {
		return $this->valorSimple('medios', $medio);
	}
}