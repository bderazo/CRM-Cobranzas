<?php

namespace Controllers\admin;

use Controllers\BaseController;
use Notificaciones\AbstractEmailSender;
use Notificaciones\EmailMessage;

class SystemTestController extends BaseController {
	
	var $area = 'admin';
	
	function init() {
		\Breadcrumbs::add('/admin/systemTest', 'System');
		\WebSecurity::secure('admin');
	}
	
	function index() {
		$config = $this->get('config');
		$emailConfig = $config['email'];
		
		foreach ($emailConfig as $k => $v) {
			if (is_array($v))
				$emailConfig[$k] = json_encode($v);
		}
		
		$dbConfig = $config['eloquent'];
		
		$pass = @$emailConfig['password'] ?? '***';
		$emailConfig['password'] = str_repeat('*', strlen($pass));
		$data['emailConfig'] = $emailConfig;
		$servicioConfig = @$config['servicioDatacleaning'] ?? [];
		
		$datos = [
			'database' => $dbConfig['database'],
			'platformPhp' => PHP_VERSION,
			'platformOS' => php_uname(),
			'API DataCleaning url' => @$servicioConfig['url'],
			'API DataCleaning user' => @$servicioConfig['user'],
			'Fecha/Hora PHP' => date('Y-m-d H:i:s'),
		];
		$data['datos'] = $datos;
		$this->render('index', $data);
	}
	
	function testEmail($email) {
		if (!$email)
			return "No hay un email";
		$config = $this->get('config')['email'];
		print_r($config);
		$this->probarEmail($email, $config);
	}
	
	protected function probarEmail($email, $config) {
		try {
			$random = mt_rand(0, 999);
			$body = "Hola usuario de pruebas! Enviado desde web app. Un número random $random<br><br><i>Cheers!</i>";
			$msg = new EmailMessage();
			$msg->addTo($email)
				->setBody($body)
				->setSubject('PRUEBAS EMAIL API')
				->setHtml(true);
			
			/** @var AbstractEmailSender $mail */
			$mail = $this->get('mailSender');
			$mail->config = $config;
			$res = $mail->sendMessage($msg);
			if ($res->exception)
				$res->exception = $res->getExceptionString();
			print_r($res);
		} catch (\Exception $ex) {
			echo $ex->getMessage() . "\n" . $ex->getTraceAsString();
		}
		exit();
	}
	
	function emailSender() {
		\Breadcrumbs::active('Prueba email');
		
		$config = $this->get('config')['email'];
		
		$from = $config['from'];
		$add = key($from);
		$config['fromAddress'] = $add;
		$config['fromName'] = $from[$add];
		
		$data['config'] = json_encode($config);
		$this->render('emailSender', $data);
	}
	
	function testEmailConfig($json) {
		$data = json_decode($json, true);
		$email = $data['email'];
		if (!$email) {
			echo 'no email';
			exit();
		}
		$config = $data['config'];
		$config['from'] = [$config['fromAddress'] => $config['fromName']];
		print_r($config);
		$this->probarEmail($email, $config);
	}
}