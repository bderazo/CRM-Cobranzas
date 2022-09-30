<?php

namespace Reportes\Mezclas;

use Models\DevolucionFormula;
use Models\Egreso;
use Models\MaterialExtrusion;

class BodegaMezclas
{
    /** @var \PDO */
    var $pdo;

    /**
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function calcular($filtros)
    {
        $lista = $this->consultaBase($filtros);
        return $lista;
    }

    function consultaBase($filtros)
    {
        $pdo = $this->pdo;
        $db = new \FluentPDO($pdo);

        $query = "SELECT o.id AS orden_id, o.numero AS orden, df.extrusora, sum(dfd.peso) AS disponible";
        $query .= " FROM devolucion_formula df ";
        $query .= " INNER JOIN devolucion_formula_detalle dfd ON df.id = dfd.devolucion_formula_id ";
        $query .= " INNER JOIN orden_extrusion o ON o.id = df.orden_extrusion_id ";
//        $query .= " INNER JOIN material m ON m.id = dfd.material_id ";
        $query .= " WHERE dfd.eliminado = 0 AND df.estado = 'aprobado' ";

        if (@$filtros['fecha_corte']) {
            $query .= " AND date(df.fecha_ingreso) <=  '" . $filtros['fecha_corte'] . "' ";
            $fecha_corte = $filtros['fecha_corte'];
        } else {
            $fecha_corte = '';
        }
        if (@$filtros['orden']) {
            $like = $pdo->quote('%' . strtoupper($filtros['orden']) . '%');
            $query .= " AND upper(o.numero) like $like ";
        }
        if (@$filtros['extrusora']) {
            $like = $pdo->quote('%' . strtoupper($filtros['extrusora']) . '%');
            $query .= " AND upper(df.extrusora) like $like ";
        }
        if (@$filtros['material']) {
            $like = $pdo->quote('%' . strtoupper($filtros['material']) . '%');
            $query .= " AND upper(m.nombre) like $like ";
        }
        $query .= " GROUP BY o.id, df.extrusora,
         o.numero,
 o.bodega,
 o.fecha_entrega,
 o.copias_etiqueta,
 o.peso_neto_rollo,
 o.largo_rollo,
 o.codigo,
 o.maquina,
 o.peso_cono,
 o.tara,
 o.estado,
 o.fecha_ingreso,
 o.fecha_modificacion,
 o.usuario_ingreso,
 o.usuario_modificacion,
 o.eliminado,
 o.observaciones,
 o.peso_bruto_rollo,
 o.solicitud_despacho_material,
 o.kilos_hora,
 o.horas_produccion,
 o.tipo,
 o.cantidad,
 o.unidad,
 o.densidad,
 o.diametro_cono,
 o.consumo_materia_prima";
        $query .= " ORDER BY o.numero DESC";
        $qData = $pdo->query($query);
        $mezclas_data = $qData->fetchAll();
        $lista = [];
        $total_ingresos = 0;
        $total_egresos = 0;
        $total_saldo = 0;
        foreach ($mezclas_data as $data) {
            $q = $db->from('material_extrusion me')
                ->innerJoin('material m ON m.id = me.material_id')
                ->select(null)
                ->select('m.nombre AS material, me.porcentaje')
                ->where('me.eliminado', 0)
                ->where('me.orden_extrusion_id', $data['orden_id'])
                ->where('me.extrusora', $data['extrusora'])
                ->orderBy('m.nombre');
            $material = $q->fetchAll();
            $data['material'] = [];
            if ($material) {
                $data['material'] = $material;
            }

			$q = $db->from('mezcla_extrusion me')
				->select(null)
				->select('me.porcentaje, me.mezcla_orden_extrusion_id, me.mezcla_extrusora')
				->where('me.eliminado', 0)
				->where('me.orden_extrusion_id', $data['orden_id'])
				->where('me.extrusora', $data['extrusora']);
			$mezcla = $q->fetchAll();
			foreach($mezcla as $m){
				$m['material'] = 'MEX'.$m['mezcla_orden_extrusion_id'].'-'.$m['mezcla_extrusora'];
				$data['material'][] = $m;
			}


            $egresos = Egreso::porMezcla($data['orden_id'], $data['extrusora'], $fecha_corte);
            $saldo = $data['disponible'] - $egresos;
            if ($saldo > 0) {
                unset($data[0]);
                unset($data[1]);
                unset($data[2]);
                unset($data[3]);
                $total_ingresos = $total_ingresos + $data['disponible'];
                $total_egresos = $total_egresos + $egresos;
                $total_saldo = $total_saldo + $saldo;
                $data['ingresos'] = number_format($data['disponible'], '2', '.', '');
                $data['egresos'] = number_format($egresos, '2', '.', '');
                $data['saldo'] = number_format($saldo, '2', '.', '');

                $movimientos_ingresos = DevolucionFormula::ingresos($data['orden_id'],$data['extrusora'],$fecha_corte);
                $movimientos_egresos = Egreso::egresos($data['orden_id'],$data['extrusora'],$fecha_corte);
                $movimientos = array_merge($movimientos_ingresos,$movimientos_egresos);
                $data['movimientos'] = $movimientos;
                $lista['data'][] = $data;
            }
        }
        $lista['total'] = [
            'total_ingresos' => number_format($total_ingresos, '2', '.', ''),
            'total_egresos' => number_format($total_egresos, '2', '.', ''),
            'total_saldo' => number_format($total_saldo, '2', '.', ''),
        ];
        return $lista;
    }

    function exportar($filtros)
    {
        $q = $this->consultaBase($filtros);
        return $q;
    }
}
