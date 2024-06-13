// opciones para los medidores del home estandar
var gaugeOptions = {
	chart: {
		type: 'solidgauge'
	},
	title: {
		text: 'Cumplimiento'
	},

	pane: {
		center: ['50%', '85%'],
		size: '140%',
		startAngle: -90,
		endAngle: 90,
		background: {
			backgroundColor: (Highcharts.theme && Highcharts.theme.background2) || '#EEE',
			innerRadius: '60%',
			outerRadius: '100%',
			shape: 'arc'
		}
	},

	tooltip: {
		enabled: false
	},

	// the value axis
	yAxis: {
		stops: [
			[0.1, '#DF5353'], // red
			[0.5, '#DDDF0D'], // yellow
			[0.9, '#55BF3B'] // green
		],
		lineWidth: 0,
		minorTickInterval: null,
		tickAmount: 2,

		labels: {
			y: 16
		},
		min: 0,
		max: 100

	},

	plotOptions: {
		solidgauge: {
			dataLabels: {
				y: 5,
				borderWidth: 0,
				useHTML: true
			}
		}
	},

	credits: {
		enabled: false
	},

	series: [{
		name: 'cumplimiento',
		data: [],
		dataLabels: {
			format: '<div style="text-align:center"><span style="font-size:25px;color:' +
			((Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black') + '">{y}%</span><br/>'
			//+ '<span style="font-size:12px;color:silver">%</span></div>'
		},
		tooltip: {
			valueSuffix: ' %'
		}
	}]
};

function cuadroModelo(reporte, idElem) {
	// datos = reporte.cuadroModelos.labels,
	//Highcharts.chart('cuadroModelo', {
	Highcharts.chart(idElem, {
		chart: {
			type: 'bar'
		},
		title: {
			text: 'Top 10 casos por familia'
		},
		xAxis: {
			categories: reporte.labels,
			title: {
				text: null
			}
		},
		legend: {
			enabled: false
		},
		yAxis: {
			min: 0,
			title: {
				text: 'Número de casos',
				align: 'high'
			},
			labels: {
				overflow: 'justify'
			}
		},
		tooltip: {
			valueSuffix: ' casos'
		},
		plotOptions: {
			bar: {
				dataLabels: {
					enabled: true
				}
			}
		},
//			legend: {
//				layout: 'vertical',
//				align: 'right',
//				verticalAlign: 'top',
//				x: -40,
//				y: 80,
//				floating: true,
//				borderWidth: 1,
//				backgroundColor: ((Highcharts.theme && Highcharts.theme.legendBackgroundColor) || '#FFFFFF'),
//				shadow: true
//			},
		credits: {
			enabled: false
		},
		series: [
			{
				name: 'Familia',
				data: reporte.datos
			}
		]
	});
}

function pantallaCSI(root) {
	var csiOptions = $.extend(true, {}, gaugeOptions);
	csiOptions.series[0].data = [0];
	var medidor2 = null; // ver mas abajo

	var csi = new Vue({
		el: '#tab2',
		data: {
			datos: {}
		},
		methods: {
			comoPor: function (num) {
				if (num)
					return num + '%';
				return '';
			},
			consultar: function () {
				var data = {
					mes: $('#mes').val(),
					anio: $('#anio').val()
				};
				var self = this;
				//$.post('{{ root }}/home/tablaCSI', data, function (res) {
				$.post(root + '/home/tablaCSI', data, function (res) {
					self.datos = res;
					var cumple = res.totales.por;
					if (cumple > 100)
						cumple = 100;

					medidor2.series[0].setData([cumple]);
					medidor2.redraw();
				});
			}
		},
		mounted: function () {
			medidor2 = Highcharts.chart('medidorCSI', csiOptions);
			this.consultar();
		}
	})
}
