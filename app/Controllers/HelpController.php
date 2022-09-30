<?php

namespace Controllers;


class HelpController extends BaseController {
	
	function init() {
		\WebSecurity::secureUserExists();
		\Breadcrumbs::add('/help', 'Ayuda');
	}
	
	function index() {
		\Breadcrumbs::active('Manual');
		
		$config = @$this->get('config')['manual'];
		if (!$config || empty($config['url_video'])) {
			$this->flash->addMessage('error', "El video del manual no se encuentra disponible en este momento.");
			return $this->redirectToAction('/home');
		}
		
		$data = [
			'video' => $config['url_video'],
			'type' => @$config['mime_type'] ?: 'video/mp4',
			'opciones' => @$config['opciones'],
		];
		
		$this->render('index', $data);
	}
	
}