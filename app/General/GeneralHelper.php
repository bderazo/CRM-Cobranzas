<?php
namespace General;


use Models\Archivo;
use upload;

class GeneralHelper {
	
	static function format_numero($numero,$caracteres) {
		for($i = strlen($numero); $i <= $caracteres; $i++){
				$numero = '0'.$numero;
		}
		return $numero;
	}

	static function uploadFiles($id_modulo, $modulo, $tipo_archivo, $archivo, $descripcion_archivo, $nombre_archivo, $dir)
	{
		$mensaje = '';
		if(!is_dir($dir)) {
			$mensaje = "Error Carga Archivo: El directorio $dir de archivos no existe";
			\Auditor::error($mensaje, $modulo, $archivo);
			return $mensaje;
		}
		$upload = new Upload($archivo);
		if(!$upload->uploaded) {
			$mensaje = "Error Carga Archivo: " . $upload->error;
			\Auditor::error($mensaje, $modulo, $archivo);
			return $mensaje;
		}
		// save uploaded image with no changes
		$upload->Process($dir);
		if($upload->processed) {
			if (($archivo['type'] == 'image/png') || ($archivo['type'] == 'image/jpeg')) {
				$uploadThumb = new Upload($archivo);
				if ($uploadThumb->uploaded) {
					$uploadThumb->image_resize = true;
					$uploadThumb->image_x = 50;
					$uploadThumb->image_ratio_y = true;
					// save uploaded image with no changes
					$uploadThumb->Process($dir . '/thumb');
				}
			}
			//INSERTAR EN BASE EL ARCHIVO
			$arch = new Archivo();
			$arch->parent_id = $id_modulo;
			$arch->parent_type = $modulo;
			$arch->tipo_archivo = $tipo_archivo;
            $arch->nombre = $nombre_archivo;
			$arch->nombre_sistema = $upload->file_dst_name;
			$arch->longitud = $archivo['size'];
			$arch->tipo_mime = $archivo['type'];
			$arch->descripcion = $descripcion_archivo;
			$arch->fecha_ingreso = date("Y-m-d H:i:s");
			$arch->fecha_modificacion = date("Y-m-d H:i:s");
			$arch->usuario_ingreso = \WebSecurity::getUserData('id');
			$arch->usuario_modificacion = \WebSecurity::getUserData('id');
			$arch->eliminado = 0;
			$arch->save();

			$mensaje = "Archivo " . $nombre_archivo . " guardado exitosamente";
			\Auditor::info($mensaje, $modulo,$archivo);
			return $mensaje;
		} else {
			$mensaje = "Error Carga Archivo: " . $upload->error;
			\Auditor::error($mensaje, $modulo, $archivo);
			return $mensaje;
		}
	}

    static function sumarDiasLaborables($fecha,$dias){
        $datestart = strtotime($fecha);
        $diasemana = date('N',$datestart);
        $totaldias = $diasemana + $dias;
        $findesemana = intval( $totaldias/5) *2 ;
        $diasabado = $totaldias % 5 ;
        if ($diasabado==6) $findesemana++;
        if ($diasabado==0) $findesemana=$findesemana-2;

        $total = (($dias+$findesemana) * 86400)+$datestart ;
        return date('Y-m-d', $total);
    }
}