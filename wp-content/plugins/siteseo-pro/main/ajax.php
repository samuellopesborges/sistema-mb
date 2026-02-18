<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Ajax{

	static function hooks(){
		add_action('wp_ajax_siteseo_pro_get_pagespeed_insights', '\SiteSEOPro\Ajax::get_pagespeed');
		add_action('wp_ajax_siteseo_pro_pagespeed_insights_remove_results', '\SiteSEOPro\Ajax::delete_speed_scores');

		//toogle option pro
		add_action('wp_ajax_siteseo_pro_save_woocommerce', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_edd', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_dublin', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_local', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_structured' , '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_update_htaccess', '\SiteSEOPro\Ajax::update_htaccess');
		add_action('wp_ajax_siteseo_pro_create_robots', '\SiteSEOPro\Ajax::create_robots');
		add_action('wp_ajax_siteseo_pro_update_robots', '\SiteSEOPro\Ajax::update_robots');
	}

	static function save_toggle(){

		check_ajax_referer('siteseo_pro_toggle_nonce', 'nonce');

		$action = $_POST['action'];
		switch($action){
			case 'siteseo_pro_save_woocommerce':
				$toggle_key = 'toggle_state_woocommerce';
				break;
			case 'siteseo_pro_save_edd':
				$toggle_key = 'toggle_state_easy_digital';
				break;
			case 'siteseo_pro_save_dublin':
				$toggle_key = 'toggle_state_dublin_core';
				break;
			case 'siteseo_pro_save_local':
				$toggle_key = 'toggle_state_local_buz';		
				break;
			case 'siteseo_pro_save_structured':
				$toggle_key = 'toggle_state_stru_data';
				break;
			default:
				wp_send_json_error(['message' => 'Invalid action']);
				return;
		}

		$toggle_value = isset($_POST['toggle_value']) ? sanitize_text_field($_POST['toggle_value']) : '0';

		$options = get_option('siteseo_pro_options', []);
		$options[$toggle_key] = $toggle_value;
		$updated = update_option('siteseo_pro_options', $options);

		if($updated){
			wp_send_json_success([
				'message' => ucfirst($toggle_key) . ' toggle state saved successfully',
				'value' => $toggle_value
			]);
		} else{
			wp_send_json_error(['message' => 'Failed to save toggle state']);
		}
	
	}

	static function get_pagespeed(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have enough privilege to use this feature', 'siteseo-pro'));
		}

		global $siteseo;

		$api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
		$api_key = $siteseo->pro['ps_api_key'];
		$site_url = isset($_POST['test_url']) ? sanitize_url($_POST['test_url']) : site_url();
		
		if(empty($api_key)){
			wp_send_json_error(__('You have not saved the API key', 'siteseo-pro'));
		}
		
		if(empty($site_url)){
			wp_send_json_error(__('The URL you have provided is not valid', 'siteseo-pro'));
		}
		
		$device = (!empty($_REQUEST['is_mobile']) && $_REQUEST['is_mobile'] != 'false') ? 'mobile' : 'desktop';
		$request_url = $api_url . '?url=' . urlencode($site_url) . '&strategy='.$device.'&key='.$api_key;

		$response = wp_remote_get($request_url, array('timeout' => 60)); // 60 sec wait time 

		if(is_wp_error($response)){
			$error_message = is_wp_error($response) ? $response->get_error_message() : $response->get_error_message();

			wp_send_json_error($error_message);
		}

		$body = wp_remote_retrieve_body($response);

		if(empty($body)){
			wp_send_json_error(__('Response body is empty', 'siteseo-pro'));
		}

		$result = json_decode($body, true);
		
		$page_speed = get_option('siteseo_pro_page_speed', []);

		// Handling Pagespeed insight result.
		foreach($result['lighthouseResult']['audits'] as $key => $audit){

			if(isset($audit['title']) && isset($audit['description']) && !isset($audit['details']['type'])){
				$page_speed[$device][$key] = [
					'id' => $audit['id'],
					'score' => $audit['score'],
					'title' => $audit['title'],
					'description' => $audit['description']
				];
			}

			if(isset($audit['details']['type']) && $audit['details']['type'] === 'opportunity'){
				$page_speed[$device]['opportunities'][] = [
					'title' => $audit['title'],
					'description' => $audit['description'],
					'score' => isset($audit['score']) ? $audit['score'] : null
				];
			}

			if(!isset($page_speed[$device]['diagnostics'])){
				$page_speed[$device]['diagnostics'] = [];
			}

			if(isset($audit['score']) && isset($audit['details']['type']) && $audit['score'] <= 0.89 && $audit['details']['type'] != 'opportunity'){
				$page_speed[$device]['diagnostics'][] = [
					'title' => $audit['title'],
					'description' => $audit['description'],
					'score' => isset($audit['score']) ? $audit['score'] : null
				];
			}
		}

		$page_speed[$device]['fetchTime'] = $result['lighthouseResult']['fetchTime'];
		$page_speed[$device]['score'] = $result['lighthouseResult']['categories']['performance']['score'];
		
		update_option('siteseo_pro_page_speed', $page_speed);
		
		wp_send_json_success();
	}
	
	static function delete_speed_scores(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have enough privilege to use this feature', 'siteseo-pro'));
		}
		
		delete_option('siteseo_pro_page_speed');
		wp_send_json_success();
	}

	static function update_htaccess(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to edit this file.', 'siteseo'));
		}

		$htaccess_enable = isset($_POST['htaccess_enable']) ? intval(sanitize_text_field(wp_unslash($_POST['htaccess_enable']))) : 0;
		$htaccess_rules = isset($_POST['htaccess_code']) ? sanitize_textarea_field(wp_unslash($_POST['htaccess_code'])) : '';

		if(empty($htaccess_enable)){
			wp_send_json_error(__('Please accept the warning first before proceeding with saving the htaccess', 'siteseo'));
		}

		$htaccess_file = ABSPATH . '.htaccess';
		$backup_file = ABSPATH . '.htaccess_backup.siteseo';

		if(!is_writable($htaccess_file)){
			wp_send_json_error(__('.htaccess file is not writable so the ', 'siteseo'));
		}

		// Backup .htaccess file
		if(!copy($htaccess_file, $backup_file)){
			wp_send_json_error(__('Failed to create backup of .htaccess file.', 'siteseo'));
		}

		// Update the .htaccess file
		if(file_put_contents($htaccess_file, $htaccess_rules) === false){
			wp_send_json_error(__('Failed to update .htaccess file.', 'siteseo'));
		}

		$response = wp_remote_get(site_url());
		$response_code = wp_remote_retrieve_response_code($response);
		
		// Restore the backup if something goes wrong.
		if($response_code > 299){
			copy($backup_file, $htaccess_file);
			wp_send_json_error(__('There was a syntax error in the htaccess rules you provided as the response to your website with the new htaccess gave response code of', 'siteseo') . ' ' . $response_code);
		}

		wp_send_json_success(__('Successfully updated .htaccess file', 'siteseo'));
	}

	static function create_robots(){
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to create robots.txt file.', 'siteseo'));
		}
		
		ob_start();
		do_robots();
		$robots_txt = ob_get_clean();
		
		$is_public = absint(get_option('blog_public'));
		$robots_txt = apply_filters('robots_txt', $robots_txt, $is_public);
		
		if(file_put_contents(ABSPATH . 'robots.txt', $robots_txt)){
			wp_send_json_success(__('Successfully create the robots.txt file', 'siteseo'));
		}

		wp_send_json_error();
		
	}

	static function update_robots(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to edit this file.', 'siteseo'));
		}
		
		$robots_txt = '';
		if(!empty($_POST['robots'])){
			$robots_txt = sanitize_textarea_field(wp_unslash($_POST['robots']));
		}

		if(empty($robots_txt)){
			wp_send_json_error(__('You have supplied empty robots rules', 'siteseo'));
		}
		
		if(!is_writable(ABSPATH . 'robots.txt')){
			wp_send_json_error(__('robots.txt file is not writable', 'siteseo'));
		}
		
		if(file_put_contents(ABSPATH . 'robots.txt', $robots_txt)){
			wp_send_json_success(__('Successfully update the robots.txt file', 'siteseo'));
		}

		wp_send_json_error(__('Unable to update the robots.txt file', 'siteseo'));
		
	}

}


