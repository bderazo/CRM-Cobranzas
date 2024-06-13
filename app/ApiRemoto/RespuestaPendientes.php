<?php
namespace ApiRemoto;

class RespuestaPendientes {
	var $error = null;
	var $lista = [];
	var $desde = null;
	
	function withError($error) {
		$this->error = $error;
		return $this;
	}
}