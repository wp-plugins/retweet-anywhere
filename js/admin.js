jQuery(document).ready(function($) {
	$("#rta-shortener").change(function() {
		if (jQuery(this).val() == "bitly")
			jQuery(".rta-bitly").attr("disabled", false).removeClass("disabled");
		else
			jQuery(".rta-bitly").attr("disabled", true).addClass("disabled");
	});
	
	$("#rta-style").change(function() {
		if (jQuery(this).val() == "html")
			jQuery(".rta-style-html").attr("disabled", false).removeClass("disabled");
		else
			jQuery(".rta-style-html").attr("disabled", true).addClass("disabled");
	});
	
	$("#rta-shortener, #rta-style").change();
});