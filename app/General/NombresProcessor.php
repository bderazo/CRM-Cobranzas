<?php

namespace General;


/**
 * Utilitario para tratar de partir un nombre completo en componentes
 */
class NombresProcessor {
	
	/**
	 * Procesa los nombres y trata de dividirlos en componentes
	 * Se espera apellidos, nombres
	 * @param string $nombres
	 * @param bool $inverso si los datos vienen nombres, apellidos
	 * @return PartesNombres
	 */
	static function partir($nombres, $inverso = false) {
		// reglas para partir los nombres
		$t = strtoupper(trim($nombres));
		$t = str_replace(' DE ', ' DE_', $t);
		$t = str_replace(' DEL ', ' DEL_', $t);
		$t = str_replace(' DE EL ', ' DE_EL_', $t);
		$t = str_replace(' DE LA ', ' DE_LA_', $t);
		$t = str_replace(' DE LAS ', ' DE_LAS_', $t);
		$t = str_replace(' DE LOS ', ' DE_LOS_', $t);
		
		$p = explode(' ', $t);
		$c = count($p);
		if ($c == 1)
			return PartesNombres::create($p[0])->setError('Informacion incompleta');
		if (!$inverso) {
			if ($c == 2) return PartesNombres::create($p[0], '', $p[1]);
			if ($c == 3) return PartesNombres::create($p[0], $p[1], $p[2]);
			$o = PartesNombres::create($p[0], $p[1], $p[2], $p[3]);
		} else {
			if ($c == 2) return PartesNombres::create($p[1], '', $p[0]);
			if ($c == 3) return PartesNombres::create($p[2], '', $p[0], $p[1]);
			$o = PartesNombres::create($p[2], $p[3], $p[0], $p[1]);
		}
		$o->nombres = $o->nombres();
		$o->apellidos = $o->apellidos();
		return $o;
	}
}

class PartesNombres {
	public $apellido1;
	public $apellido2;
	public $nombre1;
	public $nombre2;
	
	public $nombres;
	public $apellidos;
	
	public $error = '';
	
	static function create($apellido1 = '', $apellido2 = '', $nombre1 = '', $nombre2 = '') {
		$p = new PartesNombres();
		$p->apellido1 = self::rebuild($apellido1);
		$p->apellido2 = self::rebuild($apellido2);
		$p->nombre1 = self::rebuild($nombre1);
		$p->nombre2 = self::rebuild($nombre2);
		return $p;
	}
	
	static function rebuild($apellido) {
		return str_replace('_', ' ', $apellido);
	}
	
	function setError($error) {
		$this->error = $error;
		return $this;
	}
	
	function nombres() {
		return trim("$this->nombre1 $this->nombre2");
	}
	
	function apellidos() {
		return trim("$this->apellido1 $this->apellido2");
	}
}