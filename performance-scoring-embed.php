<?php
/*
Plugin Name: Performance Scoring Embed
Plugin URI: https://github.com/jdmdigital/performance-scoring-embed/
Description: Easily Embed Performance Scoring custom survey forms into your website via shortcode and Gutenberg block.
Version: 1.0.0
Author: Performance Scoring
Author URI: https://performancescoring.com
License: GPL2
Text Domain: performance-scoring-embed 
Contributors: jdmdigital
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'psce_load_textdomain' ) ) {
    function psce_load_textdomain() {
        load_plugin_textdomain( 'performance-scoring-embed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
    add_action( 'plugins_loaded', 'psce_load_textdomain' );
}


if ( ! function_exists( 'psce_add_settings_link' ) ) {
	function psce_add_settings_link( $links ) {
		$settings_url = admin_url( 'options-general.php?page=performance-scoring-settings' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'performance-scoring-embed' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'psce_add_settings_link' );
}

/**
 * Add settings menu.
 */
if ( ! function_exists( 'psce_add_admin_menu' ) ) {
	function psce_add_admin_menu() {
		add_options_page(
			__('Performance Scoring Custom Form Embed Settings', 'performance-scoring-embed'),
			__('PS Forms Embed', 'performance-scoring-embed'),
			'manage_options',
			'performance-scoring-settings',
			'psce_options_page'
		);
	}
	add_action('admin_menu', 'psce_add_admin_menu');
}

/**
 * Initialize settings.
 */
if ( ! function_exists( 'psce_settings_init' ) ) {
	function psce_settings_init() {
		register_setting('psce_options', 'psce_options', 'psce_options_sanitize');

		add_settings_section(
			'psce_section',
			__('Global Embed Settings', 'performance-scoring-embed'),
			'psce_section_callback',
			'psce_options'
		);

		add_settings_field(
			'psce_url',
			__('Global Embed Settings', 'performance-scoring-embed'),
			'psce_url_render',
			'psce_options',
			'psce_section'
		);

		add_settings_field(
			'psce_orgid',
			__('Organization ID', 'performance-scoring-embed'),
			'psce_orgid_render',
			'psce_options',
			'psce_section'
		);
	}
	add_action('admin_init', 'psce_settings_init');
}

if ( ! function_exists( 'psce_url_render' ) ) {
	function psce_url_render() {
		$options = get_option('psce_options');
		$psce_url = isset($options['psce_url']) ? untrailingslashit($options['psce_url']) : 'https://performancescoring.securedb.io';
		?>
		<input type="url" id="psce_url" name="psce_options[psce_url]" value="<?php echo esc_attr($psce_url); ?>" class="regular-text" placeholder="https://">
		<p class="description">
			<?php esc_html_e('The URL where your Performance Scoring app is hosted.', 'performance-scoring-embed'); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'psce_orgid_render' ) ) {
	function psce_orgid_render() {
		$options = get_option('psce_options');
		$psce_orgid = isset($options['psce_orgid']) ? absint($options['psce_orgid']) : '';
		?>
		<input type="number" id="psce_orgid" name="psce_options[psce_orgid]" value="<?php echo esc_attr($psce_orgid); ?>" class="small-text" placeholder="1">
		<p class="description">
			<?php esc_html_e('Your unique organization identifier provided by the Performance Scoring app.', 'performance-scoring-embed'); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'psce_section_callback' ) ) {
	function psce_section_callback() {
		?>
		<p><?php esc_html_e('These values will be used to generate the correct custom surveys embed code for your forms.', 'performance-scoring-embed'); ?></p>
		<p>
        <?php
        printf(
            wp_kses(
                /* translators: %s: URL to the app documentation */
                __('If you are unsure of these details, please check the <a href="%s" target="_blank" rel="noopener noreferrer">Performance Scoring app documentation</a>.', 'performance-scoring-embed'),
                array(
                    'a' => array(
                        'href'   => array(),
                        'target' => array(),
                        'rel'    => array(),
                    ),
                )
            ),
            esc_url('https://securedb.io/kb/?p=14637&brand=Performance%20Scoring')
        );
        ?>
    	</p>
		<?php
	}
}

if ( ! function_exists( 'psce_options_page' ) ) {
	function psce_options_page() {
		?>
		<div class="wrap">
			<form action='options.php' method='post'>
				<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
				<?php
					settings_fields('psce_options');
					do_settings_sections('psce_options');
					submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
/**
 * Sanitization callback for settings.
 */
if ( ! function_exists( 'psce_options_sanitize' ) ) {
	function psce_options_sanitize($input) {
		$output = array();
		if (isset($input['psce_url'])) {
			// Trim the URL and remove any trailing slash.
			$url = esc_url_raw(trim($input['psce_url']));
			if(function_exists('untrailingslashit')){
				$output['psce_url'] = untrailingslashit($url);
			} else {
				$output['psce_url'] = $url;
			}
		}
		if (isset($input['psce_orgid'])) {
			$output['psce_orgid'] = absint($input['psce_orgid']);
		}
		return $output;
	}
}

/**
 * Shortcode handler to output the embed code.
 * usage: [performance_scoring_embed formid="2"]
 */
if ( ! function_exists( 'psce_shortcode_handler' ) ) {
	function psce_shortcode_handler($atts) {
		$atts = shortcode_atts(array(
			'formid' => '',
		), $atts, 'performance_scoring');

		$options   = get_option('psce_options');
		$psc_url   = isset($options['psce_url']) ? esc_url($options['psce_url']) : 'https://performancescoring.securedb.io';
		$psc_orgid = isset($options['psce_orgid']) ? sanitize_text_field($options['psce_orgid']) : '1';

		if (empty($atts['formid'])) {
			return '<!-- Missing formID parameter for Performance Scoring Embed -->';
		}

		$formid = sanitize_text_field($atts['formid']);

		ob_start();
		?>
		<div id="customform-id<?php echo esc_attr($psc_orgid); ?>-form<?php echo esc_attr($formid); ?>" class="ps-custom-form-embed">
			<p style="margin-bottom: 0; text-align: center;"><?php esc_html_e('loading...', 'performance-scoring-embed'); ?></p>
		</div>
		<script src="<?php echo esc_url($psc_url); ?>/js/embed.min.js?orgID=<?php echo esc_attr($psc_orgid); ?>&formID=<?php echo esc_attr($formid); ?>" defer></script>
		<?php
		return ob_get_clean();
	}
	add_shortcode('performance_scoring_embed', 'psce_shortcode_handler');
}

/**
 * Register a dynamic Gutenberg block.
 */
if ( ! function_exists( 'psce_register_block' ) ) {
	function psce_register_block() {
		// Check if Gutenberg is active.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type('performance-scoring/embed', array(
			'attributes'      => array(
				'formID' => array(
					'type' => 'string',
				),
			),
			'render_callback' => 'psce_render_block',
		));
	}
	add_action('init', 'psce_register_block');
}

if ( ! function_exists( 'psce_render_block' ) ) {
	function psce_render_block($attributes) {
		$formid = isset($attributes['formID']) ? sanitize_text_field($attributes['formID']) : '';
		return psce_shortcode_handler(array('formid' => $formid));
	}
}
