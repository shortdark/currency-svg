$(document).ajaxStart(function() {
	$("body").addClass("loading");
});

$(document).ajaxStop(function() {
	$("body").removeClass("loading");
});

$(document).ready(function() {
	// $('#refresh').append($(window).width(),"px");
	// load SVG automatically
	$("#content").load('/currency/svg.php', 'wide=' + $(window).width() + '&tall=' + $(window).height());
	
	/*
	// refresh content on click
		$(".load-content").click(function(e) {
			e.preventDefault();
			// will not follow link
			$("#content").load($(this).attr('href'), 'wide=' + $(window).width());
		});*/
	
});

