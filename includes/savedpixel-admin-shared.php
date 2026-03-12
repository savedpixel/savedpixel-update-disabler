<?php
/**
 * Shared SavedPixel admin shell helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'savedpixel_admin_parent_slug' ) ) {
	function savedpixel_admin_parent_slug() {
		return 'savedpixel';
	}
}

if ( ! function_exists( 'savedpixel_admin_page_url' ) ) {
	function savedpixel_admin_page_url( $slug ) {
		$slug = sanitize_key( (string) $slug );

		return admin_url( 'admin.php?page=' . $slug );
	}
}

if ( ! function_exists( 'savedpixel_current_admin_page' ) ) {
	function savedpixel_current_admin_page() {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the routed admin page slug.
			return '';
		}

		return sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the routed admin page slug.
	}
}

if ( ! function_exists( 'savedpixel_register_admin_preview_asset' ) ) {
	function savedpixel_register_admin_preview_asset( $url, $version = '', $pages = array() ) {
		global $savedpixel_admin_preview_assets;

		if ( empty( $url ) ) {
			return;
		}

		if ( ! is_array( $savedpixel_admin_preview_assets ?? null ) ) {
			$savedpixel_admin_preview_assets = array(
				'default' => array(),
				'pages'   => array(),
			);
		}

		$asset = array(
			'url'     => esc_url_raw( (string) $url ),
			'version' => (string) $version,
		);

		if ( empty( $savedpixel_admin_preview_assets['default']['url'] ) ) {
			$savedpixel_admin_preview_assets['default'] = $asset;
		}

		foreach ( array_filter( array_map( 'sanitize_key', (array) $pages ) ) as $page ) {
			$savedpixel_admin_preview_assets['pages'][ $page ] = $asset;
		}
	}
}

if ( ! function_exists( 'savedpixel_admin_preview_asset' ) ) {
	function savedpixel_admin_preview_asset( $page = '' ) {
		global $savedpixel_admin_preview_assets;

		$assets = is_array( $savedpixel_admin_preview_assets ?? null ) ? $savedpixel_admin_preview_assets : array();
		$page   = '' !== $page ? sanitize_key( (string) $page ) : savedpixel_current_admin_page();

		if ( '' !== $page && ! empty( $assets['pages'][ $page ]['url'] ) ) {
			return $assets['pages'][ $page ];
		}

		return is_array( $assets['default'] ?? null ) ? $assets['default'] : array();
	}
}

if ( ! function_exists( 'savedpixel_admin_enqueue_preview_style' ) ) {
	function savedpixel_admin_enqueue_preview_style( $page = '' ) {
		$asset = savedpixel_admin_preview_asset( $page );

		if ( empty( $asset['url'] ) ) {
			return;
		}

		wp_enqueue_style(
			'savedpixel-admin-preview',
			$asset['url'],
			array(),
			'' !== $asset['version'] ? $asset['version'] : null
		);
	}
}

if ( ! function_exists( 'savedpixel_admin_enqueue_hub_style' ) ) {
	function savedpixel_admin_enqueue_hub_style() {
		$page = savedpixel_current_admin_page();

		if ( ! current_user_can( 'manage_options' ) || savedpixel_admin_parent_slug() !== $page ) {
			return;
		}

		savedpixel_admin_enqueue_preview_style( $page );
	}

	add_action( 'admin_enqueue_scripts', 'savedpixel_admin_enqueue_hub_style' );
}

if ( ! function_exists( 'savedpixel_admin_pages_config' ) ) {
	function savedpixel_admin_pages_config() {
		return array(
			'savedpixel-hidden-access'   => array(
				'title'           => 'Hidden Access',
				'overview_copy'   => 'Change the login slug and reduce direct unauthenticated access to core login endpoints.',
			),
			'savedpixel-magic-login'     => array(
				'title'           => 'Magic Login',
				'overview_copy'   => 'Generate a single-step admin login URL for a chosen user and manage regeneration safely.',
			),
			'savedpixel-activity-tracker' => array(
				'title'           => 'Activity Tracker',
				'overview_copy'   => 'Review high-privilege activity history, filter audit events, and manage tracker guard controls from one place.',
			),
			'savedpixel-update-disabler' => array(
				'title'           => 'Update Disabler',
				'overview_copy'   => 'Control WordPress updates, outbound requests, and mail behavior for staging or locked-down installs.',
			),
			'savedpixel-reset-selective-clear' => array(
				'title'           => 'Selective Clear',
				'overview_copy'   => 'Clear selected content, plugin data, upload folders, and activation rules for destructive test resets.',
			),
			'savedpixel-seo-shield' => array(
				'title'           => 'SEO Shield',
				'overview_copy'   => 'Block junk search traffic, harden crawl-facing endpoints, and manage SEO protection rules from one place.',
			),
			'savedpixel-remote-backup-monitor' => array(
				'title'           => 'Backup Monitor',
				'overview_copy'   => 'Track site status, poll remote installs, and review pulled backup history from one place.',
			),
			'savedpixel-remote-backup'   => array(
				'title'           => 'Remote Backup',
				'overview_copy'   => 'Run manual backups, configure schedules, and manage remote storage plus pull access.',
			),
			'savedpixel-admin-debug-scan' => array(
				'title'           => 'Performance Scan',
				'overview_copy'   => 'Review scan scores, key metrics, and session summaries in the admin preview.',
			),
			'savedpixel-admin-debug'     => array(
				'title'           => 'Sessions',
				'overview_copy'   => 'Browse recorded tracking sessions and review captured performance data.',
			),
			'savedpixel-admin-debug-report' => array(
				'title'           => 'Session Report',
				'overview_copy'   => 'Inspect a single session report with lifecycle metrics, event panels, and recommendations.',
			),
		);
	}
}

if ( ! function_exists( 'savedpixel_admin_available_pages' ) ) {
	function savedpixel_admin_available_pages( $include_context = array() ) {
		global $submenu;

		$config          = savedpixel_admin_pages_config();
		$registered      = array();
		$include_context = array_fill_keys( array_map( 'sanitize_key', (array) $include_context ), true );

		foreach ( $submenu[ savedpixel_admin_parent_slug() ] ?? array() as $item ) {
			$slug = sanitize_key( (string) ( $item[2] ?? '' ) );
			if ( '' !== $slug ) {
				$registered[ $slug ] = true;
			}
		}

		$pages = array();
		foreach ( $config as $slug => $page ) {
			if ( 'savedpixel-admin-debug-report' === $slug && empty( $include_context[ $slug ] ) ) {
				continue;
			}

			if ( ! empty( $registered[ $slug ] ) || ! empty( $include_context[ $slug ] ) ) {
				$pages[ $slug ] = $page;
			}
		}

		return $pages;
	}
}

if ( ! function_exists( 'savedpixel_admin_toolbar_html' ) ) {
	function savedpixel_admin_toolbar_html( $active_slug ) {
		unset( $active_slug );

		return '';
	}
}

if ( ! function_exists( 'savedpixel_admin_page_classes' ) ) {
	function savedpixel_admin_page_classes( $extra_classes = array() ) {
		$classes = array( 'sp-page' );

		foreach ( (array) $extra_classes as $class ) {
			$class = sanitize_html_class( (string) $class );
			if ( '' !== $class && ! in_array( $class, $classes, true ) ) {
				$classes[] = $class;
			}
		}

		return implode( ' ', $classes );
	}
}

if ( ! function_exists( 'savedpixel_admin_page_start' ) ) {
	function savedpixel_admin_page_start( $page_id = '', $extra_classes = array() ) {
		$page_id = sanitize_html_class( (string) $page_id );
		?>
		<main class="sp-shell">
			<div class="wrap sp-wrap">
				<div<?php echo '' !== $page_id ? ' id="' . esc_attr( $page_id ) . '"' : ''; ?> class="<?php echo esc_attr( savedpixel_admin_page_classes( $extra_classes ) ); ?>">
		<?php
	}
}

if ( ! function_exists( 'savedpixel_admin_page_end' ) ) {
	function savedpixel_admin_page_end() {
		?>
				</div>
			</div>
		</main>
		<?php
	}
}

if ( ! function_exists( 'savedpixel_render_admin_hub' ) ) {
	function savedpixel_render_admin_hub() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'savedpixel-update-disabler' ) );
		}

		$cards = savedpixel_admin_available_pages();
		savedpixel_admin_page_start();
		?>
				<header class="sp-page-header">
					<div>
						<h1 class="sp-page-title">SavedPixel</h1>
						<p class="sp-page-desc">Open an active SavedPixel tool below.</p>
					</div>
				</header>

				<div class="sp-note">
					<p>Use the cards below to open an active SavedPixel tool.</p>
				</div>

				<div class="sp-link-grid">
					<?php foreach ( $cards as $slug => $page ) : ?>
						<a class="sp-link-card" href="<?php echo esc_url( savedpixel_admin_page_url( $slug ) ); ?>">
							<strong><?php echo esc_html( $page['title'] ); ?></strong>
							<span><?php echo esc_html( $page['overview_copy'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
		<?php
		savedpixel_admin_page_end();
	}
}

if ( ! function_exists( 'savedpixel_register_admin_hub' ) ) {
	function savedpixel_register_admin_hub() {
		add_menu_page(
			'SavedPixel',
			'SavedPixel',
			'manage_options',
			savedpixel_admin_parent_slug(),
			'savedpixel_render_admin_hub',
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iODk1LjUwMDAwMHB0IiBoZWlnaHQ9IjEwMjQuMDAwMDAwcHQiIHZpZXdCb3g9IjAgMCA4OTUuNTAwMDAwIDEwMjQuMDAwMDAwIgogcHJlc2VydmVBc3BlY3RSYXRpbz0ieE1pZFlNaWQgbWVldCI+CjxtZXRhZGF0YT4KQ3JlYXRlZCBieSBwb3RyYWNlIDEuMTYsIHdyaXR0ZW4gYnkgUGV0ZXIgU2VsaW5nZXIgMjAwMS0yMDE5CjwvbWV0YWRhdGE+CjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0wLjUwMDAwMCwxMDI0LjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTQwMDIgOTk5MyBsMyAtMjQ4IDE0MyAwIDE0MiAxIDAgLTIzOCAwIC0yMzggLTEwNTcgLTIgLTEwNTggLTMgLTEKLTE5MCAtMSAtMTkwIC0yMDQgMCAtMjA0IDAgLTUgLTIwMCAtNSAtMjAwIC0yMDIgLTMgLTIwMiAtMiAwIC00ODggMCAtNDg3Ci0yMzIgLTMgLTIzMiAtMiA2IC0xOTAgNyAtMTkwIC0yMjAgMCAtMjIwIDAgMyAtNTgyIDIgLTU4MyAyMTUgLTEgMjE1IDAgMAotMjAwIDAgLTE5OSAyMjkgLTEgMjI4IDAgLTEgLTQ5NyAtMiAtNDk3IDIwMyAtMiAyMDMgLTMgLTQgLTIwMyAtMyAtMjAyIDIxMAowIDIxMCAwIDggLTc3IGM0IC00MyA0IC0xMjggMSAtMTkwIGwtNSAtMTEzIDc3NCAwIDc3NCAwIDAgLTIyMCAwIC0yMjAgNzY1IDAKNzY1IDAgMCAyMjAgMCAyMjAgNzY5IDAgNzY4IDAgNyA0NiBjMyAyNiA2IDExMSA2IDE5MCBsMCAxNDQgMjA2IDIgMjA2IDMgMgoyMDAgMSAyMDAgMjAwIDUgMjAwIDUgMCA0OTIgMCA0OTMgMjMwIDIgMjMwIDMgLTQgMTk3IC00IDE5OCAyMTkgMiAyMTkgMyAtMQo1ODAgLTIgNTgwIC0yMTYgMyAtMjE2IDIgMCAxOTAgMCAxOTAgLTIyOCAwIC0yMjcgMCAzIDQ4MCBjMyAzNzEgMSA0ODQgLTgKNDk1IC0xMCAxMiAtNDcgMTUgLTIwNiAxNSBsLTE5NCAwIDAgMTk1IDAgMTk1IC0yMDcgMiAtMjA4IDMgNCAxOTMgNCAxOTIKLTEwNjcgMCAtMTA2NiAwIDAgMjM1IDAgMjM1IDE0NSAwIDE0NSAwIDAgMjUwIDAgMjUwIC00ODAgMCAtNDgwIDAgMiAtMjQ3egptMjc4MyAtMTMxOCBsMCAtMjAwIDIxMCAwIDIxMCAwIDAgLTE5NTUgMCAtMTk1NSAtMjEyIC0zIC0yMTMgLTIgMCAtMTk1IDAKLTE5NSAtMjI5NSAwIC0yMjk1IDAgMCAxOTUgMCAxOTUgLTIxNSAwIC0yMTUgMCAwIDE5NTUgMCAxOTU1IDE0OCAwIGM4MSAwCjE3NyAzIDIxNCA3IGw2OCA2IDAgMTkzIDAgMTk0IDIyNzggMyBjMTI1MiAxIDIyODYgMiAyMjk3IDIgMTkgMCAyMCAtNyAyMAotMjAweiIvPgo8cGF0aCBkPSJNMjc4MCA3NTE1IGwwIC0xOTUgLTE4NCAwIGMtMTAxIDAgLTE5MSAtMyAtMjAwIC02IC0xNCAtNSAtMTYgLTQ1Ci0xNiAtMzM5IDAgLTI1OCAzIC0zMzQgMTMgLTMzOCA2IC0yIDk2IC0yIDIwMCAxIGwxODcgNSAwIC0yMDEgMCAtMjAyIDMyMCAwCjMyMCAwIDAgMjAwIDAgMjAwIDIwNSAwIDIwNSAwIC0yIDMzOCAtMyAzMzcgLTIwMiAzIC0yMDMgMiAwIDE5NSAwIDE5NSAtMzIwCjAgLTMyMCAwIDAgLTE5NXogbTYzMyAtMjI0IGMxMSAtMTEgNSAtNjI3IC02IC02MzggLTUgLTQgLTE0NCAtOCAtMzEwIC04CmwtMzAyIDAgLTMgMzIwIGMtMSAxNzYgMCAzMjYgMyAzMzMgNCAxMCA2NiAxMSAzMDcgNiAxNjcgLTMgMzA3IC05IDMxMSAtMTN6Ii8+CjxwYXRoIGQ9Ik01NTU4IDc1MTggbC0zIC0xOTMgLTIwMiAtMyAtMjAzIC0yIDAgLTM0MCAwIC0zNDAgMjAzIC0yIDIwMiAtMyAzCi0xOTcgMiAtMTk4IDMyMCAwIDMyMCAwIDIgMTk4IDMgMTk3IDE5NSAxIDE5NSAyIDAgMzM4IDAgMzM5IC0xOTcgMyAtMTk4IDIgMAoxOTUgMCAxOTUgLTMyMCAwIC0zMjAgMCAtMiAtMTkyeiBtNjMwIC01NDUgbC0zIC0zMjggLTMxMiAtMyAtMzEzIC0yIDAgMzMwIDAKMzMwIDMxNSAwIDMxNSAwIC0yIC0zMjd6Ii8+CjxwYXRoIGQ9Ik0zMjU0IDU3MTUgYy0xMSAtMjkgNyAtMTU5IDMxIC0yMTYgMzEgLTc2IDY5IC0xMzEgMTUyIC0yMTUgMTAxCi0xMDIgMTkzIC0xNjcgMzM4IC0yMzkgMjMxIC0xMTQgNDI0IC0xNTQgNzQ0IC0xNTUgMjI1IDAgMzUyIDE3IDUwNSA2OSAyNzkKOTUgNDY3IDIyOSA2MDAgNDMwIDYzIDk0IDg3IDE1OCA5NSAyNTQgbDcgNzcgLTE4MiAwIC0xODEgMCAtNiAtNTQgYy0xNCAtMTM3Ci0xNTQgLTI2NyAtMzYyIC0zMzcgLTE0NCAtNDggLTE5NyAtNTQgLTUxNSAtNTQgLTI3MiAwIC0zMDIgMiAtMzgzIDIyIC0xNTgKNDEgLTI5NiAxMTEgLTM4NSAxOTcgLTYzIDYwIC04NCAxMDAgLTkxIDE3MCBsLTYgNjEgLTE3OCAzIGMtMTU0IDIgLTE3OCAwCi0xODMgLTEzeiIvPgo8cGF0aCBkPSJNMjQ5MSAzMzQzIGwtNzM0IC0zIDUgLTE3NiA1IC0xNzcgLTIxNiA0IC0yMTYgNCA0IC0yMTIgNCAtMjEzIC0yMjgKMCAtMjI3IDAgMyAtMjA3IDQgLTIwOCAtMjIyIC0zIC0yMjMgLTIgMCAtMjEzIDAgLTIxMiAtMjIyIDMgLTIyMyAzIDAgLTg2NSAwCi04NjYgNjYxIDAgNjYxIDAgNyA3NzMgYzQgNDI1IDQgODE0IDAgODY1IGwtNyA5MSAyMTcgMyAyMTYgMyAwIC04NjcgMCAtODY4CjI3MTggMCAyNzE5IDAgNiAyOTcgYzQgMTY0IDQgNTUzIDAgODY1IGwtNiA1NjggMjE2IDAgMjE3IDAgMCAtODY1IDAgLTg2NQo2NjUgMCA2NjUgMCAwIDg2NiAwIDg2NyAtMTg1IC03IGMtMTAxIC00IC0yMDAgLTQgLTIyMCAwIGwtMzUgNiAwIDIwOSAwIDIwOQotMjI2IDAgLTIyNiAwIDQgMjEwIDQgMjEwIC0yMTkgMiAtMjIwIDMgLTEgMjEwIC0xIDIxMCAtMjE3IC00IC0yMTggLTQgMCAxNzcKMCAxNzYgLTcyNyAwIGMtNDAxIDAgLTc1MiAzIC03ODAgNiBsLTUzIDcgMCAtMjEyIDAgLTIxMSAtNjk3IDAgYy0zODQgMCAtOTAyCi0yIC0xMTUxIC0zIC0zNTQgLTMgLTQ1NSAtMSAtNDU5IDkgLTIgNyAtNSAxMDEgLTYgMjExIGwtMiAxOTggLTUwIDAgYy0yNyAwCi0zODAgLTEgLTc4NCAtMnoiLz4KPC9nPgo8L3N2Zz4K',
			58
		);
	}

	add_action( 'admin_menu', 'savedpixel_register_admin_hub', 8 );
}

if ( ! function_exists( 'savedpixel_remove_admin_hub_submenu' ) ) {
	function savedpixel_remove_admin_hub_submenu() {
		remove_submenu_page( savedpixel_admin_parent_slug(), savedpixel_admin_parent_slug() );
	}

	add_action( 'admin_menu', 'savedpixel_remove_admin_hub_submenu', 999 );
}
