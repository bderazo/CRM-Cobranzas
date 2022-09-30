<?php

namespace General\Validacion;

use Particle\Validator\Chain;
use Particle\Validator\Failure;
use Particle\Validator\ValidationResult;
use Particle\Validator\Validator;
use Particle\Validator\Value\Container;

/**
 * Class ExtraValidator
 * Validador que permite la modificacion de los datos del arreglo que se esta validando
 * y puede mantener un log de los cambios por cada validacion realizada
 */
class ExtraValidator extends Validator {
	
	/** @var \Particle\Validator\Value\Container */
	var $input;
	var $failures = [];
	var $changes = [];
	
	function setValue($key, $value, $log = true) {
		if (!$this->input)
			return;
		$this->input->set($key, $value);
		if ($log)
			$this->changes[$key] = $value;
	}
	
	function setValues($array, $log = true) {
		if (!$this->input)
			return;
		foreach ($array as $key => $value) {
			$this->input->set($key, $value);
			if ($log)
				$this->changes[$key] = $value;
		}
	}
	
	function getInputValues() {
		if ($this->input)
			return $this->input->getArrayCopy();
		return [];
	}
	
	function addError($error, $key) {
		$f = new Failure($key, $error, '', []);
		$this->failures[] = $f;
		return false;
	}
	
	public function validate(array $values, $context = self::DEFAULT_CONTEXT) {
		$this->changes = [];
		$isValid = true;
		$output = new Container();
		$this->input = new Container($values);
		$stack = $this->getMergedMessageStack($context);
		
		foreach ($this->chains[$context] as $chain) {
			/** @var Chain $chain */
			$isValid = $chain->validate($stack, $this->input, $output) && $isValid;
		}
		
		$failures = $stack->getFailures();
		if ($this->failures) {
			$failures = array_merge($failures, $this->failures);
		}
		
		$result = new ValidationResult(
			$isValid,
			$failures,
			$output->getArrayCopy()
		);
		
		$stack->reset();
		
		return $result;
	}
	
	/**
	 * Crea un validador con mensajes personalizados en español y retorna la clase que
	 * permite cambiar los valores del arreglo
	 * @return ExtraValidator
	 */
	static function createValidator() {
		$validator = new ExtraValidator();
		if (file_exists(__DIR__ . '/mensajesValidator.php')) {
			$mensajes = include('mensajesValidator.php');
			$validator->overwriteDefaultMessages($mensajes);
		}
		return $validator;
	}
	
}