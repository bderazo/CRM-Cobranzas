<?php

namespace General\Flujo;

use Fluxer\StateMachine;

/**
 * Generan un archivo .dot de graphviz para graficar el flujo
 * @package General\Flujo
 */
class StateMachineGraph extends StateMachine {
	
	/**
	 * @param StateMachine $machine
	 * @return string
	 */
	static function getDotData(StateMachine $machine) {
		$configs = $machine->configurations;
		$lines = [];
		$unknowns = [];
		$dynCount = 0;
		
		/** @var \Fluxer\StateConfigHolder $config */
		foreach ($configs as $config) {
			if (empty($config->transitions)) {
				$unknowns[] = $config->state;
				continue;
			}
			
			/** @var \Fluxer\Transition $tran */
			foreach ($config->transitions as $tran) {
				if ($tran->dynamic)
					$unknowns[] = 'dynamic ' . ($dynCount++);
				$cond = $tran->cond ? 'COND' : null;
				$line = self::handleLine($tran->source, $tran->trigger, $tran->destination, $cond);
				$lines[] = $line;
			}
		}
		
		if ($unknowns) {
			$txt = " {{ node [label=\"{0}\"] {0} }};";
			$lista = join(' ', $unknowns);
			$txt = str_replace('{0}', $lista, $txt);
			array_unshift($lines, $txt);
		}
		
		$events = $machine->events;
		$actionCount = 0;
		if ($events['entry'] || $events['exit']) {
			$lines[] = "node [shape=box];";
			foreach ($events['entry'] as $source => $closure) {
				$txtACtion = 'Action ' . ($actionCount++);
				$lines[] = " $source -> \"$txtACtion\" [label=\"On Entry\" style=dotted];";
			}
			foreach ($events['exit'] as $source => $closure) {
				$txtACtion = 'Action ' . ($actionCount++);
				$lines[] = " $source -> \"$txtACtion\" [label=\"On Exit\" style=dotted];";
			}
		}
		
		$dot = "digraph {\n" . join("\n", $lines) . "\n}";
		return $dot;
	}
	
	static function handleLine($sourceState, $trigger, $destination, $condDesc = '') {
		$line = " $sourceState -> $destination [label=\"$trigger";
		if ($condDesc)
			$line .= " [$condDesc]";
		$line .= '"];';
		return $line;
	}
}