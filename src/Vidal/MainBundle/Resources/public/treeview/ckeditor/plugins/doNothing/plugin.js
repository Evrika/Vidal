(function() {
	var doNothingCmd =
	{
		exec: function(editor) {
			return;
		}
	};
	var pluginName = 'doNothing';
	CKEDITOR.plugins.add(pluginName,
		{
			init: function(editor) {
				editor.addCommand(pluginName, doNothingCmd);
			}
		});
})();