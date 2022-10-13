<?php

namespace General;

use Util\IAuditoriaRepository;

/**
 * Class AuditorDatabase
 * @package General
 * Adaptador que utiliza una tabla especial de la base de datos para el log de eventos del sistema
 */
class AuditorDatabase implements IAuditoriaRepository {
	
	var $pdoFactory;
	var $ipaddress;
	var $debug = false;
	
	/**
	 * AuditorDatabase constructor.
	 * @param $pdoFactory
	 */
	public function __construct(callable $pdoFactory) {
		$this->pdoFactory = $pdoFactory;
	}
	
	function saveLog($nivel, $mensaje, $usuario = '', $modulo = '', $datos = null) {
		if (!is_callable($this->pdoFactory))
			return false;
		$data = false;
		try {
			$pdo = call_user_func($this->pdoFactory);
			$db = new \FluentPDO($pdo);
			$data = [
				'fecha' => date('Y-m-d H:i:s'), // new \FluentLiteral('now()'),
				'nivel' => $nivel,
				'mensaje' => $mensaje,
			];
			$data['usuario'] = !$usuario ? \WebSecurity::currentUsername() : $usuario;
			if ($modulo) $data['modulo'] = trim(substr($modulo, 0, 100));
			if ($datos) {
				$dat = $datos;
				if ($datos instanceof \Exception)
					$dat = ['message' => $datos->getMessage(), 'trace' => $datos->getTraceAsString()];
				$data['datos'] = json_encode($dat, JSON_PRETTY_PRINT);
			}
			
			$data['ipaddress'] = $this->ipaddress;
			$db->insertInto('log_evento', $data)->execute();
		} catch (\Exception $ex) {
			if ($this->debug)
				throw $ex;
		}
		return $data;
	}
}