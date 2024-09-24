<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @package Models
 *
 * @property integer id
 * @property integer usuario_id
 * @property string modulo
 * @property string tipo
 * @property string filtros
 */
class FiltroBusqueda extends Model
{
	protected $table = 'filtro_busqueda';
	protected $guarded = [];
	public $timestamps = false;

	/**
	 * @param $id
	 * @param array $relations
	 * @return mixed|Material
	 */
	static function porId($id, $relations = [])
	{
		$q = self::query();
		if($relations)
			$q->with($relations);
		return $q->findOrFail($id);
	}

	static function eliminar($id)
	{
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$query = $db->deleteFrom('filtro_busqueda')->where('id', $id)->execute();
		return $query;
	}

	static function porModuloUsuario($modulo, $usuario_id, $tipo = 'web') {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
		$q = $db->from('filtro_busqueda')
			->select(null)
			->select('*')
			->where('modulo',$modulo)
			->where('tipo',$tipo)
			->where('usuario_id',$usuario_id);
		$lista = $q->fetch();
		if(!$lista) return false;
		$filtro = json_decode($lista['filtros'], true);
		return $filtro;
	}
	
	static function obtenerTodosLosDatosDeMiTabla() {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
	
		// Seleccionamos todos los campos de la tabla mi_tabla
		$q = $db->from('mi_tabla');
      
		// Ejecutamos la consulta y retornamos los resultados
		return $q->fetchAll();
	}
	static function obtenerDatosDeReporte($fechaInicio, $fechaFin) {
		// Validar las fechas con el formato 'd/m/Y'
		//echo "Fecha de Inicio: " . $fechaInicio . "<br>";
		//echo "Fecha de Fin: " . $fechaFin . "<br>";
		
	
		// Obtener el PDO a través de FluentPDO
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
	
		// Preparar la consulta SQL con las fechas
		$query = $db->from('producto_seguimiento ps')
					->where('ps.fecha_ingreso BETWEEN ? AND ?', $fechaInicio, $fechaFin);
	
		return $query->fetchAll();
		
	
	}	static function obtenerDatosPorIdDeMiTabla($id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
	
		// Seleccionamos todos los campos de la tabla mi_tabla donde Arbol_5 sea igual a $id
		$q = $db->from('producto_seguimiento')
         ->whereBetween('fecha_ingreso', ['2024-01-01', '2024-09-31']);
	
		// Ejecutamos la consulta y retornamos los resultados
		return $q->fetchAll();
	}
	
	static function actualizarArbol($cedente, $id) {
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);
	
		// Actualizamos los registros donde Arbol_4 sea igual a $cedente
		$db->update('mi_tabla')
		   ->set(['Arbol_5' => $id])
		   ->where('Arbol_4 = ?', $cedente)
		   ->execute();
	}

	static function saveModuloUsuario($modulo, $usuario_id, $filtros, $tipo = 'web')
	{
		$pdo = self::query()->getConnection()->getPdo();
		$db = new \FluentPDO($pdo);

		$q = $db->from('filtro_busqueda')
			->select(null)
			->select('id')
			->where('modulo',$modulo)
			->where('tipo',$tipo)
			->where('usuario_id',$usuario_id);
		$dat = $q->fetch();
		if(!$dat){
			$values = [
				'usuario_id' => $usuario_id,
				'modulo' => $modulo,
				'tipo' => $tipo,
				'filtros' => json_encode($filtros, JSON_PRETTY_PRINT)
			];
			$query = $db->insertInto('filtro_busqueda')->values($values)->execute();
		}else{
			$values = [
				'filtros' => json_encode($filtros, JSON_PRETTY_PRINT)
			];
			$query = $db->update('filtro_busqueda')->set($values)->where('id', $dat['id'])->execute();
		}
		return $query;
	}
}