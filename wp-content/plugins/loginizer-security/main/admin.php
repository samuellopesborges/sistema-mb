<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

if(current_user_can('activate_plugins')){
	add_action('admin_notices', 'loginizer_pro_free_version_nag');
}

function loginizer_pro_free_version_nag(){
	
	if(!defined('LOGINIZER_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('loginizer_version_free_nag');
	$dismissed_pro = (int) get_option('loginizer_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(LOGINIZER_VERSION, LOGINIZER_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="loginizer-pro-version-notice" onclick="loginizer_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Loginizer Security. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'loginizer').'</p>
	</div>';
	}elseif(version_compare(LOGINIZER_VERSION, LOGINIZER_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="loginizer-pro-version-notice" onclick="loginizer_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Loginizer. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'loginizer').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('loginizer-pro-version-notice', '', array('jquery'), LOGINIZER_PRO_VERSION, true );
		wp_enqueue_script('loginizer-pro-version-notice');
		wp_add_inline_script('loginizer-pro-version-notice', '
	function loginizer_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#loginizer-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('loginizer_version_notice').'",
			action: "loginizer_pro_version_notice",
			type: type
		}, function(res){
			if(!res["success"]){
				alert(res["data"]);
			}
		}).fail(function(data){
			alert("There seems to be some issue dismissing this alert");
		});
	}');
	}
}