require.config({
	paths: {
		knockout: 'deps/knockout',

		underscore: 'deps/underscore',

		/* jQuery Plugins */
		jqueryUI: 'deps/jquery.ui',
		qtip: 'deps/jquery.qtip'
	},
	shim: {
	    underscore: {
	      exports: '_'
		}
	}
});

require(['jquery', 'util.loader'], function($) {

	/* Start loading indidcator */
	startTitleActivityIndicator();
	//iframe.showIframeLoadingOverlay();

	/* Parse the JSON in the Headway l10n array */
	Headway.blockTypeURLs = $.parseJSON(Headway.blockTypeURLs.replace(/&quot;/g, '"'));
	Headway.allBlockTypes = $.parseJSON(Headway.allBlockTypes.replace(/&quot;/g, '"'));
	Headway.ranTour = $.parseJSON(Headway.ranTour.replace(/&quot;/g, '"'));

	Headway.designEditorProperties = $.parseJSON(Headway.designEditorProperties.replace(/&quot;/g, '"'));

	Headway.layouts = $.parseJSON(Headway.layouts.replace(/&quot;/g, '"'));

	/* Setup modules */
	require(['modules/layout-selector'], function(layoutSelector) {
		layoutSelector.init();
	});

	require(['modules/panel', 'modules/iframe'], function(panel, iframe) {
		panel.init();
		iframe.init();
	});

	require(['modules/menu'], function(menu) {
		menu.init();
	});

	require(['modules/snapshots'], function(snapshots) {
		snapshots.init();
	});

	/* Init tour */
	require(['util.tour'], function (tour) {

		if ( Headway.ranTour[Headway.mode] == false && Headway.ranTour.legacy == false ) {
			tour.start();
		}

	});


	/* Load helpers all at once since they're used everywhere */
	require(['helper.data', 'helper.blocks', 'helper.wrappers', 'helper.context-menus', 'helper.notifications', 'helper.boxes', 'helper.history'], function(data, blocks, wrappers, contextMenus, notifications, boxes, history) {
		history.init();
	});

	/* Load in the appropriate modules depending on the mode */
	switch ( Headway.mode ) {

		case 'grid':

			require(['modules/grid/mode-grid', 'modules/iframe', 'modules/layout-selector'], function(modeGrid) {
				Headway.instance = modeGrid;

				modeGrid.init();
				waitForIframeLoad(modeGrid.iframeCallback);
			});

		break;

		case 'design':

			require(['modules/design/mode-design', 'modules/iframe', 'modules/layout-selector'], function(modeDesign) {
				Headway.instance = modeDesign;

				modeDesign.init();
				waitForIframeLoad(modeDesign.iframeCallback);
			});

		break;

	}

	/* After everything is loaded show the Visual Editor */
	$(document).ready(function() {

		$('body').addClass('show-ve');

	});

	$(window).bind('load', function() {

		/* Remove VE loader overlay after we know page has loaded */
		setTimeout(function () {
			$('div#ve-loading-overlay').remove();
		}, 1000);

	});


});