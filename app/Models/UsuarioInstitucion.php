<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Negocio\EnvioNotificacionesPush;

/**
 * @package Models
 *
 * @property integer $id
 * @property integer usuario_id
 * @property integer institucion_id
 * @property string fecha_ingreso
 * @property string fecha_modificacion
 */
class UsuarioInstitucion extends Model {
	
	protected $table = 'usuario_institucion';

	const CREATED_AT = 'fecha_ingreso';
	const UPDATED_AT = 'fecha_modificacion';
	protected $guarded = [];
	public $timestamps = false;

	public function getAllColumnsNames()
	{
		$pdo = self::query()->getConnection()->getPdo();
		$query = 'SHOW COLUMNS FROM usuario';
		$qpro = $pdo->query($query);
		$column_name = 'Field';
		$columns = [];
		$d = $qpro->fetchAll();
		foreach($d as $column){
			$columns[$column['Field']] = $column['Field']; // setting the column name as key too
		}
		return $columns;
	}
	

	public function save($options = []) {
		return parent::save($options);
	}
	

}


