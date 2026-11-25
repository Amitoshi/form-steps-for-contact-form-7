# Form Steps for Contact Form 7

Adds three things Contact Form 7 doesn't have, inside the form you already use:

- **A confirm screen** before sending, so visitors can check what they typed
- **Multi-step forms** with Next and Previous buttons and a progress display
- **A thank-you screen**, or a redirect after sending

You turn it on per form with one switch. There's nothing to rebuild, no extra pages, and no shortcodes to move.

> Submitted to the WordPress.org plugin directory (currently in review).

## Why I built it

A "please confirm your details" screen and multi-step layout are common requests on Contact Form 7 sites, but the usual way to get them means extra plugins or rebuilding the form. I wanted something that works on an existing form, keeps CF7's own validation, and doesn't send the mail until the visitor confirms.

Tested with Contact Form 7 5.9 and 6.2.4, WordPress 6.5 and 7.1, PHP 7.4 and 8.3, Flamingo, Cloudflare Turnstile, and several forms on one page.

## Features

- One switch per form, or turn it on with a `[confirm]` tag
- Steps are split by a form tag, with Next and Previous buttons and a progress bar
- The review screen only checks the form. It doesn't send mail, use the CAPTCHA, or create duplicate entries in add-ons like Flamingo.
- Fields can be left out of the review with the `svfs-skip` class
- Five ready-made example forms: try one live in the admin, copy it, or create it plus a draft page in one click
- Global defaults, per-form settings, and JSON import/export of settings
- Translation ready (Japanese included)
- A JS hook, `window.svfsInit( form )`, for forms loaded with AJAX

## How it's built

- **Uses CF7's own hooks:** `wpcf7_init` registers the form tags, `wpcf7_admin_init` and `wpcf7_save_contact_form` add a **Steps** tab to the CF7 editor, and the front end listens to CF7's DOM events (`wpcf7invalid`, `wpcf7mailsent`, `wpcf7submit`).
- **Code split into small classes:** `includes/` has the form tags, form render, editor panel, form settings, global settings, admin page, migration and requirements check.
- **Admin actions** go through `admin_post_*` and `wp_ajax_*` with nonces and capability checks (`wpcf7_edit_contact_forms`).
- **Front end:** vanilla JS, no jQuery dependency for the steps, with minified builds included.
- **Clean uninstall:** removes its options and post meta, and handles multisite.

Requires WordPress 6.2+, PHP 7.4+ and Contact Form 7.

## Install

Download the ZIP, go to **Plugins → Add New → Upload**, and activate it (Contact Form 7 must be active). Then open a form and use its **Steps** tab.

## License

GPL-2.0-or-later
