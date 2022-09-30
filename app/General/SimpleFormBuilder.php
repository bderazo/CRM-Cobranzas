<?php

namespace General;

class SimpleFormBuilder {
	var $tpl;
	var $defaultConfig = [];
	protected $campos = [];
	
	protected $groups = [];
	
	/** @var SimpleFormBuilder */
	protected $parent;
	var $name;
	var $label;
	var $labelFunc;
	
	var $autolabel = true;
	
	var $inputCss = 'form-control input-sm';
	var $labelCss = 'col-sm-2 control-label input-sm';
	var $inputDivCss = 'col-sm-10';
	
	var $idPrefix;
	var $namePrefix;
	
	/**
	 * SimpleFormBuilder constructor.
	 * @param string $name
	 * @param null $parent
	 */
	public function __construct($name = null, $parent = null) {
		$this->name = $name;
		$this->parent = $parent;
	}
	
	function getParent() {
		return $this->parent;
	}
	
	function createGroup($name, $label = null) {
		$group = new SimpleFormBuilder($name, $this);
		$group->label = $label ? $label : $name;
		$group->labelFunc = $this->labelFunc;
		$this->groups[$name] = $group;
		return $group;
	}
	
	function configureGroup($name, $action) {
		$group = $this->getGroup($name);
		if (!$group)
			$group = $this->createGroup($name);
		call_user_func($action, $group, $this);
		return $this;
	}
	
	/**
	 * @param $name
	 * @return null|SimpleFormBuilder
	 */
	function getGroup($name) {
		return @$this->groups[$name];
	}
	
	function groupDefinitions() {
		$data = [];
		/**
		 * @var string $name
		 * @var SimpleFormBuilder $form
		 */
		foreach ($this->groups as $name => $form) {
			$group = ['name' => $form->name, 'label' => $form->label,
				'fields' => $form->getDefinitions()
			];
			$data[$name] = $group;
		}
		return $data;
	}
	
	protected function resolveLabel($campo) {
		if (is_callable($this->labelFunc)) {
			return call_user_func($this->labelFunc, $campo);
		}
		if ($this->autolabel) {
			return str_replace('_', ' ', ucfirst($campo));
		}
		return $campo;
	}
	
	/**
	 * @param $nombre
	 * @param $tipo
	 * @param array $opciones
	 * @return CampoDef
	 */
	function add($nombre, $tipo = 'string', $opciones = []) {
		$campo = new CampoDef($nombre);
		$campo->setTipo($tipo);
		$label = $this->resolveLabel($nombre);
		$campo->label($label);
		
		$campo->inputCss = $this->inputCss;
		$campo->inputDivCss = $this->inputDivCss;
		$campo->labelCss = $this->labelCss;
		
		$this->campos[$nombre] = $campo;
		return $campo;
	}
	
	function addSelect($nombre, $label = null) {
		return $this->add($nombre, 'select')->label($label);
	}
	
	function allFields() {
		return $this->campos;
	}
	
	/**
	 * @param $nombre
	 * @return mixed|CampoDef
	 */
	function campo($nombre) {
		return empty($this->campos[$nombre]) ? null : $this->campos[$nombre];
	}
	
	function field($name) {
		return $this->campo($name);
	}
	
	function getDefinitions() {
		$lista = [];
		/** @var CampoDef $campo */
		foreach ($this->campos as $campo) {
			$data = $campo->asArray();
			$lista[] = $data;
		}
		return $lista;
	}
	
	function remove($campo) {
		unset($this->campos[$campo]);
		return $this;
	}
}

class CssHolder {
	var $sections = [
		'inputCss' => [],
		'inputDivCss' => [],
		'labelCss' => [],
	];
	
	function addClass($class, $section = 'inputCss') {
		$this->sections[$section][$class] = 1;
	}
	
	function removeClass($class, $section = 'inputCss') {
		unset($this->sections[$section][$class]);
	}
	
	function getCssText($section = 'inputCss') {
		$keys = array_keys($this->sections[$section]);
		return trim(implode(' ', $keys));
	}
}

class CampoDef {
	var $nombre;
	var $id;
	var $tipo = 'string';
	var $options = [];
	var $label;
	
	var $value = null;
	
	var $attr = [
		'input' => [],
		'label' => [],
		'inputDiv' => [],
		'container' => [],
	];
	var $inputCss = 'form-control input-sm';
	var $labelCss = 'col-sm-2 control-label input-sm';
	var $inputDivCss = 'col-sm-10';
	
	var $customHtml = '';
	
	public function __construct($nombre = '') {
		$this->nombre = $nombre;
	}
	
	function asArray() {
		$data = (array)$this;
		$attr = $this->processAttr();
		foreach ($attr as $name => $txt) {
			$data['attr_' . $name] = $txt;
		}
		return $data;
	}
	
	function setTipo($tipo) {
		$this->tipo = $tipo;
		if ($tipo == 'select')
			$this->removeInputAttr('value');
		return $this;
	}
	
	function setValue($val) {
		if ($val === null || is_bool($val))
			return $this;
		$this->inputAttr('value', $val);
		return $this;
	}
	
	function label($nombre) {
		$this->label = $nombre;
		return $this;
	}
	
	function option($name, $valor = '') {
		$this->options[$name] = $valor;
	}
	
	function required() {
		return $this->addClass('required');
	}
	
	function addAttr($part, $name, $value = '') {
		$this->attr[$part][$name] = $value;
		return $this;
	}
	
	function removeInputAttr($name) {
		unset($this->attr['input'][$name]);
		return $this;
	}
	
	function inputAttr($name, $value = '') {
		return $this->addAttr('input', $name, $value);
	}
	
	function inputName($name) {
		return $this->inputAttr('name', $name);
	}
	
	function setId($id) {
		$this->id = $id;
		return $this;
	}
	
	function withHtml($html) {
		$this->customHtml = $html;
		return $this;
	}
	
	function addClass($classes, $part = 'input') {
		if ($part == 'input') $this->inputCss .= ' ' . $classes;
		if ($part == 'inputDiv') $this->inputDivCss .= ' ' . $classes;
		if ($part == 'label') $this->labelCss .= ' ' . $classes;
		return $this;
	}
	
	function asSelect($lista, $selected = null, $prompt = null) {
		$this->setTipo('select');
		if ($prompt) {
			array_unshift($lista, ['value' => null, 'text' => $prompt]);
		}
		$this->options['selectValues'] = $lista;
		$this->options['selected'] = $selected;
		return $this;
	}
	
	function arrayForSelect($array, $selected = null, $prompt = null) {
		$items = [];
		if ($prompt !== null)
			$items[] = ['value' => '', 'text' => $prompt];
		foreach ($array as $k => $v) {
			$item = ['value' => $k, 'text' => $v];
			if ($selected !== null && $selected == $k)
				$item['selected'] = true;
			$items[] = $item;
		}
		$this->options['selectValues'] = $items;
		return $this;
	}
	
	function getAttr($seccion) {
		$valores = @$this->attr[$seccion];
		if (!$valores) return '';
		return $this->parseAttr($seccion);
	}
	
	protected function parseAttr($valores) {
		if (!$valores) return '';
		$textos = [];
		foreach ($valores as $key => $value) {
			if (!$value)
				$textos[] = $key;
			else {
				$textos[] = $key . '="' . $value . '"';
			}
		}
		return trim(implode(' ', $textos));
	}
	
	function processAttr() {
		$res = [];
		foreach ($this->attr as $parte => $valores) {
			$res[$parte] = $this->parseAttr($valores);
		}
		return $res;
	}
	
}