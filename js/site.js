/**
 * Created by Vegeta on 2016-11-06.
 */

function calculateAgeInYears(date) {
	var now = new Date();
	var current_year = now.getFullYear();
	var year_diff = current_year - date.getFullYear();
	var birthday_this_year = new Date(current_year, date.getMonth(), date.getDate());
	var has_had_birthday_this_year = (now >= birthday_this_year);

	return has_had_birthday_this_year
		? year_diff
		: year_diff - 1;
}

function toggleRequired(id, val) {
	var input = $(id);
	if (val) {
		input.addClass('required');
	} else {
		input.removeClass('required');
	}
}

VueDatepickerOptions = {
	bootstrap: false
};

/**
 * Componente que crea un input con jquery ui datepicker, version inicial
 */
Vue.component('datepicker', {
	//template: '<div class="inner-addon left-addon"><i class="glyphicon glyphicon-calendar"></i><input type="text" class="form-control"/></div>',
	template: '<input type="text"/>',
	props: ['value', 'tipo', 'bootstrap', 'options'],
	mounted: function () {
		var vm = this;
		var opt = {
			dateFormat: 'yy-mm-dd',
			showButtonPanel: true,
			changeMonth: true,
			changeYear: true
		};

		var bootstrap = VueDatepickerOptions.bootstrap;
		if (this.bootstrap !== undefined)
			bootstrap = this.bootstrap;

		if (this.tipo === 'pasado') {
			opt = $.extend({}, opt, {maxDate: new Date(), yearRange: '-100:+0'});
		}
		if (this.tipo === 'futuro') {
			opt = $.extend({}, opt, {minDate: new Date()});
		}
		opt.onClose = function (date) {
			vm.$emit('input', date);
		};

		var jel = $(this.$el);
		var extraSmall = jel.hasClass('input-xs');

		jel.attr('readonly', true).keyup(function(e) {
			if(e.keyCode === 8 || e.keyCode === 46) {
				$.datepicker._clearDate(this);
				$(this).datepicker("show");
			}
		});
		jel.val(this.value).datepicker(opt);
		//if (this.bootstrap) {
		if (bootstrap) {
			var iconStyle = extraSmall ? 'style="padding:5px"' : '';
			if (extraSmall) {
				jel.css('padding-left', '20px');
			}
			jel.addClass('form-control')
				.wrap('<div class="inner-addon left-addon"></div>');
			$('<i class="fa fa-calendar" ' + iconStyle + '></i>').insertBefore(jel);
		}
	},
	watch: {
		'value': function (val) {
			$(this.$el).val(val);
		}
	}
});

_autonameVueCount = 0;
Vue.directive('autoname', {
	inserted: function (el, binding, vnode) {
		_autonameVueCount++;
		$(el).prop('name', '_auto' + _autonameVueCount);
	}
});

Vue.directive('dinero', {
	inserted: function (el, binding, vnode) {
		if (!this.opciones) {
			this.opciones = {
				currencySymbol: '$ '
				//currencySymbolPlacement: 'p'
			};
		}
		$(el).autoNumeric('init', this.opciones);
	},
	update: function (el, binding, vnode) {
		$(el).autoNumeric('update', this.opciones);
	}
});

// ver filtros
// https://gist.github.com/belsrc/672b75d1f89a9a5c192c

// ESTO, componente de formateo de cosas y solo entrada de numeros
// Depende de autoNumeric.js
Vue.component('input-number', {
	template: '<input type="text" @blur="focusOut" @focus="seleccionar" />',
	props: ['value', 'options'],
	methods: {
		focusOut: function (e) {
			var elem = $(this.$el);
			// elem.autoNumeric('init', this.autoOptions);
			//var txt = $(this.$el).val();
			var txt = elem.autoNumeric('get');
			if (txt == '') {
				this.$emit('input', this.autoOptions.defaultEmpty);
				return;
			}
			var num = elem.autoNumeric('getNumber');
			this.$emit('input', num);
		},
		seleccionar: function () {
			if ($(this.$el).val() != '')
				$(this.$el).autoNumeric('unSet').select();
		}
	},
	watch: {
		'value': function (val) {
			$(this.$el).val(val).autoNumeric('update', this.autoOptions);
		}
	},
	data: function () {
		return {
			autoOptions: {
				//selectNumberOnly: true,
				onInvalidPaste: 'ignore',
				defaultEmpty: null
			}
		}
	},
	mounted: function () {
		if (this['options']) {
			this.autoOptions = $.extend(this.autoOptions, this.options);
		}
		$(this.$el).val(this.value)
			.autoNumeric('init', this.autoOptions);
	}
});