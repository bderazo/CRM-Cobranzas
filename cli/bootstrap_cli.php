<?php
include_once __DIR__ . '/../vendor/autoload.php';
ini_set('memory_limit', '512M');
$container = new \Pimple\Container();

$config = include_once(__DIR__ . '/../app/config.php');
$otroFile = __DIR__ . '/../config_override.php';
$config_notificaciones = __DIR__ . '/../config_notificaciones.php';
if (file_exists($otroFile)) {
	$otroConf = include($otroFile);
	$config = array_merge($config, $otroConf);
}
if (file_exists($config_notificaciones)) {
	$notificacionesConf = include($config_notificaciones);
	$config = array_merge($config, $notificacionesConf);
}
//$container['config'] = include_once('../app/config.php');
$container['config'] = $config;
include_once __DIR__ . '/../app/bootstrap.php';

class TiempoMemoria {
	
	var $start;
	var $end;
	var $initialMem;
	var $finalMem;
	var $peakMem;
	var $reporte = '';
	
	/** @var object */
	static $temp = null;
	
	static function createStart() {
		$t = new TiempoMemoria();
		return $t->start();
	}
	
	function start() {
		$this->start = microtime(true);
		$this->initialMem = memory_get_usage();
		return $this;
	}
	
	function step() {
		return microtime(true) - $this->start;
	}
	
	protected function format($num) {
		return number_format($num, 0, '.', ',');
	}
	
	function end() {
		$this->end = microtime(true) - $this->start;
		$this->finalMem = memory_get_usage();
		$this->peakMem = memory_get_peak_usage();
		
		$reporte = "tiempo: $this->end\n";
		$reporte .= 'Mem Peak: ' . $this->format($this->peakMem) . " bytes\n";
		$reporte .= 'Mem End: ' . $this->format($this->finalMem) . " bytes\n";
		$this->reporte = $reporte;
		return $reporte;
	}
}