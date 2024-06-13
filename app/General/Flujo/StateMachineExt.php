<?php

namespace General\Flujo;

use Fluxer\StateMachine;
use Fluxer\StateMachineException;

/**
 * StateMachine con funcionalidad adicional
 * @package General\Flujo
 */
class StateMachineExt extends StateMachine {
	function finalStates() {
		$todos = [];
		/** @var \Fluxer\StateConfigHolder $c */
		foreach ($this->configurations as $state => $c) {
			if (empty($todos[$state])) $todos[$state] = [];
			if (empty($c->transitions)) continue;
			/** @var \Fluxer\Transition $t */
			foreach ($c->transitions as $t) {
				if (!$t->destination) continue; // hay condicion
				$todos[$t->source][] = $t->destination;
				if (empty($todos[$t->destination])) $todos[$t->destination] = [];
			}
		}
		$finales = [];
		foreach ($todos as $k => $t)
			if (empty($t)) $finales[] = $k;
		return $finales;
	}
	
	function listStates() {
		$lista = [];
		/** @var \Fluxer\StateConfigHolder $config */
		foreach ($this->configurations as $config) {
			if ($config->state)
				$lista[$config->state] = true;
			
			/** @var \Fluxer\Transition $tran */
			foreach ($config->transitions as $tran) {
				if ($tran->source) $lista[$tran->source] = true;
				if ($tran->destination) $lista[$tran->destination] = true;
			}
		}
		$names = array_keys($lista); // sort?
		return $names;
	}
	
}
