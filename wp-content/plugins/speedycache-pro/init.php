<?php

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

if(!class_exists('SpeedyCache')){
#[\AllowDynamicProperties]
class SpeedyCache{
	public $options = array();
	public $brand_name = 'SpeedyCache';
	public $logs;
	public $settings;
	public $license;
	public $image;
	public $mobile_cache;
	public $columnjs;
	public $js;
	public $css_util;
	public $render_blocking;
	public $enhanced;
	public $object;
	public $bloat;
}
}

// Prevent update of speedycache free
// This also work for auto update
add_filter('site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'speedycache_pro_update_free_after_pro', 10, 2);

add_action('plugins_loaded', 'speedycache_pro_load_plugin');
register_activation_hook( __FILE__, 'speedycache_pro_activate');

function speedycache_pro_load_plugin(){
	global $speedycache;
	
	if(empty($speedycache)){
		$speedycache = new \SpeedyCache();
	}
	
	speedycache_pro_load_license();

	// Actions to handle WP Cron schedules
	add_action('speedycache_auto_optm', '\SpeedyCache\Image::auto_optimize', 10, 1);
	add_action('speedycache_img_delete', '\SpeedyCache\Image::scheduled_delete', 10, 1);
	add_action('speedycache_generate_ccss', '\SpeedyCache\CriticalCss::generate', 10, 1);
	add_action('speedycache_unused_css', '\SpeedyCache\UnusedCss::generate', 10, 1);
	
	speedycache_pro_update_check();
	
	// Check for updates
	include_once(SPEEDYCACHE_PRO_DIR . '/main/plugin-update-checker.php');
	$speedycache_updater = SpeedyCache_PucFactory::buildUpdateChecker(speedycache_pro_api_url().'/updates.php?version='.SPEEDYCACHE_PRO_VERSION, SPEEDYCACHE_PRO_FILE);

	// Add the license key to query arguments
	$speedycache_updater->addQueryArgFilter('speedycache_pro_updater_filter_args');
		
	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-speedycache-pro', 'speedycache_pro_updater_check_link', 10, 1);	
	
	if(!is_admin() && !current_user_can('activate_plugins')){
		return;
	}

	include_once SPEEDYCACHE_PRO_DIR . '/main/admin.php';
	add_action('admin_notices', 'speedycachepro_free_version_nag');
}
	
// Nag when plugins dont have same version.
function speedycachepro_free_version_nag(){
	
	if(!defined('SPEEDYCACHE_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('speedycache_version_free_nag');
	$dismissed_pro = (int) get_option('speedycache_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(SPEEDYCACHE_VERSION, SPEEDYCACHE_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="speedycache-pro-version-notice" onclick="speedycache_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of SpeedyCache Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'speedycache').'</p>
	</div>';
	}elseif(version_compare(SPEEDYCACHE_VERSION, SPEEDYCACHE_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="speedycache-pro-version-notice" onclick="speedycache_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of SpeedyCache. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'speedycache').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('speedycache-pro-version-notice', '', array('jquery'), SPEEDYCACHE_PRO_VERSION, true );
		wp_enqueue_script('speedycache-pro-version-notice');
		wp_add_inline_script('speedycache-pro-version-notice', '
	function speedycache_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#speedycache-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('speedycache_version_notice').'",
			action: "speedycache_pro_version_notice",
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

// Version nag ajax
function speedycache_pro_version_notice(){
	check_admin_referer('speedycache_version_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'speedycache'));
	}
	
	$type = '';
	if(!empty($_REQUEST['type'])){
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
	}

	if(empty($type)){
		wp_send_json_error(__('Unknown version difference type', 'speedycache'));
	}
	
	update_option('speedycache_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
	wp_send_json_success();
}
add_action('wp_ajax_speedycache_pro_version_notice', 'speedycache_pro_version_notice');

//register_deactivation_hook( __FILE__, '\SpeedyCache\Install::deactivate');

// Load WP CLI command(s) on demand.
if(defined('WP_CLI') && !empty(WP_CLI) && defined('SPEEDYCACHE_PRO')){
	include_once SPEEDYCACHE_PRO_DIR.'/main/cli.php';
}