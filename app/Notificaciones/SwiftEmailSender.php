<?php

namespace Notificaciones;

use Swift_Mailer;
use Swift_Message;
use Swift_Plugins_LoggerPlugin;
use Swift_Plugins_Loggers_ArrayLogger;
use Swift_SmtpTransport;

class SwiftEmailSender extends AbstractEmailSender {
	/** @var Swift_SmtpTransport */
	var $transport;
	
	function init($config) {
		$timeout = 15;
		$t = (new Swift_SmtpTransport($config['Host'], $config['Port']))
			->setUsername($config['Username'])
			->setPassword($config['Password'])
			->setStreamOptions(
				[
					'ssl' => [
						'allow_self_signed' => true,
						'verify_peer' => false
					]
				]);
		
		if (is_numeric(@$config['timeout'])) $timeout = $config['timeout'];
		$t->setTimeout($timeout);
		if (@$config['encryption']) $t->setEncryption($config['encryption']);
		$this->transport = $t;
		return $this;
	}
	
	function prepareMessage(EmailMessage $msg) {
		$ctype = $msg->isHtml ? 'text/html' : 'text/plain';
		$from = $msg->from ? $msg->from : $this->config['Username'];
		$m = new Swift_Message();
		$m->setSubject($msg->subject)
			->setFrom($from)
			->setCharset($msg->charset)
			->setContentType($ctype)
			->setBody($msg->body)
			->setTo($msg->to);
		if ($msg->cc) $m->setCc($msg->cc);
		if ($msg->bcc) $m->setBcc($msg->bcc);
		return $m;
	}
	
	function sendMessage(EmailMessage $msg) {
		if (!$this->transport)
			$this->init($this->config);
		$failed = [];
		$logger = null;
		$res = new ResEnvioCorreo();
		$res->emails = implode(';', $msg->getAllEmails());
		try {
			$m = $this->prepareMessage($msg);
			$mailer = new Swift_Mailer($this->transport);
			if ($this->debug) {
				$logger = new Swift_Plugins_Loggers_ArrayLogger();
				$mailer->registerPlugin(new Swift_Plugins_LoggerPlugin($logger));
			}
			$res->numEnviados = $mailer->send($m, $failed);
			$res->enviado = $res->numEnviados > 0; // ? y esto sera
			if (!$res->enviado)
				$res->errorMsg = 'Algunos correos no se pudieron enviar';
		} catch (\Exception $ex) {
			$res->exception = $ex;
			$res->errorMsg = $ex->getMessage();
		}
		$res->fallidos = $failed;
		if ($logger) {
			$this->debugInfo = $logger->dump();
		}
		return $res;
	}
	
	
}