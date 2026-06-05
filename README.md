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
- A connection wizard that creates a WordPress Application Password for the current user and prints ready-to-copy MCP config.

The generated Application Password is shown once and is not stored by this plugin. Revoke old MCP passwords from the WordPress user profile when they are no longer needed.

## MCP Endpoint

When the official WordPress MCP Adapter and HTTP transport are active, the plugin registers:

```text
/wp-json/mcp/ampersand-elementor-orchestrator
```

## Quick Connection Flow

1. Go to `Settings -> Elementor MCP`.
2. Click `Generate MCP Connection`.
3. Copy the generated Codex / Claude JSON.
4. Paste it into the MCP client configuration.
5. Test with `initialize` and `tools/list`.

## Guardrail

The key rule: do not build ordinary Elementor pages as large HTML/CSS/JS blobs inside HTML widgets. Use real Elementor widgets, WordPress menus, global colors/fonts, templates, forms, media controls, and other visual primitives so human editors can maintain the site.
