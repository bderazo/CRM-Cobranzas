<?php

namespace General\Excel;

use Akeneo\Component\SpreadsheetParser\SpreadsheetInterface;
use Akeneo\Component\SpreadsheetParser\Xlsx\ArchiveLoader;
use General\IRowSource;

/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-06-20
 * Time: 22:30
 */
class ExcelRowSource implements IRowSource {
	
	/** @var SpreadsheetInterface */
	var $workbook;
	var $sheets;
	
	/** @var \Iterator */
	var $iterator;
	var $limit = 0;
	
	var $startRow = 0;
	
	
	var $columnMapFunction = null;
	
	protected $lastRow;
	
	function open($file) {
		$this->workbook = NuevoXlsxParser::open($file);
		//$this->workbook = SpreadsheetParser::open($file);
		$this->sheets = $this->workbook->getWorksheets();
		return $this;
	}
	
	function processRows($func, $sheetNum = 0) {
		$it = $this->getIterator($sheetNum);
		$i = 0;
		foreach ($it as $rowIndex => $values) {
			if ($this->startRow > 0 && $rowIndex < $this->startRow)
				continue;
			$i++;
			//var_dump($rowIndex, $values);
			$this->lastRow = $values;
			if (is_callable($this->columnMapFunction)) {
				$values = call_user_func($this->columnMapFunction, $values, $rowIndex);
			}
			$res = $func($values, $rowIndex);
			if ($res === self::BREAK)
				break;
			if ($this->limit > 0 && $i == ($this->limit + 1))
				break;
		}
		return $this;
	}
	
	function lastRow() {
		return $this->lastRow;
	}
	
	function getIterator($sheetNum) {
		$curr = $this->workbook->getWorksheetIndex($this->sheets[$sheetNum]);
		return $this->workbook->createRowIterator($curr);
	}
	
	function close() {
		if ($this->workbook) {
			$tempPath = $this->prepareClose($this->workbook);
			$this->workbook = null;
			$this->deleteTemp($tempPath);
		}
		return $this;
	}
	
	protected function deleteTemp($tempPath) {
		if (!file_exists($tempPath)) {
			return;
		}
		
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($files as $file) {
			if ($file->isDir()) {
				@rmdir($file->getRealPath());
			} else {
				@unlink($file->getRealPath());
			}
		}
		rmdir($tempPath);
	}
	
	protected function prepareClose($workbook) {
		$arc = TempSheet::getArchive($workbook);
		return TempArchive::closeAndGetTemp($arc);
	}
	
}

/**
 * Esta pieza reemplaza de forma bien estupida la clase de Archive por la nuestra que NO tiene un destructor cojudo
 */
class NuevoXlsxParser extends \Akeneo\Component\SpreadsheetParser\Xlsx\XlsxParser {
	public static function createArchiveLoader() {
		//return new ArchiveLoader('Carga\Excel\ArchiveReplace'); // ojo con el namespace
		return new ArchiveLoader(\General\Excel\ArchiveReplace::class);
	}
}

//FEO truco chancroso para poder cerrar la biblioteca de forma racional
// las clases son necesarias porque los miembros no son visibles desde afuera, para variar

class TempSheet extends \Akeneo\Component\SpreadsheetParser\Xlsx\Spreadsheet {
	static function getArchive(\Akeneo\Component\SpreadsheetParser\Xlsx\Spreadsheet $book) {
		return $book->archive;
	}
}

class TempArchive extends \Akeneo\Component\SpreadsheetParser\Xlsx\Archive {
	static function closeAndGetTemp(\Akeneo\Component\SpreadsheetParser\Xlsx\Archive $otro) {
		$otro->closeArchive();
		return $otro->tempPath;
		//$otro->deleteTemp();
	}
	
}