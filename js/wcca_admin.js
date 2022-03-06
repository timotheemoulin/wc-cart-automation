(function ($) {
	let repeater = $('.wcca-input-repeater');
	let count = repeater.find('.repeater-row:visible').length;

	repeater.on('click', '.delete_current_row', function (event) {
		$(this).closest('.repeater-row').remove();

		event.preventDefault();
	});

	repeater.find('.add_row').click(function (event) {
		let clone = repeater.find('.repeater-row:hidden').clone();

		clone.insertBefore($(this));
		clone.find('label').attr('for', clone.find('label').attr('for').replace('-1', count))
		clone.find('input').attr('id', clone.find('input').attr('id').replace('-1', count))
		clone.find('input').attr('name', clone.find('input').attr('name').replace('-1', count))
		clone.show();

		count++;

		event.preventDefault();
	});
})(jQuery);
