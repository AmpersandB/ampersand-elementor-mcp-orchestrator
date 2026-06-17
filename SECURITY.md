# Security Policy

## Supported Versions

Security fixes are provided for the latest public release.

## Reporting a Vulnerability

Please report suspected vulnerabilities privately to the repository owner before opening a public issue.

Include:

- Plugin version.
- WordPress and PHP versions.
- Active MCP/Elementor dependency versions.
- Steps to reproduce.
- Impact and expected behavior.

Do not include live Application Passwords, bearer tokens, private keys, database credentials, or generated MCP JSON files in public issues.

## Operational Security Guidance

- The generated connection JSON contains a WordPress Application Password. Treat it as a secret.
- Store generated JSON files outside public web roots and do not commit them to Git.
- Revoke unused MCP credentials under `Users -> Profile -> Application Passwords`.
- Use the least-privileged WordPress user that can perform the intended MCP automation.
- Prefer staging validation before production Elementor writes.
- Back up Elementor JSON or content before bulk imports, URL replacements, or page syncs.
- Review Wordfence, host WAF, and REST security rules when MCP requests return unexpected `401` or `403` responses.

## Security Design Notes

- The JSON download action requires `manage_options`.
- The JSON download action uses a WordPress admin nonce.
- Generated credentials are streamed to the browser and are not stored by this plugin.
- The plugin update checker only accepts package URLs hosted by GitHub-controlled domains.
- Public GitHub updates require public releases. Private update support would require a separate token-authenticated flow.
