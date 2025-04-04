//
// Use internal $.serializeArray to get list of form elements which is
// consistent with $.serialize
//
// From version 2.0.0, $.serializeObject will stop converting [name] values
// to camelCase format. This is *consistent* with other serialize methods:
//
//   - $.serialize
//   - $.serializeArray
//
// If you require camel casing, you can either download version 1.0.4 or map
// them yourself.
//

(function ($) {
	$.fn.serializeForm = function () {
		"use strict";
		var result = {};
		var inputs = $(this).find(':input');
		$.each(inputs, function (i, el) {
			var name = $(el).prop('name');
			if (!name)
				return;
			result[name] = $(":input[name='" + name + "']").val();
		});

		// var extend = function (i, element) {
		// 	var node = result[element.name];
		//
		// 	// If node with same name exists already, need to convert it to an array as it
		// 	// is a multi-value field (i.e., checkboxes)
		// 	if ('undefined' !== typeof node && node !== null) {
		// 		var val = $(element).val();
		//
		// 		if ($.isArray(node)) {
		// 			node.push(val);
		// 		} else {
		// 			result[element.name] = [node, val];
		// 		}
		// 	} else {
		// 		var varo = $(element);
		//
		// 		result[element.name] = element.value;
		// 	}
		// };
		//
		// $.each(this.serializeArray(), extend);
		return result;
	};
})(jQuery);
