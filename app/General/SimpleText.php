<?php

namespace General;


class SimpleText {
	var $txt;
	
	/**
	 * SimpleText constructor.
	 * @param $txt
	 */
	public function __construct($txt) {
		$this->txt = $txt;
	}
	
	public function contains($search) {
		return mb_strpos($this->txt, $search) !== false;
	}
	
	function startsWith($search) {
		if (!$search) return false;
		return (substr($this->txt, 0, strlen($search)) === $search);
	}
	
	function endsWith($search) {
		if (!$search) return false;
		//return (substr($haystack, -$length) === $needle);
		return (substr($this->txt, -strlen($search)) === $search);
	}
	
	public function pos($search, $offset = 0) {
		return mb_strpos($this->txt, $search, $offset);
	}
	
	public function searchAll($term) {
		$lastPos = 0;
		$positions = [];
		while (($lastPos = mb_strpos($this->txt, $term, $lastPos)) !== false) {
			$positions[] = $lastPos;
			$lastPos = $lastPos + mb_strlen($term);
		}
		return $positions;
	}
	
	public function replace($search, $replacement) {
		$this->txt = str_replace($search, $replacement, $this->txt);
		return $this;
	}
	
	public function __toString() {
		return $this->txt;
	}
	
}