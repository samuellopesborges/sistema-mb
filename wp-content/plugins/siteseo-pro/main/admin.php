<?php

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Admin{
	
	static function init(){
		add_action('admin_enqueue_scripts', '\SiteSEOPro\Admin::enqueue_script');
		add_action('admin_menu', '\SiteSEOPro\Admin::add_menu', 100);
	}
	
	static function enqueue_script(){
		
		if (empty($_GET['page']) || strpos($_GET['page'], 'siteseo') === FALSE){
			return;
		}

		wp_enqueue_media();
		
		wp_enqueue_script('siteseo-pro-admin', SITESEO_PRO_URL.'assets/js/admin.js', ['jquery'], SITESEO_PRO_VERSION, true);

		wp_localize_script('siteseo-pro-admin', 'siteseo_pro', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('siteseo_pro_nonce'),
		]);

		wp_enqueue_style('siteseo-pro-admin', SITESEO_PRO_URL . 'assets/css/admin.css');

		
	}
	
	static function add_menu(){
		$capability = 'manage_options';

		add_submenu_page('siteseo', __('PRO', 'siteseo'), __('PRO', 'siteseo'), $capability, 'siteseo-pro-page', '\SiteSEOPro\Settings\Pro::home');

		add_submenu_page('siteseo', __('License', 'siteseo'), __('License', 'siteseo'), $capability, 'siteseo-license', '\SiteSEOPro\Settings\License::template');
	}
	

	static function local_business_block(){

		wp_register_script('local-business-block-script',SITESEO_PRO_URL . 'assets/js/block.js', array('wp-blocks', 'wp-element', 'wp-editor'), filemtime(SITESEO_PRO_DIR . 'assets/js/block.js'));
		
		$data = \SiteSEOPro\Tags::local_business();
		
		// Localize
		wp_localize_script('local-business-block-script', 'siteseoProLocalBusiness', array(
			'previewData' => $data,
		));

		register_block_type('siteseo-pro/local-business', array(
			'editor_script' => 'local-business-block-script',
			'render_callback' => '\SiteSEOPro\Tags::load_data_local_business'
		));
	}

}