<?php

namespace General;

use mikehaertl\wkhtmlto\Pdf;
use Tecnoready\Barcode\PrintService;
use Twig_Environment;

class GenerarPDF {

	private $barcode = "CB0080K001";

	public function generatePdfReporte($plantilla, $data, $temp_name,$orientation = 'Portrait') {
		$twig = self::getTwig();
		$template = $twig->createTemplate($plantilla);
		$html = $template->render($data);

		$pdf = new Pdf([
			'binary' => '/usr/local/bin/wkhtmltopdf',
			'encoding' => 'UTF-8',
			'disable-smart-shrinking',
			'no-outline',
			'lowquality',
			'orientation' => $orientation,
			'commandOptions' => [
				'locale' => 'es_ES.utf-8',
				'procEnv' => ['LANG' => 'es_ES.utf-8'],
			],
		]);
		$css = '<style>
					thead { display: table-header-group; }
					tfoot { display: table-row-group; }
					tr { page-break-inside: avoid; }
                 </style> ';
		$html = $css . $html;
		$pdf->addPage('<html>' . $html . '</html>');
		$pdf->setOptions('footer-center', 'Page [page]');
		$pdf->send($temp_name, false);
		exit();
	}

	public function generatePdfHorizontal($plantilla, $data, $temp_name) {
		$twig = self::getTwig();
		$template = $twig->createTemplate($plantilla);
		$html = $template->render($data);

		$pdf = new Pdf([
			'binary' => '/usr/local/bin/wkhtmltopdf',
			'encoding' => 'UTF-8',
			'disable-smart-shrinking',
			'no-outline',
			'lowquality',
			'orientation' => 'Landscape',
			'commandOptions' => [
				'locale' => 'es_ES.utf-8',
				'procEnv' => ['LANG' => 'es_ES.utf-8'],
			],
		]);
		$css = '<style>
					thead { display: table-header-group; }
					tfoot { display: table-row-group; }
					tr { page-break-inside: avoid; }
                 </style> ';
		$html = $css . $html;
		$pdf->addPage('<html>' . $html . '</html>');
		$pdf->setOptions('footer-center', 'Page [page]');
		$pdf->send($temp_name, false);
		exit();
	}

	public function generatePdfEgresoPlanta($plantilla, $data, $temp_name) {
		$twig = self::getTwig();
		$template = $twig->createTemplate($plantilla);
		$html = $template->render($data);

		$pdf = new Pdf([
			'binary' => '/usr/local/bin/wkhtmltopdf',
			'encoding' => 'UTF-8',
			'disable-smart-shrinking',
			'no-outline',

			'page-size' => 'A5',
			'orientation' => 'Landscape',


			'lowquality',
			'commandOptions' => [
				'locale' => 'es_ES.utf-8',
				'procEnv' => ['LANG' => 'es_ES.utf-8'],
			],
		]);
		$css = '<style>
					thead { display: table-header-group; }
					tfoot { display: table-row-group; }
					tr { page-break-inside: avoid; }
                 </style> ';
		$html = $css . $html;
		$pdf->addPage('<html>' . $html . '</html>');
		$pdf->setOptions('footer-center', 'Page [page]');
		$pdf->send($temp_name, false);
		exit();
	}

	static function generatePdf($plantilla, $data, $codigo_barra,$temp_name,$copias,$ancho_etiqueta,$alto_etiqueta) {
		$printService = self::getPrintService($ancho_etiqueta,$alto_etiqueta);

		$filename = self::getFileName($temp_name);
		return $printService->generatePdf($filename, $codigo_barra, $plantilla,$data,[
			"copies" => $copias,
		]);
	}

	/**
	 * @return Twig_Environment
	 */
	static function getTwig() {
		$loader = new \Twig_Loader_Array();
		$twig = new Twig_Environment($loader,[
			"strict_variables" => true,
		]);
		return $twig;
	}

	/**
	 * @return PrintService
	 */
	static function getPrintService($ancho_etiqueta,$alto_etiqueta) {
		$twig = self::getTwig();
		$options = [
			"page-width" => $ancho_etiqueta,
			"page-height" => $alto_etiqueta,
			"var-barcode" => "img_codigo_barras",
			"barcode-height" => 30,
			"barcode-width-factor" => 0.96,
		];
		return new PrintService($twig, $options);
	}

	static function getFileName($temp_name) {
		$filename = __DIR__ . '/../../temp/'.$temp_name.'.pdf';
		@unlink($filename);
		return $filename;
	}
	
}