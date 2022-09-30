<?php
/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-13
 * Time: 20:35
 */

namespace Util;

/**
 * Interface IAuditoriaRepository
 * @package Util
 */
interface IAuditoriaRepository {
	function saveLog($nivel, $mensaje, $usuario = '', $modulo = '', $datos = null);
}

