/* global wp */
document.addEventListener('click', function (event) {
	const button = event.target.closest('[data-pig-choose-photos]');
	if (!button || typeof wp === 'undefined' || !wp.media) {
		return;
	}

	const field = button.closest('[data-pig-winner-photos]').querySelector('textarea');
	const picker = wp.media({
		title: button.dataset.title,
		button: { text: button.dataset.confirm },
		library: { type: 'image' },
		multiple: true
	});

	picker.on('select', function () {
		const existing = field.value.split(/\r?\n/).map(function (line) {
			return line.trim();
		}).filter(Boolean);
		picker.state().get('selection').each(function (attachment) {
			const url = attachment.get('url');
			if (url && !existing.includes(url)) {
				existing.push(url);
			}
		});
		field.value = existing.join('\n');
		field.dispatchEvent(new Event('change', { bubbles: true }));
	});

	picker.open();
});
