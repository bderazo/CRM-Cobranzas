<?php
namespace Notificaciones;


use Pimple\Container;
use Models\Plantilla;

class TemplateNotificacion {
	/** @var Container */
	var $container;
	var $data;

	function __construct($container) {
		$this->container = $container;
		$config = $this->container['config'];
//		$this->data = $config['datos_notificacion'];
	}

	function getTemplate($tpl, $vars) {
		$tpl = Plantilla::getPrimera($tpl);
		$plantilla = $tpl->contenido;
		$twig = $this->container['twig'];
		$template = $twig->createTemplate($plantilla);
		$html = $template->render($vars);
		return $html;
	}

	function getTemplateSys($tpl, $vars) {
		$twig = $this->container['twig'];
		$t = $twig->loadTemplate($tpl);
		$html = $t->render($vars);
		return $html;
	}

}