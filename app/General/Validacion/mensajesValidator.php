<?php
return [
	\Particle\Validator\Rule\Length::TOO_LONG => '{{ name }} es muy largo y debe tener {{ length }} caracteres',
	\Particle\Validator\Rule\Length::TOO_SHORT => '{{ name }} es muy corto y debe tener {{ length }} caracteres',
	\Particle\Validator\Rule\Required::NON_EXISTENT_KEY => '{{ name }} es requerido',
	\Particle\Validator\Rule\Numeric::NOT_NUMERIC => '{{ name }} debe ser numérico',
	\Particle\Validator\Rule\NotEmpty::EMPTY_VALUE => '{{ name }} no debe estar vacío',
	\Particle\Validator\Rule\Integer::NOT_AN_INTEGER => '{{ name }} debe ser un entero',
	\Particle\Validator\Rule\Digits::NOT_DIGITS => '{{ name }} debe tener solo números',
	\Particle\Validator\Rule\Alpha::NOT_ALPHA => '{{ name }} debe consistir solo de letras',
	\Particle\Validator\Rule\Regex::NO_MATCH => '{{ name }} es inválido',
	\Particle\Validator\Rule\Callback::INVALID_VALUE => '{{ name }} es inválido',
	\Particle\Validator\Rule\Email::INVALID_FORMAT => '{{ name }} debe ser una dirección de email válida',
	\Particle\Validator\Rule\IsArray::NOT_AN_ARRAY => '{{ name }} debe ser un arreglo',
	\Particle\Validator\Rule\Between::TOO_BIG => '{{ name }} debe ser menor o igual a {{ max }}',
	\Particle\Validator\Rule\Between::TOO_SMALL => '{{ name }} debe ser mayor o igual a {{ min }}',

	\Particle\Validator\Rule\LengthBetween::TOO_SHORT => '{{ name }} debe tener {{ min }} caracteres o más',
	\Particle\Validator\Rule\LengthBetween::TOO_LONG => '{{ name }} debe tener {{ max }} caracteres o menos',

	\Particle\Validator\Rule\GreaterThan::NOT_GREATER_THAN => '{{ name }} debe ser mayor a {{ min }}',
	\Particle\Validator\Rule\LessThan::NOT_LESS_THAN => '{{ name }} debe ser menor que {{ max }}',
	\Particle\Validator\Rule\InArray::NOT_IN_ARRAY => '{{ name }} debe estar dentro de los valores definidos',
	\Particle\Validator\Rule\Datetime::INVALID_VALUE => '{{ name }} debe ser una fecha válida',
	\Particle\Validator\Rule\Alnum::NOT_ALNUM => '{{ name }} debe tener solo números o letras',
];