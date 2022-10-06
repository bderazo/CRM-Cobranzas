<?php

namespace ApiRemoto;


class RespuestaConsulta {
	var $codigo = 200;
	var $mensaje = 'OK';
	var $data = null;
	var $results = null;
	var $more = true;
	
	function conError($mensaje) {
		$this->codigo = '400';
		$this->mensaje = $mensaje;
		unset($this->results);
		unset($this->more);
		return $this;
	}
	
	function conMensaje($mensaje) {
		$this->mensaje = $mensaje;
		unset($this->results);
		unset($this->more);
		return $this;
	}
	
	function conDatos($data) {
		$this->data = $data;
		unset($this->results);
		unset($this->more);
		return $this;
	}

	function conResults($data) {
		$this->results = $data;
		unset($this->data);
		return $this;
	}
}