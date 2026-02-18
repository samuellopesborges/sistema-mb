<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

// Show menu with error if speedy cache is installed but is older than 1.1.0
// as after that we dont short circuit the free version
if(!defined('SPEEDYCACHE_VERSION') || version_compare(SPEEDYCACHE_VERSION, '1.1.1') < 0){
	add_action('admin_menu', 'speedycachepro_add_menu');
	return; // Return else going forward will break things.
}

add_action('speedycache_license_tmpl', '\SpeedyCache\SettingsPage::license_tab');
add_action('speedycache_db_tmpl', '\SpeedyCache\SettingsPage::db_tab');
add_action('speedycache_pro_logs_tmpl', '\SpeedyCache\SettingsPage::logs');
add_action('speedycache_pro_stats_tmpl', '\SpeedyCache\SettingsPage::stats');
add_action('speedycache_image_optm_tmpl', '\SpeedyCache\SettingsPage::image_optm');
add_action('speedycache_bloat_tmpl', '\SpeedyCache\SettingsPage::bloat_tab');
add_action('speedycache_object_cache_tmpl', '\SpeedyCache\SettingsPage::object_tab');

include_once SPEEDYCACHE_PRO_DIR . '/main/premium.php';

if(defined('SPEEDYCACHE_PRO') && file_exists(SPEEDYCACHE_PRO_DIR . '/main/image.php')){
	\SpeedyCache\Image::init();
	add_action('wp_ajax_speedycache_download_cwebp', '\SpeedyCache\Image::download_cwebp');
}

// ----- AJAX ACTIONS ----- //
add_action('wp_ajax_speedycache_statics_ajax_request', 'speedycache_pro_img_stats');
add_action('wp_ajax_speedycache_optimize_image_ajax_request', 'speedycache_pro_optimize_image');
add_action('wp_ajax_speedycache_update_image_settings', 'speedycache_pro_save_img_settings');
add_action('wp_ajax_speedycache_update_image_list_ajax_request', 'speedycache_pro_list_imgs');
add_action('wp_ajax_speedycache_revert_image_ajax_request', 'speedycache_pro_revert_img');
add_action('wp_ajax_speedycache_img_revert_all', 'speedycache_pro_revert_all_imgs');
add_action('wp_ajax_speedycache_verify_license', 'speedycache_pro_verify_license');

function speedycachepro_add_menu(){
	add_menu_page('SpeedyCache Settings', 'SpeedyCache', 'activate_plugins', 'speedycache', 'speedycachepro_menu_page');
}

function speedycachepro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">Update Speedycache to Latest Version!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">SpeedyCache Pro depends on the free version of SpeedyCache, so you need to update the free version to use SpeedyCache without any issue.</p>
		<a href="'.admin_url('plugin-install.php?s=speedycache&tab=search').'" style="text-decoration: none;font-size:16px;">Install Now</a>
	</div>';
}

function speedycache_pro_img_stats(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	if(!class_exists('\SpeedyCache\Image')){
		wp_send_json_error(__('The file required to Process Image optimization is not present', 'speedycache'));
	}

	$res = \SpeedyCache\Image::statics_data();
	wp_send_json($res);
}

function speedycache_pro_optimize_image(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	if(!class_exists('\SpeedyCache\Image')){
		wp_send_json_error(__('The file required to Process Image optimization is not present', 'speedycache'));
	}
	
	$res = \SpeedyCache\Image::optimize_single();
	$res[1] = isset($res[1]) ? $res[1] : '';
	$res[2] = isset($res[2]) ? $res[2] : '';
	$res[3] = isset($res[3]) ? $res[3] : '';
	
	$response = array(
		'message' => $res[0],
		'success' => $res[1],
		'id' => $res[2],
		'percentage' => $res[3],
	);
	
	wp_send_json($response);
}

function speedycache_pro_save_img_settings(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');
	
	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	global $speedycache;

	$settings = speedycache_optpost('settings');
	
	foreach($settings as $key => $setting){		
		$new_key = str_replace('img_', '', $key);
		
		$settings[$new_key] = $setting;
		unset($settings[$key]);
	}

	$speedycache->image['settings'] = $settings;
	
	if(update_option('speedycache_img', $speedycache->image['settings'])){
		wp_send_json_success();
	}
	
	wp_send_json_error();
}

function speedycache_pro_list_imgs(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');
	
	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	$query_images_args = array();
	$query_images_args['offset'] = intval(speedycache_optget('page')) * intval(speedycache_optget('per_page'));
	$query_images_args['order'] = 'DESC';
	$query_images_args['orderby'] = 'ID';
	$query_images_args['post_type'] = 'attachment';
	$query_images_args['post_mime_type'] = array('image/jpeg', 'image/png', 'image/gif');
	$query_images_args['post_status'] = 'inherit';
	$query_images_args['posts_per_page'] = speedycache_optget('per_page');
	$query_images_args['meta_query'] = array(
								array(
									'key' => 'speedycache_optimisation',
									'compare' => 'EXISTS'
									)
								);

	$query_images_args['s'] = speedycache_optget('search');

	if(!empty($_GET['filter'])){
		if(speedycache_optget('filter') == 'error_code'){
			
			$filter = array(
				'key' => 'speedycache_optimisation',
				'value' => base64_encode('"error_code"'),
				'compare' => 'LIKE'
			);

			$filter_second = array(
				'key' => 'speedycache_optimisation',
				'compare' => 'NOT LIKE'
			);

			array_push($query_images_args['meta_query'], $filter);
			array_push($query_images_args['meta_query'], $filter_second);
		}
	}

	$result = array(
		'content' => \SpeedyCache\Image::list_content($query_images_args),
		'result_count' => \SpeedyCache\Image::count_query($query_images_args)
	);

	wp_send_json($result);
}

function speedycache_pro_revert_img(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must Be admin');
	}

	global $speedycache;

	if(!empty($_GET['id'])){
		$speedycache->image['id'] = (int) speedycache_optget('id');
	}

	wp_send_json(\SpeedyCache\Image::revert());
}

function speedycache_pro_revert_all_imgs(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	\SpeedyCache\Image::revert_all();
}


function speedycache_pro_verify_license(){

	if(!wp_verify_nonce($_GET['security'], 'speedycache_license')){
		wp_send_json_error(__('Security Check Failed', 'speedycache'));
	}
	
	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have required permission.', 'speedycache'));
	}
	
	global $speedycache;

	$license = sanitize_key($_GET['license']);
	
	if(empty($license)){
		wp_send_json_error(__('The license key was not submitted', 'speedycache'));
	}
	
	$resp = wp_remote_get(SPEEDYCACHE_API.'license.php?license='.$license.'&url='.rawurlencode(site_url()), array('timeout' => 30));
	
	if(!is_array($resp)){
		wp_send_json_error(__('The response was malformed<br>'.var_export($resp, true), 'speedycache'));
	}

	$json = json_decode($resp['body'], true);

	// Save the License
	if(empty($json['license'])){
		wp_send_json_error(__('The license key is invalid', 'speedycache'));
	}
	
	$speedycache->license = $json;
	update_option('speedycache_license', $json, false);
	
	wp_send_json_success();
}
