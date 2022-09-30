<?php

namespace General\Archivos;

class LectorCsv implements IRowSource {
	
	var $filename = '';
	var $lastRow = [];
	var $headers = [];
	var $limit = 0;
	
	var $preformatFunc = null;
	
	var $noheaders = false;
	
	function init($filename) {
		$this->filename = $filename;
	}
	
	/**
	 * Leer cada fila, funcion signature (array $row, int $index)
	 * @param mixed $func Funcion de lectura, signature (array $row, int $index)
	 * @param int $sheetNum Numero de hoja
	 * @return $this
	 * @throws \Exception
	 */
	function processRows($func, $sheetNum = 0) {
		// otra forma: http://us3.php.net/manual/en/splfileobject.setcsvcontrol.php
		if (!is_callable($func))
			return $this;
		$fp = fopen($this->filename, "r");
		if (!$fp)
			throw new \Exception("No se puede abrir el archivo $this->filename");
		
		$ix = 0;
		while (($data = fgetcsv($fp, null, "\t")) !== FALSE) {
			$ix++;
			$line = $data;
			if (!$this->noheaders) {
				if (!$this->headers) {
					$this->headers = $line;
					continue;
				}
			}
			$row = [];
			
			if ($this->headers) {
				foreach ($this->headers as $j => $campo) {
					$row[$campo] = @$line[$j];
				}
			} else {
				$row = $line;
			}
			
			if (is_callable($this->preformatFunc)) {
				$row = call_user_func($this->preformatFunc, $row, $ix);
			}
			
			$res = $func($row, $ix);
			if ($res === self::BREAK)
				break;
			if ($this->limit > 0 && $ix == $this->limit)
				break;
		}
		fclose($fp);
		return $this;
	}
	
	function lastRow() {
		return $this->lastRow();
	}
}