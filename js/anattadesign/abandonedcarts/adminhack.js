Zepto(function($){
	// load widget initially
	$.get(anattadesign_abandonedcarts.url, function(data){
		if ( $.trim(data) != '' )
			$('.dashboard-container tr').first().append('<td>'+data+'</td>');
	});

	// bind click for browsing previous/next months
	$(document).on('click','#cal-widget a', function(e){

		var month = $(this).parent().attr('data-month');
		var year = $(this).parent().attr('data-year');

		if ($(this).hasClass('previous-month')) {
			month--;
			if (month == 0) {
				month = 12;
				year--;
			}
		} else {
			month++;
			if (month == 13) {
				month = 1;
				year++;
			}
		}

		// fill up widget with the requested month-year stats
		$.get(anattadesign_abandonedcarts.url+'?month='+month+'&year='+year, function(data){
			if ( $.trim(data) != '' ) {
				$('#ac-wrapper').parent().remove();
				$('.dashboard-container tr').first().append('<td>'+data+'</td>');
			}
		});

		return false;

	});

});