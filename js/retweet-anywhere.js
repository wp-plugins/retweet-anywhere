/*
	Retweet Anywhere for WordPress
	Copyright © 2010 Konstantin Kovshenin
	www.kovshenin.com
*/
jQuery(document).ready(function($){
	// Set ajaxurl for wp_localize_script call
	var ajaxurl = RetweetAnywhere.ajaxurl;

	// Retweet button click handler
	$(".retweet-anywhere").click(function() {
		// Format the data for the AJAX call
		var data = {
			action: 'rta_getmessage',
			post_id: $(this).attr("rel"),
			format: $(this).attr("rev")
		};
		
		// Set the Facebox settings according to settings from localize_script
		$.facebox.settings.opacity = parseFloat(RetweetAnywhere.opacity);
		$.facebox.settings.loadingImage = RetweetAnywhere.loadingImage;
		$.facebox.settings.closeImage = RetweetAnywhere.closeImage;'/facebox/closelabel.gif',

		// Initiate the facebox with an AJAX call
		$.facebox(function() {
			$.post(ajaxurl, data, function(response) {
				// Response is in JSON
				response = eval('(' + response + ')');
				
				// Create the Facebox with the content and fire .tweetBox()
				$.facebox('<div class="retweet-anywhere-tweetbox"></div>');
				twttr.anywhere(function (T) {
					T(".retweet-anywhere-tweetbox").tweetBox({
						// Default settings, may be changed, perhaps from the Settings at a later stage
						height: 40,
						width: 540,
						label: "Retweet This Post:",
						defaultContent: response.message, // The received message
						onTweet: function() { $.facebox.close(); } // Close the facebox upon tweet
					});
				});
				
				// Set the focus on the tweet box textarea (to enable the Tweet button)
				setTimeout(function() {
					$(".twitter-anywhere-tweet-box").focus();
					$(".twitter-anywhere-tweet-box").contents().find("#tweet-box").focus();
				}, 1000);
			});
		});
		return false;
	});
	
	// Widget workflow
	twttr.anywhere(function(T) {
		// There may be more than 1 widget, loop through each
		$(".retweet-anywhere-widget-box").each(function() {
			// Read the settings from hidden EM's
			var r_height = parseInt($(this).find("em.height").text());
			var r_width = parseInt($(this).find("em.width").text());
			var r_label = $(this).find("em.title").text();
			var r_post_id = parseInt($(this).find("em.post_id").text());
			var r_format = $(this).find("em.format").text();
			
			// For later use
			var obj = this;
			
			// Format the AJAX call
			var data = {
				action: 'rta_getmessage',
				post_id: r_post_id,
				format: r_format
			};
			
			// Fire AJAX and capture JSON response
			$.post(ajaxurl, data, function(response) {
				response = eval('(' + response + ')');
				twttr.anywhere(function(T) {
					T(obj).tweetBox({
						// Settings from the hidden EM's
						height: r_height,
						width: r_width,
						label: r_label,
						defaultContent: response.message, // Received message
					});
				});
			});
		});
	});
});