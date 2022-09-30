<?php

namespace General;

/**
 * Logica sacada del validados de fechas de la libreria
 */
class SimpleDateValidator {
	
	static function datetime($value, $format = null) {
		if ($format !== null) {
			$dateTime = date_create_from_format($format, $value);
			if ($dateTime instanceof \DateTime) {
				return self::checkDate($dateTime, $format, $value);
			}
			return false;
		}
		return @date_create($value);
	}
	
	/**
	 * Checks if $dateTime is a valid date-time object, and if the formatted date is the same as the value passed.
	 *
	 * @param \DateTime $dateTime
	 * @param string $format
	 * @param mixed $value
	 * @return \DateTime|false
	 */
	static function checkDate($dateTime, $format, $value) {
		$equal = (string)$dateTime->format($format) === (string)$value;
		
		if ($dateTime->getLastErrors()['warning_count'] === 0 && $equal) {
			return $dateTime;
		}
		return false;
	}
}