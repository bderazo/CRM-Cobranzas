<?php
namespace Reportes\Export;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use General\CsvFile;

class EscritorSecuencial {
	
	var $writer;
	var $tipo;
	var $archivo;
	var $head = false;
	
	function __construct($tipo) {
		$this->tipo = $tipo;
		if (!in_array($tipo, ['excel', 'csv']))
			throw new \Exception("Tipo $tipo de escritor secuencial no soportado");
		if ($this->tipo == 'excel')
			$this->writer = WriterFactory::create(Type::XLSX);
		if ($this->tipo == 'csv')
			$this->writer = new CsvFile();
	}
	
	function addRow($row) {
		$this->writer->addRow($row);
	}
	
	protected function endswith($str, $check) {
		if (substr_compare($str, $check, -strlen('.xlsx')) === 0) {
			return true;
		}
		return false;
	}
	
	function open($archivo) {
		if ($this->tipo == 'excel') {
			if (!$this->endswith($archivo, '.xlsx'))
				$archivo .= '.xlsx';
			$this->writer->openToFile($archivo);
		} else {
			if (!$this->endswith($archivo, '.csv'))
				$archivo .= '.csv';
			$this->writer->open($archivo);
		}
		$this->archivo = $archivo;
	}
	
	function close() {
		$this->writer->close();
	}
	
}