<?php

namespace Util;

/**
 * Simple Asset manager
 * @package Util
 */
class SimpleBundles {
	
	var $rootUrl;
	var $bundles = [];
	
	/**
	 * SimpleBundles constructor.
	 * @param $rootUrl
	 */
	public function __construct($rootUrl = null) {
		$this->rootUrl = $rootUrl;
	}
	
	function resolveUrl($script, $basedir = '') {
		if (strpos($script, 'http') === 0 || strpos($script, '//') === 0) { // para urls fuera del sitio o cdn
			return $script;
		}
		$root = $this->rootUrl;
		if ($basedir) {
			$root = $root . '/' . $basedir;
		}
		return $root . '/' . $script;
	}
	
	function loadBundlesFile($file) {
		if (!file_exists($file)) {
			throw new \Exception("El archivo de bundles $file no existe");
		}
		$this->bundles = include($file);
		return $this;
	}
	
	protected $check = [];
	protected $assets = ['js' => [], 'css' => []];
	
	function imports($names) {
		foreach ($names as $name) {
			if (!empty($this->check[$name]))
				continue;
			if (!isset($this->bundles[$name]))
				throw new \Exception("El bundle $name no se ha definido");
			$this->check[$name] = true;
			$bundle = $this->bundles[$name];
			if (!empty($bundle['import'])) {
				$this->imports($bundle['import']);
			}
			$this->loadSection($bundle, 'js');
			$this->loadSection($bundle, 'css');
		}
	}
	
	function loadSection($section, $tipo) {
		if (empty($section[$tipo])) return;
		$data = $section[$tipo];
		$basedir = empty($section['basedir']) ? '' : $section['basedir'] . '/';
		if (!is_array($data)) $data = [$data];
		foreach ($data as $asset) {
			$uri = $basedir . $asset;
			if (!in_array($uri, $this->assets[$tipo]))
				$this->assets[$tipo][] = $uri;
		}
	}
	
	function bundle($name) {
		$this->check = [];
		$this->assets = ['js' => [], 'css' => []];
		$this->imports([$name]);
		
		$lista = [];
		foreach ($this->assets['css'] as $css) {
			$lista[] = $this->cssTag($css);
		}
		
		foreach ($this->assets['js'] as $js) {
			$lista[] = $this->scriptTag($js);
		}
		return join("\n", $lista);
	}
	
	public function scriptTag($script) {
		$url = $this->resolveUrl($script);
		return "<script src=\"$url\"></script>";
	}
	
	public function cssTag($file) {
		$url = $this->resolveUrl($file);
		return "<link href=\"$url\" rel=\"stylesheet\">";
	}
	
}