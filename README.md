# PI Giveaways — WordPress Plugin

![PI Giveaways](https://ps.w.org/pi-giveaways/assets/banner-1544x500.png)

<p align="center">
  <a href="https://wordpress.org/plugins/pi-giveaways/">
    <img src="https://ps.w.org/pi-giveaways/assets/icon-256x256.gif" width="128" height="128" alt="PI Giveaways animated icon">
  </a>
</p>

[![Version](https://img.shields.io/wordpress/plugin/v/pi-giveaways?label=version&logo=wordpress)](https://wordpress.org/plugins/pi-giveaways/)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/pi-giveaways?label=downloads&logo=wordpress)](https://wordpress.org/plugins/pi-giveaways/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/pi-giveaways?label=active%20installs&logo=wordpress)](https://wordpress.org/plugins/pi-giveaways/)
[![Rating](https://img.shields.io/wordpress/plugin/stars/pi-giveaways?logo=wordpress)](https://wordpress.org/plugins/pi-giveaways/)
[![WP Tested](https://img.shields.io/wordpress/plugin/tested/pi-giveaways?label=WP%20tested)](https://wordpress.org/plugins/pi-giveaways/)
[![PHP](https://img.shields.io/wordpress/plugin/required-php/pi-giveaways?label=PHP&logo=php)](https://wordpress.org/plugins/pi-giveaways/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-red)](https://www.gnu.org/licenses/gpl-2.0.html)

> Create giveaways, collect moderated entries, and manually award one or more winners through a focused, privacy-aware workflow.

**→ Install from WordPress.org:** [wordpress.org/plugins/pi-giveaways/](https://wordpress.org/plugins/pi-giveaways/)

---

## Description

PI Giveaways provides a complete WordPress giveaway workflow without random or automatic winner selection. Build reusable entry forms, publish current and past giveaway listings, moderate submissions, export entries, and deliberately award the configured number of winners.

Giveaways remain published after closing while their entry forms close automatically. Entry data is retained indefinitely by default unless an administrator explicitly enables scheduled retention.

---

## Features

### Giveaways and listings

- Dedicated giveaway post type with prize, value, start date, end date, and event link
- Configurable number of winners for each giveaway
- Current and past listing sections with editable headings
- Grid, list, and masonry listing layouts
- Plugin-managed listing page or shortcode-based placement
- Responsive design controls and live previews

### Forms and entries

- Reusable default form with per-giveaway overrides
- First name, last name, email, phone, and terms fields
- Show, hide, require, and reorder built-in fields
- Additional custom fields and one- or two-column layouts
- One entry per email per giveaway by default
- Entry timestamps, moderation, deletion, and CSV export
- Participant and administrator email templates using WordPress `wp_mail()`

### Privacy and security

- Honeypot spam protection
- Optional Google reCAPTCHA v3 with a configuration test
- Nonces, capability checks, input sanitization, and late output escaping
- No Composer dependency or external email service requirement
- Entries are not automatically deleted unless retention is explicitly enabled

---

## Installation

**Via WordPress.org (recommended)**

1. Search for **PI Giveaways** in **Plugins → Add New**.
2. Install and activate the plugin.
3. Open **PI Giveaways → Settings** to configure the listing page, form, design, privacy, security, and email options.
4. Open **PI Giveaways → Add New** to publish your first giveaway.

**Manual**

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/pi-giveaways/).
2. Upload the `pi-giveaways` folder to `/wp-content/plugins/`.
3. Activate **PI Giveaways** from the Plugins screen.

---

## Shortcodes

Display the configured current and/or past giveaway listing:

```text
[pigiveaways_giveaways]
```

Display one complete giveaway by post ID:

```text
[pigiveaways_giveaway id="123"]
```

Display only the centered giveaway entry-form card:

```text
[pigiveaways_form id="123"]
```

Replace `123` with the giveaway post ID. The listing shortcode uses the layout and visibility options saved under **PI Giveaways → Settings → Design**.

---

## Screenshots

| Giveaway settings | Form builder | Listing design |
|:---:|:---:|:---:|
| ![Giveaway settings](https://ps.w.org/pi-giveaways/assets/screenshot-1.jpg) | ![Form builder](https://ps.w.org/pi-giveaways/assets/screenshot-2.jpg) | ![Listing design](https://ps.w.org/pi-giveaways/assets/screenshot-3.jpg) |

| Privacy and reCAPTCHA | Email templates |
|:---:|:---:|
| ![Privacy and reCAPTCHA](https://ps.w.org/pi-giveaways/assets/screenshot-4.jpg) | ![Email templates](https://ps.w.org/pi-giveaways/assets/screenshot-5.jpg) |

---

## Requirements

| | Minimum | Tested up to |
|---|---:|---:|
| WordPress | 6.2 | 7.1 |
| PHP | 7.4 | 8.x |

---

## Changelog

### 1.0.7 — 2026-10-23

- Added extension hooks for eligibility-aware winner confirmation workflows.
- Added configurable multiple-winner limits with manual enforcement and progress reporting.
- Added a post-award notification hook for extensions.

The complete release history is available in [`readme.txt`](readme.txt).

---

## Links

| | |
|---|---|
| 🔌 WordPress.org | [wordpress.org/plugins/pi-giveaways/](https://wordpress.org/plugins/pi-giveaways/) |
| 💬 Support forum | [wordpress.org/support/plugin/pi-giveaways/](https://wordpress.org/support/plugin/pi-giveaways/) |
| 🌐 Developer site | [parorrey.com](https://www.parorrey.com/) |
| ❤️ Donate | [parorrey.com/donate](https://www.parorrey.com/donate/) |

---

## Author

**Ali Qureshi** · [PI Media](https://www.parorrey.com/) · Lahore, Pakistan  
WordPress Plugin & Full Stack Developer

---

## License

PI Giveaways is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
