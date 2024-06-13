<?php

namespace Util;

use Twig_Environment;
use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

/**
 * Extensiones al twig
 */
class TwigExtras extends Twig_Extension {
	static $isoFormat = 'Y-m-d';
	
	/** @var SimpleBundles */
	var $assetsManager;
	
	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName() {
		return "twig_extras_mvgomez";
	}
	
	public function getFilters() {
		return array(
			new Twig_SimpleFilter('fecha', [$this, 'timeFormat']),
			new Twig_SimpleFilter('resumen', [$this, 'resumen']),
			
			new Twig_SimpleFilter('date', [$this, 'dateFilter'], ['needs_environment' => true]),
		);
	}
	
	public function getFunctions() {
		$options = array(
			'needs_context' => true,
			'needs_environment' => true,
			'is_safe' => array('html')
		);
		return array(
			new Twig_SimpleFunction('script', array($this, 'scriptTag'), $options),
			new Twig_SimpleFunction('css', array($this, 'cssTag'), $options),
			new Twig_SimpleFunction('bundle', array($this, 'displayBundle'), $options),
			
			new Twig_SimpleFunction('includeRaw', array($this, 'includeRaw'), $options),
		);
	}
	
	public function timeFormat($time, $format = '%Y-%m-%d') {
		if (is_numeric($time))
			return strftime($format, $time);
		$dt = new \DateTime($time);
		if (!$dt) return '';
		return strftime($format, $dt->getTimestamp());
	}
	
	public function displayBundle(Twig_Environment $env, $context, $name) {
		return $this->assetsManager->bundle($name);
	}
	
	public function scriptTag(Twig_Environment $env, $context, $script, $options = []) {
		return $this->assetsManager->scriptTag($script);
		
	}
	
	public function cssTag(Twig_Environment $env, $context, $file, $options = []) {
		return $this->assetsManager->cssTag($file);
	}
	
	public function resumen($str, $len, $suffix = '') {
		if (!$str) return $str;
		$str = trim($str);
		$suf = strlen($str) > $len ? $suffix : '';
		return substr($str, 0, $len) . $suf;
	}
	
	public function includeRaw(Twig_Environment $env, $context, $tpl) {
		return $env->getLoader()->getSource($tpl);
	}
	
	/**
	 * Nuevo filtro de fechas que devuelve null si es objeto realmente no es una fecha o es null,
	 * puede preservar los datos para ver
	 *
	 * @param Twig_Environment $env
	 * @param $date
	 * @param null $format
	 * @param null $timezone
	 * @param bool $preserve
	 * @return \DateInterval|null|string
	 */
	function dateFilter(Twig_Environment $env, $date, $format = null, $timezone = null, $preserve = false) {
		/** @var \Twig_Extension_Core $core */
		$core = $env->getExtension('Twig_Extension_Core');
		
		if (null === $format) {
			$formats = $core->getDateFormat();
			$format = $date instanceof \DateInterval ? $formats[1] : $formats[0];
		}
		
		if ($date instanceof \DateInterval) {
			return $date->format($format);
		}
		
		// simple mejora
		if (!isset($date) || empty($date)) {
			return '';
		}
		
		// twig_date_converter, le llama al mismo para no reescribir
		//return twig_date_converter($env, $date, $timezone)->format($format);
		$dt = $this->dateFromString($core, $date, $timezone);
		if ($dt)
			return $dt->format($format);
		return $preserve ? $date : null;
	}
	
	function dateFromString(\Twig_Extension_Core $core, $date, $timezone) {
		// determine the timezone
		if (!$timezone) {
			$defaultTimezone = $core->getTimezone();
		} elseif (!$timezone instanceof \DateTimeZone) {
			$defaultTimezone = new \DateTimeZone($timezone);
		} else {
			$defaultTimezone = $timezone;
		}
		
		// immutable dates
		if ($date instanceof \DateTimeImmutable) {
			return false !== $timezone ? $date->setTimezone($timezone) : $date;
		}
		
		if ($date instanceof \DateTime || $date instanceof \DateTimeInterface) {
			$date = clone $date;
			if (false !== $timezone) {
				$date->setTimezone($timezone);
			}
			
			return $date;
		}
		
		// los chequeos
		
		$dt = null;
		try {
			$dt = new \DateTime($date, $defaultTimezone);
			if (false !== $timezone) {
				$dt->setTimezone($defaultTimezone);
			}
			if ($dt instanceof \DateTime) return $dt;
			$asString = (string)$date;
			if (ctype_digit($asString) || (!empty($asString) && '-' === $asString[0] && ctype_digit(substr($asString, 1)))) {
				$dt = new \DateTime('@' . $date);
			}
		} catch (\Exception $ex) {
			return null;
		}
		return $dt ?? null;
	}
	
}