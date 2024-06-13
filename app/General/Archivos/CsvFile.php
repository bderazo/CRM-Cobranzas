<?php

namespace General\Archivos;

/**
 * Created by PhpStorm.
 * User: vegeta
 * Date: 1/20/2017
 * Time: 2:04 AM
 */
class CsvFile {
	var $fp;
	var $name;
	var $delimiter = "\t";
	
	function open($name) {
		$this->name = $name;
		$this->fp = fopen($this->name, "w+");
		return $this;
	}
	
	function addRow($row) {
		$data = array_values($row);
		fputcsv($this->fp, $data, $this->delimiter);
		return $this;
	}
	
	function close() {
		if ($this->fp)
			fclose($this->fp);
	}
}