$(document).ajaxStart(function() {
	$("body").addClass("loading");
});

$(document).ajaxStop(function() {
	$("body").removeClass("loading");
});

$(document).ready(function() {
	// load SVG automatically
	$("#content").load('/currency/svg.php', 'wide=' + $(window).width() + '&tall=' + $(window).height());
});

