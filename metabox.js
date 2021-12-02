jQuery( function() {

	var frame;

	jQuery('#kgr-featured-pdf-metabox-show').click(function(event) {
		event.preventDefault();
		if (frame) {
			frame.open();
			return;
		}
		frame = wp.media({
			frame: 'select',
			title: kgr_featured_pdf.frame_title,
			library: {
				post_mime_type: 'application/pdf',
				uploadedTo: wp.media.view.settings.post.id,
			},
			multiple: false,
			state: 'library',
		});
		frame.on('select', function() {
			var pdf = frame.state().get('selection').first().toJSON();
			var srcset = [];
			for (var s in pdf.sizes)
				srcset.push(pdf.sizes[s].url + ' ' + pdf.sizes[s].width + 'w');
			if (srcset.length === 0) {
				jQuery('#kgr-featured-pdf-metabox-id').val('');
				jQuery('#kgr-featured-pdf-metabox-img').html('');
				return;
			}
			srcset = srcset.join(', ');
			jQuery('#kgr-featured-pdf-metabox-id').val(pdf.id);
			jQuery('#kgr-featured-pdf-metabox-img').html('<img style="width: 100%;" src="' + pdf.sizes.full.url + '" srcset="' + srcset + '" />');
		});
		frame.on('escape', function() {
			jQuery('#kgr-featured-pdf-metabox-id').val('');
			jQuery('#kgr-featured-pdf-metabox-img').html('');
		});
		frame.open();
	});
} );
