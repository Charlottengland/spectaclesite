<div class="headway-small-wrap headway-getting-started-wrap">


	<div class="clearfix"></div>
	<?php if ( is_main_site() ): 	?>
		<div class="headway-sub-title"><img src="<?php echo headway_url() . '/library/admin/images/headway-32.png'; ?>" alt="">Congratulations! You installed Headway. Now you're ready to start making Headway!</div>

		<?php if ( ! headway_get_license_key( 'headway' ) ): ?>
		<div id="message" class="updated below-h2 headway-getting-started-license-notice" style="border-left-color: #ffba00;">
			<p>Please be sure to enter your license key(s) in <a href="<?php echo admin_url('admin.php?page=headway-options'); ?>">Headway Options</a> to receive updates.</p>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<div id="headway-getting-started-ve-link-container">
		<input type="submit" value="Ready to jump in? Enter the Visual Editor!"
		       class="headway-big-button button-primary action" id="headway-getting-started-ve-link" name=""
		       onclick="window.location.href = '<?php echo home_url() . '/?visual-editor=true'; ?>'"/>

		<p>
			You can hide this page by changing the <em>Default Admin Page</em> in <a href="<?php echo admin_url( 'admin.php?page=headway-options' ); ?>" target="_blank">Headway » Options</a>.
		</p>
	</div>

	<h2>New to Headway?  Keep reading!</h2>

	<p>You navigate to all of Headway's core features in the WordPress admin menu. Here's a brief overview of each.</p>

	<div class="headway-infobox-row">
		<div class="headway-infobox big inrow">
			<h3>Getting Started</h3>

			<p>You are here! If you ever get stuck or need to extend your Headway installation, this is the place to
				start.</p>

			<p>If you're totally new to Headway, it's highly recommended you read our <a
					href="http://docs.headwaythemes.com/article/95-beginners-guide-building-your-website-start-to-finish"
					target=_"blank">Headway Beginner's Guide</a>.</p>
		</div>
		<div class="headway-infobox big">
			<h3><a href="<?php echo admin_url( 'admin.php?page=headway-visual-editor' ); ?>">Visual Editor</a></h3>

			<p>The Visual Editor is the magic. It is where you design and style your amazing website.</p>

			<p>Check out this document for a quick overview of the <a
					href="http://docs.headwaythemes.com/article/26-the-basics-of-customizing-a-layout" target="_blank">basics
					of the Visual Editor</a></p>
		</div>
	</div>

	<div class="headway-infobox-row">
		<div class="headway-infobox big inrow">
			<h3><a href="<?php echo admin_url( 'admin.php?page=headway-options' ); ?>">Options</a></h3>
			<p>This is the place to go to tweak your Headway site, with things like Google Analytics, SEO, favicons and other more advanced settings.</p>
		</div>
		<div class="headway-infobox big">
			<h3><a href="<?php echo admin_url( 'admin.php?page=headway-tools' ); ?>">Tools</a></h3>
			<p>If you log a request on the forums, it is very useful to provide system info and Tools is the place to find it.</p>
			<p>And if you really just want to wipe the slate, and start afresh, there's a big red button for that.</p>
		</div>
	</div>

	<h2>Need help?</h2>
	<p>If you ever run into any problems, you can visit our forums or check out the documentation</p>

	<div class="headway-infobox-row headway-infobox-icon-row">
		<div class="headway-infobox headway-infobox-icon inrow">
			<span class="dashicons dashicons-groups"></span>
			<a class="big" href="http://support.headwaythemes.com/" target="_blank">Support</a>
		</div>

		<div class="headway-infobox headway-infobox-icon">
			<span class="dashicons dashicons-book-alt bigfix"></span>
			<a class="big" href="http://docs.headwaythemes.com/" target="_blank">Documentation</a>
		</div>
	</div>

	<h2>Extending Headway</h2>
	<p>We have a wonderful community of third party developers who are creating beautiful Templates that contain all the design and styling already done for you, and fantastically useful Blocks, which provide even more layout possibilites, saving you heaps of time in setting up your designs, such as galleries, sliders, advanced content display and utility blocks.</p>


	<div class="headway-infobox-row headway-infobox-icon-row">
		<div class="headway-infobox inrow headway-infobox-icon">
			<span class="dashicons dashicons-welcome-widgets-menus"></span>
			<a class="big" href="http://headwaythemes.com/extend/templates" target="_blank">Templates</a>
		</div>

		<div class="headway-infobox headway-infobox-icon">
			<span class="dashicons dashicons-screenoptions"></span>
			<a class="big" href="http://headwaythemes.com/extend/blocks" target="_blank">Blocks</a>
		</div>
	</div>

</div>
