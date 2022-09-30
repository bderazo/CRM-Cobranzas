<?php

// rutas

$app->get('/', $convention->simpleDispatch('home'))->setName('home');
$app->map(['GET', 'POST'], '/login', $convention->simpleDispatch('home:login'))->setName('login');
$app->get('/logout', $convention->simpleDispatch('home:logout'))->setName('logout');

// rutas definidas para el api de recepcion de cosas
$app->group('/api', function () use ($convention) {
	/** @var \Slim\App $this */
	$this->post('/solicitarDatos', $convention->simpleDispatch('api.Carga:solicitud'));
	$this->post('/recibirDatos', $convention->simpleDispatch('api.Carga:recibirDatos'));
	$this->post('/recibir', $convention->simpleDispatch('api.Carga:recibir'));
});


// ----- DEMOS PRUEBAS----!!!!

// usando solo el dispatch de slim, nombre completo
$app->get('/normalSlimDispatch', 'controllers\HomeController:fiero');

// usando una ruta compleja
$app->get('/rutaCompleja', $convention->simpleDispatch('admin.Users:index'));

// usando ruta con parametros
$app->get('/home/hola/{id}', $convention->simpleDispatch('home:hola'));
