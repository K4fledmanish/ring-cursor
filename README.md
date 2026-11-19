# Ring Cursor

A lightweight custom mouse cursor for WordPress. Keep the normal system cursor and add a coloured ring around it, or swap in a Font Awesome icon (with or without the ring).

## Features

- **Three styles:** default cursor + ring, icon + ring, or icon only
- **Nine icons:** dot, arrow, hand, heart, brain, leaf, star, care hand, chat (Font Awesome Free, embedded as inline SVG)
- Ring grows over links and buttons, shrinks on click
- Choose icon colour and size, ring colour, ring size and ring thickness from **Settings → Ring Cursor**
- Automatically disabled on touch devices
- Keeps the normal text cursor over input fields so typing feels normal

## Install

1. Download `ring-cursor.zip` (or zip the `ring-cursor` folder).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, upload the zip and activate.
3. Open **Settings → Ring Cursor** to choose your style and colours.

Requires WordPress 6.3+ and PHP 7.4+.

## Performance and security

- One cached option read per page; no database writes on the front end
- No external requests, no Font Awesome CSS/JS/CDN: icons are inline SVG
- About 1.5 KB of CSS and JS, added through the WordPress asset system (works with CSP nonce plugins)
- The script only runs while the mouse moves: at most one DOM update per frame, and nothing runs while idle
- Every setting is validated when saved **and** when read (hex colours, whitelisted style and icon names, clamped numbers), so unsafe values never reach the page
- Settings page requires `manage_options` and uses the WordPress Settings API (nonce-protected)
- Settings are removed when the plugin is deleted

## Author

[Manish Kafle](https://k4fledmanish.github.io/)

## Credits

Icon shapes are from [Font Awesome Free](https://fontawesome.com) by Fonticons, Inc., licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

## License

Copyright (c) 2026 Manish Kafle.

Ring Cursor is dual-licensed. You may use it under **either** of these licenses, at your option:

- [GNU General Public License v2.0 or later](LICENSE) (`GPL-2.0-or-later`), the license WordPress plugins normally use
- [MIT License](LICENSE-MIT)

The bundled Font Awesome Free icon shapes remain under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) (see Credits).
