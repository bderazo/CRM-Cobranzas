<?php

namespace Util;

/*
 * Basado en: https://github.com/njh/twig-html-helpers
 *
 * HTML helpers for Twig.
 *
 * (c) 2013 Nicholas Humfrey
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Cambios adaptados para la ocasion
 */

use Twig_Environment;
use Twig_SimpleFunction;

class Twig_Extension_HTMLHelpers extends \Twig_Extension {
	public function getName() {
		return 'html_helpers';
	}
	
	public function getFilters() {
		$filter = new \Twig_SimpleFilter('jsbool', function ($val) {
			if ($val) return 'true';
			return 'false';
		});
		$filterTrunc = new \Twig_SimpleFilter('trunc', function ($val, $num = 10) {
			if ($val == '') return '';
			if (strlen($val) <= $num) return $val;
			return substr($val, 0, $num) . '...';
		});
		return array($filter, $filterTrunc);
	}
	
	public function getFunctions() {
		$options = array(
			'needs_context' => true,
			'needs_environment' => true,
			'is_safe' => array('html')
		);
		
		return array(
			new Twig_SimpleFunction('check_box_tag', array($this, 'checkBoxTag'), $options),
			new Twig_SimpleFunction('check_box', array($this, 'checkBoxTag'), $options),
			new Twig_SimpleFunction('checkbox', array($this, 'checkBoxTag'), $options),
			new Twig_SimpleFunction('content_tag', array($this, 'contentTag'), $options),
			new Twig_SimpleFunction('hidden_field_tag', array($this, 'hiddenFieldTag'), $options),
			new Twig_SimpleFunction('hidden_tag', array($this, 'hiddenFieldTag'), $options),
			new Twig_SimpleFunction('hidden', array($this, 'hiddenFieldTag'), $options),
			new Twig_SimpleFunction('html_tag', array($this, 'htmlTag'), $options),
			new Twig_SimpleFunction('image_tag', array($this, 'imageTag'), $options),
			new Twig_SimpleFunction('input_tag', array($this, 'inputTag'), $options),
			new Twig_SimpleFunction('label_tag', array($this, 'labelTag'), $options),
			new Twig_SimpleFunction('label', array($this, 'labelTag'), $options),
			new Twig_SimpleFunction('labelled_text_field_tag', array($this, 'labeledTextFieldTag'), $options),
			new Twig_SimpleFunction('link_tag', array($this, 'linkTag'), $options),
			new Twig_SimpleFunction('password_field_tag', array($this, 'passwordFieldTag'), $options),
			new Twig_SimpleFunction('password_tag', array($this, 'passwordFieldTag'), $options),
			new Twig_SimpleFunction('password', array($this, 'passwordFieldTag'), $options),
			new Twig_SimpleFunction('radio_button_tag', array($this, 'radioButtonTag'), $options),
			new Twig_SimpleFunction('radio', array($this, 'radioButtonTag'), $options),
			new Twig_SimpleFunction('reset_tag', array($this, 'resetTag'), $options),
			new Twig_SimpleFunction('select_tag', array($this, 'selectTag'), $options),
			new Twig_SimpleFunction('submit_tag', array($this, 'submitTag'), $options),
			new Twig_SimpleFunction('submit', array($this, 'submitTag'), $options),
			new Twig_SimpleFunction('text_area_tag', array($this, 'textAreaTag'), $options),
			new Twig_SimpleFunction('textarea', array($this, 'textAreaTag'), $options),
			new Twig_SimpleFunction('text_field_tag', array($this, 'textFieldTag'), $options),
			new Twig_SimpleFunction('textfield', array($this, 'textFieldTag'), $options),
		);
	}
	
	protected function tagOptions(Twig_Environment $env, $options) {
		$html = "";
		foreach ($options as $key => $value) {
			//if ($key and $value) {
			if ($key) {
				$html .= " " .
					twig_escape_filter($env, $key) . "=\"" .
					twig_escape_filter($env, $value) . "\"";
			}
		}
		return $html;
	}
	
	public function htmlTag(Twig_Environment $env, $context, $name, $options = array()) {
		return "<$name" . $this->tagOptions($env, $options) . " />";
	}
	
	public function contentTag(Twig_Environment $env, $context, $name, $content = '', $options = array()) {
		return "<$name" . $this->tagOptions($env, $options) . ">" .
			twig_escape_filter($env, $content) .
			"</$name>";
	}
	
	public function linkTag(Twig_Environment $env, $context, $title, $url = null, $options = array()) {
		if (is_null($url)) {
			$url = $title;
		}
		$options = array_merge(array('href' => $url), $options);
		return $this->contentTag($env, $context, 'a', $title, $options);
	}
	
	public function imageTag(Twig_Environment $env, $context, $src, $options = array()) {
		$options = array_merge(array('src' => $src), $options);
		return $this->htmlTag($env, $context, 'img', $options);
	}
	
	public function inputTag(Twig_Environment $env, $context, $type, $name, $value = null, $options = array()) {
		$options = array_merge(
			array(
				'type' => $type,
				'name' => $name,
				'id' => str_replace('.', '_', $name),
				'value' => $value
			),
			$options
		);
		return $this->htmlTag($env, $context, 'input', $options);
	}
	
	public function textFieldTag(Twig_Environment $env, $context, $name, $default = null, $options = array()) {
		$value = isset($context[$name]) ? $context[$name] : $default;
		return $this->inputTag($env, $context, 'text', $name, $value, $options);
	}
	
	public function textAreaTag(Twig_Environment $env, $context, $name, $default = null, $options = array()) {
		$content = isset($context[$name]) ? $context[$name] : $default;
		$options = array_merge(
			array(
				'name' => $name,
				'id' => str_replace('.', '_', $name),
				'cols' => 60,
				'rows' => 5
			),
			$options
		);
		return $this->contentTag($env, $context, 'textarea', $content, $options);
	}
	
	
	public function hiddenFieldTag(Twig_Environment $env, $context, $name, $default = null, $options = array()) {
		$value = isset($context[$name]) ? $context[$name] : $default;
		return $this->inputTag($env, $context, 'hidden', $name, $value, $options);
	}
	
	public function passwordFieldTag(Twig_Environment $env, $context, $name = 'password', $default = null, $options = array()) {
		$value = isset($context[$name]) ? $context[$name] : $default;
		return $this->inputTag($env, $context, 'password', $name, $value, $options);
	}
	
	public function radioButtonTag(Twig_Environment $env, $context, $name, $value, $default = false, $options = array()) {
		if ((isset($context[$name]) and $context[$name] == $value) or (!isset($context[$name]) and $default)) {
			$options = array_merge(array('checked' => 'checked'), $options);
		}
		$options = array_merge(array('id' => $name . '_' . $value), $options);
		return $this->inputTag($env, $context, 'radio', $name, $value, $options);
	}
	
	public function checkBoxTag(Twig_Environment $env, $context, $name, $value = '1', $default = false, $options = array()) {
		if ((isset($context[$name]) and $context[$name] == $value) or (!isset($context['submit']) and $default)) {
			$options = array_merge(array('checked' => 'checked'), $options);
		}
		return $this->inputTag($env, $context, 'checkbox', $name, $value, $options);
	}
	
	public function labelTag(Twig_Environment $env, $context, $name, $text = null, $options = array()) {
		if (is_null($text)) {
			$text = ucwords(str_replace('_', ' ', $name)) . ': ';
		}
		$options = array_merge(
			array('for' => $name, 'id' => "label_for_$name"),
			$options
		);
		return $this->contentTag($env, $context, 'label', $text, $options);
	}
	
	public function labeledTextFieldTag(Twig_Environment $env, $context, $name, $default = null, $options = array()) {
		return $this->labelTag($env, $context, $name) . $this->textFieldTag($env, $context, $name, $default, $options);
	}
	
	public function selectTag(Twig_Environment $env, $context, $name, $options, $default = null, $html_options = array()) {
		$keyFunc = $valFunc = null;
		if (!empty($html_options['key'])) {
			$keyFunc = $html_options['key'];
			unset($html_options['key']);
		}
		
		$keyText = '';
		$keys = array_keys($html_options);
		if (in_array('value', $keys))
			$keyText = 'value';
		if (in_array('text', $keys))
			$keyText = 'text';

//		if(!empty($html_options['value'])) {
//			$valFunc = $html_options['value'];
//			unset($html_options['value']);
//		}
		
		if (!empty($html_options[$keyText])) {
			$valFunc = $html_options[$keyText];
			unset($html_options[$keyText]);
		}
		$opts = '';
		if (isset($html_options['prompt'])) {
			$opts .= $this->contentTag($env, $context, 'option', $html_options['prompt'], ['value' => '']);
			unset($html_options['prompt']);
		}
		
		if ($options) {
			if ($default)
				$check = $default;
			else
				$check = isset($context[$name]) ? $context[$name] : null;
			foreach ($options as $k => $value) {
				list($key, $label) = $this->keySelector($k, $value, $keyFunc, $valFunc);
				$arr = array('value' => $key);
				if (!is_array($check)) {
					if ($check == $key)
						//$arr = array_merge(array('selected' => 'selected'), $arr);
						$arr['selected'] = 'selected';
				} else {
					
					if (in_array($key, $check)) {
						$arr['selected'] = 'selected';
					}
				}
//				if ((isset($context[$name]) and $context[$name] === $key) or (!isset($context[$name]) and $default === $key)) {
//					$arr = array_merge(array('selected' => 'selected'), $arr);
//				}
				$opts .= $this->contentTag($env, $context, 'option', $label, $arr);
			}
		}
		$html_options = array_merge(
			array('name' => $name, 'id' => str_replace('.', '_', $name)),
			$html_options
		);
		return "<select" . $this->tagOptions($env, $html_options) . ">$opts</select>";
	}
	
	function keySelector($key, $value, $keyFunc = null, $valFunc = null) {
		$k = $key;
		$val = $value;
		if ($keyFunc != null) $k = is_object($value) ? @$value->$keyFunc : @$value[$keyFunc];
		if ($valFunc != null) $val = is_object($value) ? @$value->$valFunc : @$value[$valFunc];
		return [$k, $val];
	}
	
	public function submitTag(Twig_Environment $env, $context, $value = 'Submit', $options = array()) {
		if (isset($options['name'])) {
			$name = $options['name'];
		} else {
			$name = '';
		}
		return $this->inputTag($env, $context, 'submit', $name, $value, $options);
	}
	
	public function resetTag(Twig_Environment $env, $context, $value = 'Reset', $options = array()) {
		if (isset($options['name'])) {
			$name = $options['name'];
		} else {
			$name = '';
		}
		return $this->inputTag($env, $context, 'reset', $name, $value, $options);
	}
}
