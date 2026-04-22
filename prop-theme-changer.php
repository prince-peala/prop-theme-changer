<?php
/**
 * Plugin Name:  Prop Theme Changer
 * Plugin URI:   https://github.com/prop-theme-changer
 * Description:  Système de thèmes visuels basé sur les couleurs Elementor Global. Héritage, conditions, transitions, shortcode, widget Elementor, REST API.
 * Version:      4.0.0
 * Author:       Prince Peala
 * Text Domain:  prop-theme-changer
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PTC_VERSION',    '4.0.0' );
define( 'PTC_DIR',        plugin_dir_path( __FILE__ ) );
define( 'PTC_URL',        plugin_dir_url( __FILE__ ) );
define( 'PTC_OPTION_KEY', 'ptc_themes' );

require_once PTC_DIR . 'includes/helpers.php';
require_once PTC_DIR . 'includes/admin.php';
require_once PTC_DIR . 'includes/frontend.php';
require_once PTC_DIR . 'includes/rest-api.php';
require_once PTC_DIR . 'includes/shortcode.php';
