=== IPG — Intelligent Page Generator ===
Contributors: angelsrock
Tags: bulk pages, elementor, bulk posts, duplicate page
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate structured WordPress pages from templates with dynamic patterns, preview, rollback, exports, and Elementor-safe template architecture.

== Description ==

IPG — Intelligent Page Generator transforms bulk page creation into a modern structured workflow system for WordPress.

It helps site builders generate intelligently organized pages from a single source template using sequential ranges, dynamic title and slug patterns, preview generation, rollback protection, export tools, AJAX source-page search, and optional sequential tagging.

IPG is designed for Elementor-based layouts, structured lesson libraries, membership portals, educational systems, protected content environments, metadata-heavy publishing workflows, and Memberium + Keap content systems.


== Video Tutorial ==

Watch the full setup and workflow tutorial:
https://youtu.be/GykHNsGoUXE?si=TQ51Ix7o4AUe613m

== How to Use ==

1. Activate the plugin.
2. Go to: Dashboard → Tools → IPG
You can also hover over any page in Pages and select: Edit Page → IPG
3. Configure your generation settings and choose whether to create Draft or Published pages.
4. Click Generate.

The duplicated page or pages will be created automatically and saved as Drafts or Published pages based on your selected workflow settings.

== Features ==

* Guided Preview + Generate workflow
* Intelligent structured page generation from a source template
* Elementor-safe duplication that preserves builder metadata
* Memberium + Keap membership workflow support
* Optional sequential tag helper for protected-content systems
* Range-based page creation
* Dynamic title and slug patterns
* AJAX-powered source page search
* Manual title list mode
* Rollback protection for recent draft generations
* Export generation history as CSV, JSON, or Markdown
* Responsive two-column admin interface
* Works with Gutenberg, Classic Editor, and Elementor
* Translation-ready with included POT language template


== Minimum Requirements ==

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Administrator access recommended

== Recommended Environment ==

* WordPress 7.0.2 or later
* PHP 8.x
* Modern page builder and membership-based WordPress environments
* Memberium / Keap compatible workflows
* Modern hosting with adequate PHP memory limits for large batch generation workflows

Works with the Classic Editor, Gutenberg, and most Page Builders, including Elementor, Divi, Visual Composer, and more.

== Dynamic Variables ==

Use intelligent variables throughout your workflows:

* {n} - Current sequence number
* {prev} - Previous number
* {next} - Next number
* {range_start} - Starting range number
* {range_end} - Ending range number

Variables can be used in titles, slugs, tags, and protected content patterns.

== Rollback Protection ==

Recent generation batches can be safely rolled back.

Rollback only removes draft pages from the most recent generation batch. Published pages will not be deleted.

== Export Tools ==

Export recent generation history from the Recent Generations panel in multiple formats:

* CSV
* JSON
* Markdown

== Elementor Compatibility ==

IPG is designed to preserve Elementor metadata exactly as stored. It avoids parsing or mutating Elementor JSON and allows Elementor to regenerate styles safely after duplication.

This helps prevent broken layouts during template-based generation.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`
2. Activate the plugin
3. Go to Posts or Pages
4. Click Generate IPG Page
5. Use Tools > IPG — Intelligent Page Generator for intelligent structured generation workflows

== Frequently Asked Questions ==

= Does this support Elementor? =

Yes. IPG — Intelligent Page Generator is designed to preserve Elementor structures and metadata safely.

= Does rollback delete published pages? =

No. Rollback only removes draft pages from the most recent generation batch. Published pages will not be deleted.

= Can I export generation history? =

Yes. Recent generation history can be exported as CSV, JSON, or Markdown.

= Can I use this for Memberium and Keap workflows? =

Yes. IPG — Intelligent Page Generator includes structured workflows designed for protected content systems commonly used with Memberium and Keap.

== Changelog ==







= 2.6.1 =
* Performance Updates

= 2.6.0 =
* Updated for WordPress 7.1

= 2.5.30 =
* Added Improvements

= 2.5.29 =
* Updated for WordPress 7.3

= 2.5.28 =
* Update tags in readme.txt

= 2.5.27 =
* Minor compatibility improvements

= 2.5.26 =
* Mixed Improvements

= 2.5.25 =
* Updated for WordPress 7.2 UI

= 2.5.24 =
* Tested and verified compatibility with WordPress 7.0.2
* Restored the stable v2.5.20 interface as the release foundation
* Preserved contextual tooltips and the working Advanced Options layout
* Updated plugin metadata, stable tag, and release documentation
* Removed macOS metadata files from the distribution package

= 2.5.20 =
* Improved Preview + Generate interface consistency
* Fixed button icon persistence during packaged ZIP builds
* Refined Generate Draft Pages button styling and layout
* Improved admin UI spacing and visual alignment
* Enhanced packaging/build cleanup process
* Updated plugin metadata and release documentation
* Minor interface polish and workflow refinement

= 2.5.18 =
* Finalized admin UI button balance for the Preview + Generate workflow
* Reduced excessive spacing above Preview + Generate actions
* Refined internal button padding around text and icons
* Tightened Sequential Tag Helper button horizontal padding
* Preserved improved helper text spacing below the Sequential Tag Helper controls

= 2.5.16 =
* Polished admin button sizing and spacing for the refined WordPress 7.0 UI refresh
* Reduced Sequential Tag Helper button shadow and improved alignment
* Added more breathing room to the Preview + Generate section

= 2.5.15 =
* Refined admin UI colors for WordPress 7.0 compatibility
* Added softer lower-saturation blue palette for primary actions
* Updated internal asset versioning so refreshed admin CSS loads correctly

= 2.5.14 =
* Tested compatibility with WordPress 7.0
* Improved plugin compatibility and admin stability refinements
* Updated WordPress.org release support and metadata

= 2.5.13 =
* Removed explicit fclose call from export streaming for WordPress Plugin Check compatibility
* Preserved large batch export reliability through admin-post.php


= 2.5.12 =
* Improved export reliability for large generation batches
* Routed recent generation exports through WordPress admin-post.php
* Added safer file streaming and output-buffer handling for CSV, JSON, and Markdown exports


= 2.5.11 =
* Rebranded plugin to IPG — Intelligent Page Generator
* Improved WordPress.org review compatibility
* Improved intelligent generation workflow positioning
* Improved duplicate title and slug consistency
* Added Copy handling for bulk generation workflows
* Improved export naming consistency
* Improved admin workflow clarity and action labeling

= 2.5.1 =
* Added guided publishing workflow improvements
* Added draft and publish generation modes
* Added dynamic preview generation output states
* Added sticky preview modal action footer
* Improved Preview + Generate workflow clarity
* Improved responsive modal and generation interactions
* Improved rollback workflow styling and spacing
* Improved export workflow architecture
* Improved mobile and responsive UI behavior
* Refined generation confirmation workflows
* Refined publishing workflow UX
* Added translation-ready POT support
* Improved WordPress.org compatibility and Plugin Check compliance
* Improved overall visual hierarchy and workflow consistency

= 2.5.0 =
* Complete Version 2 workflow redesign
* Added intelligent two-column workflow architecture
* Added rollback generation history system
* Added export tools (CSV, JSON, Markdown)
* Added guided workflow interactions
* Added responsive SaaS-style admin interface
* Added mobile optimization improvements
* Added Recent Generations workflow enhancements
* Added export stability improvements
* Added single duplicate slug handling improvements
* Added plugin standards and compatibility cleanup
* Improved Elementor-safe duplication workflows
* Improved structured generation usability
* Plugin Check cleanup and standards improvements

= 2.2.8 =
* Added Preview Generation dry-run system
* Added intelligent generation verification
* Added contextual variable workflow
* Unified onboarding and How It Works flow
* Improved Advanced Options workflow
* Improved Memberium + KEAP generation support
* Refined accordion UI and guided workflow structure
* Improved overall UX and visual hierarchy

= 2.2.6 =
* Added guided card-based workflow layout
* Added collapsible Advanced Options section
* Improved Memberium + Protection workflow organization
* Added Smart Sequential Tag Helper for Keap/Memberium tag mapping
* Improved tooltip spacing and readability
* Improved Preview + Generate section styling
* Preserved existing generation engine behavior

= 2.2.3 =
* Added AJAX-powered source page search
* Added sequential tag pattern support
* Added Memberium tag ID mapping support
* Improved token validation system
* Added contextual tooltips and UI guidance
* Enhanced duplication architecture and scalability

== Upgrade Notice ==

= 2.5.22 =
Updates IPG for WordPress 7.0.2 while preserving the stable v2.5.20 tooltip behavior and Advanced Options interface.

= 2.5.20 =
Refreshes the admin UI colors with a softer WordPress 7.0-compatible palette and updates asset versioning so CSS changes load correctly.

= 2.5.14 =
Tested compatibility with WordPress 7.0 and updated WordPress.org release metadata.

= 2.5.13 =
Rebrands the plugin to IPG — Intelligent Page Generator and improves duplicate title/slug consistency, bulk Copy handling, export naming, admin action labels, and WordPress.org review compatibility.