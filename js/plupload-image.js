jQuery( document ).ready( function($)
{
	// Object containing all the plupload uploaders
	var rwmb_image_uploaders = {},
		hundredMB = null,
		max = null,
		throbbers = {};

	// Hide "Uploaded files" title as long as there are no files uploaded
	if ( 1 == $( '.rwmb-uploaded' ).children().length )
		$( '.rwmb-uploaded-title' ).addClass( 'hidden' );

	// Check on mouseenter & -leave if we got files and add the "Uploaded files" title
	$( '.rwmb-drag-drop' ).bind(
		'mouseenter mouseleave',
		function()
		{
			if ( 1 < $( '.rwmb-uploaded' ).children().length )
				$( '.rwmb-uploaded-title' ).removeClass( 'hidden' );
		}
	);
	$( '.rwmb-images' ).on(
		'click', '.rwmb-delete-file',
		function()
		{
			// check if we need to show drop target
			var container = $(this).parent().parent().parent().parent(),
				max_file_count = container.find('.max_file_count').val(),
				uploaded = container.find('ul li').length - 1; // -1 for the one we just deleted
			if (uploaded < max_file_count) {
				container.find('.rwmb-drag-drop').show();
			}

			if ( 1 >= $( '.rwmb-uploaded' ).children().length )
				$( '.rwmb-uploaded-title' ).addClass( 'hidden' );
		}
	);

	//helper functions
	//removes li element if there is an error with the file
	function removeError(file)
	{
		$('li#' + file.id)
			.addClass('rwmb-image-error')
			.delay(1600)
			.fadeOut( 'slow', function()
				{
					$(this).remove();
				}
			);
	}
	//Adds loading li element
	function addLoading (up, file)
	{
		$list =  $( '#' + up.settings.container ).find( 'ul' );
		$list.append("<li id='" + file.id + "'><div class='rwmb-image-uploading-bar'></div><div id='" + file.id + "-throbber' class='rwmb-image-uploading-status'></div></li>");
	}
	//Adds loading throbber while waiting for a response
	function addThrobber(file)
	{
		$('#' + file.id + '-throbber').html("<img class='rwmb-loader' height=64 width=64 src='" + RWMB.url + "img/loader.gif'/>");
	}

	// Using all the image prefixes
	$( 'input:hidden.rwmb-image-prefix' ).each( function()
	{
		var
			prefix = $( this ).val(),
			nonce = $('input#nonce-upload-images_' + prefix).val();
		// Adding container, browser button and drag ang drop area
		rwmb_plupload_init = $.extend(
			{
				container:		prefix + '-container',
				browse_button:	prefix + '-browse-button',
				drop_element:	prefix + '-dragdrop'
			},
			rwmb_plupload_defaults
		);
		// Add field_id to the ajax call
		rwmb_plupload_init['multipart_params'] =
		{
			action : 'plupload_image_upload',
			field_id: prefix,
			_ajax_nonce: nonce,
			post_id: $('input#post_ID').val()
		};
		// Create new uploader
		rwmb_image_uploaders[ prefix ] = new plupload.Uploader( rwmb_plupload_init );
		rwmb_image_uploaders[ prefix ].init();

		rwmb_image_uploaders[ prefix ].bind(
			'FilesAdded',
			function( up, files )
			{
				var max_file_count = $('#' + this.settings.container + ' .max_file_count').val(),
					uploaded = $('#' + this.settings.container + ' .rwmb-uploaded').children().length,
					msg = "You may only upload " + max_file_count + " file";
				if (max_file_count > 1) {
					msg += 's';
				}
				if ((uploaded + files.length) > max_file_count) {
					// remove files from queue
					for (var i = 0; i < files.length; i++) {
						up.removeFile(files[i]);
					}
					alert(msg);
					return false;
				}
				if ((uploaded + files.length) == max_file_count) {
					$('#' + this.settings.container).find('.rwmb-drag-drop').hide();
				}

				hundredMB	= 100 * 1024 * 1024,
				max			= parseInt( up.settings.max_file_size, 10 );
				plupload.each(
					files,
					function( file )
					{
						addLoading(up, file);
						addThrobber(file);
						if ( file.size >= max )
						{
							removeError(file);
						}
					}
				);
				up.refresh();
				up.start();
			}
		);

		rwmb_image_uploaders[ prefix ].bind(
			'Error',
			function( up, e )
			{
				addLoading(up, e.file);
				removeError(e.file);
				up.removeFile(e.file);
			}
		);

		rwmb_image_uploaders[ prefix ].bind(
			'UploadProgress',
			function( up, file )
			{
				//update the loading div
				$('div.rwmb-image-uploading-bar', 'li#' + file.id).css('height', file.percent + "%");
			}
		);

		rwmb_image_uploaders[ prefix ].bind(
			'FileUploaded',
			function( up, file, response )
			{
				res = wpAjax.parseAjaxResponse( $.parseXML( response.response ), 'ajax-response' );
				if ( false === res.errors )
				{
					$('li#'+file.id).replaceWith(res.responses[0].data);
				}
				else
				{
					removeError(file);
				}
			});
	});
});
