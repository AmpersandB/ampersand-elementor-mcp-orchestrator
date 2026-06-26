# Changelog

## 1.5.0 - 2026-06-26

### Added

- Curated default MCP tool allowlist to keep Claude Desktop and similar clients below practical tool registration limits.
- Admin tool-set controls with opt-in groups for WooCommerce, Elementor v4 atomic widgets, extended widgets, code injection, design QA, form submissions, and site maintenance.
- Live selected/available tool count with a warning when the selected tool set exceeds the recommended threshold.
- Cached resolved tool names keyed by plugin version, settings, and cache salt.

### Changed

- The orchestrator no longer exposes every `elementor/...` and `elementor-mcp/...` ability by default.
- Fresh installs now expose the editor-first core workflow tools only; heavier or riskier capabilities are one checkbox away.

### Security

- PHP snippet and custom code capabilities are now disabled by default behind a security-sensitive opt-in group.

## 1.4.0 - 2026-06-17

### Added

- Public GitHub release updater.
- Minimal admin connection page with a single JSON download button.
- Plugin/capability status table.
- Unique MCP client server names per site/install.
- Claude Desktop, Codex, and direct HTTP MCP config in one JSON bundle.
- AI-assisted MCP installation guidance for clients that receive the downloaded JSON.
- Explicit User-Agent headers in generated configs.
- Diagnostics object in generated JSON.
- Editor-first Elementor guardrails for Theme Builder awareness, section review gates, PDF/menu work, UTF-8 preservation, no-op patches, and large import safety.
- Security and release documentation.

### Changed

- Renamed the main plugin file to `ampersand-elementor-mcp-orchestrator.php` before public adoption.
- Replaced internal legacy bridge prefixes with Ampersand/orchestrator naming.
- EMCP Tools (`emcp-tools`) is treated as the current provider for `elementor-mcp/...` abilities.
- The old `wordpress-mcp-trunk` plugin is no longer part of the expected status checklist.
- Troubleshooting language now describes generic environment security/WAF problems instead of a project-specific staging/production case.
- JSON output keeps Unicode readable.

### Security

- JSON download requires `manage_options` and a nonce.
- Generated credentials are downloaded once and not stored by the plugin.
- Update package URLs are restricted to GitHub-controlled domains.
- JSON responses include `X-Content-Type-Options: nosniff`.
