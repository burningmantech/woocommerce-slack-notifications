(function($){
	$(document).ready(function(e){
		var error_array = new Array();
		$('body').on('DOMNodeInserted', '.woocommerce-error', function () {
			$('.woocommerce-error').find('li').each(function(index,element){
				error_array[index] = $(element).text();
			});
			$.ajax({
				type : 		"post",
				url : 		'/wp-admin/admin-ajax.php?action=record_woocommerce_errors',
				dataType: 	'json',            	
				data: JSON.stringify(error_array),
				success: function(response) {
				
				},
			}); 				
		});
	});
}(jQuery));