=== Form Steps for Contact Form 7 ===
Contributors: amitoshi
Tags: contact form 7, confirmation, multi step, confirm screen, form steps
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a confirm screen and multi-step pages to Contact Form 7. Review only checks the form: no email, no captcha used, no duplicate add-on entries.

== Description ==

**Form Steps** adds three things Contact Form 7 does not have, inside the form you already use:

* **A confirm screen** before sending, so visitors can check what they typed.
* **Multi-step forms** with Next and Previous buttons and a progress display.
* **A thank-you screen** or a redirect after sending.

Turn it on for a form with one switch. Nothing to rebuild, no extra pages, no shortcodes to move.

Start from one of five **ready-made example forms**. Try each one live in the admin, copy its form, CSS and HTML, or create it with one click, together with a draft page.

= Why it is different =

Most confirm and multi-step add-ons run a full Contact Form 7 submission when the visitor presses "Review" or "Next", and only skip the email. That fires every add-on hooked into the submission, so CRM, Google Sheets and database plugins can save the entry twice, and captcha tokens get used up.

Form Steps stops Contact Form 7 right after its own validation. On Review and Next:

* no email is sent,
* no spam check runs, so reCAPTCHA and Turnstile tokens stay valid,
* no add-on that saves or forwards entries runs.

When the visitor presses Send, Contact Form 7 runs its normal submission exactly once.

= Confirm screen =

* **In place** (recommended): each field is replaced by what the visitor typed. Your layout, labels and theme styles stay exactly as designed.
* **Summary table**: a clean table of labels and answers. Labels are found automatically from your form and can be renamed.
* Optional heading and message, editable button texts.
* Checkboxes, radio buttons, drop-downs, dates, file names and multi-line messages are shown properly. Passwords are masked.
* Back keeps every value, including uploaded files.
* Exclude a field with the CSS class `svfs-skip` (the Confirm Plus class `avoid-confirm` works too).

= Multi-step forms =

* Split a form by adding `[step "Contact details"]` tags. A "step" button in the form editor inserts them.
* Each Next press checks only the fields on that step, with Contact Form 7's own rules. A required field on step 3 never blocks step 1.
* Numbered steps with titles, a progress bar, or no progress display.
* The confirm screen can be the last step.
* If anything is wrong on the final send, the form jumps to the step with the error.
* No page per step, no cookies, no cache exclusions.

= Thank-you screen =

* Four styled templates: simple, check icon, "what happens next" and with a button.
* Show what the visitor sent: write [your-name] or any field name and it is filled in.
* Live preview while you edit, with sample answers.
* **Create with AI**: copies a ready prompt for ChatGPT, Claude or Gemini with your fields, the plugin's classes and the allowed HTML. Paste the answer back.

= Design without guessing =

* **Button design** per form or site-wide: theme default, filled, outline or rounded; colors; left, centre, right, full width or split; order; show or hide Back and Previous. A live preview shows the result as you click.
* **Five built-in Form styles** (navy, teal, warm, sky and dark cards) to pick per form. Stylesheets load only where such a form is shown.
* A **styling reference** in Help lists every class and CSS variable with copy-ready examples for WordPress's Additional CSS, plus the HTML the plugin adds. No need to inspect elements.
* **Examples tab**: five complete designs (contact, multi-step quote, booking, Japanese, dark). Each has a live preview, its Form, CSS and HTML output, the settings it uses, and an AI prompt to restyle it.

= Switching from "Contact Form 7 add confirm" =

Forms built for "Contact Form 7 add confirm" and its successors keep working without edits. Form Steps supports the same `[confirm]` and `[back]` tags and the `wpcf7c-elm-step1`, `wpcf7c-elm-step2` and `wpcf7c-elm-step3` classes. When it finds one of these plugins active, it shows a one-click button to deactivate it.

= Light and private =

* Plain JavaScript, no jQuery. Files load only on pages that show a form using these features.
* No tracking, no ads, no account, no external requests.
* Works with JavaScript turned off: the form then sends normally in one step.

= Tested with =

Contact Form 7 5.9 and 6.2.4, WordPress 6.5 and 7.1, PHP 7.4 and 8.3, Flamingo, Cloudflare Turnstile (Contact Form 7's built-in integration), several forms on one page.

= 日本語 =

Contact Form 7 に **確認画面**・**マルチステップ**・**送信完了画面** を追加するプラグインです。フォームごとにスイッチひとつで有効になります。

* 「入力内容を確認」ボタンでは、Contact Form 7 の入力チェックだけを行います。メール送信・スパムチェック・外部連携プラグインは動作しないため、データの二重登録や reCAPTCHA・Turnstile のエラーが起きません。
* 確認画面は「その場で表示」（レイアウトそのまま）と「一覧表」から選べます。
* 「Contact Form 7 add confirm」の `[confirm]`・`[back]` タグと `wpcf7c-elm-step1/2/3` クラスにそのまま対応しています。フォームを編集せずに乗り換えられます。
* `[step "タイトル"]` タグでフォームをステップに分割できます。
* 管理画面と送信画面は翻訳に対応しています（translate.wordpress.org）。

This plugin is not affiliated with or endorsed by Contact Form 7 or its developer.

== Installation ==

1. Install and activate Contact Form 7.
2. Install Form Steps from Plugins → Add New, or upload the plugin folder to `/wp-content/plugins/`, then activate it.
3. Go to Contact → Form Steps and turn on the confirm screen for a form. It is live at once.
4. For steps, button texts, the confirm style and the thank-you screen, open the form and use its **Steps** tab.

== Frequently Asked Questions ==

= Do I need to change my form? =

No. Turn on the confirm screen and the form's own submit button becomes the Review button. Add `[step]` tags only if you want several steps.

= Will my CRM, Google Sheets or database add-on save entries twice? =

No. Review and Next stop before Contact Form 7 runs any add-on. Add-ons run once, when the visitor presses Send.

= Does it work with reCAPTCHA and Cloudflare Turnstile? =

Yes. Review and Next do not run the spam check, so no token is used. Turnstile widgets stay visible on every step, and if Send is pressed while Turnstile is still issuing a fresh token, Form Steps waits a moment instead of failing.

= Does it work with file uploads? =

Yes. File names are shown on the confirm screen and the files are attached when the visitor sends. In multi-step forms, files are uploaded with the final send, not on every Next.

= Where do I put the [step] tags? =

On their own line, at the same level of the form. If you wrap fields in layout blocks, keep every step tag outside those blocks. If the tags are inside different blocks, the form safely stays a normal one-page form.

= I use "Contact Form 7 add confirm". How do I switch? =

Activate Form Steps. It shows a notice with a button to deactivate the old plugin. Your forms keep working without edits.

= Can I change the button texts? =

Yes. Site-wide defaults are under Contact → Form Steps → Defaults, and each form can override them in its Steps tab.

= Does it store visitor data? =

No. Form Steps stores only its own settings. Submissions are handled by Contact Form 7 as usual.

= How do I style the form? =

Pick a Form style, a button style and colors in the form's Steps tab. For anything more, use WordPress's Additional CSS. Help → Styling reference lists every class with an example. The Examples tab has five complete designs to copy.

= Can I use AI to design the thank-you screen or the form? =

Yes. "Create with AI" (thank-you screen) and "Restyle with AI" (Examples tab) copy a prompt with everything the AI needs. Paste it into your AI tool, then paste the result back.

= Where are the settings? =

Contact → Form Steps, for all forms and site-wide defaults. Each form also has a Steps tab in the Contact Form 7 editor.

= Does it work with page builders and popups? =

Forms added to the page after loading set themselves up when the visitor clicks into a field. Developers can also call `window.svfsInit( form )`.

== Screenshots ==

1. The confirm screen "in place": every answer appears where the field was, in your theme's style.
2. The summary-table confirm screen with a heading.
3. A multi-step form with numbered steps.
4. Contact → Form Steps: one switch per form, live at once.
5. The Steps tab in the Contact Form 7 editor, with pictures of each choice.
6. The Examples tab: try a complete form live, copy it, or create it with one click.
7. Button design with live preview.
8. The thank-you screen builder: templates, answers, preview and Create with AI.

== Changelog ==

= 1.0.0 =
* First release: confirm screen (in place or summary table), multi-step forms, thank-you screen or redirect, compatibility with "Contact Form 7 add confirm" tags and classes.
* Examples tab with five complete forms: live preview, copy, one-click create.
* Built-in Form styles, button design, styling reference.
* Thank-you screen builder with templates, answers and an AI prompt.

== Upgrade Notice ==

= 1.0.0 =
First release.
