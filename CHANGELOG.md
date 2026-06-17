# Changelog

## 1.4.0 - 2026-06-17

### Added

- Public GitHub release updater.
- Minimal admin connection page with a single JSON download button.
- Plugin/capability status table.
- Unique MCP client server names per site/install.
- Claude Desktop, Codex, and direct HTTP MCP config in one JSON bundle.
- Explicit User-Agent headers in generated configs.
- Diagnostics object in generated JSON.
- Editor-first Elementor guardrails for Theme Builder awareness, section review gates, PDF/menu work, UTF-8 preservation, no-op patches, and large import safety.
- Security and release documentation.

### Changed

- EMCP Tools (`emcp-tools`) is treated as the current provider for `elementor-mcp/...` abilities.
- The old `wordpress-mcp-trunk` plugin is no longer part of the expected status checklist.
- JSON output keeps Unicode readable.

### Security

- JSON download requires `manage_options` and a nonce.
- Generated credentials are downloaded once and not stored by the plugin.
- Update package URLs are restricted to GitHub-controlled domains.
- JSON responses include `X-Content-Type-Options: nosniff`.
