<?php
namespace Notificaciones;

use PHPMailer\PHPMailer;

class EmailSender {
	/**
	 * @var PHPMailer
	 */
	public $mail;
	public $data = [];
	
	var $config = [];
	
	protected $emails = [];
	
	public function init($exceptions = false) {
		$config = $this->config;
		$this->mail = new PHPMailer\PHPMailer($exceptions);
		$this->mail->isSMTP();                                      // Set mailer to use SMTP
		$this->mail->Host = $this->config['Host'];    //'smtp1.example.com;smtp2.example.com';  // Specify main and backup SMTP servers
		$this->mail->Username = $this->config['Username'];//'user@example.com';                 // SMTP username
		$this->mail->Password = $this->config['Password'];//'secret';                           // SMTP password
		if (@$config['SMTPAuth'])
			$this->mail->SMTPAuth = $this->config['SMTPAuth'];//true;                               // Enable SMTP authentication
		if (@$config['SMTPSecure'])
			$this->mail->SMTPSecure = $this->config['SMTPSecure'];//'tls';                            // Enable TLS encryption, `ssl` also accepted
		if (@$config['Port'])
			$this->mail->Port = (int)$this->config['Port'];//587;
		
		$this->mail->setFrom($this->config['Username'], $this->config['nombre_app']);
		$this->mail->CharSet = 'UTF-8';
		// si no, no manda
		// http://stackoverflow.com/questions/32694103/phpmailer-openssl-error
		if (@$config['options']) {
			$this->mail->SMTPOptions = $config['options'];
		}
	}
	
	function getEmails() {
		return $this->emails;
	}
	
	function setDebug($level) {
		$this->mail->SMTPDebug = $level;
	}
	
	public function addAddress($address, $name = '') {
		$this->emails[] = $address;
		$this->mail->addAddress($address, $name);
	}
	
	
	public function addRecipient($address, $name = '') {
		$this->emails[] = $address;
		$this->mail->addCC($address, $name);
	}
	
	
	public function addBCC($address, $name = '') {
		$this->emails[] = $address;
		$this->mail->addBCC($address, $name);
	}
	
	public function addAttachment($file) {
		if (is_file($file)) {
			$this->mail->addAttachment($file);
		}
	}
	
	public function isHtml($valor = true) {
		$this->mail->isHTML($valor);
	}
	
	public function setSubject($subject) {
		$this->mail->Subject = $subject;
	}
	
	
	public function setBody($contenido) {
		$this->mail->Body = $contenido;
	}
	
	public function enviar() {
		$res = new ResultadoEnvioEmail();
		$res->emails = implode(';', $this->emails);
		if ($this->mail->send()) {
			$res->enviado = true;
		} else {
			$res->error = $this->mail->ErrorInfo;
			\Auditor::error("Error al enviar correo", "EMailSender", $this->mail->ErrorInfo);
		}
		return $res;
	}
	
	public function setData($data = []) {
		$this->data = $data;
	}
}

class ResultadoEnvioEmail {
	var $enviado = false;
	var $error = null;
	var $emails = null;
}