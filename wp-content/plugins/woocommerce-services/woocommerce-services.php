<?php
/**
 * Plugin Name: WooCommerce Services
 * Plugin URI: https://woocommerce.com/
 * Description: Hosted services for WooCommerce: automated tax calculation, live shipping rates, shipping label printing, and smoother payment setup.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-services
 * Domain Path: /i18n/languages/
 * Version: 1.8.2
 *
 * Copyright (c) 2017 Automattic
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * WooCommerce Services incorporates code from WooCommerce Sales Tax Plugin by TaxJar, Copyright 2014-2017 TaxJar.
 * WooCommerce Sales Tax Plugin by TaxJar is distributed under the terms of the GNU GPL, Version 2 (or later).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( plugin_basename( 'classes/class-wc-connect-options.php' ) );
require_once( plugin_basename( 'classes/class-wc-connect-jetpack.php' ) );

if ( ! class_exists( 'WC_Connect_Loader' ) ) {

	define( 'WOOCOMMERCE_CONNECT_MINIMUM_WOOCOMMERCE_VERSION', '2.6' );
	define( 'WOOCOMMERCE_CONNECT_MINIMUM_JETPACK_VERSION', '3.9' );
	define( 'WOOCOMMERCE_CONNECT_SCHEMA_AGE_WARNING_THRESHOLD', DAY_IN_SECONDS );
	define( 'WOOCOMMERCE_CONNECT_SCHEMA_AGE_ERROR_THRESHOLD', 3 * DAY_IN_SECONDS );
	define( 'WOOCOMMERCE_CONNECT_MAX_JSON_DECODE_DEPTH', 32 );

	class WC_Connect_Loader {

		/**
		 * @var WC_Connect_Logger
		 */
		protected $logger;

		/**
		 * @var WC_Connect_API_Client
		 */
		protected $api_client;

		/**
		 * @var WC_Connect_Service_Schemas_Store
		 */
		protected $service_schemas_store;

		/**
		 * @var WC_Connect_Service_Settings_Store
		 */
		protected $service_settings_store;

		/**
		 * @var WC_Connect_Payment_Methods_Store
		 */
		protected $payment_methods_store;

		/**
		 * @var WC_REST_Connect_Account_Settings_Controller
		 */
		protected $rest_account_settings_controller;

		/**
		 * @var WC_REST_Connect_Packages_Controller
		 */
		protected $rest_packages_controller;

		/**
		 * @var WC_REST_Connect_Services_Controller
		 */
		protected $rest_services_controller;

		/**
		 * @var WC_REST_Connect_Services_Dismiss_Service_Notice_Controller
		 */
		protected $rest_dismiss_service_notice_controller;


		/**
		 * @var WC_REST_Connect_Self_Help_Controller
		 */
		protected $rest_self_help_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Label_Controller
		 */
		protected $rest_shipping_label_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Label_Status_Controller
		 */
		protected $rest_shipping_label_status_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Label_Refund_Controller
		 */
		protected $rest_shipping_label_refund_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Label_Preview_Controller
		 */
		protected $rest_shipping_label_preview_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Label_Print_Controller
		 */
		protected $rest_shipping_label_print_controller;

		/**
		 * @var WC_REST_Connect_Shipping_Rates_Controller
		 */
		protected $rest_shipping_rates_controller;

		/**
		 * @var WC_REST_Connect_Address_Normalization_Controller
		 */
		protected $rest_address_normalization_controller;

		/**
		 * @var WC_Connect_Service_Schemas_Validator
		 */
		protected $service_schemas_validator;

		/**
		 * @var WC_Connect_Settings_Pages
		 */
		protected $settings_pages;

		/**
		 * @var WC_Connect_Help_View
		 */
		protected $help_view;

		/**
		 * @var WC_Connect_Shipping_Label
		 */
		protected $shipping_label;

		/**
		 * @var WC_Connect_Nux
		 */
		protected $nux;

		/**
		 * @var WC_Connect_TaxJar_Integration
		 */
		protected $taxjar;

		/**
		 * @var WC_Connect_Stripe
		 */
		protected $stripe;

		/**
		 * @var WC_REST_Connect_Tos_Controller
		 */
		protected $rest_tos_controller;

		protected $services = array();

		protected $service_object_cache = array();

		protected $wc_connect_base_url;

		static function plugin_deactivation() {
			wp_clear_scheduled_hook( 'wc_connect_fetch_service_schemas' );
		}

		static function plugin_uninstall() {
			WC_Connect_Options::delete_all_options();
		}

		/**
		 * Get WCS plugin version
		 *
		 * @return string
		 */
		static function get_wcs_version() {
			$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
			return $plugin_data[ 'Version' ];
		}

		public function __construct() {
			$this->wc_connect_base_url = trailingslashit( defined( 'WOOCOMMERCE_CONNECT_DEV_SERVER_URL' ) ? WOOCOMMERCE_CONNECT_DEV_SERVER_URL : plugins_url( 'dist/', __FILE__ ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'before_woocommerce_init', array( $this, 'pre_wc_init' ) );
		}

		public function get_logger() {
			return $this->logger;
		}

		public function set_logger( WC_Connect_Logger $logger ) {
			$this->logger = $logger;
		}

		public function get_api_client() {
			return $this->api_client;
		}

		public function set_api_client( WC_Connect_API_Client $api_client ) {
			$this->api_client = $api_client;
		}

		public function get_service_schemas_store() {
			return $this->service_schemas_store;
		}

		public function set_service_schemas_store( WC_Connect_Service_Schemas_Store $schemas_store ) {
			$this->service_schemas_store = $schemas_store;
		}

		public function get_service_settings_store() {
			return $this->service_settings_store;
		}

		public function set_service_settings_store( WC_Connect_Service_Settings_Store $settings_store ) {
			$this->service_settings_store = $settings_store;
		}

		public function get_payment_methods_store() {
			return $this->payment_methods_store;
		}

		public function set_payment_methods_store( WC_Connect_Payment_Methods_Store $payment_methods_store ) {
			$this->payment_methods_store = $payment_methods_store;
		}

		public function get_tracks() {
			return $this->tracks;
		}

		public function set_tracks( WC_Connect_Tracks $tracks ) {
			$this->tracks = $tracks;
		}

		public function get_rest_account_settings_controller() {
			return $this->rest_account_settings_controller;
		}

		public function set_rest_tos_controller( WC_REST_Connect_Tos_Controller $rest_tos_controller ) {
			$this->rest_tos_controller = $rest_tos_controller;
		}

		public function set_rest_packages_controller( WC_REST_Connect_Packages_Controller $rest_packages_controller ) {
			$this->rest_packages_controller = $rest_packages_controller;
		}

		public function set_rest_account_settings_controller( WC_REST_Connect_Account_Settings_Controller $rest_account_settings_controller ) {
			$this->rest_account_settings_controller = $rest_account_settings_controller;
		}

		public function get_rest_services_controller() {
			return $this->rest_services_controller;
		}

		public function set_rest_services_controller( WC_REST_Connect_Services_Controller $rest_services_controller ) {
			$this->rest_services_controller = $rest_services_controller;
		}

		public function set_rest_dismiss_service_notice_controller( WC_REST_Connect_Services_Dismiss_Service_Notice_Controller $rest_dismiss_service_notice_controller ) {
			$this->rest_dismiss_service_notice_controller = $rest_dismiss_service_notice_controller;
		}

		public function get_rest_self_help_controller() {
			return $this->rest_self_help_controller;
		}

		public function set_rest_self_help_controller( WC_REST_Connect_Self_Help_Controller $rest_self_help_controller ) {
			$this->rest_self_help_controller = $rest_self_help_controller;
		}

		public function get_rest_shipping_label_controller() {
			return $this->rest_shipping_label_controller;
		}

		public function set_rest_shipping_label_controller( WC_REST_Connect_Shipping_Label_Controller $rest_shipping_label_controller ) {
			$this->rest_shipping_label_controller = $rest_shipping_label_controller;
		}

		public function get_rest_shipping_label_status_controller() {
			return $this->rest_shipping_label_status_controller;
		}

		public function set_rest_shipping_label_status_controller( WC_REST_Connect_Shipping_Label_Status_Controller $rest_shipping_label_status_controller ) {
			$this->rest_shipping_label_status_controller = $rest_shipping_label_status_controller;
		}

		public function get_rest_shipping_label_refund_controller() {
			return $this->rest_shipping_label_refund_controller;
		}

		public function set_rest_shipping_label_refund_controller( WC_REST_Connect_Shipping_Label_Refund_Controller $rest_shipping_label_refund_controller ) {
			$this->rest_shipping_label_refund_controller = $rest_shipping_label_refund_controller;
		}

		public function get_rest_shipping_label_preview_controller() {
			return $this->rest_shipping_label_preview_controller;
		}

		public function set_rest_shipping_label_preview_controller( WC_REST_Connect_Shipping_Label_Preview_Controller $rest_shipping_label_preview_controller ) {
			$this->rest_shipping_label_preview_controller = $rest_shipping_label_preview_controller;
		}

		public function get_rest_shipping_label_print_controller() {
			return $this->rest_shipping_label_print_controller;
		}

		public function set_rest_shipping_label_print_controller( WC_REST_Connect_Shipping_Label_Print_Controller $rest_shipping_label_print_controller ) {
			$this->rest_shipping_label_print_controller = $rest_shipping_label_print_controller;
		}

		public function set_rest_shipping_rates_controller( WC_REST_Connect_Shipping_Rates_Controller $rest_shipping_rates_controller ) {
			$this->rest_shipping_rates_controller = $rest_shipping_rates_controller;
		}

		public function set_rest_address_normalization_controller( WC_REST_Connect_Address_Normalization_Controller $rest_address_normalization_controller ) {
			$this->rest_address_normalization_controller = $rest_address_normalization_controller;
		}

		public function get_service_schemas_validator() {
			return $this->service_schemas_validator;
		}

		public function set_service_schemas_validator( WC_Connect_Service_Schemas_Validator $validator ) {
			$this->service_schemas_validator = $validator;
		}

		public function get_settings_pages() {
			return $this->settings_pages;
		}

		public function set_settings_pages( WC_Connect_Settings_Pages $settings_pages ) {
			$this->settings_pages = $settings_pages;
		}

		public function get_help_view() {
			return $this->help_view;
		}

		public function set_help_view( WC_Connect_Help_View $help_view ) {
			$this->help_view = $help_view;
		}

		public function set_shipping_label( WC_Connect_Shipping_Label $shipping_label ) {
			$this->shipping_label = $shipping_label;
		}

		public function set_nux( WC_Connect_Nux $nux ) {
			$this->nux = $nux;
		}

		public function set_taxjar( WC_Connect_TaxJar_Integration $taxjar ) {
			$this->taxjar = $taxjar;
		}

		public function set_stripe( WC_Connect_Stripe $stripe ) {
			$this->stripe = $stripe;
		}

		/**
		 * Load our textdomain
		 *
		 * @codeCoverageIgnore
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'woocommerce-services', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
		}

		/**
		 * Perform plugin bootstrapping that needs to happen before WC init.
		 *
		 * This allows the modification of extensions, integrations, etc.
		 */
		public function pre_wc_init() {
			$this->load_dependencies();

			$tos_accepted = WC_Connect_Options::get_option( 'tos_accepted' );

			// Prevent presenting users with TOS they've already
			// accepted in the core WC Setup Wizard or on WP.com
			if ( ! $tos_accepted &&
				( get_option( 'woocommerce_setup_jetpack_opted_in' ) || WC_Connect_Jetpack::is_atomic_site() )
			) {
				WC_Connect_Options::update_option( 'tos_accepted', true );
				delete_option( 'woocommerce_setup_jetpack_opted_in' );

				$tos_accepted = true;
			}

			add_action( 'admin_init', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $this->nux, 'set_up_nux_notices' ) );

			// Plugin should be enabled if dev mode or connected + TOS
			$jetpack_status = $this->nux->get_jetpack_install_status();
			$is_jetpack_connected = WC_Connect_Nux::JETPACK_CONNECTED === $jetpack_status;
			$is_jetpack_dev_mode = WC_Connect_Nux::JETPACK_DEV === $jetpack_status;

			if (  ! $is_jetpack_connected && ! $is_jetpack_dev_mode ) {
				return;
			}

			add_action( 'rest_api_init', array( $this, 'tos_rest_init' ) );

			if ( ! $tos_accepted ) {
				return;
			}

			add_action( 'woocommerce_init', array( $this, 'after_wc_init' ) );
		}

		public function get_service_schema_defaults( $schema ) {
			$defaults = array();

			if ( ! property_exists( $schema, 'properties' ) ) {
				return $defaults;
			}

			foreach ( get_object_vars( $schema->properties ) as $prop_id => $prop_schema ) {
				if ( property_exists( $prop_schema, 'default' ) ) {
					$defaults[ $prop_id ] = $prop_schema->default;
				}

				if (
					property_exists( $prop_schema, 'type' ) &&
					'object' === $prop_schema->type
				) {
					$defaults[ $prop_id ] = $this->get_service_schema_defaults( $prop_schema );
				}
			}

			return $defaults;
		}

		protected function add_method_to_shipping_zone( $zone_id, $method_id ) {
			$method = $this->get_service_schemas_store()->get_service_schema_by_id( $method_id );
			if ( empty( $method ) ) {
				return;
			}

			$zone = WC_Shipping_Zones::get_zone( $zone_id );
			$instance_id = $zone->add_shipping_method( $method->method_id );
			$zone->save();

			$instance = WC_Shipping_Zones::get_shipping_method( $instance_id );
			if ( empty( $instance ) ) {
				return;
			}

			$schema   = $instance->get_service_schema();
			$defaults = (object) $this->get_service_schema_defaults( $schema->service_settings );
			WC_Connect_Options::update_shipping_method_option( 'form_settings', $defaults, $method->method_id, $instance_id );
		}

		public function init_core_wizard_shipping_config() {
			$store_currency = get_woocommerce_currency();

			if ( 'USD' === $store_currency ) {
				$currency_method = 'usps';
			} elseif ( 'CAD' === $store_currency ) {
				$currency_method = 'canada_post';
			} else {
				return; // Only set up live rates for USD and CAD
			}

			if ( get_option( 'woocommerce_setup_intl_live_rates_zone' ) ) {
				$this->add_method_to_shipping_zone( 0, $currency_method );
				delete_option( 'woocommerce_setup_intl_live_rates_zone' );
			}

			if ( get_option( 'woocommerce_setup_domestic_live_rates_zone' ) ) {
				$store_country = WC()->countries->get_base_country();

				// Find the "domestic" zone (only location must be the base country)
				foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
					if (
						1 === count( $zone['zone_locations'] ) &&
						'country' === $zone['zone_locations'][0]->type &&
						$store_country === $zone['zone_locations'][0]->code
					) {
						$this->add_method_to_shipping_zone( $zone['id'], $currency_method );
						break;
					}
				}
				delete_option( 'woocommerce_setup_domestic_live_rates_zone' );
			}
		}

		public function init_core_wizard_payments_config() {
			$stripe_settings = get_option( 'woocommerce_stripe_settings', false );
			$stripe_enabled  = is_array( $stripe_settings )
				&& ( isset( $stripe_settings['create_account'] ) && 'yes' === $stripe_settings['create_account'] )
				&& ( isset( $stripe_settings['enabled'] ) && 'yes' === $stripe_settings['enabled'] );

			if ( $stripe_enabled && is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
				unset( $stripe_settings['create_account'] );
				update_option( 'woocommerce_stripe_settings', $stripe_settings );

				$email = isset( $stripe_settings['email'] ) ? $stripe_settings['email'] : wp_get_current_user()->user_email;
				$country = WC()->countries->get_base_country();
				$response = $this->stripe->create_account( $email, $country );

				if ( is_wp_error( $response ) ) {
					// TODO handle case of existing account
					$this->logger->debug( $response, __CLASS__ );
				}
			}
		}

		/**
		 * Bootstrap our plugin and hook into WP/WC core.
		 *
		 * @codeCoverageIgnore
		 */
		public function after_wc_init() {
			$this->schedule_service_schemas_fetch();
			$this->service_settings_store->migrate_legacy_services();
			$this->attach_hooks();
		}

		/**
		 * Load all plugin dependencies.
		 */
		public function load_dependencies() {
			require_once( plugin_basename( 'classes/class-wc-connect-logger.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-api-client.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-service-schemas-validator.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-taxjar-integration.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-error-notice.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-compatibility.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-shipping-method.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-service-schemas-store.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-service-settings-store.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-payment-methods-store.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-tracks.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-help-view.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-shipping-label.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-nux.php' ) );
			require_once( plugin_basename( 'classes/class-wc-connect-stripe.php' ) );

			$logger                = new WC_Connect_Logger( new WC_Logger() );
			$validator             = new WC_Connect_Service_Schemas_Validator();
			$api_client            = new WC_Connect_API_Client( $validator, $this );
			$schemas_store         = new WC_Connect_Service_Schemas_Store( $api_client, $logger );
			$settings_store        = new WC_Connect_Service_Settings_Store( $schemas_store, $api_client, $logger );
			$payment_methods_store = new WC_Connect_Payment_Methods_Store( $settings_store, $api_client, $logger );
			$tracks                = new WC_Connect_Tracks( $logger, __FILE__ );
			$shipping_label        = new WC_Connect_Shipping_Label( $api_client, $settings_store, $schemas_store, $payment_methods_store );
			$nux                   = new WC_Connect_Nux( $tracks, $shipping_label );
			$taxjar                = new WC_Connect_TaxJar_Integration( $api_client, $logger );
			$options               = new WC_Connect_Options();
			$stripe                = new WC_Connect_Stripe( $api_client, $options, $logger );

			$this->set_logger( $logger );
			$this->set_api_client( $api_client );
			$this->set_service_schemas_validator( $validator );
			$this->set_service_schemas_store( $schemas_store );
			$this->set_service_settings_store( $settings_store );
			$this->set_payment_methods_store( $payment_methods_store );
			$this->set_tracks( $tracks );
			$this->set_shipping_label( $shipping_label );
			$this->set_nux( $nux );
			$this->set_taxjar( $taxjar );
			$this->set_stripe( $stripe );
		}

		/**
		 * Load admin-only plugin dependencies.
		 */
		public function load_admin_dependencies() {
			require_once( plugin_basename( 'classes/class-wc-connect-debug-tools.php' ) );
			new WC_Connect_Debug_Tools( $this->api_client );

			require_once( plugin_basename( 'classes/class-wc-connect-settings-pages.php' ) );
			$settings_pages = new WC_Connect_Settings_Pages( $this->payment_methods_store, $this->service_settings_store, $this->service_schemas_store );
			$this->set_settings_pages( $settings_pages );

			$schema = $this->get_service_schemas_store();
			$settings = $this->get_service_settings_store();
			$logger = $this->get_logger();
			$this->set_help_view( new WC_Connect_Help_View( $schema, $settings, $logger ) );
			add_action( 'admin_notices', array( WC_Connect_Error_Notice::instance(), 'render_notice' ) );
		}

		/**
		 * Hook plugin classes into WP/WC core.
		 */
		public function attach_hooks() {
			$schemas_store = $this->get_service_schemas_store();
			$schemas = $schemas_store->get_service_schemas();

			if ( $schemas ) {
				add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods' ) );
				add_action( 'woocommerce_load_shipping_methods', array( $this, 'woocommerce_load_shipping_methods' ) );
				add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_payment_gateways' ) );
				add_action( 'wc_connect_service_init', array( $this, 'init_service' ), 10, 2 );
				add_action( 'wc_connect_service_admin_options', array( $this, 'localize_and_enqueue_service_script' ), 10, 2 );
				add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'shipping_zone_method_added' ), 10, 3 );
				add_action( 'woocommerce_shipping_zone_method_deleted', array( $this, 'shipping_zone_method_deleted' ), 10, 3 );
				add_action( 'woocommerce_shipping_zone_method_status_toggled', array( $this, 'shipping_zone_method_status_toggled' ), 10, 4 );

				// Initialize user choices from the core setup wizard.
				// Note: Avoid doing so on non-primary requests so we don't duplicate efforts.
				if ( ! defined( 'DOING_AJAX' ) && is_admin() && ! isset( $_GET['noheader'] ) ) {
					$this->init_core_wizard_shipping_config();
					$this->init_core_wizard_payments_config();
				}
			}

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
			add_action( 'woocommerce_settings_saved', array( $schemas_store, 'fetch_service_schemas_from_connect_server' ) );
			add_action( 'wc_connect_fetch_service_schemas', array( $schemas_store, 'fetch_service_schemas_from_connect_server' ) );
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_wc_connect_package_meta_data' ) );
			add_filter( 'is_protected_meta', array( $this, 'hide_wc_connect_order_meta_data' ), 10, 3 );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 5 );
			add_filter( 'woocommerce_shipping_fields' , array( $this, 'add_shipping_phone_to_checkout' ) );
			add_action( 'woocommerce_admin_shipping_fields', array( $this, 'add_shipping_phone_to_order_fields' ) );
			add_filter( 'woocommerce_get_order_address', array( $this, 'get_shipping_phone_from_order' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this->nux, 'show_pointers' ) );
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_action_links' ) );
			add_action( 'enqueue_wc_connect_script', array( $this, 'enqueue_wc_connect_script' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'load_admin_dependencies' ) );
			add_filter( 'wc_connect_shipping_service_settings', array( $this, 'shipping_service_settings' ), 10, 3 );

			$tracks = $this->get_tracks();
			$tracks->init();

			$this->taxjar->init();
		}

		public function tos_rest_init() {
			$settings_store = $this->get_service_settings_store();
			$logger = $this->get_logger();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-base-controller.php' ) );

			require_once( plugin_basename( 'classes/class-wc-rest-connect-tos-controller.php' ) );
			$rest_tos_controller = new WC_REST_Connect_Tos_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_tos_controller( $rest_tos_controller );
			$rest_tos_controller->register_routes();
		}

		/**
		 * Hook the REST API
		 * Note that we cannot load our controller until this time, because prior to
		 * rest_api_init firing, WP_REST_Controller is not yet defined
		 */
		public function rest_api_init() {
			$schemas_store = $this->get_service_schemas_store();
			$settings_store = $this->get_service_settings_store();
			$logger = $this->get_logger();

			if ( ! class_exists( 'WP_REST_Controller' ) ) {
				$this->logger->debug( 'Error. WP_REST_Controller could not be found', __FUNCTION__ );
				return;
			}

			require_once( plugin_basename( 'classes/class-wc-rest-connect-base-controller.php' ) );

			require_once( plugin_basename( 'classes/class-wc-rest-connect-packages-controller.php' ) );
			$rest_packages_controller = new WC_REST_Connect_Packages_Controller( $this->api_client, $settings_store, $logger, $this->service_schemas_store );
			$this->set_rest_packages_controller( $rest_packages_controller );
			$rest_packages_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-account-settings-controller.php' ) );
			$rest_account_settings_controller = new WC_REST_Connect_Account_Settings_Controller( $this->api_client, $settings_store, $logger, $this->payment_methods_store );
			$this->set_rest_account_settings_controller( $rest_account_settings_controller );
			$rest_account_settings_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-services-controller.php' ) );
			$rest_services_controller = new WC_REST_Connect_Services_Controller( $this->api_client, $settings_store, $logger, $schemas_store );
			$this->set_rest_services_controller( $rest_services_controller );
			$rest_services_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-dismiss-service-notice-controller.php' ) );
			$rest_dismiss_service_notice_controller = new WC_REST_Connect_Services_Dismiss_Service_Notice_Controller( $this->api_client, $settings_store, $logger, $this->nux );
			$this->set_rest_dismiss_service_notice_controller( $rest_dismiss_service_notice_controller );
			$rest_dismiss_service_notice_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-self-help-controller.php' ) );
			$rest_self_help_controller = new WC_REST_Connect_Self_Help_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_self_help_controller( $rest_self_help_controller );
			$rest_self_help_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-label-controller.php' ) );
			$rest_shipping_label_controller = new WC_REST_Connect_Shipping_Label_Controller( $this->api_client, $settings_store, $logger, $this->shipping_label );
			$this->set_rest_shipping_label_controller( $rest_shipping_label_controller );
			$rest_shipping_label_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-label-status-controller.php' ) );
			$rest_shipping_label_status_controller = new WC_REST_Connect_Shipping_Label_Status_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_shipping_label_status_controller( $rest_shipping_label_status_controller );
			$rest_shipping_label_status_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-label-refund-controller.php' ) );
			$rest_shipping_label_refund_controller = new WC_REST_Connect_Shipping_Label_Refund_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_shipping_label_refund_controller( $rest_shipping_label_refund_controller );
			$rest_shipping_label_refund_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-label-preview-controller.php' ) );
			$rest_shipping_label_preview_controller = new WC_REST_Connect_Shipping_Label_Preview_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_shipping_label_preview_controller( $rest_shipping_label_preview_controller );
			$rest_shipping_label_preview_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-label-print-controller.php' ) );
			$rest_shipping_label_print_controller = new WC_REST_Connect_Shipping_Label_Print_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_shipping_label_print_controller( $rest_shipping_label_print_controller );
			$rest_shipping_label_print_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-shipping-rates-controller.php' ) );
			$rest_shipping_rates_controller = new WC_REST_Connect_Shipping_Rates_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_shipping_rates_controller( $rest_shipping_rates_controller );
			$rest_shipping_rates_controller->register_routes();

			require_once( plugin_basename( 'classes/class-wc-rest-connect-address-normalization-controller.php' ) );
			$rest_address_normalization_controller = new WC_REST_Connect_Address_Normalization_Controller( $this->api_client, $settings_store, $logger );
			$this->set_rest_address_normalization_controller( $rest_address_normalization_controller );
			$rest_address_normalization_controller->register_routes();

			if ( $this->stripe->is_stripe_plugin_enabled() ) {
				require_once( plugin_basename( 'classes/class-wc-rest-connect-stripe-account-controller.php' ) );
				$rest_stripe_account_controller = new WC_REST_Connect_Stripe_Account_Controller( $this->stripe, $this->api_client, $settings_store, $logger );
				$rest_stripe_account_controller->register_routes();

				require_once( plugin_basename( 'classes/class-wc-rest-connect-stripe-oauth-init-controller.php' ) );
				$rest_stripe_settings_controller = new WC_REST_Connect_Stripe_Oauth_Init_Controller( $this->stripe, $this->api_client, $settings_store, $logger );
				$rest_stripe_settings_controller->register_routes();

				require_once( plugin_basename( 'classes/class-wc-rest-connect-stripe-oauth-connect-controller.php' ) );
				$rest_stripe_oauth_controller = new WC_REST_Connect_Stripe_Oauth_Connect_Controller( $this->stripe, $this->api_client, $settings_store, $logger );
				$rest_stripe_oauth_controller->register_routes();

				require_once( plugin_basename( 'classes/class-wc-rest-connect-stripe-deauthorize-controller.php' ) );
				$rest_stripe_account_controller = new WC_REST_Connect_Stripe_Deauthorize_Controller( $this->stripe, $this->api_client, $settings_store, $logger );
				$rest_stripe_account_controller->register_routes();
			}

			add_filter( 'rest_request_before_callbacks', array( $this, 'log_rest_api_errors' ), 10, 3 );
		}

		/**
		 * Log any WP_Errors encountered before our REST API callbacks
		 *
		 * Note: intended to be hooked into 'rest_request_before_callbacks'
		 *
		 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
		 * @param WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 *
		 * @return mixed - pass through value of $response.
		 */
		public function log_rest_api_errors( $response, $handler, $request ) {
			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			if ( 0 === strpos( $request->get_route(), '/wc/v1/connect/' ) ) {
				$route_info = $request->get_method() . ' ' . $request->get_route();

				$this->get_logger()->error( $response, $route_info );
				$this->get_logger()->error( $route_info, $request->get_body() );
			}

			return $response;
		}

		/**
		 * Added to the wc_connect_shipping_service_settings filter, returns service settings
		 *
		 * @param $settings
		 * @param $method_id
		 * @param $instance_id
		 *
		 * @return array
		 */
		public function shipping_service_settings( $settings, $method_id, $instance_id ) {
			$settings_store = $this->get_service_settings_store();
			$schemas_store = $this->get_service_schemas_store();
			$service_schema = $schemas_store->get_service_schema_by_id_or_instance_id( $instance_id ? $instance_id : $method_id );
			if ( ! $service_schema ) {
				return array_merge( $settings, array(
					'formType'   => 'services',
					'methodId'   => $method_id,
					'instanceId' => $instance_id,
				) );
			}

			return array_merge( $settings, array(
				'storeOptions'       => $settings_store->get_store_options(),
				'formSchema'         => $service_schema->service_settings,
				'formLayout'         => $service_schema->form_layout,
				'formData'           => $settings_store->get_service_settings( $method_id, $instance_id ),
				'formType'           => 'services',
				'methodId'           => $method_id,
				'instanceId'         => $instance_id,
				'noticeDismissed'    => $this->nux->is_notice_dismissed( 'service_settings' ),
			) );
		}

		/**
		 * This function is added to the wc_connect_service_admin_options action by this class
		 * (see attach_hooks) and then that action is fired by WC_Connect_Shipping_Method::admin_options
		 * to get the service instance form layout and settings bundled inside wcConnectData
		 * as the form container is emitted into the body's HTML
		 */
		public function localize_and_enqueue_service_script( $method_id, $instance_id = false ) {
			if ( ! function_exists( 'get_rest_url' ) ) {
				return;
			}

			do_action( 'enqueue_wc_connect_script',
				'wc-connect-service-settings',
				apply_filters( 'wc_connect_shipping_service_settings', array(), $method_id, $instance_id ) );
		}

		/**
		 * Hook fetching the available services from the connect server
		 */
		public function schedule_service_schemas_fetch() {

			$schemas_store = $this->get_service_schemas_store();
			$schemas = $schemas_store->get_service_schemas();

			if ( ! $schemas ) {
				$schemas_store->fetch_service_schemas_from_connect_server();
			} else if ( defined( 'WOOCOMMERCE_CONNECT_FREQUENT_FETCH' ) && WOOCOMMERCE_CONNECT_FREQUENT_FETCH ) {
				$schemas_store->fetch_service_schemas_from_connect_server();
			} else if ( ! wp_next_scheduled( 'wc_connect_fetch_service_schemas' ) ) {
				wp_schedule_event( time(), 'daily', 'wc_connect_fetch_service_schemas' );
			}

		}

		/**
		 * Inject API Client and Logger into WC Connect shipping method instances.
		 *
		 * @param WC_Connect_Shipping_Method $method
		 * @param int|string                 $id_or_instance_id
		 */
		public function init_service( WC_Connect_Shipping_Method $method, $id_or_instance_id ) {

			// TODO - make more generic - allow things other than WC_Connect_Shipping_Method to work here

			$method->set_api_client( $this->get_api_client() );
			$method->set_logger( $this->get_logger() );
			$method->set_service_settings_store( $this->get_service_settings_store() );

			$service_schema = $this->get_service_schemas_store()->get_service_schema_by_id_or_instance_id( $id_or_instance_id );

			if ( $service_schema ) {
				$method->set_service_schema( $service_schema );
			}

		}

		/**
		 * Returns a reference to a service (e.g. WC_Connect_Shipping_Method) of
		 * a particular id so we can avoid instantiating them multiple times
		 *
		 * @param string $class_name Class name of service to create (e.g. WC_Connect_Shipping_Method)
		 * @param string $service_id Service id of service to create (e.g. usps)
		 * @return mixed
		 */
		protected function get_service_object_by_id( $class_name, $service_id ) {
			if ( ! array_key_exists( $service_id, $this->service_object_cache ) ) {
				$this->service_object_cache[ $service_id ] = new $class_name( $service_id );
			}

			return $this->service_object_cache[ $service_id ];
		}

		/**
		 * Filters in shipping methods for things like WC_Shipping::get_shipping_method_class_names
		 *
		 * @param $shipping_methods
		 * @return mixed
		 */
		public function woocommerce_shipping_methods( $shipping_methods ) {
			$shipping_service_ids = $this->get_service_schemas_store()->get_all_shipping_method_ids();

			foreach ( $shipping_service_ids as $shipping_service_id ) {
				$shipping_methods[ $shipping_service_id ] = $this->get_service_object_by_id( 'WC_Connect_Shipping_Method', $shipping_service_id );
			}

			return $shipping_methods;
		}

		/**
		 * Registers shipping methods for use in things like the Add Shipping Method dialog
		 * on the Shipping Zones view
		 *
		 */
		public function woocommerce_load_shipping_methods() {
			$shipping_service_ids = $this->get_service_schemas_store()->get_all_shipping_method_ids();

			foreach ( $shipping_service_ids as $shipping_service_id ) {
				$shipping_method = $this->get_service_object_by_id( 'WC_Connect_Shipping_Method', $shipping_service_id );
				WC_Shipping::instance()->register_shipping_method( $shipping_method );
			}
		}


		public function woocommerce_payment_gateways( $payment_gateways ) {
			return $payment_gateways;
		}

		function get_i18n_json() {
			$i18n_json = plugin_dir_path( __FILE__ ) . 'i18n/json/woocommerce-services-' . get_locale() . '.json';
			if ( is_file( $i18n_json ) && is_readable( $i18n_json ) ) {
				$locale_data = @file_get_contents( $i18n_json );
				if ( $locale_data ) {
					return $locale_data;
				}
			}
			// Return empty if we have nothing to return so it doesn't fail when parsed in JS
			return '{}';
		}

		/**
		 * Registers the React UI bundle
		 */
		public function admin_enqueue_scripts() {
			// Note: This will break outside of wp-admin, if/when we put user-facing JS/CSS we'll have to figure out another way to version them
			$plugin_data = get_plugin_data( __FILE__, false, false );
			$plugin_version = $plugin_data[ 'Version' ];

			wp_register_style( 'wc_connect_admin', $this->wc_connect_base_url . 'woocommerce-services.css', array(), $plugin_version );
			wp_register_script( 'wc_connect_admin', $this->wc_connect_base_url . 'woocommerce-services.js', array(), $plugin_version );
			wp_register_script( 'wc_services_admin_pointers', $this->wc_connect_base_url . 'woocommerce-services-admin-pointers.js', array( 'wp-pointer', 'jquery' ), $plugin_version );
			wp_register_style( 'wc_connect_banner', $this->wc_connect_base_url . 'woocommerce-services-banner.css', array(), $plugin_version );
			wp_register_script( 'wc_connect_banner', $this->wc_connect_base_url . 'woocommerce-services-banner.js', array( 'updates' ), $plugin_version );

			$i18n_json = $this->get_i18n_json();
			/** @var array $i18nStrings defined in i18n/strings.php */
			wp_localize_script( 'wc_connect_admin', 'i18nLocale', array(
					'json' => $i18n_json,
					'localeSlug' => join( '-', explode( '_', get_locale() ) ),
			) );
		}

		public function get_active_shipping_services() {
			global $wpdb;
			$active_shipping_services = array();
			$shipping_service_ids = $this->get_service_schemas_store()->get_all_shipping_method_ids();

			foreach ( $shipping_service_ids as $shipping_service_id ) {
				$is_active = $wpdb->get_var( $wpdb->prepare(
					"SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = 1 AND method_id = %s LIMIT 1;",
					$shipping_service_id
				) );

				if ( $is_active ) {
					$active_shipping_services[] = $shipping_service_id;
				}
			}

			return $active_shipping_services;
		}

		public function get_active_services() {
			return $this->get_active_shipping_services();
		}

		public function is_wc_connect_shipping_service( $service_id ) {
			$shipping_service_ids = $this->get_service_schemas_store()->get_all_shipping_method_ids();
			return in_array( $service_id, $shipping_service_ids );
		}

		public function shipping_zone_method_added( $instance_id, $service_id, $zone_id ) {
			if ( $this->is_wc_connect_shipping_service( $service_id ) ) {
				do_action( 'wc_connect_shipping_zone_method_added', $instance_id, $service_id, $zone_id );
			}
		}

		public function shipping_zone_method_deleted( $instance_id, $service_id, $zone_id ) {
			if ( $this->is_wc_connect_shipping_service( $service_id ) ) {
				WC_Connect_Options::delete_shipping_method_options(  $service_id, $instance_id );
				do_action( 'wc_connect_shipping_zone_method_deleted', $instance_id, $service_id, $zone_id );
			}
		}

		public function shipping_zone_method_status_toggled( $instance_id, $service_id, $zone_id, $enabled ) {
			if ( $this->is_wc_connect_shipping_service( $service_id ) ) {
				do_action( 'wc_connect_shipping_zone_method_status_toggled', $instance_id, $service_id, $zone_id, $enabled );
			}
		}

		public function add_meta_boxes() {
			if ( $this->shipping_label->should_show_meta_box() ) {
				add_meta_box( 'woocommerce-order-label', __( 'Shipping Label', 'woocommerce-services' ), array( $this->shipping_label, 'meta_box' ), null, 'side', 'default' );
			}
		}

		public function hide_wc_connect_package_meta_data( $hidden_keys ) {
			$hidden_keys[] = 'wc_connect_packages';
			return $hidden_keys;
		}

		function hide_wc_connect_order_meta_data( $protected, $meta_key, $meta_type ) {
			if ( in_array( $meta_key, array( 'wc_connect_labels', 'wc_connect_destination_normalized' ) ) ) {
				$protected = true;
			}

			return $protected;
		}

		function add_shipping_phone_to_checkout( $fields ) {
			$fields[ 'shipping_phone' ] = array(
				'label'        => __( 'Phone', 'woocommerce-services' ),
				'type'         => 'tel',
				'required'     => false,
				'class'        => array( 'form-row-wide' ),
				'clear'        => true,
				'validate'     => array( 'phone' ),
				'autocomplete' => 'tel',
			);
			return $fields;
		}

		function add_shipping_phone_to_order_fields( $fields ) {
			$fields[ 'phone' ] = array(
				'label' => __( 'Phone', 'woocommerce-services' ),
			);
			return $fields;
		}

		function get_shipping_phone_from_order( $fields, $address_type, WC_Order $order ) {
			$order_id = WC_Connect_Compatibility::instance()->get_order_id( $order );
			if ( 'shipping' === $address_type ) {
				$shipping_phone = get_post_meta( $order_id, '_shipping_phone', true );
				if ( ! $shipping_phone ) {
					$billing_address = $order->get_address( 'billing' );
					$shipping_phone = $billing_address[ 'phone' ];
				}
				$fields[ 'phone' ] =  $shipping_phone;
			}
			return $fields;
		}

		function add_plugin_action_links( $links ) {
			$links[] = sprintf(
				wp_kses(
					__( '<a href="%s">Support</a>', 'woocommerce-services' ),
					array(  'a' => array( 'href' => array() ) )
				),
				esc_url( 'https://woocommerce.com/my-account/create-a-ticket/' )
			);
			return $links;
		}

		function enqueue_wc_connect_script( $root_view, $extra_args = array() ) {
			$payload = array(
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'baseURL'      => get_rest_url(),
			);

			wp_localize_script( 'wc_connect_admin', 'wcConnectData', $payload );
			wp_enqueue_script( 'wc_connect_admin' );
			wp_enqueue_style( 'wc_connect_admin' );

			$debug_page_uri = esc_url( add_query_arg(
				array(
					'page' => 'wc-status',
					'tab' => 'connect'
				),
				admin_url( 'admin.php' )
			) );

			?>
				<div class="wcc-root <?php echo esc_attr( $root_view ) ?>" data-args="<?php echo esc_attr( wp_json_encode( $extra_args ) ) ?>">
					<span class="form-troubles" style="opacity: 0">
						<?php printf( __( 'Section not loading? Visit the <a href="%s">status page</a> for troubleshooting steps.', 'woocommerce-services' ), $debug_page_uri ); ?>
					</span>
				</div>
			<?php
		}
	}

	if ( ! defined( 'WC_UNIT_TESTING' ) ) {
		new WC_Connect_Loader();
	}
}

register_deactivation_hook( __FILE__, array( 'WC_Connect_Loader', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'WC_Connect_Loader', 'plugin_uninstall' ) );
