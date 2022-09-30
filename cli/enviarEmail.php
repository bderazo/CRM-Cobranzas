<?php

include __DIR__ . '/bootstrap_cli.php';

function finError($msg) {
	echo $msg . "\n";
	exit(1);
}

$txtIds = @$argv[1];
if (!$txtIds) {
	finError("Ids de log vacio");
}

$modulo = 'enviarEmail.php';
/** @var \Negocio\ManagerCorreos $servicio */
$servicio = $container['mailManager'];
try {
	$ids = explode(',', $txtIds);
	foreach ($ids as $id) {
		$msg = \Models\Notificacion::porId($id);
		if (!$msg) {
			Auditor::error("Envio por cola, id $id no existe", $modulo);
			continue;
		}
		$servicio->enviar($msg);
	}
} catch (\Exception $ex) {
	Auditor::error($ex->getMessage(), $modulo, $ex);
	echo "ERROR: " . $ex->getMessage() . "\n";
	echo $ex->getTraceAsString();
	exit(1);
}






