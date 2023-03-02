<?php
/**
 * Created by PhpStorm.
 * User: vegeta
 * Date: 8/1/2017
 * Time: 2:32 PM
 */

namespace Reportes;


use Catalogos\CatalogoCasospqr;
use General\ListasSistema;

class ActividadReciente {
	/** @var  \PDO */
	var $pdo;
	var $soloHoy;
	var $incluirHora;
	var $usuarioIdActual;

	function actividadRecienteUsuario($limit = 10, $fecha = null) {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('producto_seguimiento ps')
			->innerJoin('producto p ON p.id = ps.producto_id')
			->innerJoin('cliente cl ON cl.id = ps.cliente_id')
			->select(null)
			->select('ps.*, cl.nombres AS cliente_nombre, p.producto AS producto')
			->orderBy('ps.fecha_ingreso desc');
//			->limit($limit);

		if ($this->soloHoy) {
			if ($this->usuarioIdActual)
				$q->where("ps.usuario_ingreso", $this->usuarioIdActual);
		}
		
		$lista = $q->fetchAll();
		$hoy = new \DateTime();
		$retorno = [];
		$cont = 0;
		foreach ($lista as &$row) {
			$fecha = new \DateTime($row['fecha_ingreso']);
			$diff = $hoy->diff($fecha);
			$hace = $this->formatInterval($diff);
			$row['hace'] = $hace;
			$row['tiempo'] = $this->formatCorto($diff);
			if ($this->incluirHora)
				$row['hora'] = $fecha->format('H:i:s');

			if($cont < 4){
				$retorno['resumen'][] = $row;
				$retorno['data'][] = $row;
			}else{
				$retorno['data'][] = $row;
			}
			$cont++;
		}
		return $retorno;
	}

	function formatCorto(\DateInterval $dt) {
		if ($dt->h) return $dt->h . 'h';
		if ($dt->i) return $dt->i . 'm';
		if ($dt->s) return $dt->s . 'seg';
		return 'segundos';
	}
	
	function formatInterval(\DateInterval $dt) {
		$format = function ($num, $unidad) {
			$post = $unidad;
			if ($num > 1 && $unidad != 'min.' && $unidad != 'sec.') {
				if ($unidad == 'mes') $post = 'meses';
				else $post .= 's';
			}
			return $num . ' ' . $post;
		};
		
		$hace = '';
		if ($dt->m) $hace = $format($dt->m, 'mes');
		elseif ($dt->days) $hace = $format($dt->days, 'días');
		elseif ($dt->h) $hace = $format($dt->h, 'hora');
		elseif ($dt->i) $hace = $format($dt->i, 'min.');
		elseif ($dt->s) $hace = $format($dt->s, 'sec.');
		return $hace;
	}
}