<?php
namespace General;

use mikehaertl\wkhtmlto\Pdf;

class ImpresionWkhtml {
	
	/// generacion pdf
	
	var $wkbinary;
	var $wkoptions = [];
	
	static $opcionesWk = [
		'margin-top' => 5,
		'margin-right' => 5,
		'margin-bottom' => 5,
		'margin-left' => 5,
		'zoom' => null,
		'no-outline' => true, // Make Chrome not complain
		'disable-smart-shrinking' => false,
	];
	
	static function prepareOptions($options, $forJson = false) {
		$array = [];
		foreach ($options as $key => $val) {
			if ($val === false || $val === '')
				continue;
			if (!array_key_exists($key, self::$opcionesWk))
				continue;
			$checkVal = self::$opcionesWk[$key];
			if ($forJson) {
				if ($val != $checkVal)
					$array[$key] = $val;
				continue;
			}
			// por el formato pendejo que require la clase
			if (is_bool($checkVal)) {
				if ($val) $array[] = $key;
			} else {
				if ($val != $checkVal)
					$array[$key] = $val;
			}
		}
		return $array;
	}
	
	function pdfFromHtml($htmlFile, $options = []) {
		$defaults = self::$opcionesWk;
		$opt = [
			'binary' => $this->wkbinary,
			'no-outline', // Make Chrome not complain
			'margin-top' => $defaults['margin-top'],
			'margin-right' => $defaults['margin-right'],
			'margin-bottom' => $defaults['margin-bottom'],
			'margin-left' => $defaults['margin-left'],
			//'zoom' => 0.75,
			//'disable-smart-shrinking',
			
			'commandOptions' => [
				'escapeArgs' => true,
				'procOptions' => [
					// This will bypass the cmd.exe which seems to be recommended on Windows
					'bypass_shell' => false,
					//'useExec' => true,
					// Also worth a try if you get unexplainable errors
					'suppress_errors' => false,
				],
			],
		];
		
		if ($this->wkoptions)
			$opt = array_merge($opt, $this->wkoptions);
		
		if (@$options['wkoptions'])
			$opt = array_merge($opt, $options['wkoptions']);
		
		if (@$options['css_file']) {
			$opt['user-style-sheet'] = $options['css_file'];
		}
		
		$pdf = new Pdf($opt);
		$pdf->addPage($htmlFile);
		if (@$options['send']) {
			$name = $options['name'];
			$pdf->send($name, @$options['inline']);
		}
		
		if (@$options['saveAs']) {
			$output = $options['saveAs'];
			$pdf->saveAs($output);
		}
		
		$debug = [
			'command' => $pdf->getCommand()->getExecCommand(),
			'error' => $pdf->getError()
		];
		return $debug;
	}
	
}