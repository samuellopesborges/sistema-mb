<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro\Settings;

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class Util{

    static function render_toggle($toggle_key, $toggle_state, $nonce, $label = false){
		$is_active = $toggle_state ? 'active' : '';
		$state_text = $toggle_state ? 'Click to disable this feature' : 'Click to enable this feature';

		// for dashbord screen
		if($label){
			echo'<div class="siteseo-toggleCnt">
					<div class="siteseo-toggleSw '.esc_attr($is_active).'" id="siteseo-toggleSw-' . esc_attr($toggle_key) . '" data-nonce="' . esc_attr($nonce) . '" data-toggle-key="'.esc_attr($toggle_key).'" data-action="siteseo_pro_save_'.esc_attr($toggle_key).'"></div>
					<input type="hidden" name="siteseo_options['.esc_attr($toggle_key) . ']" id="'.esc_attr($toggle_key).'" value="'.esc_attr($toggle_state).'">
				</div>';
		} else{
			
			echo'<div class="siteseo-toggleCnt">
					<div class="siteseo-toggleSw '.esc_attr($is_active).'" id="siteseo-toggleSw-'.esc_attr($toggle_key).'" data-nonce="' . esc_attr($nonce) . '" data-toggle-key="'.esc_attr($toggle_key).'" data-action="siteseo_pro_save_'.esc_attr($toggle_key).'"></div>
					<span id="siteseo-arrow-icon" class="dashicons dashicons-arrow-left-alt siteseo-arrow-icon"></span>
					<p class="toggle_state_'.esc_attr($toggle_key).'">'.esc_html($state_text).'</p>
					<input type="hidden" name="siteseo_options['.esc_attr($toggle_key).']" id="'.esc_attr($toggle_key).'" value="'.esc_attr($toggle_state).'">
				</div>';
		}

	}
}