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
use Negocio\PermisosPQR;

class ActividadReciente {
	/** @var  \PDO */
	var $pdo;
	/** @var  PermisosPQR */
	var $permisosPqr;
	
	var $soloHoy;
	var $conComentarios;
	
	var $incluirHora;
	
	var $usuarioIdActual;
	
	function actividadRecienteUsuario($limit = 10, $fecha = null) {
		$db = new \FluentPDO($this->pdo);
		$q = $db->from('casopqr_avance a')->innerJoin('casopqr c on c.id = a.caso_id')
			->select(null)
			->select('a.id, a.caso_id, a.estado_actual, a.operacion, a.fecha_evento, a.usuario')// a.*
			->select('c.origen, c.tipo, c.estado, c.familia, c.categoria, c.subcategoria')
			->select('c.medio, c.numero_servicio, c.nivel_escalamiento')
			->orderBy('a.fecha_evento desc')->limit($limit);
		
		if ($this->conComentarios)
			$q->select('a.comentarios');
		
		if ($this->permisosPqr && $this->permisosPqr->soloEmpresa) {
			$q->where('c.concesionario_id', $this->permisosPqr->empresas);
		}
		
		
		if ($this->soloHoy) {
			// TODO hacer que para el dia en alertas no se muestre los casos que solo YO (actual) cree
			if ($this->usuarioIdActual)
				$q->where("(a.usuario_id <> ? and a.operacion = 'abierto')", $this->usuarioIdActual);
			$this->filtroDiario($q);
		}
		
		if ($fecha) {
			$q->where('a.fecha_evento >= ?', $fecha . ' 00:00:00')
				->where('a.fecha_evento <= ?', $fecha . ' 23:59:59');
		}
		
		$per = $this->permisosPqr;
		if (@$per->data['area'] == 'repuestos') $q->where('c.tipo', 'repuestos');
		if (@$per->data['area'] == 'cat') $q->where('c.tipo', 'falla_tecnica');
		
		$hoy = new \DateTime();
		$cat = new CatalogoCasospqr();
		$textos = $cat->getByKey('textos_historico');
		$lista = $q->fetchAll();
		foreach ($lista as &$row) {
			$estado = $row['estado_actual'];
			$operacion = $row['operacion'];
			$nombreEstado = $cat->nombreEstado($estado);
			
			$row['n_estado'] = $nombreEstado;
			$row['n_tipo'] = $cat->nombreTipo($row['tipo']);
			
			// texto acciones
			if (isset($textos[$operacion])) {
				$texto = $textos[$operacion];
			} else
				$texto = ListasSistema::simpleLabel($operacion);
			$row['texto'] = $texto;
			
			$fecha = new \DateTime($row['fecha_evento']);
			$diff = $hoy->diff($fecha);
			$hace = $this->formatInterval($diff);
			$row['hace'] = $hace;
			$row['tiempo'] = $this->formatCorto($diff);
			if ($this->incluirHora)
				$row['hora'] = $fecha->format('H:i:s');
		}
		return $lista;
	}
	
	function filtroDiario(\SelectQuery $q) {
		$hoy = date('Y-m-d');
		// esto saca solo el primer resultado por los grupos de la primera sentencia, exclusivo de postgresql
		// https://stackoverflow.com/questions/16914098/how-to-select-id-with-max-date-group-by-category-in-postgresql
		$sql = "select distinct on(caso_id) id
		from casopqr_avance where fecha_evento >= '$hoy' order by caso_id, fecha_evento desc";
		$q->where("a.id in ($sql)");
	}
	
	function formatCorto(\DateInterval $dt) {
		if ($dt->h) return $dt->h . 'h';
		if ($dt->i) return $dt->i . 'm';
		if ($dt->s) return $dt->s . 'seg';
		return '';
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