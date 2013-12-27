$(document).ready(function() {
	var $searcheForm = $('#searche_form');

	$searcheForm.find('.search-query')
		.autocomplete({
			minLength: 2,
			source:    function(request, response) {
				$.ajax({
					url:      "http://vidal.evrika.ru:9200/website/autocompleteext/_search",
					type:     "POST",
					dataType: "JSON",
					data:     '{ "query":{"query_string":{"query":"' + request.term + '*"}}, "fields":["name"], "size":15, "highlight":{"fields":{"name":{}}} }',
					success:  function(data) {
						response($.map(data.hits.hits, function(item) {
							return {
								label: item.highlight.name,
								value: item.fields.name
							}
						}));
					}
				});
			}
		})
		.data("ui-autocomplete")._renderItem =
		function(ul, item) {
			return $("<li></li>").data("item.autocomplete", item).append("<a>" + item.label + "</a>").appendTo(ul);
		};

	$searcheForm.find('.search-type').chosen({disable_search: true});

	$('#searche_nozology').autocomplete({
		minLength: 2,
		source:    function(request, response) {
			$.ajax({
				url:      "http://vidal.evrika.ru:9200/website/autocomplete_nozology/_search",
				type:     "POST",
				dataType: "JSON",
				data:     '{ "query":{"query_string":{"query":"' + request.term + '*"}}, "fields":["name","code"], "size":15, "highlight":{"fields":{"name":{}}} }',
				success:  function(data) {
					response($.map(data.hits.hits, function(item) {
						return {
							label: item.highlight.name,
							value: item.fields.name,
							code:  item.fields.code
						}
					}));
				}
			});
		},
		select:    function(event, ui) {
			$(this.nextElementSibling).append('<li><i></i><span>' + ui.item.value + '</span><b>' + ui.item.code + '</b></li>');
		}
	}).data("ui-autocomplete")._renderItem =
		function(ul, item) {
			return $("<li></li>").data("item.autocomplete", item).append("<a>" + item.label + "</a>").appendTo(ul);
		};

	$('#searche_contraindication')
		.autocomplete({
			minLength: 2,
			source:    function(request, response) {
				$.ajax({
					url:      "http://vidal.evrika.ru:9200/website/autocomplete_contraindication/_search",
					type:     "POST",
					dataType: "JSON",
					data:     '{ "query":{"query_string":{"query":"' + request.term + '*"}}, "fields":["name","code"], "size":15, "highlight":{"fields":{"name":{}}} }',
					success:  function(data) {
						response($.map(data.hits.hits, function(item) {
							return {
								label: item.highlight.name,
								value: item.fields.name,
								code: item.fields.code
							}
						}));
					}
				});
			},
			select:    function(event, ui) {
				$(this.nextElementSibling).append('<li><i></i><span>' + ui.item.value + '</span><b>' + ui.item.code +'</b></li>');
			}
		})
		.data("ui-autocomplete")._renderItem =
		function(ul, item) {
			return $("<li></li>").data("item.autocomplete", item).append("<a>" + item.label + "</a>").appendTo(ul);
		};
});