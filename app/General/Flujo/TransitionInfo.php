<?php

namespace General\Flujo;

class TransitionInfo {
	var $source = '';
	var $trigger = '';
	var $destination = '';
	
	/**
	 * TransitionEvent constructor.
	 * @param array $arrayData
	 */
	public function __construct($arrayData = []) {
		if ($arrayData)
			$this->fromArray($arrayData);
	}
	
	function fromArray($data) {
		if (!empty($data['state']))
			$this->source = $data['state'];
		foreach ($this as $key => $dum) {
			if (!empty($data[$key]))
				$this->$key = $data[$key];
		}
		return $this;
	}
}