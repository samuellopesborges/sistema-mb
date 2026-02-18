jQuery(document).ready(function($){
	$('#siteseopro-pagespeed-results .siteseo-metabox-tab-label').click(function(){
		$('.siteseo-metabox-tab-label').removeClass('siteseo-metabox-tab-label-active');
		$('.siteseo-metabox-tab').hide();

		$(this).addClass('siteseo-metabox-tab-label-active');

		var activeTab = $(this).data('tab');
		$('.' + activeTab).show();
	});
	
	$('input[name="ps_device_type"]').on('change', function(){
		jEle = jQuery(this),
		val = jEle.val();
		
		if(val == 'mobile'){
			jQuery('#siteseo-ps-mobile').css('display', 'flex');
			jQuery('#siteseo-ps-mobile').find('.siteseo-metabox-tab-label:first-child').trigger('click');
			jQuery('#siteseo-ps-desktop').hide();
		} else {
			jQuery('#siteseo-ps-mobile').hide();
			jQuery('#siteseo-ps-desktop').css('display', 'flex');
			jQuery('#siteseo-ps-desktop').find('.siteseo-metabox-tab-label:first-child').trigger('click');
		}
		
	});

    $('#siteseopro-pagespeed-btn').on('click', function(){
		$('#siteseopro-pagespeed-results').empty();
    let spinner = $(this).next(),
		input = $(this).closest('div').find('input');

    spinner.addClass('is-active'),

		siteseo_pagespeed_request(input.val(), true);
		siteseo_pagespeed_request(input.val(), false);
    });

	$('#siteseopro-clear-Page-speed-insights').on('click', function(){
		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_pagespeed_insights_remove_results',
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				$('#siteseopro-pagespeed-results').empty();
			}
		});

	});

	$('.siteseo-audit-title').next('.description').hide();

    $('.siteseo-audit-title').on('click', function(e){
        var description = $(this).next('.description');
        var icon = $(this).find(".toggle-icon");

        if(description.is(':visible')){
			description.hide();
			icon.addClass('class', 'toggle-icon dashicons dashicons-arrow-up-alt2');
        } else {
			description.show();
			icon.addClass('class', 'toggle-icon dashicons dashicons-arrow-down-alt2');
        }
    });
	
	// create robots txt
  $('#siteseo-create-robots').on('click', function(){
    event.preventDefault();
    let spinner = $(event.target).next('.spinner');
		
	  if(spinner.length){
		  spinner.addClass('is-active');
		}

		$.ajax({
      method : 'POST',
      url : siteseo_pro.ajax_url,
      data : {
      	action : 'siteseo_pro_create_robots',
      	_ajax_nonce : siteseo_pro.nonce
			},
			success: function(res){

				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(res.success){
					alert(res.data);
					window.location.reload();
					return;
				}

				alert('Unable to create the robots.txt file');
			}
		});
	});
	
	//htaccess
	$('#siteseo_htaccess_btn').on('click', function(){
        event.preventDefault();
		
		let spinner = $(event.target).next('.spinner');

		if(spinner.length){
			spinner.addClass('is-active');
		}

        let htaccess_code = $('#siteseo_htaccess_file').val(),
        htaccess_enable = $('#siteseo_htaccess_enable').is(':checked') ? 1 : 0;

        $.ajax({
        
            url : siteseo_pro.ajax_url,
			method: 'POST',
            data: {
                action: 'siteseo_pro_update_htaccess',
                htaccess_code: htaccess_code,
                htaccess_enable: htaccess_enable,
                _ajax_nonce : siteseo_pro.nonce
            },
            success: function(res){
				if(spinner.length){
					spinner.removeClass('is-active');
				}
				
				if(res.success){
					alert(res.data);
					return;
				}
				
				if(res.data){
					alert(res.data)
					return;
				}

				alert('Something went wrong, updating the file');
            }
        });
    });

	// update robots file
	$('#siteseo-update-robots').on('click', function(){
		event.preventDefault();
	
		let spinner = $(event.target).next('.spinner');

		if(spinner.length){
			spinner.addClass('is-active');
		}

		$.ajax({
			method : 'POST',
			url : siteseo_pro.ajax_url,
			data : {
				action : 'siteseo_pro_update_robots',
				robots : $('#siteseo_robots_file_content').val(),
				_ajax_nonce : siteseo_pro.nonce
			},
			success: function(res){
				
				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(res.success){
					alert(res.data);
					window.location.reload();
					return;
				}

				if(res.data){
					alert(res.data);
					return;
				}
				
				alert('Unable to create the robots.txt file');
			}
		});
	});

	// handel ajax toggle
  $('.siteseo-toggleSw').on('click', function(){
    const $toggle = $(this);
    const toggleKey = $toggle.data('toggle-key');
    const action = $toggle.data('action');

    saveToggle($toggle, toggleKey, action);
	});

  function saveToggle($toggle, toggleKey, action){
    const $container = $toggle.closest('.siteseo-toggleCnt');
    const $stateText = $container.find(`.toggle_state_${toggleKey}`);
    const $input = $(`#${toggleKey}`);

    $container.addClass('loading');
    $toggle.toggleClass('active');

    const newValue = $toggle.hasClass('active') ? '1' : '0';
    $input.val(newValue);
    $stateText.text($toggle.hasClass('active') ? 'Click to disable this feature' : 'Click to enable this feature');

    $.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
    	action: action,
    	toggle_value: newValue,
    	nonce: $toggle.data('nonce')
    },
    success: function(response){
    	if(response.success){
    		// Show the custom toast message
    		ToastMsg('Your settings have been saved.');
    	} else{
    		console.error('Failed to save toggle state');
    		toggleError($toggle, $input, $stateText);
    		ToastMsg(response.data.message || 'Failed to save toggle state', 'error');
    	}
    },
    error: function() {
    	console.error('Ajax request failed');
    	toggleError($toggle, $input, $stateText);
    	ToastMsg('Unable to save settings', 'error');
    },
    complete: function() {
    $container.removeClass('loading');
			}
		});
	}
	
	//toast
	function ToastMsg(message, type = 'success') {

		const toast = $('<div>')
			.addClass('siteseo-toast')
			.addClass(type) 
			.html(`<span class="dashicons dashicons-yes"></span> ${message}`);

		$('body').append(toast); 

		// 3 seconds
		toast.fadeIn(300).delay(3000).fadeOut(300, function () {
			toast.remove();
		});
	}

	// error hadeler
	function toggleError($toggle, $input, $stateText) {
		$toggle.toggleClass('active');
		$input.val($toggle.hasClass('active') ? '1' : '0');
		$stateText.text($toggle.hasClass('active') ? 'Disable' : 'Enable');
	}

	// media uploader for image logo 
	$('#siteseopro_structured_data_upload_img').click(function(e) {
		var mediaUploader;
		e.preventDefault();
		
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}

		
		mediaUploader = wp.media.frames.file_frame = wp.media({
			title: 'Media',
			button: {
				text: 'Select'
			},
			multiple: false
		});

		
		mediaUploader.on('select', function() {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			$('#structured_data_image_url').val(attachment.url);
		});
		
		mediaUploader.open();

	});
	
});

async function siteseo_pagespeed_request(url, is_mobile = false){
	jQuery.ajax({
		url: siteseo_pro.ajax_url,
		type: 'POST',
		data: {
			action: 'siteseo_pro_get_pagespeed_insights',
			is_mobile : is_mobile,
			test_url : url,
			nonce: siteseo_pro.nonce
		},
		success: function(response){
			if(!response.success){
				alert(response.data ?? 'Something went wrong');
				return;
			}

			if(siteseo_pro.pagespeed_response){
				//spinner.removeClass('is-active');
				location.reload(true);
				return;
			}

			siteseo_pro['pagespeed_response'] = true;
		}
	});	

	
}