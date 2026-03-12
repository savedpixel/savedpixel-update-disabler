# SavedPixel Update Disabler

Control WordPress update behavior, outbound HTTP requests, and `wp_mail()` from one staging-focused settings page.

## What It Does

SavedPixel Update Disabler centralizes several lock-down controls that are often scattered across constants, filters, or environment-specific config. It can suppress update checks, turn off core auto-update channels, disable `wp_mail()`, block external HTTP requests, and keep a log of blocked outbound calls.

## Key Workflows

- Disable core, plugin, theme, and translation update activity for controlled environments.
- Disable only selected plugin updates instead of turning off every plugin update.
- Turn off development, minor, and major core auto-update channels independently.
- Block external HTTP requests globally or only when they originate from specific plugins or themes.
- Review the blocked-request log to see what the network rules are stopping.

## Features

- Disable WordPress core update checks and update UI.
- Disable plugin updates globally.
- Disable updates only for selected plugin basenames.
- Disable theme update checks.
- Disable translation update offers and background language-pack updates.
- Disable development, minor, and major core auto-update channels.
- Disable all mail sent through `wp_mail()`.
- Block all external HTTP requests while still allowing localhost and same-site loopbacks.
- Block plugin-originated requests unless the plugin is on an allowlist.
- Block theme-originated requests unless the theme is on an allowlist.
- Best-effort request-origin detection using the PHP stack trace.
- Global admin notice when all outbound requests are blocked.
- Blocked request log with rule labels, origin labels, and pagination.
- Clear-log action from the settings page.

## Admin Page

The settings page is organized into update controls, outbound request controls, per-plugin update exceptions, plugin and theme outbound allowlists, and the blocked request log. The UI also explains when a sub-setting is currently inactive because a broader global toggle overrides it.

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later

## Installation

1. Upload the `savedpixel-update-disabler` folder to `wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Open **SavedPixel > Update Disabler**.
4. Enable only the controls that match your staging, demo, or locked-down environment.

## Usage Notes

- This plugin is best suited to staging, demos, and controlled client handoff environments.
- Global outbound blocking can break plugin installers, license checks, remote APIs, and background jobs.
- Selective plugin/theme request blocking is best-effort; requests with no clear stack-trace caller are allowed.

## Author

**Byron Jacobs**  
[GitHub](https://github.com/savedpixel)

## License

GPL-2.0-or-later
