<?php
/**
 * Plugin Name: Ampersand Elementor MCP Orchestrator
 * Description: Orchestrates Elementor MCP abilities, exposes editor-first guardrails, and provides an admin prompt/settings page.
 * Version: 1.1.0
 * Author: Ampersand Studios
 * License: GPL-2.0-or-later
 * Requires at least: 6.8
 * Requires PHP: 8.0
 *
 * @package Amp_Bjorn_Elementor_MCP_Bridge
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMP_BJORN_ELEMENTOR_MCP_BRIDGE_VERSION', '1.1.0' );
define( 'AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION', 'amp_bjorn_elementor_mcp_bridge_settings' );

/**
 * Return default plugin settings.
 *
 * @return array<string, bool>
 */
function amp_bjorn_elementor_mcp_bridge_default_settings(): array {
	return array(
		'enable_bjorn_tools'             => true,
		'enable_msrbuilds_tools'         => true,
		'enable_editor_first_guardrails' => true,
	);
}

/**
 * Return sanitized plugin settings.
 *
 * @return array<string, bool>
 */
function amp_bjorn_elementor_mcp_bridge_get_settings(): array {
	$settings = get_option( AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION, array() );
	$settings = is_array( $settings ) ? $settings : array();

	return array_merge( amp_bjorn_elementor_mcp_bridge_default_settings(), array_map( 'rest_sanitize_boolean', $settings ) );
}

/**
 * Sanitize settings from the admin page.
 *
 * @param array<string, mixed> $settings Raw settings.
 * @return array<string, bool>
 */
function amp_bjorn_elementor_mcp_bridge_sanitize_settings( array $settings ): array {
	$defaults  = amp_bjorn_elementor_mcp_bridge_default_settings();
	$sanitized = array();

	foreach ( $defaults as $key => $default ) {
		$sanitized[ $key ] = isset( $settings[ $key ] ) ? rest_sanitize_boolean( $settings[ $key ] ) : false;
	}

	return $sanitized;
}

/**
 * Register settings.
 *
 * @return void
 */
function amp_bjorn_elementor_mcp_bridge_register_settings(): void {
	register_setting(
		'amp_bjorn_elementor_mcp_bridge',
		AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'amp_bjorn_elementor_mcp_bridge_sanitize_settings',
			'default'           => amp_bjorn_elementor_mcp_bridge_default_settings(),
		)
	);
}
add_action( 'admin_init', 'amp_bjorn_elementor_mcp_bridge_register_settings' );

/**
 * Return active plugin map.
 *
 * @return array<string, bool>
 */
function amp_bjorn_elementor_mcp_bridge_plugin_status(): array {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return array(
		'Elementor'                     => is_plugin_active( 'elementor/elementor.php' ),
		'Elementor Pro'                 => is_plugin_active( 'elementor-pro/elementor-pro.php' ) || is_plugin_active( 'pro-elements/pro-elements.php' ),
		'Official MCP Adapter'          => class_exists( '\WP\MCP\Core\McpAdapter' ),
		'WordPress Abilities API'       => function_exists( 'wp_get_abilities' ),
		'MCP Adapter HTTP Transport'    => class_exists( '\WP\MCP\Transport\HttpTransport' ),
		'MCP Abilities - Elementor'     => amp_bjorn_elementor_mcp_bridge_has_ability_prefix( 'elementor/' ),
		'MCP Tools for Elementor'       => amp_bjorn_elementor_mcp_bridge_has_ability_prefix( 'elementor-mcp/' ),
		'Legacy WordPress MCP Trunk'    => is_plugin_active( 'wordpress-mcp-trunk/wordpress-mcp.php' ),
		'Ampersand MCP Orchestrator'    => true,
	);
}

/**
 * Check whether a registered ability prefix exists.
 *
 * @param string $prefix Ability prefix.
 * @return bool
 */
function amp_bjorn_elementor_mcp_bridge_has_ability_prefix( string $prefix ): bool {
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
function amp_bjorn_elementor_mcp_bridge_dependencies(): array {
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
function amp_bjorn_elementor_mcp_bridge_prompt(): string {
	return <<<'PROMPT'
You are working on a WordPress site that uses Elementor. Before doing any Elementor work, read and follow the site's Ampersand Elementor MCP Orchestrator guidance.

Core requirement: build Elementor pages so a non-programmer can edit them later through the WordPress and Elementor graphical UI.

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

1. Inspect the current page/template structure.
2. Inspect Elementor Site Settings / kit settings.
3. Inspect existing global colors, fonts, templates, menus, and relevant page patterns.
4. Reuse existing global styles and components when possible.
5. If missing, create clear reusable global colors/fonts/templates/components and document them.

For multi-section page creation or redesign:

- Build one section at a time.
- After each section, pause and ask for review before continuing.
- Report the section name, page/template ID, widgets/templates used, global tokens used or created, and anything that affects future manual editing.
- Continue only after approval, requested changes, or explicit permission to proceed without review gates.

Tool routing:

- Use `elementor-mcp/...` tools for construction: creating pages, adding containers, widgets, buttons, forms, nav menus, loop grids, templates, popups, and broad layout assembly.
- Use `elementor/...` tools for precision: inspecting data, patching existing elements, replacing URLs, clearing cache, design audits, repairing Elementor JSON, kit settings, and theme builder conditions.
- Prefer the smallest safe change.
- Read current state immediately before writing.
- Verify immediately after writing.

Completion checklist:

- Text is editable in Elementor text/heading/button widgets.
- Images/backgrounds are editable through Elementor or media library controls.
- Menus are WordPress menus or Elementor Nav Menu widgets.
- Forms are visual form widgets or the chosen form plugin.
- Repeated content uses reusable structures where practical.
- Global colors/fonts/styles are used or created and documented.
- Any HTML widget is minimal, justified, and does not trap ordinary content.
- A human editor can copy a section/widget to another page without needing to understand code.
- Elementor cache/rendered output has been refreshed or verified when needed.
PROMPT;
}

/**
 * Show an admin notice instead of failing hard when dependencies are missing.
 *
 * @return void
 */
function amp_bjorn_elementor_mcp_bridge_admin_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$deps    = amp_bjorn_elementor_mcp_bridge_dependencies();
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
add_action( 'admin_notices', 'amp_bjorn_elementor_mcp_bridge_admin_notice' );

/**
 * Add settings page.
 *
 * @return void
 */
function amp_bjorn_elementor_mcp_bridge_admin_menu(): void {
	add_options_page(
		'Ampersand Elementor MCP',
		'Elementor MCP',
		'manage_options',
		'ampersand-elementor-mcp',
		'amp_bjorn_elementor_mcp_bridge_render_settings_page'
	);
}
add_action( 'admin_menu', 'amp_bjorn_elementor_mcp_bridge_admin_menu' );

/**
 * Render settings page.
 *
 * @return void
 */
function amp_bjorn_elementor_mcp_bridge_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = amp_bjorn_elementor_mcp_bridge_get_settings();
	$status   = amp_bjorn_elementor_mcp_bridge_plugin_status();
	$tools    = amp_bjorn_elementor_mcp_bridge_get_tools();
	$prompt   = amp_bjorn_elementor_mcp_bridge_prompt();
	?>
	<div class="wrap">
		<h1>Ampersand Elementor MCP Orchestrator</h1>
		<p>Use this page to copy the agent prompt, check MCP/Elementor plugin status, and decide which Elementor MCP tool families this orchestrator exposes.</p>

		<h2>Tool Families</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'amp_bjorn_elementor_mcp_bridge' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Bjorn precision tools</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION ); ?>[enable_bjorn_tools]" value="1" <?php checked( $settings['enable_bjorn_tools'] ); ?>>
							Expose abilities beginning with <code>elementor/</code>.
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">MSRBuilds construction tools</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION ); ?>[enable_msrbuilds_tools]" value="1" <?php checked( $settings['enable_msrbuilds_tools'] ); ?>>
							Expose abilities beginning with <code>elementor-mcp/</code> when available.
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Editor-first guardrails</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AMP_BJORN_ELEMENTOR_MCP_BRIDGE_OPTION ); ?>[enable_editor_first_guardrails]" value="1" <?php checked( $settings['enable_editor_first_guardrails'] ); ?>>
							Show and expose the human-editable Elementor workflow prompt.
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Installed / Active Tools</h2>
		<table class="widefat striped" style="max-width: 900px;">
			<thead><tr><th>Capability</th><th>Status</th></tr></thead>
			<tbody>
				<?php foreach ( $status as $label => $active ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><?php echo $active ? '<span style="color:#008a20;">Active / Available</span>' : '<span style="color:#b32d2e;">Missing / Inactive</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Orchestrated MCP Endpoint</h2>
		<p>When the official MCP Adapter dependencies are active, this plugin registers:</p>
		<p><code><?php echo esc_html( rest_url( 'mcp/ampersand-elementor-orchestrator' ) ); ?></code></p>
		<p>Currently selected abilities: <strong><?php echo esc_html( (string) count( $tools ) ); ?></strong></p>

		<h2>Agent Prompt</h2>
		<p>Copy this prompt into Codex, Claude, or another MCP-capable agent before Elementor work.</p>
		<textarea readonly rows="28" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $prompt ); ?></textarea>
	</div>
	<?php
}

/**
 * Collect selected Elementor ability names.
 *
 * @return string[]
 */
function amp_bjorn_elementor_mcp_bridge_get_tools(): array {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$settings = amp_bjorn_elementor_mcp_bridge_get_settings();
	$tools    = array();

	foreach ( wp_get_abilities() as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
			continue;
		}

		$name = (string) $ability->get_name();

		if ( $settings['enable_bjorn_tools'] && 0 === strpos( $name, 'elementor/' ) ) {
			$tools[] = $name;
		}

		if ( $settings['enable_msrbuilds_tools'] && 0 === strpos( $name, 'elementor-mcp/' ) ) {
			$tools[] = $name;
		}
	}

	sort( $tools );

	return array_values( array_unique( $tools ) );
}

/**
 * Register a dedicated MCP server for selected Elementor abilities.
 *
 * @param object $adapter Official MCP Adapter instance.
 * @return void
 */
function amp_bjorn_elementor_mcp_bridge_register_server( $adapter ): void {
	$deps = amp_bjorn_elementor_mcp_bridge_dependencies();

	if ( ! $deps['abilities_api'] || ! $deps['mcp_adapter'] || ! $deps['http_transport'] ) {
		return;
	}

	if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
		return;
	}

	$tools = amp_bjorn_elementor_mcp_bridge_get_tools();

	if ( empty( $tools ) ) {
		return;
	}

	$adapter->create_server(
		'ampersand-elementor-orchestrator',
		'mcp',
		'ampersand-elementor-orchestrator',
		'Ampersand Elementor MCP Orchestrator',
		'Exposes selected Elementor MCP abilities with editor-first workflow guidance.',
		'v' . AMP_BJORN_ELEMENTOR_MCP_BRIDGE_VERSION,
		array( '\WP\MCP\Transport\HttpTransport' ),
		null,
		null,
		$tools
	);
}
add_action( 'mcp_adapter_init', 'amp_bjorn_elementor_mcp_bridge_register_server', 50 );

