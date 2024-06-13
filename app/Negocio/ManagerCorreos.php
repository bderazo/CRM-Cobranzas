<?php

namespace Negocio;

use Catalogos\CatalogoCasospqr;
use Models\Casopqr;
use Models\Notificacion;
use Models\Plantilla;
use Models\RedContactosPqr;
use Notificaciones\AbstractEmailSender;
use Notificaciones\EmailMessage;
use Util\TwigProcessor;

class ManagerCorreos {
	var $config = []; // algun default? si deberia
	
	/** @var AbstractEmailSender */
	var $sender;
	
	function resolverEmailsRed(Casopqr $caso, $nivel = null) {
		if (!empty($this->config['testEmails']['nivel']))
			return $this->config['testEmails']['nivel'];
		//tipos especiales?
		if (!$nivel) $nivel = $caso->nivel_escalamiento;
		return RedContactosPqr::contactosRed($nivel, $caso->concesionario_id, $caso->baccode, 'normal');
	}
	
	function resolverCliente(Casopqr $caso) {
		if (!empty($this->config['testEmails']['cliente']))
			return [$this->config['testEmails']['cliente']];
		return [
			['email' => $caso->cliente->email, 'name' => $caso->cliente->nombreCompleto(), 'tipo' => 'principal']
		];
	}
	
	function crearLinkCaso($idCaso) {
		$root = @$this->config['urlRoot'];
		if (!$root)
			return '';
		return $root . "/proceso/detalle?id=$idCaso";
	}
	
	// funciones particulares de cada correo
	
	function prepararDatosPlantilla(Casopqr $caso, $emails, $destino, $evento) {
		$datos['caso'] = $caso;
		$datos['cliente'] = $caso->cliente;
		$datos['linkCaso'] = $this->crearLinkCaso($caso->id);
		
		if ($evento == 'cita_cliente') {
			$dt = new \DateTime($caso->cita);
			$datos = array_merge($datos, [
				'cita' => $caso->cita,
				'fecha' => $dt->format('Y/m/d'),
				'fecha_cita' => $dt->format('Y/m/d'),
				'hora_cita' => $dt->format('H:i')
			]);
		}
		
		if ($destino == 'concesionario') {
			$usuario = $emails[0];
			$arr = $caso->toArray();
			$cat = new CatalogoCasospqr();
			$arr['dias'] = $caso->diasAbierto();
			$arr['origen'] = $cat->nombreOrigen($caso->origen);
			$arr['medio'] = $cat->nombreMedio($caso->medio);
			$arr['tipo'] = $cat->nombreTipo($caso->tipo);
			
			$datos = array_merge($datos, [
				'caso' => $arr,
				'telefonos' => $caso->cliente->listaTelefonos(),
				'concesionario' => $caso->concesionario->nombre,
				'sucursal' => $caso->sucursal->nombre,
				'usuario' => $usuario['name'] ?? '',
			]);
		}
		
		if ($evento == 'apertura')
			$datos['operacion'] = 'asignado';
		
		if ($evento == 'escalamiento')
			$datos['operacion'] = 'escalado';
		
		if ($evento == 'devolucion')
			$datos['operacion'] = 'devuelto';
		
		return $datos;
	}
	
	function resolverPlantilla(Notificacion $not) {
		// mas plantillas? o poner el tipo directo en la plantilla? hmmmm
		if ($not->caso->esVentas())
			return 'correo_ventas';
		$destinos = [
			'concesionario' => 'correo_nivel',
			'cliente' => 'correo_cliente',
		];
		if ($not->evento == 'cita_cliente') return 'correo_cita';
		return @$destinos[$not->destino] ?? null;
	}
	
	function getSubject(Notificacion $msg) {
		$subject = 'Caso PQR #' . $msg->caso->id;
		if ($msg->destino == 'cliente')
			$subject = 'Caso PQR Registrado';
		if ($msg->destino == 'cita_cliente')
			$subject = 'Cita para caso agendada';
		return $subject;
	}
	
	function resolverEmails(Notificacion $msg) {
		$caso = $msg->caso;
		
		$copias = [];
		$generales = RedContactosPqr::query()->where('tipo', 'copia_general')->get();
		foreach ($generales as $gen) {
			$copias[] = ['email' => $gen->email, 'name' => $gen->nombre, 'tipo' => 'copia'];
		}
		
		$emails = [];
		
		
		if ($msg->destino == 'concesionario') {
			if ($msg->caso->esVentas()) {
				$lista = RedContactosPqr::query()->where('escalamiento', 'ventas')
					->where('baccode', $caso->baccode)->get();
				foreach ($lista as $row) {
					$emails[] = ['email' => $row->email, 'name' => $row->nombre, 'tipo' => 'principal'];
				}
			} else {
				$emails = $this->resolverEmailsRed($caso, $caso->nivel_escalamiento);
			}
		}
		
		if ($msg->destino == 'cliente') {
			$emails = $this->resolverCliente($caso);
		}
		
		if (!$emails)
			return null;
		return array_merge($emails, $copias);
	}
	
	function enviar(Notificacion $msg) {
		$caso = $msg->caso;
		
		$emails = $this->resolverEmails($msg);
		if (empty($emails)) {
			$msg->setError("No se resolvieron emails para el caso", 'error')->save();
			return $msg;
		}
		
		$tplName = $this->resolverPlantilla($msg);
		if (!$tplName) {
			$msg->setError("Tipo de plantilla no definida para Destino $msg->destino, Evento $msg->evento", 'error')->save();
			return $msg;
		}
		$tpl = Plantilla::getPrimera($tplName);
		if (!$tpl) {
			$msg->setError("Plantilla $tplName no encontrada en la base", 'error')->save();
			return $msg;
		}
		
		$data = $this->prepararDatosPlantilla($caso, $emails, $msg->destino, $msg->evento);
		$twig = new TwigProcessor();
		$htmlEmail = $twig->renderFromString($tpl->contenido, $data);
		$htmlEmail = TwigProcessor::wrapForEmail($htmlEmail);
		
		$mensaje = new EmailMessage();
		$direcciones = []; // OJO hacer algo con esto
		foreach ($emails as $row) {
			if (in_array($row['email'], $direcciones)) continue;
			if (@$row['tipo'] != 'copia')
				$mensaje->addTo($row['email'], $row['name']);
			else
				$mensaje->addCC($row['email'], $row['name']);
			$direcciones[] = $row['email'];
		}
		$msg->subject = $this->getSubject($msg);
		$msg->emails = implode(';', $direcciones);
		$mensaje->setSubject($msg->subject)->setBody($htmlEmail);
		
		$res = $this->sender->sendMessage($mensaje);
		if (!$res) return $msg;
		if ($res->exception)
			$msg->setError($res->exception, 'error');
		if ($res->enviado) {
			$msg->fecha_envio = date('Y-m-d H:i:s');
			$msg->estado = 'enviado';
			$msg->error = null;
		}
		if ($res->fallidos)
			$msg->data = json_encode(['fallidos' => $res->fallidos]);
		$msg->save();
		return $msg;
	}
}



