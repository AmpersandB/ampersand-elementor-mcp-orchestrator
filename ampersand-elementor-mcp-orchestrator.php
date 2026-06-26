<?php
/**
 * Plugin Name: Ampersand Elementor MCP Orchestrator
 * Description: Orchestrates Elementor MCP abilities, exposes editor-first guardrails, and provides an admin prompt/settings page.
 * Version: 1.5.0
 * Author: Ampersand Studios
 * License: GPL-2.0-or-later
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Update URI: https://github.com/AmpersandB/ampersand-elementor-mcp-orchestrator
 *
 * @package Ampersand_Elementor_MCP_Orchestrator
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION', '1.5.0' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_OPTION', 'ampersand_elementor_mcp_orchestrator_settings' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_INSTANCE_OPTION', 'ampersand_elementor_mcp_orchestrator_instance_id' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_CACHE_SALT_OPTION', 'ampersand_elementor_mcp_orchestrator_tool_cache_salt' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_APP_PASSWORD_NAME', 'Ampersand Elementor MCP' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_ACTION', 'ampersand_elementor_mcp_orchestrator_download_config' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_NONCE', 'ampersand_elementor_mcp_orchestrator_download_config' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_WARNING_THRESHOLD', 80 );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG', basename( __DIR__ ) );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME', basename( __DIR__ ) . '/' . basename( __FILE__ ) );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY', 'AmpersandB/ampersand-elementor-mcp-orchestrator' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL', 'https://github.com/' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_LATEST_RELEASE_URL', 'https://api.github.com/repos/' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY . '/releases/latest' );
define( 'AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_RELEASE_CACHE_KEY', 'ampersand_elementor_mcp_orchestrator_github_release' );

/**
 * Fetch latest public GitHub release metadata.
 *
 * @param bool $force_refresh Whether to bypass the transient cache.
 * @return array<string, mixed>|null
 */
function ampersand_elementor_mcp_orchestrator_get_github_release( bool $force_refresh = false ): ?array {
	$cached = get_site_transient( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_RELEASE_CACHE_KEY );

	if ( ! $force_refresh && is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_LATEST_RELEASE_URL,
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'AmpersandElementorMCP/' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION,
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return null;
	}

	set_site_transient( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_RELEASE_CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Convert a GitHub release tag into a semantic version.
 *
 * @param array<string, mixed> $release GitHub release payload.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_release_version( array $release ): string {
	$version = ltrim( (string) $release['tag_name'], 'vV' );

	return preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version ) ? $version : '';
}

/**
 * Return the preferred ZIP URL for a GitHub release.
 *
 * @param array<string, mixed> $release GitHub release payload.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_release_package_url( array $release ): string {
	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}

			$name = isset( $asset['name'] ) ? strtolower( (string) $asset['name'] ) : '';

			if ( str_ends_with( $name, '.zip' ) ) {
				return (string) $asset['browser_download_url'];
			}
		}
	}

	return isset( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
}

/**
 * Return true when a package URL is hosted by GitHub.
 *
 * @param string $package Package URL.
 * @return bool
 */
function ampersand_elementor_mcp_orchestrator_is_trusted_package_url( string $package ): bool {
	$host = wp_parse_url( $package, PHP_URL_HOST );

	return is_string( $host ) && in_array(
		strtolower( $host ),
		array(
			'api.github.com',
			'github.com',
			'codeload.github.com',
			'objects.githubusercontent.com',
		),
		true
	);
}

/**
 * Build the WordPress plugin update object.
 *
 * @param array<string, mixed> $release GitHub release payload.
 * @return object|null
 */
function ampersand_elementor_mcp_orchestrator_update_object( array $release ): ?object {
	$version = ampersand_elementor_mcp_orchestrator_release_version( $release );
	$package = ampersand_elementor_mcp_orchestrator_release_package_url( $release );

	if ( '' === $version || '' === $package || ! ampersand_elementor_mcp_orchestrator_is_trusted_package_url( $package ) || ! version_compare( $version, AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION, '>' ) ) {
		return null;
	}

	return (object) array(
		'id'            => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL,
		'slug'          => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG,
		'plugin'        => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME,
		'new_version'   => $version,
		'url'           => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL,
		'package'       => $package,
		'requires'      => '6.8',
		'requires_php'  => '8.0',
		'tested'        => get_bloginfo( 'version' ),
		'upgrade_notice' => isset( $release['name'] ) ? (string) $release['name'] : '',
	);
}

/**
 * Register GitHub release updates with WordPress.
 *
 * @param object $transient Update transient.
 * @return object
 */
function ampersand_elementor_mcp_orchestrator_check_for_update( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$release = ampersand_elementor_mcp_orchestrator_get_github_release();

	if ( ! $release ) {
		return $transient;
	}

	$update = ampersand_elementor_mcp_orchestrator_update_object( $release );

	if ( $update ) {
		$transient->response[ AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME ] = $update;
	} else {
		$transient->no_update[ AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME ] = (object) array(
			'id'          => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL,
			'slug'        => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG,
			'plugin'      => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME,
			'new_version' => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION,
			'url'         => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL,
			'package'     => '',
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'ampersand_elementor_mcp_orchestrator_check_for_update' );

/**
 * Show release details in the plugin update modal.
 *
 * @param mixed  $result Existing result.
 * @param string $action Plugin API action.
 * @param object $args Request args.
 * @return mixed
 */
function ampersand_elementor_mcp_orchestrator_plugins_api( $result, string $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG !== ( $args->slug ?? '' ) ) {
		return $result;
	}

	$release = ampersand_elementor_mcp_orchestrator_get_github_release();

	if ( ! $release ) {
		return $result;
	}

	$version = ampersand_elementor_mcp_orchestrator_release_version( $release );
	$package = ampersand_elementor_mcp_orchestrator_release_package_url( $release );
	$body    = isset( $release['body'] ) && '' !== trim( (string) $release['body'] ) ? (string) $release['body'] : 'See the GitHub release for details.';

	return (object) array(
		'name'          => 'Ampersand Elementor MCP Orchestrator',
		'slug'          => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG,
		'version'       => $version,
		'author'        => 'Ampersand Studios',
		'homepage'      => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_GITHUB_REPOSITORY_URL,
		'requires'      => '6.8',
		'requires_php'  => '8.0',
		'tested'        => get_bloginfo( 'version' ),
		'download_link' => $package,
		'sections'      => array(
			'description' => 'Orchestrates Elementor MCP abilities and editor-first guardrails.',
			'changelog'   => wp_kses_post( wpautop( $body ) ),
		),
	);
}
add_filter( 'plugins_api', 'ampersand_elementor_mcp_orchestrator_plugins_api', 20, 3 );

/**
 * Rename GitHub zipball folders so WordPress updates this plugin in place.
 *
 * @param string|\WP_Error $source Remote source path.
 * @param string           $remote_source Parent temp path.
 * @param object $upgrader Upgrader instance.
 * @param array<string, mixed> $hook_extra Update context.
 * @return string|\WP_Error
 */
function ampersand_elementor_mcp_orchestrator_fix_update_source_folder( $source, string $remote_source, $upgrader, array $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
		return $source;
	}

	if ( is_wp_error( $source ) || ! is_string( $source ) ) {
		return $source;
	}

	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		return $source;
	}

	$desired_source = trailingslashit( $remote_source ) . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_PLUGIN_SLUG;

	if ( trailingslashit( $source ) === trailingslashit( $desired_source ) ) {
		return $source;
	}

	if ( $wp_filesystem->exists( $desired_source ) ) {
		$wp_filesystem->delete( $desired_source, true );
	}

	if ( ! $wp_filesystem->move( $source, $desired_source, true ) ) {
		return new WP_Error( 'ampersand_elementor_mcp_orchestrator_update_rename_failed', 'Could not prepare the GitHub release folder for plugin update.' );
	}

	return $desired_source;
}
add_filter( 'upgrader_source_selection', 'ampersand_elementor_mcp_orchestrator_fix_update_source_folder', 10, 4 );

/**
 * Return MCP tool groups exposed by the orchestrator.
 *
 * @return array<string, array<string, mixed>>
 */
function ampersand_elementor_mcp_orchestrator_tool_groups(): array {
	return array(
		'core'             => array(
			'setting'     => 'enable_core_tools',
			'label'       => 'Curated core tools',
			'description' => 'Default editor-first Elementor workflow tools. Keep this on for normal page building.',
			'default'     => true,
			'abilities'   => array(
				'elementor-mcp/list-pages',
				'elementor-mcp/list-templates',
				'elementor-mcp/list-widgets',
				'elementor-mcp/list-media',
				'elementor-mcp/get-page-structure',
				'elementor-mcp/get-element-settings',
				'elementor-mcp/get-widget-schema',
				'elementor-mcp/get-global-settings',
				'elementor-mcp/detect-elementor-version',
				'elementor/find-elements',
				'elementor/get-theme-builder-conditions',
				'elementor-mcp/add-container',
				'elementor-mcp/add-heading',
				'elementor-mcp/add-text-editor',
				'elementor-mcp/add-button',
				'elementor-mcp/add-image',
				'elementor-mcp/add-gallery',
				'elementor-mcp/add-icon',
				'elementor-mcp/add-icon-box',
				'elementor-mcp/add-icon-list',
				'elementor-mcp/add-image-box',
				'elementor-mcp/add-image-carousel',
				'elementor-mcp/add-spacer',
				'elementor-mcp/add-divider',
				'elementor-mcp/add-video',
				'elementor-mcp/add-google-maps',
				'elementor-mcp/add-social-icons',
				'elementor-mcp/add-html',
				'elementor-mcp/add-shortcode',
				'elementor-mcp/add-star-rating',
				'elementor-mcp/add-tabs',
				'elementor-mcp/add-accordion',
				'elementor-mcp/add-toggle',
				'elementor-mcp/add-slides',
				'elementor-mcp/add-testimonial',
				'elementor-mcp/add-testimonial-carousel',
				'elementor-mcp/add-call-to-action',
				'elementor-mcp/add-counter',
				'elementor-mcp/add-nav-menu',
				'elementor-mcp/add-search',
				'elementor-mcp/add-form',
				'elementor-mcp/add-loop-grid',
				'elementor-mcp/add-posts-grid',
				'elementor-mcp/set-dynamic-tag',
				'elementor-mcp/update-element',
				'elementor-mcp/update-widget',
				'elementor-mcp/update-container',
				'elementor-mcp/move-element',
				'elementor-mcp/remove-element',
				'elementor-mcp/duplicate-element',
				'elementor-mcp/reorder-elements',
				'elementor-mcp/batch-update',
				'elementor-mcp/create-page',
				'elementor-mcp/build-page',
				'elementor-mcp/apply-template',
				'elementor-mcp/save-as-template',
				'elementor-mcp/create-popup',
				'elementor-mcp/set-template-conditions',
				'elementor-mcp/update-global-colors',
				'elementor-mcp/update-global-typography',
				'elementor-mcp/update-page-settings',
				'elementor/get-kit-settings',
				'elementor/update-kit-settings',
				'elementor-mcp/search-images',
				'elementor-mcp/sideload-image',
				'elementor-mcp/upload-svg-icon',
				'elementor/clear-cache',
				'elementor/replace-urls',
			),
		),
		'woocommerce'      => array(
			'setting'     => 'enable_group_woocommerce',
			'label'       => 'WooCommerce widgets',
			'description' => 'Product, cart, checkout, and menu cart widgets.',
			'default'     => false,
			'abilities'   => array(
				'elementor-mcp/add-wc-products',
				'elementor-mcp/add-wc-add-to-cart',
				'elementor-mcp/add-wc-cart',
				'elementor-mcp/add-wc-checkout',
				'elementor-mcp/add-wc-menu-cart',
			),
		),
		'atomic_v4'        => array(
			'setting'     => 'enable_group_atomic_v4',
			'label'       => 'Elementor v4 atomic widgets',
			'description' => 'Atomic/flexbox widgets for sites intentionally using Elementor v4 patterns.',
			'default'     => false,
			'abilities'   => array(
				'elementor-mcp/add-flexbox',
				'elementor-mcp/add-div-block',
				'elementor-mcp/add-atomic-heading',
				'elementor-mcp/add-atomic-paragraph',
				'elementor-mcp/add-atomic-button',
				'elementor-mcp/add-atomic-image',
				'elementor-mcp/add-atomic-svg',
				'elementor-mcp/add-atomic-video',
				'elementor-mcp/add-atomic-youtube',
				'elementor-mcp/add-atomic-divider',
				'elementor-mcp/add-atomic-widget',
				'elementor-mcp/update-atomic-widget',
			),
		),
		'extended_widgets' => array(
			'setting'     => 'enable_group_extended_widgets',
			'label'       => 'Extended widgets',
			'description' => 'Niche or decorative widgets that are useful only on some projects.',
			'default'     => false,
			'abilities'   => array(
				'elementor-mcp/add-flip-box',
				'elementor-mcp/add-hotspot',
				'elementor-mcp/add-countdown',
				'elementor-mcp/add-animated-headline',
				'elementor-mcp/add-text-path',
				'elementor-mcp/add-lottie',
				'elementor-mcp/add-progress',
				'elementor-mcp/add-progress-tracker',
				'elementor-mcp/add-rating',
				'elementor-mcp/add-blockquote',
				'elementor-mcp/add-alert',
				'elementor-mcp/add-author-box',
				'elementor-mcp/add-price-list',
				'elementor-mcp/add-price-table',
				'elementor-mcp/add-share-buttons',
				'elementor-mcp/add-reviews',
				'elementor-mcp/add-portfolio',
				'elementor-mcp/add-table-of-contents',
				'elementor-mcp/add-menu-anchor',
				'elementor-mcp/add-off-canvas',
				'elementor-mcp/add-nested-accordion',
				'elementor-mcp/add-nested-tabs',
				'elementor-mcp/add-media-carousel',
				'elementor-mcp/add-login',
				'elementor-mcp/add-code-highlight',
			),
		),
		'code_injection'   => array(
			'setting'     => 'enable_group_code_injection',
			'label'       => 'Code injection tools',
			'description' => 'Security-sensitive PHP snippet and custom code CRUD tools. Enable only for trusted operators.',
			'default'     => false,
			'abilities'   => array(
				'elementor-mcp/create-php-snippet',
				'elementor-mcp/get-php-snippet',
				'elementor-mcp/update-php-snippet',
				'elementor-mcp/delete-php-snippet',
				'elementor-mcp/validate-php-snippet',
				'elementor-mcp/list-php-snippets',
				'elementor/create-custom-code',
				'elementor/get-custom-code',
				'elementor/list-custom-code',
				'elementor/update-custom-code',
				'elementor/delete-custom-code',
			),
		),
		'design_qa'        => array(
			'setting'     => 'enable_group_design_qa',
			'label'       => 'Design QA tools',
			'description' => 'Style guide, design evaluation, token extraction, and official widget/pattern guidance tools.',
			'default'     => false,
			'abilities'   => array(
				'elementor/get-style-guide',
				'elementor/evaluate-design',
				'elementor/suggest-design-fixes',
				'elementor/extract-design-tokens',
				'elementor/get-official-widget-catalog',
				'elementor/get-official-pattern-guidance',
			),
		),
		'form_submissions' => array(
			'setting'     => 'enable_group_form_submissions',
			'label'       => 'Form submissions',
			'description' => 'Read and delete Elementor form submissions.',
			'default'     => false,
			'abilities'   => array(
				'elementor/list-form-submissions',
				'elementor/get-form-submission',
				'elementor/delete-form-submission',
			),
		),
		'site_maintenance' => array(
			'setting'     => 'enable_group_site_maintenance',
			'label'       => 'Site maintenance',
			'description' => 'Maintenance mode, experiments, trash, and active kit operations.',
			'default'     => false,
			'abilities'   => array(
				'elementor/get-maintenance-mode',
				'elementor/update-maintenance-mode',
				'elementor/list-experiments',
				'elementor/update-experiment',
				'elementor/empty-trash',
				'elementor/set-active-kit',
			),
		),
	);
}

/**
 * Return default plugin settings.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_default_settings(): array {
	$settings = array(
		'enable_editor_first_guardrails' => true,
	);

	foreach ( ampersand_elementor_mcp_orchestrator_tool_groups() as $group ) {
		$settings[ (string) $group['setting'] ] = (bool) $group['default'];
	}

	return $settings;
}

/**
 * Return setting keys that are rendered as checkboxes.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_checkbox_setting_keys(): array {
	$keys = array();

	foreach ( ampersand_elementor_mcp_orchestrator_tool_groups() as $group ) {
		$keys[ (string) $group['setting'] ] = true;
	}

	return $keys;
}

/**
 * Return sanitized plugin settings.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_get_settings(): array {
	$stored   = get_option( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_OPTION, array() );
	$stored   = is_array( $stored ) ? $stored : array();
	$defaults = ampersand_elementor_mcp_orchestrator_default_settings();
	$settings = $defaults;

	foreach ( $defaults as $key => $default ) {
		if ( array_key_exists( $key, $stored ) ) {
			$settings[ $key ] = rest_sanitize_boolean( $stored[ $key ] );
		}
	}

	return $settings;
}

/**
 * Invalidate cached tool names.
 *
 * @param mixed ...$unused Unused hook arguments.
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_invalidate_tool_cache( ...$unused ): void {
	update_option( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_CACHE_SALT_OPTION, (string) time() . '-' . wp_rand(), false );
}

/**
 * Sanitize settings from the admin page.
 *
 * @param array<string, mixed> $settings Raw settings.
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_sanitize_settings( array $settings ): array {
	$defaults      = ampersand_elementor_mcp_orchestrator_default_settings();
	$checkbox_keys = ampersand_elementor_mcp_orchestrator_checkbox_setting_keys();
	$sanitized     = array();

	foreach ( $defaults as $key => $default ) {
		if ( isset( $checkbox_keys[ $key ] ) ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? rest_sanitize_boolean( $settings[ $key ] ) : false;
			continue;
		}

		$sanitized[ $key ] = isset( $settings[ $key ] ) ? rest_sanitize_boolean( $settings[ $key ] ) : $default;
	}

	ampersand_elementor_mcp_orchestrator_invalidate_tool_cache();

	return $sanitized;
}

/**
 * Register settings.
 *
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_register_settings(): void {
	register_setting(
		'ampersand_elementor_mcp_orchestrator',
		AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ampersand_elementor_mcp_orchestrator_sanitize_settings',
			'default'           => ampersand_elementor_mcp_orchestrator_default_settings(),
		)
	);
}
add_action( 'admin_init', 'ampersand_elementor_mcp_orchestrator_register_settings' );
add_action( 'admin_post_' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_ACTION, 'ampersand_elementor_mcp_orchestrator_handle_config_download' );
add_action( 'activated_plugin', 'ampersand_elementor_mcp_orchestrator_invalidate_tool_cache' );
add_action( 'deactivated_plugin', 'ampersand_elementor_mcp_orchestrator_invalidate_tool_cache' );
add_action( 'upgrader_process_complete', 'ampersand_elementor_mcp_orchestrator_invalidate_tool_cache' );

/**
 * Return active plugin map.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_plugin_status(): array {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$emcp_tools_active     = is_plugin_active( 'emcp-tools/emcp-tools.php' );
	$legacy_emcp_active   = is_plugin_active( 'elementor-mcp/elementor-mcp.php' );
	$msr_abilities_active = ampersand_elementor_mcp_orchestrator_has_ability_prefix( 'elementor-mcp/' );

	return array(
		'Elementor'                                      => is_plugin_active( 'elementor/elementor.php' ),
		'Elementor Pro'                                  => is_plugin_active( 'elementor-pro/elementor-pro.php' ) || is_plugin_active( 'pro-elements/pro-elements.php' ),
		'Official MCP Adapter'                           => class_exists( '\WP\MCP\Core\McpAdapter' ),
		'WordPress Abilities API'                        => function_exists( 'wp_get_abilities' ),
		'MCP Adapter HTTP Transport'                     => class_exists( '\WP\MCP\Transport\HttpTransport' ),
		'MCP Abilities - Elementor'                      => ampersand_elementor_mcp_orchestrator_has_ability_prefix( 'elementor/' ),
		'EMCP Tools / MCP Tools for Elementor abilities' => $emcp_tools_active || $msr_abilities_active,
		'Legacy elementor-mcp folder active'             => $legacy_emcp_active,
		'Ampersand MCP Orchestrator'                     => true,
	);
}

/**
 * Return true when EMCP Tools is paused by the old elementor-mcp plugin.
 *
 * @return bool
 */
function ampersand_elementor_mcp_orchestrator_has_legacy_emcp_conflict(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'emcp-tools/emcp-tools.php' ) && is_plugin_active( 'elementor-mcp/elementor-mcp.php' );
}

/**
 * Check whether a registered ability prefix exists.
 *
 * @param string $prefix Ability prefix.
 * @return bool
 */
function ampersand_elementor_mcp_orchestrator_has_ability_prefix( string $prefix ): bool {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return false;
	}

	foreach ( wp_get_abilities() as $ability ) {
		if ( is_object( $ability ) && method_exists( $ability, 'get_name' ) && 0 === strpos( (string) $ability->get_name(), $prefix ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Return dependency status map for the bridge.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_dependencies(): array {
	return array(
		'abilities_api'  => function_exists( 'wp_get_abilities' ),
		'mcp_adapter'    => class_exists( '\WP\MCP\Core\McpAdapter' ),
		'http_transport' => class_exists( '\WP\MCP\Transport\HttpTransport' ),
	);
}

/**
 * Return the canonical prompt.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_prompt(): string {
	return <<<'PROMPT'
You are working on a WordPress site that uses Elementor. Before doing any Elementor work, read and follow the site's Ampersand Elementor MCP Orchestrator guidance.

Core requirement: build Elementor pages so a non-programmer can edit them later through the WordPress and Elementor graphical UI.

If the user provides an Ampersand Elementor MCP Orchestrator connection JSON and asks you to install or configure this MCP server:

1. Treat the JSON as a client connection bundle, not as WordPress configuration.
2. Choose the config shape that matches the current client:
   - Claude Desktop: use the `claude_desktop` object.
   - Codex: use the `codex.toml` value.
   - Direct HTTP MCP clients: use `direct_http`, or `url` plus `headers` when the client expects manual HTTP setup.
3. Use the client's normal MCP installation/configuration workflow when available.
4. Ask permission before writing local config files, changing MCP settings, or restarting/reloading the client.
5. Preserve existing MCP server entries and merge this server by the generated `server` name.
6. Do not print the Application Password or Authorization header in chat, logs, screenshots, or documentation.
7. After installation, reload the client and test with `initialize` and `tools/list` when the client supports it.

Do not build normal page content, layouts, cards, menus, buttons, forms, typography, or sections as large HTML/CSS/JS blobs inside Elementor HTML widgets. HTML widgets are allowed only as a documented last resort for small embeds, integrations, tracking snippets, or behavior that cannot reasonably be created with native Elementor/WordPress tools.

Use what a skilled human Elementor editor would use:

- Real Elementor containers/sections/columns.
- Heading, Text Editor, Button, Image, Gallery, Icon, Form, Nav Menu, Loop Grid, WooCommerce, and other native widgets.
- Elementor Site Settings, global colors, global fonts, reusable styles, CSS classes, templates, theme builder, and design tokens.
- WordPress menus for navigation.
- WordPress media library for images/backgrounds.
- Elementor Form widgets or the site's chosen form plugin for forms.
- Posts, pages, CPTs, taxonomies, WooCommerce products/categories, ACF fields, loop templates, or reusable Elementor templates for repeated content.

Before writing or changing Elementor data:

1. Identify the visible URL, WordPress object ID, post type, slug, edit URL, and Elementor document ID.
2. Determine what actually controls the rendered URL before editing: page content, single template, archive template, loop item, header, footer, popup, or global widget.
3. Read Theme Builder conditions before writing. If a shared single/archive/template controls the visible layout, pause and ask whether to edit the shared template or create a page-specific change.
4. Inspect Elementor Site Settings / kit settings, including global colors, global fonts, spacing defaults, container padding, gaps, width, flex/grid behavior, and existing responsive rules.
5. Inspect existing templates, menus, reusable components, and relevant page patterns.
6. Reuse existing global styles and components when possible.
7. If missing, create clear reusable global colors/fonts/templates/components and document them.
8. Do not assume Elementor defaults are neutral. Padding, gap, flex sizing, grid columns, background rendering, and cached CSS can change the final output.

When source material is a PDF, menu, spreadsheet, or external document:

- Treat the source document as the content source of truth.
- Extract the content first, then verify columns, prices, symbols, accents, and visual reading order against a preview or rendered source.
- Preserve Unicode text exactly, including words such as purée, jalapeño, Piña, Buddha’s, Pirate’s, Rosé, and d’Arenberg.
- For menus, preserve the existing Elementor-native layout pattern. Shared prices belong once in the category/group Heading; variable prices stay with the individual item, variant, size, preparation, or add-on.
- Keep menu sections in real Heading and Text Editor widgets so restaurant staff can edit text visually.
- Remove, reorder, or add visible menu content only when the source document requires it.

For multi-section page creation or redesign:

- Build one section at a time.
- After each section, pause and ask for review before continuing.
- Report the section name, page/template ID, widgets/templates used, global tokens used or created, and anything that affects future manual editing.
- Continue only after approval, requested changes, or explicit permission to proceed without review gates.

Tool routing:

- Use `elementor-mcp/...` tools for construction: creating pages, adding containers, widgets, buttons, forms, nav menus, loop grids, templates, popups, and broad layout assembly. In newer installs this may be provided by EMCP Tools (`emcp-tools`) even though the ability namespace remains `elementor-mcp/...`.
- Use `elementor/...` tools for precision: inspecting data, patching existing elements, replacing URLs, clearing cache, design audits, repairing Elementor JSON, kit settings, and theme builder conditions.
- Prefer the smallest safe change.
- Read current state immediately before writing.
- Verify immediately after writing.
- For large imports or content syncs, make a backup first, prefer dry-run/validation if the tool supports it, clear Elementor cache, and verify the rendered output.
- Treat "success but no matches/no changes" as a no-op. Report it as unchanged and retry with a structured element ID/settings path approach when available.
- Avoid fragile raw JSON string matching for rich text when a parsed Elementor data path or element ID can be targeted.

Completion checklist:

- Text is editable in Elementor text/heading/button widgets.
- Images/backgrounds are editable through Elementor or media library controls.
- Menus are WordPress menus or Elementor Nav Menu widgets.
- Forms are visual form widgets or the chosen form plugin.
- Repeated content uses reusable structures where practical.
- Global colors/fonts/styles are used or created and documented.
- Any HTML widget is minimal, justified, and does not trap ordinary content.
- A human editor can copy a section/widget to another page without needing to understand code.
- The correct document was edited: page content vs Theme Builder single/archive/loop/header/footer template.
- Elementor default padding/gap/flex/grid behavior was inspected and accounted for.
- Rendered CSS/output was verified after cache clearing, especially for backgrounds, carousels, grids, and responsive spacing.
PROMPT;
}

/**
 * Return a readable slug for this WordPress site.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_site_slug(): string {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$slug = sanitize_title( str_replace( '.', '-', $host ) );

	return $slug ? $slug : 'site';
}

/**
 * Return a stable per-installation instance ID.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_instance_id(): string {
	$id = get_option( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_INSTANCE_OPTION, '' );

	if ( is_string( $id ) && preg_match( '/^[a-z0-9]{8}$/', $id ) ) {
		return $id;
	}

	$id = strtolower( substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) );
	update_option( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_INSTANCE_OPTION, $id, false );

	return $id;
}

/**
 * Return the unique MCP client config server name.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_config_server_name(): string {
	return sprintf(
		'ampersand-elementor-orchestrator-%s-%s',
		ampersand_elementor_mcp_orchestrator_site_slug(),
		ampersand_elementor_mcp_orchestrator_instance_id()
	);
}

/**
 * Return a safe JSON download filename.
 *
 * @param string $kind Config kind.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_config_filename( string $kind ): string {
	return sanitize_file_name( ampersand_elementor_mcp_orchestrator_config_server_name() . '-' . $kind . '.json' );
}

/**
 * Return the orchestrator endpoint URL.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_endpoint(): string {
	return rest_url( 'mcp/ampersand-elementor-orchestrator' );
}

/**
 * Return the endpoint Claude Desktop should use.
 *
 * Claude Desktop reaches HTTP MCP servers through mcp-remote. In LocalWP,
 * Node often rejects the self-signed HTTPS certificate, so local .local sites
 * are easier to connect over plain HTTP. This is intended for local dev only.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_claude_desktop_endpoint(): string {
	$endpoint = ampersand_elementor_mcp_orchestrator_endpoint();
	$host     = (string) wp_parse_url( $endpoint, PHP_URL_HOST );

	if ( 'local' === wp_get_environment_type() || str_ends_with( $host, '.local' ) ) {
		return preg_replace( '#^https://#', 'http://', $endpoint ) ?: $endpoint;
	}

	return $endpoint;
}

/**
 * Generate a WordPress Application Password for the current user.
 *
 * @return array<string, string>|\WP_Error
 */
function ampersand_elementor_mcp_orchestrator_generate_app_password() {
	if ( ! class_exists( 'WP_Application_Passwords' ) ) {
		return new WP_Error( 'missing_application_passwords', 'WordPress Application Passwords are not available on this site.' );
	}

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return new WP_Error( 'missing_user', 'No current user is available.' );
	}

	if ( function_exists( 'wp_is_application_passwords_available_for_user' ) && ! wp_is_application_passwords_available_for_user( get_userdata( $user_id ) ) ) {
		return new WP_Error( 'application_passwords_disabled', 'Application Passwords are disabled for this user.' );
	}

	$created = WP_Application_Passwords::create_new_application_password(
		$user_id,
		array(
			'name' => AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_APP_PASSWORD_NAME . ' - ' . wp_date( 'Y-m-d H:i:s' ),
		)
	);

	if ( is_wp_error( $created ) ) {
		return $created;
	}

	$password = $created[0];
	$user     = wp_get_current_user();
	$token    = base64_encode( $user->user_login . ':' . $password );

	return array(
		'username'        => $user->user_login,
		'password'        => $password,
		'server_name'     => ampersand_elementor_mcp_orchestrator_config_server_name(),
		'endpoint'        => ampersand_elementor_mcp_orchestrator_endpoint(),
		'claude_endpoint' => ampersand_elementor_mcp_orchestrator_claude_desktop_endpoint(),
		'authorization'   => 'Basic ' . $token,
	);
}

/**
 * Check whether Application Passwords can be generated for the current user.
 *
 * @return bool
 */
function ampersand_elementor_mcp_orchestrator_app_passwords_available(): bool {
	if ( ! class_exists( 'WP_Application_Passwords' ) || ! function_exists( 'wp_is_application_passwords_available' ) || ! function_exists( 'wp_is_application_passwords_available_for_user' ) ) {
		return false;
	}

	if ( ! wp_is_application_passwords_available() ) {
		return false;
	}

	return wp_is_application_passwords_available_for_user( wp_get_current_user() );
}

/**
 * Return the User-Agent sent by generated MCP client configs.
 *
 * Some security layers block REST traffic that does not identify a
 * client. Keeping this explicit makes generated configs more reliable while
 * leaving the server-side policy visible to site owners.
 *
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_client_user_agent(): string {
	return 'Mozilla/5.0 (compatible; AmpersandElementorMCP/' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION . '; WordPress MCP client)';
}

/**
 * Return headers for generated MCP client configs.
 *
 * @param string $authorization Authorization header value.
 * @return array<string, string>
 */
function ampersand_elementor_mcp_orchestrator_client_headers( string $authorization ): array {
	return array(
		'Authorization' => $authorization,
		'User-Agent'    => ampersand_elementor_mcp_orchestrator_client_user_agent(),
	);
}

/**
 * Escape a scalar value for a simple TOML string.
 *
 * @param string $value Raw value.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_toml_string( string $value ): string {
	return str_replace(
		array( '\\', '"' ),
		array( '\\\\', '\"' ),
		$value
	);
}

/**
 * Build a Codex TOML snippet for the generated credential.
 *
 * @param string $server_name Server name.
 * @param string $endpoint Endpoint URL.
 * @param string $authorization Authorization header.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_codex_toml_snippet( string $server_name, string $endpoint, string $authorization ): string {
	$toml  = '[mcp_servers."' . $server_name . '"]' . "\n";
	$toml .= 'url = "' . ampersand_elementor_mcp_orchestrator_toml_string( $endpoint ) . '"' . "\n\n";
	$toml .= '[mcp_servers."' . $server_name . '".headers]' . "\n";

	foreach ( ampersand_elementor_mcp_orchestrator_client_headers( $authorization ) as $header => $value ) {
		$toml .= $header . ' = "' . ampersand_elementor_mcp_orchestrator_toml_string( $value ) . '"' . "\n";
	}

	return $toml;
}

/**
 * Build the single downloaded config bundle.
 *
 * @param array<string, string> $generated Generated credential data.
 * @return array<string, mixed>
 */
function ampersand_elementor_mcp_orchestrator_connection_bundle( array $generated ): array {
	$server_name   = $generated['server_name'];
	$authorization = $generated['authorization'];
	$plugin_status = ampersand_elementor_mcp_orchestrator_plugin_status();
	$tools         = ampersand_elementor_mcp_orchestrator_get_tools();

	return array(
		'server'          => $server_name,
		'site'            => home_url(),
		'url'             => $generated['endpoint'],
		'claude_url'      => $generated['claude_endpoint'],
		'headers'         => ampersand_elementor_mcp_orchestrator_client_headers( $authorization ),
		'generated_at'    => gmdate( 'c' ),
		'notes'           => array(
			'This file contains a WordPress Application Password. Store it securely.',
			'The password is shown only once by WordPress and can be revoked from Users -> Profile -> Application Passwords.',
			'Use the claude_desktop object for Claude Desktop.',
			'Use the codex.toml value for Codex config.toml.',
			'Use the direct_http object for clients that support HTTP MCP directly.',
			'Generated configs include an explicit User-Agent because some security layers reject REST/MCP requests without one.',
		),
		'diagnostics'     => array(
			'plugin_status'        => $plugin_status,
			'orchestrated_tools'   => count( $tools ),
			'legacy_emcp_conflict' => ampersand_elementor_mcp_orchestrator_has_legacy_emcp_conflict(),
		),
		'claude_desktop'  => json_decode( ampersand_elementor_mcp_orchestrator_claude_desktop_config_snippet( $generated['claude_endpoint'], $authorization ), true ),
		'codex'           => array(
			'config_file' => '~/.codex/config.toml',
			'toml'        => ampersand_elementor_mcp_orchestrator_codex_toml_snippet( $server_name, $generated['endpoint'], $authorization ),
		),
		'direct_http'     => json_decode( ampersand_elementor_mcp_orchestrator_http_config_snippet( $generated['endpoint'], $authorization ), true ),
		'agent_prompt'    => ampersand_elementor_mcp_orchestrator_prompt(),
	);
}

/**
 * Generate an Application Password and stream a JSON connection bundle.
 *
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_handle_config_download(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'ampersand-elementor-mcp-orchestrator' ) );
	}

	check_admin_referer( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_NONCE );

	if ( ! ampersand_elementor_mcp_orchestrator_app_passwords_available() ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ampersand-elementor-mcp',
					'amp_mcp_error' => 'app_passwords_unavailable',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	$generated = ampersand_elementor_mcp_orchestrator_generate_app_password();

	if ( is_wp_error( $generated ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ampersand-elementor-mcp',
					'amp_mcp_error' => rawurlencode( $generated->get_error_code() ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	$json = wp_json_encode( ampersand_elementor_mcp_orchestrator_connection_bundle( $generated ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( ! is_string( $json ) ) {
		wp_die( esc_html__( 'Could not encode the MCP connection bundle.', 'ampersand-elementor-mcp-orchestrator' ) );
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . ampersand_elementor_mcp_orchestrator_config_filename( 'connection' ) . '"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON file body.
	exit;
}

/**
 * Build a direct HTTP MCP config snippet for clients that support HTTP MCP natively.
 *
 * @param string $endpoint Endpoint URL.
 * @param string $authorization Authorization header.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_http_config_snippet( string $endpoint, string $authorization ): string {
	return wp_json_encode(
		array(
			'mcpServers' => array(
				ampersand_elementor_mcp_orchestrator_config_server_name() => array(
					'type'    => 'http',
					'url'     => $endpoint,
					'headers' => ampersand_elementor_mcp_orchestrator_client_headers( $authorization ),
				),
			),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
}

/**
 * Build a Claude Desktop MCP config snippet.
 *
 * Claude Desktop's config is stdio-oriented, so HTTP MCP endpoints should be
 * proxied through mcp-remote.
 *
 * @param string $endpoint Endpoint URL.
 * @param string $authorization Authorization header.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_claude_desktop_config_snippet( string $endpoint, string $authorization ): string {
	$args = array(
		'-y',
		'mcp-remote@latest',
		$endpoint,
	);

	if ( 0 === strpos( $endpoint, 'http://' ) ) {
		$args[] = '--allow-http';
	}

	$args[] = '--header';
	$args[] = 'Authorization:' . $authorization;
	$args[] = '--header';
	$args[] = 'User-Agent:' . ampersand_elementor_mcp_orchestrator_client_user_agent();

	return wp_json_encode(
		array(
			'mcpServers' => array(
				ampersand_elementor_mcp_orchestrator_config_server_name() => array(
					'command' => 'npx',
					'args'    => $args,
				),
			),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
}

/**
 * Show an admin notice instead of failing hard when dependencies are missing.
 *
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_admin_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$deps    = ampersand_elementor_mcp_orchestrator_dependencies();
	$missing = array();

	if ( ! $deps['abilities_api'] ) {
		$missing[] = 'WordPress Abilities API';
	}
	if ( ! $deps['mcp_adapter'] ) {
		$missing[] = 'official MCP Adapter plugin';
	}
	if ( ! $deps['http_transport'] ) {
		$missing[] = 'MCP Adapter HTTP transport';
	}

	if ( empty( $missing ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>Ampersand Elementor MCP Orchestrator</strong> can show guidance now, but its MCP bridge is inactive until these dependencies are available: %s.</p></div>',
		esc_html( implode( ', ', $missing ) )
	);
}
add_action( 'admin_notices', 'ampersand_elementor_mcp_orchestrator_admin_notice' );

/**
 * Add settings page.
 *
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_admin_menu(): void {
	add_options_page(
		'Ampersand Elementor MCP',
		'Elementor MCP',
		'manage_options',
		'ampersand-elementor-mcp',
		'ampersand_elementor_mcp_orchestrator_render_settings_page'
	);
}
add_action( 'admin_menu', 'ampersand_elementor_mcp_orchestrator_admin_menu' );

/**
 * Render settings page.
 *
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$app_passwords_available = ampersand_elementor_mcp_orchestrator_app_passwords_available();
	$legacy_emcp_conflict    = ampersand_elementor_mcp_orchestrator_has_legacy_emcp_conflict();
	$status                  = ampersand_elementor_mcp_orchestrator_plugin_status();
	$settings                = ampersand_elementor_mcp_orchestrator_get_settings();
	$tool_groups             = ampersand_elementor_mcp_orchestrator_tool_groups();
	$tool_group_counts       = ampersand_elementor_mcp_orchestrator_tool_group_counts();
	$tool_selection_counts   = ampersand_elementor_mcp_orchestrator_tool_selection_counts( $settings );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only error display.
	$error = isset( $_GET['amp_mcp_error'] ) ? sanitize_key( wp_unslash( $_GET['amp_mcp_error'] ) ) : '';
	?>
	<div class="wrap amp-mcp">
		<h1>Ampersand Elementor MCP Orchestrator</h1>
		<p class="description">Generate one connection file for Claude Desktop, Codex, and direct HTTP MCP clients.</p>

		<?php if ( 'app_passwords_unavailable' === $error || ! $app_passwords_available ) : ?>
			<div class="notice notice-error inline">
				<p>Application Passwords are not available for this user/site. Enable them, then reload this page.</p>
			</div>
		<?php elseif ( $error ) : ?>
			<div class="notice notice-error inline">
				<p>Could not create the MCP connection file. Error: <code><?php echo esc_html( $error ); ?></code></p>
			</div>
		<?php endif; ?>

		<?php if ( $legacy_emcp_conflict ) : ?>
			<div class="notice notice-warning inline">
				<p><strong>EMCP Tools upgrade conflict:</strong> the old <code>elementor-mcp</code> plugin is active alongside <code>emcp-tools</code>. Deactivate and delete the old plugin so EMCP Tools can boot normally.</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_ACTION ); ?>">
			<?php wp_nonce_field( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_DOWNLOAD_NONCE ); ?>
			<p>
				<button type="submit" class="button button-primary button-hero" <?php disabled( ! $app_passwords_available ); ?>>Generate Application Password & Download JSON</button>
			</p>
		</form>

		<h2>Tool Set</h2>
		<p class="description" style="max-width: 820px;">The default curated core keeps the MCP server small enough for clients that struggle with very large tool lists. Enable extra groups only when the site needs them.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" style="max-width: 820px;">
			<?php settings_fields( 'ampersand_elementor_mcp_orchestrator' ); ?>
			<input type="hidden" name="<?php echo esc_attr( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_OPTION ); ?>[enable_editor_first_guardrails]" value="1">

			<p>
				<strong>Selected tool slots:</strong>
				<span id="amp-mcp-tool-count"><?php echo esc_html( (string) $tool_selection_counts['selected'] ); ?></span>
				/
				<?php echo esc_html( (string) AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_WARNING_THRESHOLD ); ?> recommended max.
				<span style="margin-left: 8px;">Currently registered: <span id="amp-mcp-available-tool-count"><?php echo esc_html( (string) $tool_selection_counts['available'] ); ?></span>.</span>
			</p>
			<div id="amp-mcp-tool-warning" class="notice notice-warning inline" <?php echo $tool_selection_counts['selected'] > AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_WARNING_THRESHOLD ? '' : 'hidden'; ?>>
				<p>Large tool sets may connect but fail to appear in some MCP clients. Keep the selected total near the recommended limit unless you are testing a client that can handle larger payloads.</p>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width: 80px;">Expose</th>
						<th>Group</th>
						<th style="width: 170px;">Tools</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tool_groups as $group_id => $group ) : ?>
						<?php
						$setting_key = (string) $group['setting'];
						$count       = $tool_group_counts[ $group_id ] ?? array(
							'total'     => count( (array) $group['abilities'] ),
							'available' => 0,
						);
						?>
						<tr>
							<td>
								<label>
									<input
										type="checkbox"
										class="amp-mcp-tool-group"
										name="<?php echo esc_attr( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_OPTION . '[' . $setting_key . ']' ); ?>"
										value="1"
										data-tool-count="<?php echo esc_attr( (string) $count['total'] ); ?>"
										data-available-count="<?php echo esc_attr( (string) $count['available'] ); ?>"
										<?php checked( ! empty( $settings[ $setting_key ] ) ); ?>
									>
								</label>
							</td>
							<td>
								<strong><?php echo esc_html( (string) $group['label'] ); ?></strong>
								<br>
								<span class="description"><?php echo esc_html( (string) $group['description'] ); ?></span>
							</td>
							<td>
								<?php echo esc_html( (string) $count['total'] ); ?> selected
								<br>
								<span class="description"><?php echo esc_html( (string) $count['available'] ); ?> available now</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( 'Save Tool Selection', 'secondary', 'submit', false, array( 'style' => 'margin-top: 12px;' ) ); ?>
		</form>

		<script>
			(function () {
				const threshold = <?php echo (int) AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_WARNING_THRESHOLD; ?>;
				const boxes = document.querySelectorAll('.amp-mcp-tool-group');
				const totalEl = document.getElementById('amp-mcp-tool-count');
				const availableEl = document.getElementById('amp-mcp-available-tool-count');
				const warningEl = document.getElementById('amp-mcp-tool-warning');

				function updateToolCount() {
					let selected = 0;
					let available = 0;

					boxes.forEach(function (box) {
						if (!box.checked) {
							return;
						}

						selected += parseInt(box.dataset.toolCount || '0', 10);
						available += parseInt(box.dataset.availableCount || '0', 10);
					});

					totalEl.textContent = selected.toString();
					availableEl.textContent = available.toString();
					warningEl.hidden = selected <= threshold;
				}

				boxes.forEach(function (box) {
					box.addEventListener('change', updateToolCount);
				});
				updateToolCount();
			}());
		</script>

		<h2>Plugin Status</h2>
		<table class="widefat striped" style="max-width: 820px;">
			<thead>
				<tr>
					<th>Capability</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status as $label => $active ) : ?>
					<?php
					$is_legacy_row = 'Legacy elementor-mcp folder active' === $label;
					$status_text   = $active ? 'Active / Available' : 'Missing / Inactive';
					$status_color  = $active ? '#008a20' : '#b32d2e';

					if ( $is_legacy_row ) {
						$status_text  = $active ? 'Active - remove old plugin' : 'Not detected / OK';
						$status_color = $active ? '#b32d2e' : '#008a20';
					}
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><span style="color: <?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( $status_text ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Return registered ability names keyed by name.
 *
 * @return array<string, bool>
 */
function ampersand_elementor_mcp_orchestrator_registered_ability_map(): array {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$registered = array();

	foreach ( wp_get_abilities() as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
			continue;
		}

		$name = (string) $ability->get_name();

		if ( '' !== $name ) {
			$registered[ $name ] = true;
		}
	}

	return $registered;
}

/**
 * Return configured ability names from enabled groups.
 *
 * @param array<string, bool>|null $settings Optional settings override.
 * @return string[]
 */
function ampersand_elementor_mcp_orchestrator_selected_tool_names( ?array $settings = null ): array {
	$settings = is_array( $settings ) ? $settings : ampersand_elementor_mcp_orchestrator_get_settings();
	$selected = array();

	foreach ( ampersand_elementor_mcp_orchestrator_tool_groups() as $group ) {
		$setting_key = (string) $group['setting'];

		if ( empty( $settings[ $setting_key ] ) ) {
			continue;
		}

		foreach ( (array) $group['abilities'] as $ability_name ) {
			$selected[ (string) $ability_name ] = true;
		}
	}

	return array_keys( $selected );
}

/**
 * Return group counts for UI display.
 *
 * @return array<string, array<string, int>>
 */
function ampersand_elementor_mcp_orchestrator_tool_group_counts(): array {
	$registered = ampersand_elementor_mcp_orchestrator_registered_ability_map();
	$counts     = array();

	foreach ( ampersand_elementor_mcp_orchestrator_tool_groups() as $group_id => $group ) {
		$total     = 0;
		$available = 0;

		foreach ( (array) $group['abilities'] as $ability_name ) {
			$total++;

			if ( isset( $registered[ (string) $ability_name ] ) ) {
				$available++;
			}
		}

		$counts[ $group_id ] = array(
			'total'     => $total,
			'available' => $available,
		);
	}

	return $counts;
}

/**
 * Return selected and currently available tool counts.
 *
 * @param array<string, bool>|null $settings Optional settings override.
 * @return array<string, int>
 */
function ampersand_elementor_mcp_orchestrator_tool_selection_counts( ?array $settings = null ): array {
	$selected_names = ampersand_elementor_mcp_orchestrator_selected_tool_names( $settings );
	$registered     = ampersand_elementor_mcp_orchestrator_registered_ability_map();
	$available      = 0;

	foreach ( $selected_names as $ability_name ) {
		if ( isset( $registered[ $ability_name ] ) ) {
			$available++;
		}
	}

	return array(
		'selected'  => count( $selected_names ),
		'available' => $available,
	);
}

/**
 * Return cache key for selected tool names.
 *
 * @param array<string, bool> $settings Current settings.
 * @return string
 */
function ampersand_elementor_mcp_orchestrator_tool_cache_key( array $settings ): string {
	$encoded_settings = wp_json_encode( $settings );
	$settings_hash    = md5( is_string( $encoded_settings ) ? $encoded_settings : serialize( $settings ) );
	$salt             = (string) get_option( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_TOOL_CACHE_SALT_OPTION, '1' );

	return 'amp_mcp_tools_' . md5( AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION . '|' . $salt . '|' . $settings_hash );
}

/**
 * Resolve selected Elementor ability names against currently registered abilities.
 *
 * @param array<string, bool> $settings Current settings.
 * @return string[]
 */
function ampersand_elementor_mcp_orchestrator_resolve_tools( array $settings ): array {
	$registered = ampersand_elementor_mcp_orchestrator_registered_ability_map();
	$tools      = array();

	foreach ( ampersand_elementor_mcp_orchestrator_selected_tool_names( $settings ) as $ability_name ) {
		if ( isset( $registered[ $ability_name ] ) ) {
			$tools[] = $ability_name;
		}
	}

	return $tools;
}

/**
 * Collect selected Elementor ability names.
 *
 * @return string[]
 */
function ampersand_elementor_mcp_orchestrator_get_tools(): array {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$settings  = ampersand_elementor_mcp_orchestrator_get_settings();
	$cache_key = ampersand_elementor_mcp_orchestrator_tool_cache_key( $settings );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return array_values( array_filter( $cached, 'is_string' ) );
	}

	$tools = ampersand_elementor_mcp_orchestrator_resolve_tools( $settings );

	set_transient( $cache_key, $tools, HOUR_IN_SECONDS );

	return $tools;
}

/**
 * Register a dedicated MCP server for selected Elementor abilities.
 *
 * @param object $adapter Official MCP Adapter instance.
 * @return void
 */
function ampersand_elementor_mcp_orchestrator_register_server( $adapter ): void {
	$deps = ampersand_elementor_mcp_orchestrator_dependencies();

	if ( ! $deps['abilities_api'] || ! $deps['mcp_adapter'] || ! $deps['http_transport'] ) {
		return;
	}

	if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
		return;
	}

	$tools = ampersand_elementor_mcp_orchestrator_get_tools();

	if ( empty( $tools ) ) {
		return;
	}

	$adapter->create_server(
		'ampersand-elementor-orchestrator',
		'mcp',
		'ampersand-elementor-orchestrator',
		'Ampersand Elementor MCP Orchestrator',
		'Exposes selected Elementor MCP abilities with editor-first workflow guidance.',
		'v' . AMPERSAND_ELEMENTOR_MCP_ORCHESTRATOR_VERSION,
		array( '\WP\MCP\Transport\HttpTransport' ),
		null,
		null,
		$tools
	);
}
add_action( 'mcp_adapter_init', 'ampersand_elementor_mcp_orchestrator_register_server', 50 );
