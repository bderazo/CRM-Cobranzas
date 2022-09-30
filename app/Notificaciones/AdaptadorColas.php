<?php

namespace Notificaciones;

use Httpful\Request;

class AdaptadorColas implements IAdaptadorNotificacion {
	
	var $url;
	
	function enviar($mensajes) {
		$ids = [];
		foreach ($mensajes as $msg) $ids[] = $msg->id;
		$json = json_encode(['notificaciones' => $ids]);
		$res = Request::post($this->url, $json)
			->contentType('application/json')
			->timeout(5)
			->whenError(function () { })// wut
			->send();
		if ($res->code != 200) {
			\Auditor::error('Error enviando a cola, respuesta: ' . $res->code, 'NOTIFICADOR', $res->raw_body);
		}
		return $ids;
	}
}