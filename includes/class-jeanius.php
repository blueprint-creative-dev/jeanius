<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://blueprintdigital.com/
 * @since      1.0.0
 *
 * @package    Jeanius
 * @subpackage Jeanius/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Jeanius
 * @subpackage Jeanius/includes
 * @author     Blueprint Digital <development@blueprintdigital.com>
 */
class Jeanius {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Jeanius_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0 
	 */
	public function __construct() {
		
		/* Custom Post Type */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-assessment-cpt.php';
		// Load Gravity-Forms integration
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-gravity.php';
		\Jeanius\Gravity::init();
		/* Provisioner */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-provisioner.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-consent.php';
		\Jeanius\Consent::init();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers.php';


		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-rest.php';
		\Jeanius\Rest::init();
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-wizard-page.php';

		

		/* Wizard pretty URL */
		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/wizard/?$',
				'index.php?jeanius_wizard=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'jeanius_wizard';
			return $vars;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'jeanius_wizard' ) ) {
				\Jeanius\Wizard_Page::render();
				exit;
			}
		});

		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/wizard-stage-2/?$',
				'index.php?jeanius_stage2=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $vars ) {
			$vars[]='jeanius_stage2'; return $vars;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var('jeanius_stage2') ){
				\Jeanius\Wizard_Page::render_stage_two();
				exit;
			}
		});


		// Stage-3 URL
		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/wizard-stage-3/?$',
				'index.php?jeanius_stage3=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'jeanius_stage3';
			return $vars;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'jeanius_stage3' ) ) {
				\Jeanius\Wizard_Page::render_stage_three();
				exit;
			}
		});

		/* ---------- Stage 4  URL  /wizard-stage-4 ---------- */
		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/wizard-stage-4/?$',
				'index.php?jeanius_stage4=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'jeanius_stage4';
			return $vars;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'jeanius_stage4' ) ) {
				\Jeanius\Wizard_Page::render_stage_four();
				exit;
			}
		});


		/* ---------- Review screen URL  /jeanius-assessment/review ---------- */
		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/review/?$',
				'index.php?jeanius_review=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'jeanius_review';
			return $vars;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'jeanius_review' ) ) {
				\Jeanius\Wizard_Page::render_review();
				exit;
			}
		});
		add_action('init',function(){
			add_rewrite_rule('^jeanius-assessment/describe/?$','index.php?jeanius_describe=1','top');
		});
		add_filter('query_vars',function($v){$v[]='jeanius_describe';return $v;});
		add_action('template_redirect',function(){
			if(get_query_var('jeanius_describe')){
			\Jeanius\Wizard_Page::render_describe();
			exit;
			}
		});
		

		add_action( 'init', function () {
			add_rewrite_rule(
				'^jeanius-assessment/timeline/?$',
				'index.php?jeanius_timeline=1',
				'top'
			);
		});
		add_filter( 'query_vars', function ( $v ) {
			$v[] = 'jeanius_timeline';
			return $v;
		});
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'jeanius_timeline' ) ) {
				\Jeanius\Wizard_Page::render_timeline();
				exit;
			}
		});


/* ---------- Results URL ---------- */
add_action( 'init', function () {
    add_rewrite_rule(
        '^jeanius-assessment/results/?$',
        'index.php?jeanius_results=1',
        'top'
    );
});
add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'jeanius_results';
    return $vars;
});
add_action( 'template_redirect', function () {
    if ( get_query_var( 'jeanius_results' ) ) {
        \Jeanius\Wizard_Page::render_results();
        exit;
    }
});






		if ( defined( 'JEANIUS_VERSION' ) ) {
			$this->version = JEANIUS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'jeanius';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		/* -----------------------------------------------------------
		* ACF Options Page – Jeanius Settings
		* ---------------------------------------------------------- */
		add_action( 'acf/init', function () {

			// Only run if ACF Pro is active.
			if ( function_exists( 'acf_add_options_page' ) ) {

				acf_add_options_page( [
					'page_title'  => 'Jeanius Settings',
					'menu_title'  => 'Jeanius Settings',
					'menu_slug'   => 'jeanius-settings',
					'capability'  => 'manage_options',   // admins only
					'redirect'    => false
				] );
			}
		} );


	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Jeanius_Loader. Orchestrates the hooks of the plugin.
	 * - Jeanius_i18n. Defines internationalization functionality.
	 * - Jeanius_Admin. Defines all hooks for the admin area.
	 * - Jeanius_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jeanius-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-jeanius-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-jeanius-public.php';

		$this->loader = new Jeanius_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Jeanius_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Jeanius_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Jeanius_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Jeanius_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Jeanius_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}