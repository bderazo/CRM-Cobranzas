<?php
// aca poner los nombres de las acciones de reporte para que el otro coso genere las urls...
return [
	'pqr' => [
		['nombre' => 'Número de casos PQR', 'link' => 'numeroCasos',],
		['nombre' => 'Tiempo de Respuesta - Cierre', 'link' => 'tiempoRespuesta',],
		['nombre' => 'Valor promotor neto ', 'link' => 'promotorNeto',],
	
	],
	
	'csi' => [
		//['nombre' => 'Tendencia por Pregunta TB', 'link' => 'tendenciasTB',],
	],
	
	'urls' => [
		'csi' => '/reportes/',
		'pqr' => '/reportes/',
	]
];