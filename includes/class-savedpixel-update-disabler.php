<?php
/**
 * Runtime and admin UI for SavedPixel Update Disabler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SavedPixel_Update_Disabler {

	const VERSION   = '1.0';
	const OPTION    = 'savedpixel_update_disabler_settings';
	const LOG_OPTION = 'savedpixel_update_disabler_log';
	const LOG_MAX   = 200;
	const MENU_SLUG = 'savedpixel-update-disabler';

	private static $instance = null;
	private static $plugins  = null;
	private static $themes   = null;

	public static function bootstrap() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		self::ensure_settings();
	}

	public static function ensure_settings() {
		$current  = get_option( self::OPTION, null );
		$settings = self::normalize_settings( $current );

		if ( ! is_array( $current ) || $settings !== $current ) {
			update_option( self::OPTION, $settings );
		}
	}

	public static function settings() {
		self::ensure_settings();

		return self::normalize_settings( get_option( self::OPTION, array() ) );
	}

	private static function defaults() {
		return array(
			'disable_core_updates'              => 0,
			'disable_plugin_updates'            => 0,
			'disable_theme_updates'             => 0,
			'disable_translation_updates'       => 0,
			'disable_core_dev_updates'          => 0,
			'disable_core_minor_updates'        => 0,
			'disable_core_major_updates'        => 0,
			'disable_wp_mail'                   => 0,
			'disabled_plugin_update_basenames'  => array(),
			'block_all_outgoing_requests'       => 0,
			'block_plugin_requests'             => 0,
			'block_theme_requests'              => 0,
			'allowed_outgoing_plugins'          => array(),
			'allowed_outgoing_themes'           => array(),
		);
	}

	private static function normalize_settings( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::defaults();

		$settings = array(
			'disable_core_updates'             => self::sanitize_checkbox( $raw['disable_core_updates'] ?? $defaults['disable_core_updates'] ),
			'disable_plugin_updates'           => self::sanitize_checkbox( $raw['disable_plugin_updates'] ?? $defaults['disable_plugin_updates'] ),
			'disable_theme_updates'            => self::sanitize_checkbox( $raw['disable_theme_updates'] ?? $defaults['disable_theme_updates'] ),
			'disable_translation_updates'      => self::sanitize_checkbox( $raw['disable_translation_updates'] ?? $defaults['disable_translation_updates'] ),
			'disable_core_dev_updates'         => self::sanitize_checkbox( $raw['disable_core_dev_updates'] ?? $defaults['disable_core_dev_updates'] ),
			'disable_core_minor_updates'       => self::sanitize_checkbox( $raw['disable_core_minor_updates'] ?? $defaults['disable_core_minor_updates'] ),
			'disable_core_major_updates'       => self::sanitize_checkbox( $raw['disable_core_major_updates'] ?? $defaults['disable_core_major_updates'] ),
			'disable_wp_mail'                  => self::sanitize_checkbox( $raw['disable_wp_mail'] ?? $defaults['disable_wp_mail'] ),
			'disabled_plugin_update_basenames' => self::sanitize_plugin_basenames( $raw['disabled_plugin_update_basenames'] ?? $defaults['disabled_plugin_update_basenames'] ),
			'block_all_outgoing_requests'      => self::sanitize_checkbox( $raw['block_all_outgoing_requests'] ?? $defaults['block_all_outgoing_requests'] ),
			'block_plugin_requests'            => self::sanitize_checkbox( $raw['block_plugin_requests'] ?? $defaults['block_plugin_requests'] ),
			'block_theme_requests'             => self::sanitize_checkbox( $raw['block_theme_requests'] ?? $defaults['block_theme_requests'] ),
			'allowed_outgoing_plugins'         => self::sanitize_plugin_basenames( $raw['allowed_outgoing_plugins'] ?? $defaults['allowed_outgoing_plugins'] ),
			'allowed_outgoing_themes'          => self::sanitize_theme_slugs( $raw['allowed_outgoing_themes'] ?? $defaults['allowed_outgoing_themes'] ),
		);

		if ( $settings['disable_core_updates'] ) {
			$settings['disable_core_dev_updates']   = 1;
			$settings['disable_core_minor_updates'] = 1;
			$settings['disable_core_major_updates'] = 1;
		}

		return $settings;
	}

	private static function sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	private static function installed_plugins() {
		if ( null !== self::$plugins ) {
			return self::$plugins;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$plugins = get_plugins();

		return self::$plugins;
	}

	private static function installed_themes() {
		if ( null !== self::$themes ) {
			return self::$themes;
		}

		self::$themes = wp_get_themes();

		return self::$themes;
	}

	private static function sanitize_plugin_basenames( $value ) {
		$installed = self::installed_plugins();
		$selected  = array();

		foreach ( (array) $value as $basename ) {
			$basename = self::normalize_plugin_basename( $basename );
			if ( '' !== $basename && isset( $installed[ $basename ] ) ) {
				$selected[ $basename ] = $basename;
			}
		}

		return array_values( $selected );
	}

	private static function sanitize_theme_slugs( $value ) {
		$themes   = self::installed_themes();
		$selected = array();

		foreach ( (array) $value as $slug ) {
			$slug = sanitize_key( (string) wp_unslash( $slug ) );
			if ( '' !== $slug && isset( $themes[ $slug ] ) ) {
				$selected[ $slug ] = $slug;
			}
		}

		return array_values( $selected );
	}

	private static function normalize_plugin_basename( $value ) {
		$value = wp_normalize_path( (string) wp_unslash( $value ) );

		return ltrim( $value, '/' );
	}

	private static function html_id( $value ) {
		return sanitize_html_class( str_replace( array( '/', '.', '\\' ), '-', (string) $value ) );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_runtime_hooks' ), 0 );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_global_block_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_global_block_notice' ) );
		add_action( 'admin_head', array( $this, 'suppress_update_nag' ) );
		add_action( 'network_admin_head', array( $this, 'suppress_update_nag' ) );
	}

	public function register_runtime_hooks() {
		$settings = self::settings();

		if ( $settings['disable_core_updates'] ) {
			add_filter( 'pre_site_transient_update_core', array( $this, 'empty_core_updates_transient' ) );
		}

		if ( $settings['disable_plugin_updates'] ) {
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'empty_plugin_updates_transient' ) );
			add_filter( 'auto_update_plugin', '__return_false' );
		}

		if ( $settings['disable_theme_updates'] ) {
			add_filter( 'pre_site_transient_update_themes', array( $this, 'empty_theme_updates_transient' ) );
			add_filter( 'auto_update_theme', '__return_false' );
		}

		if ( $settings['disable_translation_updates'] ) {
			add_filter( 'site_transient_update_core', array( $this, 'strip_translation_updates' ) );
			add_filter( 'site_transient_update_plugins', array( $this, 'strip_translation_updates' ) );
			add_filter( 'site_transient_update_themes', array( $this, 'strip_translation_updates' ) );
			add_filter( 'auto_update_translation', '__return_false' );
		}

		if ( $settings['disable_core_updates'] || $settings['disable_core_dev_updates'] ) {
			add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		}

		if ( $settings['disable_core_updates'] || $settings['disable_core_minor_updates'] ) {
			add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		}

		if ( $settings['disable_core_updates'] || $settings['disable_core_major_updates'] ) {
			add_filter( 'allow_major_auto_core_updates', '__return_false' );
		}

		if ( ! empty( $settings['disabled_plugin_update_basenames'] ) ) {
			add_filter( 'site_transient_update_plugins', array( $this, 'filter_selected_plugin_updates' ) );
		}

		if ( $this->has_update_controls( $settings ) ) {
			add_filter( 'site_status_tests', array( $this, 'filter_site_status_tests' ) );
		}

		if ( $settings['disable_wp_mail'] ) {
			add_filter( 'pre_wp_mail', array( $this, 'preempt_wp_mail' ), 10, 2 );
		}

		if ( $settings['block_all_outgoing_requests'] || $settings['block_plugin_requests'] || $settings['block_theme_requests'] ) {
			add_filter( 'pre_http_request', array( $this, 'preempt_http_request' ), 10, 3 );
		}
	}

	private function has_update_controls( $settings ) {
		return ! empty( $settings['disable_core_updates'] )
			|| ! empty( $settings['disable_plugin_updates'] )
			|| ! empty( $settings['disable_theme_updates'] )
			|| ! empty( $settings['disable_translation_updates'] )
			|| ! empty( $settings['disable_core_dev_updates'] )
			|| ! empty( $settings['disable_core_minor_updates'] )
			|| ! empty( $settings['disable_core_major_updates'] );
	}

	public function suppress_update_nag() {
		if ( ! $this->has_update_controls( self::settings() ) ) {
			return;
		}

		remove_action( 'admin_notices', 'update_nag', 3 );
	}

	public function render_global_block_notice() {
		$settings = self::settings();

		if ( empty( $settings['block_all_outgoing_requests'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong>SavedPixel Update Disabler:</strong> External HTTP requests are currently blocked except for localhost and same-site loopbacks.</p>
		</div>
		<?php
	}

	public function empty_core_updates_transient() {
		global $wp_version;

		return (object) array(
			'updates'         => array(),
			'last_checked'    => time(),
			'version_checked' => (string) $wp_version,
			'translations'    => array(),
		);
	}

	public function empty_plugin_updates_transient() {
		$plugins = self::installed_plugins();
		$checked = array();

		foreach ( $plugins as $basename => $plugin ) {
			$checked[ $basename ] = (string) ( $plugin['Version'] ?? '' );
		}

		return (object) array(
			'last_checked' => time(),
			'checked'      => $checked,
			'response'     => array(),
			'no_update'    => array(),
			'translations' => array(),
		);
	}

	public function empty_theme_updates_transient() {
		$themes  = self::installed_themes();
		$checked = array();

		foreach ( $themes as $slug => $theme ) {
			$checked[ $slug ] = (string) $theme->get( 'Version' );
		}

		return (object) array(
			'last_checked' => time(),
			'checked'      => $checked,
			'response'     => array(),
			'no_update'    => array(),
			'translations' => array(),
		);
	}

	public function strip_translation_updates( $transient ) {
		if ( is_object( $transient ) ) {
			$transient->translations = array();
		}

		return $transient;
	}

	public function filter_selected_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->response ) ) {
			return $transient;
		}

		$settings = self::settings();

		foreach ( $settings['disabled_plugin_update_basenames'] as $basename ) {
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	public function filter_site_status_tests( $tests ) {
		unset( $tests['async']['background_updates'], $tests['direct']['plugin_theme_auto_updates'] );

		return $tests;
	}

	public function preempt_wp_mail( $return, $atts ) {
		unset( $atts );

		return false;
	}

	public function preempt_http_request( $preempt, $parsed_args, $url ) {
		unset( $parsed_args );

		$settings = self::settings();
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( '' === $host || $this->is_internal_host( $host ) ) {
			return $preempt;
		}

		if ( $settings['block_all_outgoing_requests'] ) {
			$this->log_blocked_request( $url, 'all_outgoing', '' );
			return new WP_Error(
				'savedpixel_outgoing_blocked',
				'External HTTP requests are blocked by SavedPixel Update Disabler.'
			);
		}

		$origin = $this->classify_http_request_origin();

		if ( 'plugin' === $origin['type'] && $settings['block_plugin_requests'] && ! in_array( $origin['slug'], $settings['allowed_outgoing_plugins'], true ) ) {
			$this->log_blocked_request( $url, 'plugin', $origin['slug'] );
			return new WP_Error(
				'savedpixel_plugin_http_blocked',
				'Plugin-originated HTTP requests are blocked by SavedPixel Update Disabler.'
			);
		}

		if ( 'theme' === $origin['type'] && $settings['block_theme_requests'] && ! in_array( $origin['slug'], $settings['allowed_outgoing_themes'], true ) ) {
			$this->log_blocked_request( $url, 'theme', $origin['slug'] );
			return new WP_Error(
				'savedpixel_theme_http_blocked',
				'Theme-originated HTTP requests are blocked by SavedPixel Update Disabler.'
			);
		}

		return $preempt;
	}

	private function log_blocked_request( $url, $rule, $origin_slug ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time'   => current_time( 'mysql' ),
			'url'    => (string) $url,
			'rule'   => (string) $rule,
			'origin' => (string) $origin_slug,
		);

		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, -self::LOG_MAX );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	private function is_internal_host( $host ) {
		$allowed_hosts = array(
			'localhost',
			'127.0.0.1',
			'::1',
		);

		$current_hosts = array(
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( site_url(), PHP_URL_HOST ),
			isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '',
		);

		if ( function_exists( 'network_home_url' ) ) {
			$current_hosts[] = wp_parse_url( network_home_url(), PHP_URL_HOST );
			$current_hosts[] = wp_parse_url( network_site_url(), PHP_URL_HOST );
		}

		foreach ( $current_hosts as $current_host ) {
			$current_host = strtolower( trim( (string) $current_host ) );
			if ( '' !== $current_host ) {
				$allowed_hosts[] = $current_host;
			}
		}

		return in_array( $host, array_unique( $allowed_hosts ), true );
	}

	private function classify_http_request_origin() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

		foreach ( $trace as $frame ) {
			$file = isset( $frame['file'] ) ? wp_normalize_path( (string) $frame['file'] ) : '';

			if ( '' === $file ) {
				continue;
			}

			$plugin = $this->plugin_for_file( $file );
			if ( null !== $plugin && self::MENU_SLUG . '/' . basename( dirname( __DIR__ ) ) . '.php' !== $plugin ) {
				return array(
					'type'  => 'plugin',
					'slug'  => $plugin,
				);
			}

			$theme = $this->theme_for_file( $file );
			if ( null !== $theme ) {
				return array(
					'type'  => 'theme',
					'slug'  => $theme,
				);
			}
		}

		return array(
			'type' => 'unknown',
			'slug' => '',
		);
	}

	private function plugin_for_file( $file ) {
		static $identities = null;

		if ( null === $identities ) {
			$identities = array();
			foreach ( self::installed_plugins() as $basename => $plugin ) {
				unset( $plugin );
				$basename     = self::normalize_plugin_basename( $basename );
				$absolute     = wp_normalize_path( WP_PLUGIN_DIR . '/' . $basename );
				$has_subdir   = false !== strpos( $basename, '/' );
				$match_prefix = $has_subdir ? trailingslashit( dirname( $absolute ) ) : $absolute;
				$identities[] = array(
					'basename' => $basename,
					'prefix'   => $match_prefix,
					'exact'    => $absolute,
					'has_dir'  => $has_subdir,
				);
			}

			usort(
				$identities,
				static function ( $left, $right ) {
					return strlen( $right['prefix'] ) <=> strlen( $left['prefix'] );
				}
			);
		}

		foreach ( $identities as $identity ) {
			if ( $identity['has_dir'] && 0 === strpos( $file, $identity['prefix'] ) ) {
				return $identity['basename'];
			}

			if ( ! $identity['has_dir'] && $file === $identity['exact'] ) {
				return $identity['basename'];
			}
		}

		return null;
	}

	private function theme_for_file( $file ) {
		static $identities = null;

		if ( null === $identities ) {
			$identities = array();
			foreach ( self::installed_themes() as $slug => $theme ) {
				$identities[] = array(
					'slug'               => $slug,
					'stylesheet_prefix'  => trailingslashit( wp_normalize_path( $theme->get_stylesheet_directory() ) ),
					'template_prefix'    => trailingslashit( wp_normalize_path( $theme->get_template_directory() ) ),
				);
			}
		}

		foreach ( $identities as $identity ) {
			if ( 0 === strpos( $file, $identity['stylesheet_prefix'] ) || 0 === strpos( $file, $identity['template_prefix'] ) ) {
				return $identity['slug'];
			}
		}

		return null;
	}

	public function register_settings_page() {
		add_submenu_page(
			function_exists( 'savedpixel_admin_parent_slug' ) ? savedpixel_admin_parent_slug() : 'options-general.php',
			'SavedPixel Update Disabler',
			'Update Disabler',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			12
		);
	}

	public function enqueue_admin_assets() {
		if ( function_exists( 'savedpixel_current_admin_page' ) && self::MENU_SLUG !== savedpixel_current_admin_page() ) {
			return;
		}

		savedpixel_admin_enqueue_preview_style();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'savedpixel-update-disabler' ) );
		}

		$settings        = self::settings();
		$updated         = false;
		$log_cleared     = false;
		$request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? strtolower( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'post' === $request_method && isset( $_POST['spud_clear_log'] ) ) {
			check_admin_referer( 'spud_clear_log' );
			delete_option( self::LOG_OPTION );
			$log_cleared = true;
		}

		if ( 'post' === $request_method && isset( $_POST['spud_save_settings'] ) ) {
			check_admin_referer( 'spud_save_settings' );

			$settings = self::normalize_settings( $_POST );
			update_option( self::OPTION, $settings );
			$updated = true;
		}

		$plugins                     = self::installed_plugins();
		$themes                      = self::installed_themes();
		$disabled_plugin_count       = count( $settings['disabled_plugin_update_basenames'] );
		$allowed_plugin_count        = count( $settings['allowed_outgoing_plugins'] );
		$allowed_theme_count         = count( $settings['allowed_outgoing_themes'] );
		$global_requests_blocked     = ! empty( $settings['block_all_outgoing_requests'] );
		$plugin_request_rules_active = ! empty( $settings['block_plugin_requests'] ) && ! $global_requests_blocked;
		$theme_request_rules_active  = ! empty( $settings['block_theme_requests'] ) && ! $global_requests_blocked;
		?>
		<?php savedpixel_admin_page_start( 'spud-page' ); ?>
				<header id="spud-header" class="sp-page-header">
					<div id="spud-header-main">
						<h1 id="spud-header-title" class="sp-page-title">SavedPixel Update Disabler</h1>
						<p id="spud-header-desc" class="sp-page-desc">Control WordPress update checks, core auto-update channels, outbound HTTP requests, and mail behavior for staging or locked-down installs.</p>
					</div>
					<div id="spud-header-actions" class="sp-header-actions">
						<a id="spud-back-link" class="button" href="<?php echo esc_url( savedpixel_admin_page_url( savedpixel_admin_parent_slug() ) ); ?>">Back to Overview</a>
					</div>
				</header>

				<div id="spud-intro-note" class="sp-note">
					<p>Use these controls on staging, demos, or tightly managed environments. Disabling updates or outbound requests can break plugin installers, license checks, remote APIs, and background jobs.</p>
				</div>

				<?php if ( $updated ) : ?>
					<div id="spud-saved-note" class="sp-note">
						<p>Settings saved.</p>
					</div>
				<?php endif; ?>

				<form id="spud-form" method="post" class="sp-stack">
					<?php wp_nonce_field( 'spud_save_settings' ); ?>

					<section id="spud-update-controls-section">
						<div id="spud-update-controls-card" class="sp-card">
							<div class="sp-card__body">
								<h2 id="spud-update-controls-title">Update Controls</h2>
								<table id="spud-update-controls-table" class="form-table sp-form-table">
									<tr id="spud-row-core-updates">
										<th><label for="spud-disable-core-updates">Core updates</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-disable-core-updates" name="disable_core_updates" value="1" <?php checked( $settings['disable_core_updates'], 1 ); ?>>
												Disable WordPress core update checks and update UI.
											</label>
											<div id="spud-core-auto-updates">
												<p>
													<label>
														<input type="checkbox" class="spud-core-auto-toggle" name="disable_core_dev_updates" value="1" <?php checked( $settings['disable_core_dev_updates'], 1 ); ?>>
														Disable development auto-updates
													</label>
													<br>
													<label>
														<input type="checkbox" class="spud-core-auto-toggle" name="disable_core_minor_updates" value="1" <?php checked( $settings['disable_core_minor_updates'], 1 ); ?>>
														Disable minor auto-updates
													</label>
													<br>
													<label>
														<input type="checkbox" class="spud-core-auto-toggle" name="disable_core_major_updates" value="1" <?php checked( $settings['disable_core_major_updates'], 1 ); ?>>
														Disable major auto-updates
													</label>
												</p>
											</div>
											<p class="description">When core updates are disabled, all core auto-update channels are forced off as well.</p>
										</td>
									</tr>
									<tr id="spud-row-plugin-updates">
										<th><label for="spud-disable-plugin-updates">Plugin updates</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-disable-plugin-updates" name="disable_plugin_updates" value="1" <?php checked( $settings['disable_plugin_updates'], 1 ); ?>>
												Disable update checks for all installed plugins.
											</label>
											<p class="description">Use the exceptions table below when you only need to suppress updates for specific plugins.</p>
										</td>
									</tr>
									<tr id="spud-row-theme-updates">
										<th><label for="spud-disable-theme-updates">Theme updates</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-disable-theme-updates" name="disable_theme_updates" value="1" <?php checked( $settings['disable_theme_updates'], 1 ); ?>>
												Disable update checks for installed themes.
											</label>
										</td>
									</tr>
									<tr id="spud-row-translation-updates">
										<th><label for="spud-disable-translation-updates">Translation updates</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-disable-translation-updates" name="disable_translation_updates" value="1" <?php checked( $settings['disable_translation_updates'], 1 ); ?>>
												Disable translation update offers and background language-pack updates.
											</label>
										</td>
									</tr>
									<tr id="spud-row-wp-mail">
										<th><label for="spud-disable-wp-mail">WordPress mail</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-disable-wp-mail" name="disable_wp_mail" value="1" <?php checked( $settings['disable_wp_mail'], 1 ); ?>>
												Disable all email sent through <code>wp_mail()</code>.
											</label>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</section>

					<section id="spud-outbound-controls-section">
						<div id="spud-outbound-controls-card" class="sp-card">
							<div class="sp-card__body">
								<h2 id="spud-outbound-controls-title">Outbound Request Controls</h2>
								<div id="spud-outbound-note" class="sp-note" style="margin-bottom:8px;">
									<p>Selective outbound blocking is best-effort. SavedPixel inspects the PHP stack trace to identify plugin or theme callers. Requests without a clear caller are allowed.</p>
								</div>
								<table id="spud-outbound-controls-table" class="form-table sp-form-table">
									<tr id="spud-row-all-outgoing-requests">
										<th><label for="spud-block-all-outgoing-requests">Global blocking</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-block-all-outgoing-requests" name="block_all_outgoing_requests" value="1" <?php checked( $settings['block_all_outgoing_requests'], 1 ); ?>>
												Block all external HTTP requests.
											</label>
											<p class="description">Localhost and same-site loopbacks are always allowed so local tooling and loopback checks continue to work.</p>
										</td>
									</tr>
									<tr id="spud-row-plugin-requests">
										<th><label for="spud-block-plugin-requests">Plugin-originated requests</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-block-plugin-requests" name="block_plugin_requests" value="1" <?php checked( $settings['block_plugin_requests'], 1 ); ?>>
												Block external requests when the caller is a plugin that is not allowlisted below.
											</label>
											<p class="description">This only applies when the caller can be identified from the PHP stack trace.</p>
										</td>
									</tr>
									<tr id="spud-row-theme-requests">
										<th><label for="spud-block-theme-requests">Theme-originated requests</label></th>
										<td>
											<label>
												<input type="checkbox" id="spud-block-theme-requests" name="block_theme_requests" value="1" <?php checked( $settings['block_theme_requests'], 1 ); ?>>
												Block external requests when the caller is a theme that is not allowlisted below.
											</label>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</section>

					<section id="spud-plugin-exceptions-section">
						<div id="spud-plugin-exceptions-header" class="sp-card__header">
							<h2 id="spud-plugin-exceptions-title" class="sp-card__title">Plugin Update Exceptions</h2>
							<span id="spud-plugin-exceptions-count" class="sp-badge sp-badge--neutral"><?php echo esc_html( $disabled_plugin_count . ' items' ); ?></span>
						</div>
						<div id="spud-plugin-exceptions-note" class="sp-note sp-note--section-gap"<?php echo $settings['disable_plugin_updates'] ? '' : ' hidden'; ?>>
							<p>Global plugin updates are disabled. These per-plugin exceptions are preserved, but they are currently inactive.</p>
						</div>
						<div id="spud-plugin-exceptions-card" class="sp-card">
							<div class="sp-card__body sp-card__body--flush">
								<?php if ( empty( $plugins ) ) : ?>
									<p class="sp-empty">No installed plugins were found.</p>
								<?php else : ?>
									<div class="sp-table-wrap" id="spud-plugin-exceptions-group">
										<table id="spud-plugin-exceptions-table" class="sp-table">
											<thead>
												<tr id="spud-plugin-exceptions-thead-row">
													<th>Plugin</th>
													<th>File</th>
													<th class="sp-th-actions">Disable Updates</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $plugins as $basename => $plugin ) : ?>
													<?php $plugin_id = 'spud-plugin-exception-' . self::html_id( $basename ); ?>
													<?php $row_id = 'spud-exception-row-' . self::html_id( dirname( $basename ) ); ?>
													<tr id="<?php echo esc_attr( $row_id ); ?>">
														<td><?php echo esc_html( $plugin['Name'] ); ?></td>
														<td><?php echo esc_html( $basename ); ?></td>
														<td class="sp-td-actions">
															<div class="sp-actions">
																<label>
																	<input type="checkbox" id="<?php echo esc_attr( $plugin_id ); ?>" class="spud-plugin-exception-checkbox" name="disabled_plugin_update_basenames[]" value="<?php echo esc_attr( $basename ); ?>" <?php checked( in_array( $basename, $settings['disabled_plugin_update_basenames'], true ) ); ?>>
																	<span class="screen-reader-text">Disable updates for <?php echo esc_html( $plugin['Name'] ); ?></span>
																</label>
															</div>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</section>

					<section id="spud-plugin-allowlist-section">
						<div id="spud-plugin-allowlist-header" class="sp-card__header">
							<h2 id="spud-plugin-allowlist-title" class="sp-card__title">Allowed Plugin Callers</h2>
							<span id="spud-plugin-allowlist-count" class="sp-badge sp-badge--neutral"><?php echo esc_html( $allowed_plugin_count . ' items' ); ?></span>
						</div>
						<div id="spud-plugin-allowlist-note" class="sp-note sp-note--section-gap"<?php echo $plugin_request_rules_active ? ' hidden' : ''; ?>>
							<p>The plugin allowlist below is only active when plugin request blocking is enabled and global outbound blocking is off.</p>
						</div>
						<div id="spud-plugin-allowlist-card" class="sp-card">
							<div class="sp-card__body sp-card__body--flush">
								<div class="sp-table-wrap" id="spud-plugin-allowlist-group">
									<table id="spud-plugin-allowlist-table" class="sp-table">
										<thead>
											<tr id="spud-plugin-allowlist-thead-row">
												<th>Plugin</th>
												<th>File</th>
												<th class="sp-th-actions">Allow Requests</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $plugins as $basename => $plugin ) : ?>
												<?php $plugin_allow_id = 'spud-plugin-allow-' . self::html_id( $basename ); ?>
												<?php $allow_row_id = 'spud-allow-row-' . self::html_id( dirname( $basename ) ); ?>
												<tr id="<?php echo esc_attr( $allow_row_id ); ?>">
													<td><?php echo esc_html( $plugin['Name'] ); ?></td>
													<td><?php echo esc_html( $basename ); ?></td>
													<td class="sp-td-actions">
														<div class="sp-actions">
															<label>
																<input type="checkbox" id="<?php echo esc_attr( $plugin_allow_id ); ?>" class="spud-plugin-allowlist-checkbox" name="allowed_outgoing_plugins[]" value="<?php echo esc_attr( $basename ); ?>" <?php checked( in_array( $basename, $settings['allowed_outgoing_plugins'], true ) ); ?>>
																<span class="screen-reader-text">Allow outbound requests for <?php echo esc_html( $plugin['Name'] ); ?></span>
															</label>
														</div>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</section>

					<section id="spud-theme-allowlist-section">
						<div id="spud-theme-allowlist-header" class="sp-card__header">
							<h2 id="spud-theme-allowlist-title" class="sp-card__title">Allowed Theme Callers</h2>
							<span id="spud-theme-allowlist-count" class="sp-badge sp-badge--neutral"><?php echo esc_html( $allowed_theme_count . ' items' ); ?></span>
						</div>
						<div id="spud-theme-allowlist-note" class="sp-note sp-note--section-gap"<?php echo $theme_request_rules_active ? ' hidden' : ''; ?>>
							<p>The theme allowlist below is only active when theme request blocking is enabled and global outbound blocking is off.</p>
						</div>
						<div id="spud-theme-allowlist-card" class="sp-card">
							<div class="sp-card__body sp-card__body--flush">
								<div class="sp-table-wrap" id="spud-theme-allowlist-group">
									<table id="spud-theme-allowlist-table" class="sp-table">
										<thead>
											<tr id="spud-theme-allowlist-thead-row">
												<th>Theme</th>
												<th>Slug</th>
												<th class="sp-th-actions">Allow Requests</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $themes as $slug => $theme ) : ?>
												<?php $theme_allow_id = 'spud-theme-allow-' . self::html_id( $slug ); ?>
												<tr id="<?php echo esc_attr( 'spud-theme-row-' . self::html_id( $slug ) ); ?>">
													<td><?php echo esc_html( $theme->get( 'Name' ) ); ?></td>
													<td><?php echo esc_html( $slug ); ?></td>
													<td class="sp-td-actions">
														<div class="sp-actions">
															<label>
																<input type="checkbox" id="<?php echo esc_attr( $theme_allow_id ); ?>" class="spud-theme-allowlist-checkbox" name="allowed_outgoing_themes[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['allowed_outgoing_themes'], true ) ); ?>>
																<span class="screen-reader-text">Allow outbound requests for <?php echo esc_html( $theme->get( 'Name' ) ); ?></span>
															</label>
														</div>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</section>

					<div id="spud-submit-row" class="sp-header-actions">
						<button type="submit" name="spud_save_settings" class="button button-primary">Save Settings</button>
					</div>
				</form>

				<?php
				$blocked_log = get_option( self::LOG_OPTION, array() );
				$blocked_log = is_array( $blocked_log ) ? array_reverse( $blocked_log ) : array();
				$log_count   = count( $blocked_log );
				$per_page    = 25;
				$rule_labels = array(
					'all_outgoing' => 'All outgoing blocked',
					'plugin'       => 'Plugin request blocked',
					'theme'        => 'Theme request blocked',
				);
				?>

				<section id="spud-debug-log-section" class="sp-card" style="margin-top:24px;">
					<div id="spud-debug-log-header" class="sp-card__header">
						<div>
							<h2 id="spud-debug-log-title" class="sp-card__title">Blocked Request Log</h2>
							<p id="spud-debug-log-desc" class="sp-card__subtitle"><?php echo esc_html( $log_count ); ?> entries (max <?php echo esc_html( self::LOG_MAX ); ?>). Newest first.</p>
						</div>
						<?php if ( $log_count > 0 ) : ?>
							<form method="post">
								<?php wp_nonce_field( 'spud_clear_log' ); ?>
								<button id="spud-clear-log-btn" type="submit" name="spud_clear_log" value="1" class="button button-secondary">Clear Log</button>
							</form>
						<?php endif; ?>
					</div>
					<div id="spud-debug-log-body" class="sp-card__body">
						<?php if ( 0 === $log_count ) : ?>
							<p id="spud-debug-log-empty">No blocked requests recorded yet. Entries appear here when outgoing HTTP requests are blocked by the firewall rules above.</p>
						<?php else : ?>
							<table id="spud-debug-log-table" class="sp-table widefat striped" style="table-layout:fixed;">
								<thead>
									<tr>
										<th id="spud-log-th-time" style="width:160px;">Time</th>
										<th id="spud-log-th-url">URL</th>
										<th id="spud-log-th-rule" style="width:180px;">Rule</th>
										<th id="spud-log-th-origin" style="width:160px;">Origin</th>
									</tr>
								</thead>
								<tbody id="spud-debug-log-tbody">
									<?php foreach ( $blocked_log as $i => $entry ) : ?>
										<tr id="spud-log-row-<?php echo (int) $i; ?>" class="spud-log-row"<?php echo $i >= $per_page ? ' style="display:none;"' : ''; ?>>
											<td id="spud-log-time-<?php echo (int) $i; ?>"><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
											<td id="spud-log-url-<?php echo (int) $i; ?>" style="word-break:break-all;"><?php echo esc_html( $entry['url'] ?? '' ); ?></td>
											<td id="spud-log-rule-<?php echo (int) $i; ?>"><?php echo esc_html( $rule_labels[ $entry['rule'] ?? '' ] ?? $entry['rule'] ?? '' ); ?></td>
											<td id="spud-log-origin-<?php echo (int) $i; ?>"><?php echo esc_html( $entry['origin'] ?? '—' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php if ( $log_count > $per_page ) : ?>
								<div id="spud-log-pagination" class="sp-pagination" style="margin-top:12px;display:flex;align-items:center;gap:8px;">
									<button id="spud-log-prev" type="button" class="button button-secondary" disabled>&laquo; Previous</button>
									<span id="spud-log-page-info">Page 1 of <?php echo (int) ceil( $log_count / $per_page ); ?></span>
									<button id="spud-log-next" type="button" class="button button-secondary">Next &raquo;</button>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</section>

				<script>
					document.addEventListener('DOMContentLoaded', function () {
						const coreToggle = document.getElementById('spud-disable-core-updates');
						const pluginUpdatesToggle = document.getElementById('spud-disable-plugin-updates');
						const blockAllToggle = document.getElementById('spud-block-all-outgoing-requests');
						const blockPluginsToggle = document.getElementById('spud-block-plugin-requests');
						const blockThemesToggle = document.getElementById('spud-block-theme-requests');

						function mirrorDisabledValues(group, disabled) {
							if (!group) {
								return;
							}

							group.querySelectorAll('input[type="hidden"][data-mirror-for]').forEach(function (input) {
								input.remove();
							});

							group.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
								if (disabled && input.checked) {
									const hidden = document.createElement('input');
									hidden.type = 'hidden';
									hidden.name = input.name;
									hidden.value = input.value;
									hidden.setAttribute('data-mirror-for', input.id || input.name);
									input.parentNode.appendChild(hidden);
								}

								input.disabled = disabled;
							});
						}

						function syncCoreOptions() {
							const disabled = coreToggle && coreToggle.checked;
							document.querySelectorAll('.spud-core-auto-toggle').forEach(function (input) {
								if (disabled) {
									input.checked = true;
								}
								input.disabled = !!disabled;
							});
						}

						function syncPluginExceptions() {
							const group = document.getElementById('spud-plugin-exceptions-group');
							const note = document.getElementById('spud-plugin-exceptions-note');
							const disabled = pluginUpdatesToggle && pluginUpdatesToggle.checked;

							mirrorDisabledValues(group, !!disabled);

							if (note) {
								note.hidden = !disabled;
							}
						}

						function syncPluginAllowlist() {
							const group = document.getElementById('spud-plugin-allowlist-group');
							const note = document.getElementById('spud-plugin-allowlist-note');
							const disabled = (blockAllToggle && blockAllToggle.checked) || !(blockPluginsToggle && blockPluginsToggle.checked);

							mirrorDisabledValues(group, !!disabled);

							if (note) {
								note.hidden = !disabled;
							}
						}

						function syncThemeAllowlist() {
							const group = document.getElementById('spud-theme-allowlist-group');
							const note = document.getElementById('spud-theme-allowlist-note');
							const disabled = (blockAllToggle && blockAllToggle.checked) || !(blockThemesToggle && blockThemesToggle.checked);

							mirrorDisabledValues(group, !!disabled);

							if (note) {
								note.hidden = !disabled;
							}
						}

						syncCoreOptions();
						syncPluginExceptions();
						syncPluginAllowlist();
						syncThemeAllowlist();

						if (coreToggle) {
							coreToggle.addEventListener('change', syncCoreOptions);
						}

						if (pluginUpdatesToggle) {
							pluginUpdatesToggle.addEventListener('change', syncPluginExceptions);
						}

						if (blockAllToggle) {
							blockAllToggle.addEventListener('change', function () {
								syncPluginAllowlist();
								syncThemeAllowlist();
							});
						}

						if (blockPluginsToggle) {
							blockPluginsToggle.addEventListener('change', syncPluginAllowlist);
						}

						if (blockThemesToggle) {
							blockThemesToggle.addEventListener('change', syncThemeAllowlist);
						}

						// Debug log pagination
						(function() {
							var rows = document.querySelectorAll('.spud-log-row');
							var prev = document.getElementById('spud-log-prev');
							var next = document.getElementById('spud-log-next');
							var info = document.getElementById('spud-log-page-info');
							if (!rows.length || !prev || !next || !info) return;

							var perPage = 25;
							var totalPages = Math.ceil(rows.length / perPage);
							var currentPage = 1;

							function render() {
								var start = (currentPage - 1) * perPage;
								var end = start + perPage;
								for (var i = 0; i < rows.length; i++) {
									rows[i].style.display = (i >= start && i < end) ? '' : 'none';
								}
								info.textContent = 'Page ' + currentPage + ' of ' + totalPages;
								prev.disabled = currentPage <= 1;
								next.disabled = currentPage >= totalPages;
							}

							prev.addEventListener('click', function() {
								if (currentPage > 1) { currentPage--; render(); }
							});
							next.addEventListener('click', function() {
								if (currentPage < totalPages) { currentPage++; render(); }
							});

							render();
						})();
					});
				</script>
		<?php
		savedpixel_admin_page_end();
	}
}
