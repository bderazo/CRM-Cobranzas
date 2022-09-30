<?php

namespace Util;

class TwigProcessor {
	
	var $env;
	
	var $loader;
	
	public function __construct($twigOptions = []) {
		$this->loader = new \Twig_Loader_Array();
		$twigOptions['autoescape'] = false;
		$this->env = new \Twig_Environment($this->loader, $twigOptions);
	}
	
	function addTemplate($name, $string) {
		$this->loader->setTemplate($name, $string);
		return $this;
	}
	
	function render($tpl, $data) {
		return $this->env->render($tpl, $data);
	}
	
	function renderFromString($string, $data) {
		$name = uniqid('tpl_');
		$this->addTemplate($name, $string);
		return $this->render($name, $data);
	}
	
	static function wrapForPdf($txt) {
		$head = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body><div style="page-break-after:auto">';
		$foot = '</div></body></html>';
		return $head . $txt . $foot;
	}
	
	static function wrapForEmail($txt) {
		$head = '<html lang="es"><head><meta charset="UTF-8"></head><body>';
		$foot = '</body></html>';
		return $head . $txt . $foot;
	}
}