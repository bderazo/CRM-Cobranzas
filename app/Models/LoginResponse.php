<?php
/**
 * Created by PhpStorm.
 * User: Vegeta
 * Date: 2016-11-06
 * Time: 20:55
 */

namespace Models;

/**
 * Respuesta a un challenge de login
 * @package Models
 */
class LoginResponse {
	var $username;
	var $success = false;
	var $error = '';
	var $userdata;
	var $permisos = [];
	var $perfiles = [];
	
	/**
	 * LoginResponse constructor.
	 * @param $username
	 */
	public function __construct($username) {
		$this->username = $username;
	}
	
	function retError($error) {
		$this->error = $error;
		return $this;
	}
}