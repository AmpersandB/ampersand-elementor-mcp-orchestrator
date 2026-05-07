# Ampersand Elementor MCP Orchestrator

WordPress plugin that orchestrates Elementor MCP tools and embeds an editor-first workflow prompt for agents such as Codex and Claude.

## Purpose

Elementor automation should produce pages a non-programmer can maintain in the WordPress and Elementor UI. This plugin helps by:

- Detecting Elementor/MCP-related plugin availability.
- Exposing selected Elementor ability families through one orchestrated MCP server.
- Providing a WordPress settings page with a copyable agent prompt.
- Letting site admins disable tool families from the orchestrator.
- Keeping the old Bjorn bridge behavior while expanding toward all Elementor MCP tool families.

## Tool Families

- Bjorn precision abilities: `elementor/...`
- MSRBuilds construction abilities: `elementor-mcp/...`

## Admin Page

After activation, go to:

```text
Settings -> Elementor MCP
```

The page shows:

- Active/missing plugin status.
- Which tool families are exposed by the orchestrator.
- The orchestrated MCP endpoint.
- A prompt compatible with Codex and Claude.

## MCP Endpoint

When the official WordPress MCP Adapter and HTTP transport are active, the plugin registers:

```text
/wp-json/mcp/ampersand-elementor-orchestrator
```

## Guardrail

The key rule: do not build ordinary Elementor pages as large HTML/CSS/JS blobs inside HTML widgets. Use real Elementor widgets, WordPress menus, global colors/fonts, templates, forms, media controls, and other visual primitives so human editors can maintain the site.

