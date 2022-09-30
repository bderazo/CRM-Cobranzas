/**
 * Validador de Cedulas y RUCs del Ecuador, 2016
 * @type {{procesar, esDocumentoValido, setNumProvincias}}
 */
var ValidadorCedula = (function () {
	var config = {
		'natural': [2, 1, 2, 1, 2, 1, 2, 1, 2],
		'privada': [4, 3, 2, 7, 6, 5, 4, 3, 2],
		'publica': [3, 2, 7, 6, 5, 4, 3, 2],
		'error': []
	};
	var numProvincias = 24;

	function withResponse(numero, mensaje, esRuc, valido, tipo) {
		tipo = tipo || '';
		return {
			numero: numero,
			mensaje: mensaje,
			ruc: esRuc || false,
			valido: valido || false,
			tipo: tipo
		}
	}

	var proceso = function (cedula) {
		if (!cedula)
			return withResponse(cedula, 'Cedula vacia');
		var $len = cedula.length;
		if (!/^\d+$/.test(cedula))
			return withResponse(cedula, 'Todos los caracteres deben ser numeros');

		if (!($len == 10 || $len == 13))
			return withResponse(cedula, 'Longitud inadecuada');
		var $esruc = $len == 13;
		var $modulo = 11;

		var $d = [];
		for (var $i = 0; $i < 10; $i++)
			$d.push(parseInt(cedula[$i]));

		if ($d[2] == 7 || $d[2] == 8)
			return withResponse(cedula, 'El tercer dígito ingresado es inválido', $esruc);

		var $prov = cedula.substr(0, 2);
		if ($prov != 30 && ($prov < 1 || $prov > numProvincias))
			return withResponse(cedula, 'El codigo de la provincia (dos primeros dígitos) es inválido', $esruc);

		var $tipo = 'error';
		var $p = new Array(9);
		$p = $p.map(function (x, i) {
			return 0;
		});

		//$p = array_fill(0, 9, 0); // llenar de ceros
		if ($d[2] < 6) {
			$tipo = 'natural';
			$modulo = 10;
		} else if ($d[2] == 6) $tipo = 'publica';
		else if ($d[2] == 9) $tipo = 'privada';

		if ($tipo == 'error') {
			return withResponse(cedula, 'El numero de cédula no corresponde a ningún formato reconocido.', $esruc, false, $tipo);
		}


		var coeficientes = config[$tipo];
		for (var i in coeficientes) {
			var coef = coeficientes[i];
			$p[i] = $d[i] * coef;
			if ($tipo == 'natural' && $p[i] >= 10)
				$p[i] -= 9;
		}

		var suma = $p.reduce(function (a, b) {
			return a + b;
		}, 0);

		var $residuo = suma % $modulo;
		var $digitoVerificador = $residuo == 0 ? 0 : $modulo - $residuo;

		if ($esruc && cedula.substr(10, 3) == '000')
			return withResponse(cedula, 'El ruc no puede terminar con ceros.', $esruc, false, $tipo);

		switch ($tipo) {
			case 'natural':
				if ($digitoVerificador != $d[9] || cedula == '2222222222') // caso especial
					return withResponse(cedula, 'El numero de cedula de la persona natural es incorrecto.', $esruc, false, $tipo);
				break;
			case 'privada':
				if ($digitoVerificador != $d[9])
					return withResponse(cedula, 'El ruc de la empresa del sector privado es incorrecto.', $esruc, false, $tipo);
				break;
			case 'publica':
				if ($digitoVerificador != $d[8])
					return withResponse(cedula, 'El ruc de la empresa del sector público es incorrecto.', $esruc, false, $tipo);
				break;
		}
		return withResponse(cedula, 'OK', $esruc, true, $tipo);
	};

	var docValido = function (cedula) {
		var res = proceso(cedula);
		return res.valido;
	};

	// binds jquery validator
	var _mensajeDetallado = "";
	var bindJquery = function () {

		$.validator.addMethod("cedulaRuc", function (value, element, params) {
			_mensajeDetallado = "";

			// el control de que si esta vacio o no es para "required"
			if (!value || 0 === value.length) {
				return true;
			}

			var res = proceso(value);
			//console.log(res);
			if (!res.valido) {
				_mensajeDetallado = res.mensaje;
				return false;
			}

			if (params == 'soloRuc' && !res.ruc) {
				_mensajeDetallado = 'El valor debe ser un RUC';
				return false;
			}

			if (params == 'soloCedula' && res.ruc) {
				_mensajeDetallado = 'El valor debe ser una cédula';
				return false;
			}

			return res.valido;
		}, function () {
			return _mensajeDetallado;
		});

		return this;
	};

	// exports
	return {
		procesar: proceso,
		esDocumentoValido: docValido,
		withJqueryValidate: bindJquery,
		setNumProvincias: function (num) {
			numProvincias = num;
		}
	};
})();