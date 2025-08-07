=== Plugin Name ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: https://https://blueprintdigital.com//
Tags: comments, spam
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

# Jeanius WordPress Plugin

This repository contains the source code for the **Jeanius** plugin. It was generated from the WordPress Plugin Boilerplate and heavily customized.

## Directory Structure

- **jeanius/** – Main plugin folder used inside `wp-content/plugins/`
  - `jeanius.php` – Plugin bootstrap file. Loads all classes and starts the plugin.
  - `admin/` – Admin‑side assets and hooks.
  - `public/` – Public‑facing assets, shortcode and scripts.
  - `includes/` – Core logic shared by both sides.
  - `languages/` – Localization files (currently empty).
  - `uninstall.php` – Cleanup on uninstall.
  - `index.php` – Silence file to block directory browsing.
- **jeanius.zip** – Prebuilt distribution of the plugin.

## Key Classes

The bulk of the functionality lives under `jeanius/includes`:

- `class-jeanius.php` – Central class that loads dependencies and registers rewrite rules for the assessment wizard and results pages.
- `class-jeanius-loader.php` – Manages all WordPress hooks for the plugin.
- `class-jeanius-assessment-cpt.php` – Registers a custom post type to store user assessments.
- `class-jeanius-provisioner.php` – Ensures each user has a single assessment post.
- `class-jeanius-gravity.php` – Hooks into Gravity Forms to create/reset assessments when a form is submitted.
- `class-jeanius-consent.php` – Saves consent information from a Gravity Form.
- `class-jeanius-rest.php` – Defines REST API endpoints for saving stage data, generating reports via OpenAI and more.
- `class-jeanius-wizard-page.php` – Renders the various wizard pages and the final results page.
- `helpers.php` – Utility helper functions such as `current_assessment_id()`.

Admin/UI classes live in `admin/` and front‑end logic is in `public/`.

## Shortcode

`[jeanius_assessment]` – Displays the training screen and start button for the assessment wizard. Only logged‑in users with consent can proceed.

## REST Endpoints

The plugin registers several REST routes under `/wp-json/jeanius/v1/`:

- `POST /stage` – Save stage data from the wizard.
- `POST /review` – Save reordered timeline words.
- `POST /describe` – Save individual descriptions.
- `POST /generate` – Generate the final report using OpenAI.

Endpoints require the user to be logged in and use the standard WP REST nonce for authentication.

## OpenAI Integration

`includes/class-jeanius-rest.php` makes sequential calls to OpenAI to produce the final report. The API key is pulled from an ACF options field named `openai_api_key`. Make sure ACF Pro is installed and the key is set under **Jeanius Settings** in the admin.

## Development Notes

1. Copy the `jeanius` folder (or unzip `jeanius.zip`) into your WordPress installation’s `wp-content/plugins` directory and activate the plugin.
2. Gravity Forms and Advanced Custom Fields Pro are required for full functionality.
3. The custom post type `jeanius_assessment` stores user data. Helper `current_assessment_id()` (in `includes/helpers.php`) retrieves or creates the post for the current user.
4. Wizard pages are served via pretty permalinks such as `/jeanius-assessment/wizard/` and subsequent stages. Rewrite rules are added in `class-jeanius.php` – flush permalinks after activating if the pages do not load.
5. Results are generated on demand by visiting `/jeanius-assessment/results/`. This triggers the OpenAI workflow if the HTML copy fields are empty.

Refer to the individual class files for more detailed logic and to extend functionality.
