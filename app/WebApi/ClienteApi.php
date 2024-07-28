<?php

namespace WebApi;

use Models\Cliente;
use Slim\Http\Request;
use Slim\Http\Response;

class ClienteApi
{
    /**
     * Busca un cliente por su cédula
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function buscarPorCedula(Request $request, Response $response, array $args): Response
    {
        // Obtén la cédula del parámetro de la consulta
        $cedula = $request->getQueryParam('cedula');

        if (empty($cedula)) {
            return $response->withJson(['error' => 'La cédula es requerida'], 400);
        }

        $cliente = Cliente::porCedula($cedula);

        if ($cliente) {
            return $response->withJson($cliente);
        } else {
            return $response->withJson(['error' => 'Cliente no encontrado'], 404);
        }
    }
}
