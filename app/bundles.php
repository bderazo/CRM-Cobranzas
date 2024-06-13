<?php
/**
 * Configuracion de recursos web agrupados
 */
return [
	'sinnada' => [
		'css' => [
			'css/bootstrap.min.css',
			'css/bootstrap.theme.min.css',
			'css/site.css',
		],
		'js' => [
			'js/jquery-3.1.1.min.js',
			'js/bootstrap.min.js',
			'js/vue.js',
			'template/js/inspinia.js',
		]
	],
	
	'site' => [
		'css' => [
			'template_ok/plugins/bootstrap/css/bootstrap.min.css',
			'template_ok/css/style.css',
			'template_ok/css/dark-style.css',
			'template_ok/css/transparent-style.css',
			'template_ok/css/skin-modes.css',
			'template_ok/css/icons.css',
			'template_ok/colors/color1.css',
		],
		'js' => [
			'template_ok/js/jquery.min.js',

			'js/vue.js',
			'js/lodash.js',

		]
	],

	'template' => [
		'css' => [],
		'js' => [
//			'template_ok/js/typehead.js',

			'template_ok/js/themeColors.js',
			'template_ok/js/custom.js',
			'template_ok/js/show-password.min.js',
			'template_ok/js/generate-otp.js',
			'template_ok/js/sticky.js',

			'template_ok/plugins/bootstrap/js/popper.min.js',
			'template_ok/plugins/bootstrap/js/bootstrap.min.js',
			'template_ok/plugins/sidemenu/sidemenu.js',
			'template_ok/plugins/bootstrap5-typehead/autocomplete.js',
			'template_ok/plugins/sidebar/sidebar.js',

			'template_ok/plugins/p-scroll/perfect-scrollbar.js',
			'template_ok/plugins/p-scroll/pscroll.js',
			'template_ok/plugins/p-scroll/pscroll-1.js',

			//INTERNAL SELECT2 JS
			'template_ok/plugins/select2/select2.full.min.js',
			//INTERNAL DATA TABLES JS
			'template_ok/plugins/datatable/js/jquery.dataTables.min.js',
			'template_ok/plugins/datatable/js/dataTables.bootstrap5.js',
			'template_ok/plugins/datatable/dataTables.responsive.min.js',
			//INTERNAL FLOT JS -->
			'template_ok/plugins/flot/jquery.flot.js',
			'template_ok/plugins/flot/jquery.flot.fillbetween.js',
			'template_ok/plugins/flot/chart.flot.sampledata.js',
			'template_ok/plugins/flot/dashboard.sampledata.js',

			//INTERNAL VECTOR JS -->
			'template_ok/plugins/jvectormap/jquery-jvectormap-2.0.2.min.js',
			'template_ok/plugins/jvectormap/jquery-jvectormap-world-mill-en.js',

			//INTERNAL INDEX1 JS -->
			'template_ok/js/index1.js',
		]
	],
	
	'extraPlugins' => [
		'css' => [
			'template/css/plugins/toastr/toastr.min.css',
			'template/js/plugins/gritter/jquery.gritter.css',
		],
		'js' => [
			'template/js/plugins/slimscroll/jquery.slimscroll.min.js',
		]
	],
	
	'select2' => [
		'css' => 'template/css/plugins/select2/select2.min.css',
		'js' => 'template/js/plugins/select2/select2.full.min.js',
	],
	
	'jqueryui' => [
		'css' => [
			'js/jqueryui/jquery-ui.min.css',
			'js/jqueryui/jquery-ui.theme.min.css',
		],
		'js' => [
			'js/jqueryui/jquery-ui.min.js',
			'js/jquery.ui.datepicker-es.js'
		],
	],
	
	'validate' => [
		'js' => [
			'js/validation/jquery.validate.min.js',
			'js/validation/additional-methods.min.js',
			'js/validation/messages_es_PE.js',
			'js/jquery.alphanum.js',
//			'js/autoNumeric.min.js',
			'js/serializeObject2.js',
		]
	],

	'printThis' => [
		'js' => [
			'js/printThis/printThis.js',
		]
	],
	
	'boot_select' => [
		'basedir' => 'js/boot_select',
		'css' => 'css/bootstrap-select.min.css',
		'js' => [
			'js/bootstrap-select.min.js',
			'js/defaults-es_EC.js',
		]
	],

	'select2' => [
		'basedir' => 'js/select2',
		'css' => 'dist/css/select2.min.css',
		'js' => [
			'dist/js/select2.min.js',
			'dist/js/i18n/es.js',
		]
	],
	
	'jasny' => [
		'basedir' => 'template/js/plugins/jasny-bootstrap',
		'css' => 'css/jasny-bootstrap.min.css',
		'js' => 'js/jasny-bootstrap.min.js'
	],
	
	'charts' => [
		'import' => ['boot_select'],
		'basedir' => 'js/highcharts',
		'js' => [
			'highcharts.js', 'modules/exporting.js'
		]
	],
	
	'summer' => [
		'basedir' => 'js/summernote',
		'css' => 'summernote.css',
		'js' => ['summernote.min.js', 'lang/summernote-es-ES.js']
	],

	'gallery' => [
		'css' => [
			'js/unitegallery/package/unitegallery/css/unite-gallery.css',
			'js/unitegallery/package/unitegallery/themes/default/ug-theme-default.css',
		],
		'js' => [
			'js/unitegallery/package/unitegallery/js/unitegallery.js',
			'js/unitegallery/package/unitegallery/themes/default/ug-theme-default.js',
		],
	],

	'socket' => [
		'js' => [
			'js/socket.io.js',
		]
	],

	'datatable' => [
		'basedir' => 'js/DataTables',
		'css' => 'datatables.min.css',
		'js' => [
			'datatables.min.js',
		]
	],

];