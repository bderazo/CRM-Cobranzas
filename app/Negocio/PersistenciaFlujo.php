<?php

namespace Negocio;

use General\Flujo\TransitionInfo;
use Models\Casopqr;
use Models\CasopqrAvance;

class PersistenciaFlujo {
	
	var $usuario;
	var $usuario_id;
	var $extraData = [];
	
	function actualizarProceso(Casopqr $caso, TransitionInfo $transition) {
		$caso->estado = $transition->destination;
		$caso->save();
		
		$avance = $this->crearHistorico($caso);
		$avance->estado_anterior = $transition->source;
		$avance->operacion = $transition->trigger;
		$avance->save();
	}
	
	function crearHistorico(Casopqr $caso, $calcularGestion = true) {
		// historico
		$avance = new CasopqrAvance();
		$avance->caso_id = $caso->id;
		$avance->fecha_evento = new \DateTime();
		//$avance->fecha_evento = date('Y-m-d H:i:s');
		$avance->usuario = $this->usuario;
		$avance->usuario_id = $this->usuario_id;
		$avance->estado_actual = $caso->estado;
		if ($calcularGestion)
			$avance->dias_gestion = ManagerCaso::tiempoGestionAvance($caso, $avance->fecha_evento);
		if (!empty($this->extraData['comentarios'])) {
			$avance->comentarios = $this->extraData['comentarios'];
		}
		return $avance;
		
		
	}
}