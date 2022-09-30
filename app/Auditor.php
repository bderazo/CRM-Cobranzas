<?php

/**
 * Class Auditor
 * Fachada para utilizar algun mecanismo de log que se defina
 */
class Auditor
{
    const INFO = 'INFO';
    const WARN = 'WARNING';
    const DEBUG = 'DEBUG';
    const ERROR = 'ERROR';

    static $enabled = true;

    /** @var \Util\IAuditoriaRepository */
    static protected $logRepository;

    static function setLogRepository($repo)
    {
        self::$logRepository = $repo;
    }

    public static function log($nivel, $mensaje, $usuario = '', $modulo = '', $datos = null)
    {
        if (!self::$enabled) return false;
//		if (!self::$logRepository) // throw?
//			return false;
        return self::$logRepository->saveLog($nivel, $mensaje, $usuario, $modulo, $datos);
    }

    public static function info($mensaje, $modulo = '', $datos = null)
    {
        return self::log(Auditor::INFO, $mensaje, '', $modulo, $datos);
    }

    public static function warn($mensaje, $modulo = '', $datos = null)
    {
        return self::log(Auditor::WARN, $mensaje, '', $modulo, $datos);
    }

    public static function error($mensaje, $modulo = '', $datos = null)
    {
        return self::log(Auditor::ERROR, $mensaje, '', $modulo, $datos);
    }

    public static function debug($mensaje, $modulo = '', $datos = null)
    {
        return self::log(Auditor::DEBUG, $mensaje, '', $modulo, $datos);
    }
}