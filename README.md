# Ampersand Elementor MCP Orchestrator

Ampersand Elementor MCP Orchestrator is a WordPress plugin that exposes selected Elementor MCP abilities through one orchestrated MCP endpoint and gives AI coding agents an editor-first Elementor workflow prompt.

The goal is simple: AI-assisted Elementor work should remain editable by non-programmers through the normal WordPress and Elementor interfaces.

## What It Does

- Registers an MCP server named `ampersand-elementor-orchestrator` through the official WordPress MCP Adapter.
- Orchestrates Elementor ability families from Bjorn Elementor abilities and EMCP Tools / MCP Tools for Elementor.
- Provides a minimal admin page under `Settings -> Elementor MCP`.
- Generates one JSON connection bundle for Claude Desktop, Codex, and direct HTTP MCP clients.
- Includes diagnostics for installed/active Elementor MCP dependencies.
- Embeds guardrails that tell agents to use native Elementor widgets, templates, menus, global styles, and Theme Builder awareness instead of HTML blobs.
- Checks GitHub Releases for plugin updates.

## Requirements

Minimum runtime:

- WordPress 6.8 or newer.
- PHP 8.0 or newer.
- Administrator access to install plugins and generate WordPress Application Passwords.
- WordPress Application Passwords enabled for the admin user.

Recommended Elementor/MCP stack:

- Elementor.
- Elementor Pro or Pro Elements when Pro widgets/templates are needed.
- Official WordPress MCP Adapter.
- WordPress Abilities API.
- MCP Adapter HTTP Transport.
- Bjorn `mcp-abilities-elementor` for `elementor/...` abilities.
- EMCP Tools (`emcp-tools`) for `elementor-mcp/...` abilities.
- This plugin.

Release/download pages for the MCP plugins used by this stack:

- Official WordPress MCP Adapter: <https://github.com/WordPress/mcp-adapter/releases>
- Bjorn MCP abilities / expose abilities: <https://github.com/bjornfix/mcp-expose-abilities/releases>
- MSRBuilds EMCP Tools / Elementor MCP: <https://github.com/msrbuilds/elementor-mcp/releases>

Not recommended:

- The old MSRBuilds plugin folder `elementor-mcp` should not be active together with `emcp-tools`. EMCP Tools pauses itself when the old folder is still active.
- The old `wordpress-mcp-trunk` plugin is intentionally not part of the expected stack.

## Installation

1. Download the latest release ZIP from GitHub Releases.
2. In WordPress, go to `Plugins -> Add New -> Upload Plugin`.
3. Upload the ZIP and activate `Ampersand Elementor MCP Orchestrator`.
4. Install and activate the required MCP/Elementor dependencies listed above.
5. Go to `Settings -> Elementor MCP` and confirm the plugin status table.

## Admin Page

After activation, open:

```text
Settings -> Elementor MCP
```

The page contains:

- One button: `Generate Application Password & Download JSON`.
- A compact plugin/capability status table.
- Critical notices for missing Application Password support or EMCP Tools / legacy `elementor-mcp` conflicts.

The plugin does not store generated Application Passwords. The password is written into the downloaded JSON file once. Store that file securely.

## Generated JSON

The downloaded JSON includes:

- `server`: unique MCP client config name for this site/install.
- `site`: WordPress site URL.
- `url`: direct MCP endpoint URL.
- `claude_url`: endpoint URL selected for Claude Desktop / LocalWP compatibility.
- `headers`: Authorization and User-Agent headers.
- `diagnostics`: plugin status, orchestrated tool count, and legacy conflict state.
- `claude_desktop`: config object for `claude_desktop_config.json`.
- `codex.toml`: TOML snippet for Codex `config.toml`.
- `direct_http`: config for clients that support direct HTTP MCP.
- `agent_prompt`: editor-first Elementor guardrails for Codex, Claude, or another MCP-capable agent.

The generated MCP client name follows this pattern:

```text
ampersand-elementor-orchestrator-<site-slug>-<instance-id>
```

That avoids collisions when one Claude or Codex installation connects to multiple WordPress sites.

## Use With Claude Desktop

1. Open the downloaded JSON.
2. Copy the value under `claude_desktop`.
3. Merge it into `claude_desktop_config.json`.
4. Restart Claude Desktop.
5. Confirm that the MCP server loads and tools are visible.

Claude Desktop does not accept direct HTTP MCP entries with `type`, `url`, and `headers`. This plugin generates a `mcp-remote` stdio bridge config instead.

For LocalWP `.local` sites, the generated Claude endpoint uses `http://` and adds `--allow-http` to avoid local self-signed certificate failures.

## Use With Codex

1. Open the downloaded JSON.
2. Copy `codex.toml`.
3. Add it to `~/.codex/config.toml`.
4. Restart Codex or reload MCP configuration.
5. Test with `initialize` and `tools/list`.

## MCP Endpoint

When the official MCP Adapter and HTTP transport are active, this plugin registers:

```text
/wp-json/mcp/ampersand-elementor-orchestrator
```

## Agent Guardrails

The generated prompt instructs agents to:

- Build with native Elementor widgets and containers.
- Keep text in Heading, Text Editor, Button, and similar visual widgets.
- Use WordPress menus and Elementor Nav Menu widgets for navigation.
- Use Elementor Site Settings, global colors, global fonts, reusable templates, and Theme Builder.
- Detect whether a URL is controlled by page content, a single/archive template, a loop item, header, footer, popup, or global widget before editing.
- Pause for review between major sections unless the user waives review gates.
- Preserve PDF/menu source content, ordering, prices, symbols, and UTF-8 text exactly.
- Treat "success but no matches/no changes" as a no-op.
- Back up before large Elementor JSON imports or content syncs.
- Clear Elementor cache and verify rendered output after writes.

## Security Notes

- Only users with `manage_options` can generate the JSON connection bundle.
- The JSON contains a live WordPress Application Password. Treat it like a secret.
- Revoke old credentials from `Users -> Profile -> Application Passwords`.
- Do not commit generated JSON files to Git.
- Use the least-privileged WordPress admin account practical for MCP automation.
- Production WAF/security plugins may block REST/MCP requests without a User-Agent. Generated configs include one.

See [SECURITY.md](SECURITY.md) for reporting and operational guidance.

## GitHub Updates

The plugin checks public GitHub Releases from:

```text
https://github.com/AmpersandB/ampersand-elementor-mcp-orchestrator
```

When a release tag is newer than the installed plugin version, WordPress can show the update in the normal Plugins/Updates screens.

The repository/releases must be publicly reachable for this updater. Private GitHub updates require a separate token-authenticated download flow.

Recommended release flow:

1. Update the plugin header `Version` and `AMP_BJORN_ELEMENTOR_MCP_BRIDGE_VERSION`.
2. Create a GitHub release tag such as `v1.4.1`.
3. Attach a plugin ZIP if available. The updater prefers release `.zip` assets and falls back to GitHub's source `zipball`.

Updating the plugin does not revoke existing WordPress Application Passwords or invalidate already downloaded JSON config files. Old JSON files keep working as long as their Application Password remains valid. Download a fresh JSON when you want newer connection defaults.

## Troubleshooting

`Application Passwords are not available`

Confirm WordPress Application Passwords are enabled and the current user can use them.

`EMCP Tools upgrade conflict`

Deactivate and delete the old `elementor-mcp` plugin folder so `emcp-tools` can boot normally.

Production returns `403` but staging works

Check Wordfence, host WAF rules, REST security settings, and whether the client sends the generated User-Agent header.

Tool count differs between staging and production

Compare plugin versions, active ability providers, and legacy folders. The JSON `diagnostics` object helps with this.

Claude Desktop says the MCP server is invalid

Use the `claude_desktop` object, not the `direct_http` object. Claude Desktop needs the generated `mcp-remote` command config.

## License

GPL-2.0-or-later.
