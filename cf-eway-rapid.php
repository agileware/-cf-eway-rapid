<?php

/**
 * Plugin Name: Caldera Forms eWAY Rapid API Payment Processor
 * Plugin URI: https://bitbucket.org/agileware/cf-eway-rapid
 * Description: eWay Rapid API payment processor for Caldera Forms.
 * Version: 1.0.0
 * Author: Agileware
 * Author URI: https://agileware.com.au
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Bitbucket Plugin URI: https://github.com/CalderaWP/Caldera-Forms/
 */


// Defining Path & Url to use across plugin.
define( 'CF_EWAY_RAPID_PATH',  plugin_dir_path( __FILE__ ) );
define( 'CF_EWAY_RAPID_URL',  plugin_dir_url( __FILE__ ) );
define( 'CF_EWAY_RAPID_VER', '1.0.0' );

// Registering eWAY Rapid as a Caldera processor.
add_filter('caldera_forms_get_form_processors', 'cf_eway_rapid_register_processor');

// filter to prepare redirect URL before form starts processing
add_filter('caldera_forms_submit_return_transient_pre_process', 'cf_eway_rapid_set_redirect_url', 10, 4);

// filter to add eWAY Rapid redirect
add_filter('caldera_forms_submit_return_redirect-eway_rapid', 'cf_eway_rapid_redirect_toeway', 10, 4);

// load eWAY SDK dependencies
include_once CF_EWAY_RAPID_PATH . 'vendor/autoload.php';

// Including eWAY Rapid functions file.
include CF_EWAY_RAPID_PATH . 'includes/functions.php';

?>
