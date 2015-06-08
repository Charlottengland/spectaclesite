define(['jquery', 'deps/mousetrap'], function($, mousetrap) {

	Headway.aceEditors = {};

	var aceHelper = {
		init: function() {

			/* Close all Ace Editors when Visual Editor is closed */
			window.onunload = function() {

				$.each(Headway.aceEditors, function(index, aceEditor) {

					if ( typeof aceEditor.window != 'undefined' && !aceEditor.window.closed ) {
						aceEditor.window.close();
					}

				});

			}

		},

		showEditor: function(id, mode, initialValue, changeCallback) {

			if ( typeof Headway.aceEditors[id] != 'undefined' && !Headway.aceEditors[id].window.closed ) {
				Headway.aceEditors[id].window.focus();

				return Headway.aceEditors[id];
			}

			var editorConfig = {
				width: 750,
				height: 550
			};

			editorConfig.left = ( screen.width / 2 ) - (editorConfig.width / 2);
			editorConfig.top = ( screen.height / 2 ) - (editorConfig.height / 2);

			Headway.aceEditors[id] = {
				window: window.open(Headway.homeURL + '/?headway-trigger=ace-editor&mode=' + mode, id, 'width=' + editorConfig.width + ',height=' + editorConfig.height + ',top=' + editorConfig.top + ',left=' + editorConfig.left, true)
			}

			Headway.aceEditors[id].window.focus();
			aceHelper.bindEditor(id, mode, initialValue, changeCallback);

			return Headway.aceEditors[id];

		},

		bindEditor: function(id, mode, initialValue, changeCallback) {

			var window = Headway.aceEditors[id].window;

			return $(window).bind('load', function() {

				/* Add keybindings */
				mousetrap.bindEventsTo(window.document);

				var ace = window.ace;

				/* Set paths */
				var acePath = Headway.headwayURL + '/library/visual-editor/' + Headway.scriptFolder + '/deps/ace/';

				ace.config.set('basePath', acePath);
				ace.config.set('modePath', acePath);
				ace.config.set('workerPath', acePath);
				ace.config.set('themePath', acePath);

				/* Init editor */
				Headway.aceEditors[id].editor = ace.edit($(window.document).contents().find('#ace-editor').get(0));
				Headway.aceEditors[id].editorSession = Headway.aceEditors[id].editor.getSession();

				/* Set editor config */
				Headway.aceEditors[id].editor.setTheme('ace/theme/textmate');
				Headway.aceEditors[id].editorSession.setMode('ace/mode/' + mode);

				Headway.aceEditors[id].editor.setShowPrintMargin(false);

				Headway.aceEditors[id].editorSession.setUseWrapMode(true);

				/* Populate the editor */
				Headway.aceEditors[id].editor.setValue(initialValue);

				/* Focus editor */
				Headway.aceEditors[id].editor.gotoLine(0);
				Headway.aceEditors[id].editor.focus();

				/* Bind the editor */
				Headway.aceEditors[id].editorSession.on('change', function(e) {
					return changeCallback(Headway.aceEditors[id].editor);
				});

			});

		}

	}

	aceHelper.init();

	return aceHelper;

});