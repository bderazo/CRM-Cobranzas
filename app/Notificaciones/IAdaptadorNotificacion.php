<?php

namespace Notificaciones;

interface IAdaptadorNotificacion {
	function enviar($mensajes);
}