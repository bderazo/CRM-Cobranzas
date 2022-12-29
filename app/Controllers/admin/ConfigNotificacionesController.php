<?php

namespace Controllers\admin;

use Controllers\BaseController;
use JasonGrimes\Paginator;
use Models\UsuarioLogin;

class ConfigNotificacionesController extends BaseController {

	var $area = 'admin';
	
	function init() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::add('/admin/configNotificaciones', 'Configuración Notificaciones');
	}
	
	function index() {
		\WebSecurity::secure('admin');
		\Breadcrumbs::active('Configuración Notificaciones');
		$file_path = __DIR__ . '/../../../config_notificaciones.php';
		$config_notificaciones = include $file_path;
		$data['model'] = json_encode($config_notificaciones);
		return $this->render('index',$data);
	}
	
	function guardar($json) {
		$data = json_decode($json, true);
		$configEmail = $data['model']['configuracion_email'];

		$file_path = __DIR__ . '/../../../config_notificaciones.php';

		$myfile = fopen($file_path, "w") or die("Unable to open file!");
		$txt = "<?php
return [
    'configuracion_email' => [
		'Username' => '".$configEmail['Username']."',
		'Password' => '".$configEmail['Password']."',
		'Host' => '".$configEmail['Host']."',
		'Port' => '".$configEmail['Port']."',
		'SMTPSecure' => '".$configEmail['SMTPSecure']."',
		'SMTPAuth' => ".$configEmail['SMTPAuth'].",
		'nombre_app' => '".$configEmail['nombre_app']."'
    ],
];";
		fwrite($myfile, $txt);
		fclose($myfile);
		sleep(5);
		$this->flash->addMessage('confirma', 'Devolución modificada');
		return $this->redirectToAction('index');
	}
	
	
}