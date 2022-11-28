<?php

namespace Reportes\Export;

use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\BorderBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\XLSX\Writer;

class ExcelDatasetExport {
	
	function sendData($datasets, $filename) {
//		printDie($datasets);
		/** @var Writer $writer */
		$writer = WriterFactory::create(Type::XLSX);
		$writer->openToBrowser($filename);
		$this->addData($writer, $datasets);
		$writer->close();
	}
	
	function addData(Writer $writer, $datasets) {
//		$formatNum = function ($row, $camposNum) {
//			foreach ($camposNum as $campo) {
//				if (!isset($row[$campo])) continue;
//				$val = $row[$campo];
//				$row[$campo] = $val == null ? null : floatval($val);
//			}
//			return $row;
//		};
		$formatNum = function ($row) {
			$row = $row == null ? 0 : floatval($row);
			return $row;
		};
		
		$i = 1;
		foreach ($datasets as $dataset) {
			if ($i == 1)
				$sheet = $writer->getCurrentSheet();
			else
				$sheet = $writer->addNewSheetAndMakeItCurrent();
			
			$name = @$dataset['name'] ?? "Hoja $i";
			$sheet->setName($name);
			$data = $dataset['data'];
			
			$head = [];
			if (!empty($dataset['header'])) {
				$head = $dataset['header'];
				$writer->addRow($head); // formato head, etc.
			}
			foreach ($data as $ix => $row) {
				if (!$head) {
					$head = array_keys($row); // format, etc
//					$writer->addRow($head);
					$border = (new BorderBuilder())->setBorderBottom("000000")->build();
					$styles = (new StyleBuilder())->setBackgroundColor("2B74B3")->setBorder($border)->setFontBold()->setFontColor("FFFFFF")->build();
					$writer->addRowWithStyle($head,$styles);
				}

				// formateo
				$rowData = [];
				foreach ($row as $key => $value){
					$rowData[$key] = $value['valor'];
					if ($value['formato'] == 'number') {
						$rowData[$key] = $formatNum($value['valor']);
					}
				}

//				$styles = (new StyleBuilder())->setBackgroundColor(Color::RED)->build();
//				$writer->addRowWithStyle($rowData,$styles);
				
				$writer->addRow($rowData);
			}
			$i++;
		}
		
	}
	
	function createFile($datasets, $path = '') {
		/** @var Writer $writer */
		$writer = WriterFactory::create(Type::XLSX);
		if ($path)
			$file = $path;
		else
			$file = tempnam(sys_get_temp_dir(), 'polipack_');
		$writer->openToFile($file);
		$this->addData($writer, $datasets);
		$writer->close();
		return $file;
	}
}