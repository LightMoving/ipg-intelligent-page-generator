<?php
/**
 * Plugin Name: IPG — Intelligent Page Generator
 * Plugin URI: https://github.com/Debo Grim/ipg-intelligent-page-generator/
 * Description: Intelligent bulk page generation for Elementor and membership workflows with dynamic variables, previews, rollback, exports, and builder-safe template architecture.
 * Version: 2.6.3
 * Author: Debo Grim
 * Author URI: https://github.com/Debo Grim/
 * Requires at least: 5.8
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ipg-intelligent-page-generator
 * Domain Path: /languages
 */

/*
 * IPG — Intelligent Page Generator - Generate structured WordPress pages from templates.
 * Copyright (C) 2026 Debo Grim
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IPG_INTELLIGENT_PAGE_GENERATOR_VERSION')) {
    define('IPG_INTELLIGENT_PAGE_GENERATOR_VERSION', '2.6.3');
}


class Precision_Duplicate {

    public function __construct() {
        add_filter('page_row_actions', [$this, 'add_link'], 10, 2);
        add_action('admin_action_precision_duplicate', [$this, 'duplicate']);
        add_action('admin_bar_menu', [$this, 'admin_bar'], 999);
        add_action('init', [$this, 'process_pending_elementor_css'], 99);
        add_action('admin_menu', [$this, 'add_bulk_tool_page']);
        add_action('wp_ajax_precision_duplicate_search_pages', [$this, 'ajax_search_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_ipg_export_recent_generation', [$this, 'handle_export_recent_generation']);
    }

    public function add_link($actions, $post) {
        if (!$post || $post->post_type !== 'page' || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=precision_duplicate&post=' . absint($post->ID)),
            'precision_duplicate_' . absint($post->ID)
        );

        $actions['precision_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate IPG Page', 'ipg-intelligent-page-generator') . '</a>';
        return $actions;
    }

    public function admin_bar($bar) {
        if (!is_singular('page') || is_admin()) {
            return;
        }

        $id = get_queried_object_id();
        if (!$id || !current_user_can('edit_post', $id)) {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=precision_duplicate&post=' . absint($id)),
            'precision_duplicate_' . absint($id)
        );

        $bar->add_node([
            'id'     => 'ipg-intelligent-page-generator',
            'parent' => 'edit',
            'title'  => esc_html__('Duplicate IPG Page', 'ipg-intelligent-page-generator'),
            'href'   => $url,
        ]);
    }


    private function regenerate_elementor_css($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return;
        }

        // Elementor's frontend CSS is generated from post meta. Since this plugin
        // copies meta at the database level to preserve JSON exactly, force all
        // relevant caches to forget the old/empty values before Elementor reads.
        delete_post_meta($post_id, '_elementor_css');
        wp_cache_delete($post_id, 'post_meta');
        clean_post_cache($post_id);

        if (!did_action('elementor/loaded')) {
            return;
        }

        try {
            if (isset(\Elementor\Plugin::$instance->files_manager)) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }

            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                if (method_exists('\Elementor\Core\Files\CSS\Post', 'create')) {
                    $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);
                } else {
                    $css_file = new \Elementor\Core\Files\CSS\Post($post_id);
                }

                if ($css_file) {
                    if (method_exists($css_file, 'delete')) {
                        $css_file->delete();
                    }
                    if (method_exists($css_file, 'update')) {
                        $css_file->update();
                    }
                }
            }
        } catch (\Throwable $e) {
            // Do not block duplication if Elementor CSS regeneration fails.
        }

        wp_cache_delete($post_id, 'post_meta');
        clean_post_cache($post_id);
    }

    public function process_pending_elementor_css() {
        if (!did_action('elementor/loaded')) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional small lookup for pending duplicate CSS regeneration.
        $pending = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 10",
                '_precision_duplicate_regenerate_elementor_css',
                '1'
            )
        );

        if (empty($pending)) {
            return;
        }

        foreach ($pending as $post_id) {
            $post_id = absint($post_id);
            $this->regenerate_elementor_css($post_id);
            delete_post_meta($post_id, '_precision_duplicate_regenerate_elementor_css');
        }
    }



    /**
     * Get the supported dynamic variables for generated pages.
     *
     * Keeping this in one place makes the replacement engine easier to expand
     * later for premium AI variables and conditional smartness.
     */
    private function get_supported_tokens() {
        return array(
            'n',
            'prev',
            'next',
            'range_start',
            'range_end',
        );
    }

    /**
     * Build one reusable context array for every generated page.
     */
    private function build_token_context($number, $range_start, $range_end) {
        $number      = absint($number);
        $range_start = absint($range_start);
        $range_end   = absint($range_end);

        return array(
            'n'           => $number,
            'prev'        => ($number > $range_start) ? $number - 1 : '',
            'next'        => ($number < $range_end) ? $number + 1 : '',
            'range_start' => $range_start,
            'range_end'   => $range_end,
        );
    }

    /**
     * Normalize typed/pasted variable braces before validation and replacement.
     */
    private function normalize_token_template($template) {
        $template = (string) $template;

        return str_replace(
            array('%7B', '%7D', '%7b', '%7d', '｛', '｝'),
            array('{', '}', '{', '}', '{', '}'),
            $template
        );
    }

    /**
     * Return true when a template includes at least one supported variable.
     */
    private function template_has_supported_token($template) {
        $template = $this->normalize_token_template($template);
        $tokens   = array_map('preg_quote', $this->get_supported_tokens());
        $pattern  = '/\{(' . implode('|', $tokens) . ')\}/';

        return (bool) preg_match($pattern, $template);
    }

    /**
     * Parse a pasted Memberium / Keap tag ID map.
     *
     * Accepted formats, one per line:
     * 502=10985
     * 502:10985
     * Text Day 502 (10985)
     */
    private function parse_memberium_tag_id_map($raw_map) {
        $raw_map = trim((string) $raw_map);
        $map     = array();

        if ($raw_map === '') {
            return $map;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw_map);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Standard mapping: 502=10985 or 502:10985
            if (preg_match('/^\s*(\d+)\s*[:=]\s*(\d+)\s*$/', $line, $matches)) {
                $map[absint($matches[1])] = absint($matches[2]);
                continue;
            }

            // Memberium display label: Text Day 502 (10985)
            if (preg_match('/(\d+)[^\d]+(\d+)\s*\)?\s*$/', $line, $matches)) {
                $map[absint($matches[1])] = absint($matches[2]);
                continue;
            }
        }

        return $map;
    }

    /**
     * Format a Memberium tag ID value for the Require Tag ID's meta field.
     */
    private function get_memberium_tag_id_for_number($number, $map) {
        $number = absint($number);

        if (!$number || empty($map[$number])) {
            return '';
        }

        return (string) absint($map[$number]);
    }

    /**
     * Force override copied Memberium / Keap required tag ID meta.
     *
     * Memberium commonly stores the Require Tag ID's value in is4wp_access_tags.
     * We also clear/update related access tag keys defensively so a copied source tag
     * cannot remain as the active protection value.
     */
    private function apply_memberium_access_tag_id($post_id, $tag_id) {
        $post_id = absint($post_id);
        $tag_id  = absint($tag_id);

        if (!$post_id || !$tag_id) {
            return;
        }

        $tag_value = (string) $tag_id;

        $possible_keys = array(
            'is4wp_access_tags',
            '_is4wp_access_tags',
            'memberium_access_tags',
            '_memberium_access_tags',
            'memb_access_tags',
            '_memb_access_tags',
            'i4w_access_tags',
            '_i4w_access_tags',
        );

        foreach ($possible_keys as $key) {
            update_post_meta($post_id, $key, $tag_value);
        }
    }

    /**
     * Central dynamic replacement engine.
     *
     * This is used by titles, slugs, source-page content, excerpts, and selected
     * plain text metadata during generation.
     */
    private function replace_tokens($template, $context = array()) {
        $template = $this->normalize_token_template($template);

        $defaults = array_fill_keys($this->get_supported_tokens(), '');
        $context  = wp_parse_args($context, $defaults);

        $replacements = array();
        foreach ($this->get_supported_tokens() as $token) {
            $replacements['{' . $token . '}'] = isset($context[$token]) ? (string) $context[$token] : '';
        }

        /**
         * Allows future add-ons/premium modules to add custom variables.
         *
         * Example: add {course_name}, {member_level}, or AI-generated values later
         * without rewriting the generator.
         */
        $replacements = apply_filters('precision_duplicate_token_replacements', $replacements, $context, $template);

        return strtr($template, $replacements);
    }

    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_ipg-intelligent-page-generator') {
            return;
        }

        $version = defined('IPG_INTELLIGENT_PAGE_GENERATOR_VERSION') ? IPG_INTELLIGENT_PAGE_GENERATOR_VERSION : '2.5.5';
        $asset_url = plugin_dir_url(__FILE__) . 'assets/';

        wp_enqueue_style(
            'ipg-intelligent-page-generator-admin',
            $asset_url . 'css/ipg-intelligent-page-generator-admin.css',
            array(),
            $version
        );

        wp_enqueue_script(
            'ipg-intelligent-page-generator-admin',
            $asset_url . 'js/ipg-intelligent-page-generator-admin.js',
            array(),
            $version,
            true
        );

        wp_add_inline_script(
            'ipg-intelligent-page-generator-admin',
            'window.ipgIntelligentPageGenerator = ' . wp_json_encode(array(
                'i18n' => array(
                    'noMatchingPages' => __('No matching pages found.', 'ipg-intelligent-page-generator'),
                    'untitledPage'    => __('Untitled page', 'ipg-intelligent-page-generator'),
                    'searchFailed'    => __('Search failed. Please try again.', 'ipg-intelligent-page-generator'),
                ),
            )) . ';',
            'before'
        );
    }

public function add_bulk_tool_page() {
        add_management_page(
            esc_html__('IPG — Intelligent Page Generator', 'ipg-intelligent-page-generator'),
            esc_html__('IPG — Intelligent Page Generator', 'ipg-intelligent-page-generator'),
            'edit_pages',
            'ipg-intelligent-page-generator',
            [$this, 'render_bulk_tool_page']
        );
    }

    /**
     * AJAX search for source pages in the bulk generator.
     */
    public function ajax_search_pages() {
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => __('You do not have permission to search pages.', 'ipg-intelligent-page-generator')), 403);
        }

        check_ajax_referer('precision_duplicate_page_search', 'nonce');

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        $term = trim($term);

        if (strlen($term) < 2) {
            wp_send_json_success(array('results' => array()));
        }

        $query_args = array(
            'post_type'      => 'page',
            'post_status'    => array('publish', 'draft', 'private', 'pending', 'future'),
            'posts_per_page' => 12,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            's'              => $term,
            'no_found_rows'  => true,
        );

        if (ctype_digit($term)) {
            $query_args['post__in'] = array(absint($term));
            unset($query_args['s']);
        }

        $query   = new WP_Query($query_args);
        $results = array();

        foreach ($query->posts as $page) {
            $results[] = array(
                'id'        => absint($page->ID),
                'title'     => get_the_title($page),
                'status'    => get_post_status($page),
                'edit_url'  => get_edit_post_link($page->ID, 'raw'),
                'permalink' => get_permalink($page->ID),
            );
        }

        wp_send_json_success(array('results' => $results));
    }

    /**
     * Render a small admin tooltip beside field labels.
     */
    
    private function save_recent_generation_batch($page_ids) {
        $page_ids = array_values(array_filter(array_map('absint', (array) $page_ids)));

        if (empty($page_ids)) {
            return;
        }

        update_option('precision_duplicate_recent_generation_batch', array(
            'page_ids'   => $page_ids,
            'created_at' => current_time('mysql'),
            'count'      => count($page_ids),
        ), false);
    }

    private function get_recent_generation_batch() {
        $batch = get_option('precision_duplicate_recent_generation_batch', array());

        if (!is_array($batch) || empty($batch['page_ids'])) {
            return array();
        }

        $batch['page_ids'] = array_values(array_filter(array_map('absint', (array) $batch['page_ids'])));
        $batch['count']    = count($batch['page_ids']);

        return $batch;
    }



    public function handle_export_recent_generation() {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to export generation history.', 'ipg-intelligent-page-generator'));
        }

        check_admin_referer('ipg_export_recent_generation', 'ipg_export_nonce');

        $export_format = isset($_POST['precision_duplicate_export_format']) ? sanitize_key(wp_unslash($_POST['precision_duplicate_export_format'])) : 'csv';

        $this->export_recent_generation_batch($export_format);
    }

    private function export_recent_generation_batch($format = 'csv') {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to export generation history.', 'ipg-intelligent-page-generator'));
        }

        $batch = $this->get_recent_generation_batch();

        if (empty($batch['page_ids'])) {
            wp_safe_redirect(add_query_arg('precision_duplicate_export_status', 'empty', admin_url('tools.php?page=ipg-intelligent-page-generator')));
            exit;
        }

        $format = in_array($format, array('csv', 'json', 'markdown'), true) ? $format : 'csv';
        $created_at = isset($batch['created_at']) ? sanitize_text_field($batch['created_at']) : current_time('mysql');
        $rows = array();

        foreach ($batch['page_ids'] as $page_id) {
            $post = get_post($page_id);

            $rows[] = array(
                'id'           => absint($page_id),
                'title'        => $post ? get_the_title($post) : '',
                'status'       => $post ? get_post_status($post) : 'missing',
                'edit_url'     => $post ? get_edit_post_link($page_id, 'raw') : '',
                'permalink'    => $post ? get_permalink($page_id) : '',
                'generated_at' => $created_at,
            );
        }

        $timestamp = gmdate('Ymd-His');
        $filename_base = 'ipg-recent-generation-' . $timestamp;

        while (ob_get_level()) {
            $status = ob_get_status();
            if (empty($status['del'])) {
                break;
            }
            ob_end_clean();
        }

        if (headers_sent()) {
            wp_die(esc_html__('The export could not start because output was already sent. Please try again.', 'ipg-intelligent-page-generator'));
        }

        nocache_headers();
        header('X-Content-Type-Options: nosniff');

        if ('json' === $format) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.json"');
            echo wp_json_encode(
                array(
                    'generated_at' => $created_at,
                    'count'        => count($rows),
                    'pages'        => $rows,
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
            exit;
        }

        if ('markdown' === $format) {
            header('Content-Type: text/markdown; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.md"');
            echo '# IPG — Intelligent Page Generator Recent Generation' . "\n\n";
            echo '- Generated at: ' . esc_html($created_at) . "\n";
            echo '- Page count: ' . absint(count($rows)) . "\n\n";
            echo "| ID | Title | Status | Edit URL | Permalink |\n";
            echo "|---:|---|---|---|---|\n";

            foreach ($rows as $row) {
                echo '| '
                    . absint($row['id']) . ' | '
                    . esc_html($this->escape_markdown_table_cell($row['title'])) . ' | '
                    . esc_html($this->escape_markdown_table_cell($row['status'])) . ' | '
                    . esc_html($this->escape_markdown_table_cell($row['edit_url'])) . ' | '
                    . esc_html($this->escape_markdown_table_cell($row['permalink'])) . " |\n";
            }
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');

        $output = fopen('php://output', 'w');

        if (false === $output) {
            wp_die(esc_html__('Unable to create the export file.', 'ipg-intelligent-page-generator'));
        }

        fputcsv($output, array('ID', 'Title', 'Status', 'Edit URL', 'Permalink', 'Generated At'));

        foreach ($rows as $row) {
            fputcsv($output, array(
                $row['id'],
                $row['title'],
                $row['status'],
                $row['edit_url'],
                $row['permalink'],
                $row['generated_at'],
            ));
        }

        exit;
    }

    private function escape_markdown_table_cell($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = str_replace(array("\r", "\n"), ' ', $value);
        $value = str_replace('|', '\\|', $value);
        return trim($value);
    }

    private function rollback_recent_generation_batch() {
        $batch = $this->get_recent_generation_batch();

        if (empty($batch['page_ids'])) {
            return array('deleted' => 0, 'skipped' => 0);
        }

        $deleted = 0;
        $skipped = 0;

        foreach ($batch['page_ids'] as $page_id) {
            $post = get_post($page_id);

            if (!$post || $post->post_status !== 'draft') {
                $skipped++;
                continue;
            }

            if (wp_delete_post($page_id, true)) {
                $deleted++;
            } else {
                $skipped++;
            }
        }

        delete_option('precision_duplicate_recent_generation_batch');

        return array('deleted' => $deleted, 'skipped' => $skipped);
    }

    private function render_recent_generations_panel() {
        $batch = $this->get_recent_generation_batch();
        ?>
        <div class="precision-duplicate-card precision-duplicate-recent-card" style="background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%) !important;border:1px solid #dbe7f3 !important;border-radius:14px !important;box-shadow:0 8px 22px rgba(15,23,42,.06) !important;overflow:hidden !important;">
            <div class="precision-duplicate-card-header" style="padding:20px 24px 12px !important;">
                <h2 style="margin:0 0 10px !important;"><?php echo esc_html__('Recent Generations', 'ipg-intelligent-page-generator'); ?></h2>
                <p><?php echo esc_html__('Review the most recent generation batch, export it, or roll back created draft pages if needed.', 'ipg-intelligent-page-generator'); ?></p>
            </div>
            <div class="precision-duplicate-card precision-duplicate-command-card-body" style="padding:18px 24px 22px !important;">
                <?php if (empty($batch['page_ids'])) : ?>
                    <p class="description"><?php echo esc_html__('No recent generation batch is currently available for rollback.', 'ipg-intelligent-page-generator'); ?></p>
                <?php else : ?>
                    <p>
                        <strong><?php echo esc_html__('Last generation batch:', 'ipg-intelligent-page-generator'); ?></strong>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: number of generated pages, 2: generation date and time. */
                                _n('%1$d page generated on %2$s.', '%1$d pages generated on %2$s.', absint($batch['count']), 'ipg-intelligent-page-generator'),
                                absint($batch['count']),
                                isset($batch['created_at']) ? $batch['created_at'] : ''
                            )
                        );
                        ?>
                    </p>
                    <details class="precision-duplicate-recent-details">
                        <summary><?php echo esc_html__('View generated page IDs', 'ipg-intelligent-page-generator'); ?></summary>
                        <p><code><?php echo esc_html(implode(', ', $batch['page_ids'])); ?></code></p>
                    </details>
                    <p class="description"><?php echo esc_html__('Rollback only deletes draft pages from the most recent generation batch. Published pages or changed pages are skipped for safety.', 'ipg-intelligent-page-generator'); ?></p>
                    <div class="precision-duplicate-recent-actions">
                        <form method="post" class="precision-duplicate-rollback-form" novalidate>
                            <?php wp_nonce_field('precision_duplicate_bulk_pages', 'precision_duplicate_bulk_nonce'); ?>
                            <input type="hidden" name="precision_duplicate_rollback_last" value="1">
                            <button type="submit"
                                    class="button button-secondary precision-duplicate-rollback-button"
                                    formnovalidate="formnovalidate"
                                    onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete the last generated draft pages? This action only removes draft pages from the most recent generation batch. Published pages will not be deleted.', 'ipg-intelligent-page-generator')); ?>');">
                                <?php echo esc_html__('Rollback Last Generated Pages', 'ipg-intelligent-page-generator'); ?>
                            </button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="precision-duplicate-export-form" novalidate>
                            <?php wp_nonce_field('ipg_export_recent_generation', 'ipg_export_nonce'); ?>
                            <input type="hidden" name="action" value="ipg_export_recent_generation">
                            <label class="screen-reader-text" for="precision-duplicate-export-format"><?php echo esc_html__('Export format', 'ipg-intelligent-page-generator'); ?></label>
                            <select id="precision-duplicate-export-format" name="precision_duplicate_export_format">
                                <option value="csv"><?php echo esc_html__('CSV', 'ipg-intelligent-page-generator'); ?></option>
                                <option value="json"><?php echo esc_html__('JSON', 'ipg-intelligent-page-generator'); ?></option>
                                <option value="markdown"><?php echo esc_html__('Markdown', 'ipg-intelligent-page-generator'); ?></option>
                            </select>
                            <button type="submit" class="button button-secondary precision-duplicate-export-button">
                                <?php echo esc_html__('Export', 'ipg-intelligent-page-generator'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }


private function render_tooltip($text) {
        if (empty($text)) {
            return;
        }

        ?>
        <span class="precision-duplicate-help-wrap" aria-label="<?php echo esc_attr($text); ?>">
            <span class="precision-duplicate-help-icon" aria-hidden="true">?</span>
            <span class="precision-duplicate-help-text"><?php echo esc_html($text); ?></span>
        </span>
        <?php
    }

    /**
     * Print lightweight tooltip styles for the bulk tool page.
     */
    private function print_tooltip_styles() {
        ?>
        <style>
            .precision-duplicate-wrap {
                --ipg-blue: #216fae;
                --ipg-blue-dark: #185987;
                --ipg-sage: #edf7f3;
                --ipg-border: #dbe8ef;
                --ipg-text: #1d3142;
            }

            .precision-duplicate-wrap .precision-duplicate-card,
            .precision-duplicate-wrap .precision-duplicate-side-card {
                border: 1px solid var(--ipg-border);
                border-radius: 16px;
                background: #fff;
                box-shadow: 0 8px 22px rgba(33, 111, 174, 0.08);
                overflow: hidden;
                margin-bottom: 18px;
            }

            .precision-duplicate-wrap .precision-duplicate-card-header {
                padding: 18px 20px 14px;
                background: linear-gradient(135deg, #f7fbff 0%, #edf7f3 100%);
                border-bottom: 1px solid var(--ipg-border);
            }

            .precision-duplicate-wrap .precision-duplicate-card-header h2,
            .precision-duplicate-wrap .precision-duplicate-side-card h2 {
                margin: 0 0 4px;
                color: var(--ipg-text);
            }

            .precision-duplicate-wrap .precision-duplicate-card-header p,
            .precision-duplicate-wrap .precision-duplicate-side-card p {
                margin: 0;
            }

            .precision-duplicate-wrap .precision-duplicate-card-body {
                padding: 16px 20px 18px;
            }

            .precision-duplicate-wrap .form-table th,
            .precision-duplicate-wrap .form-table td {
                padding-top: 12px;
                padding-bottom: 12px;
            }

            .precision-duplicate-wrap .precision-duplicate-sequential-helper {
                gap: 10px !important;
                align-items: flex-end;
            }

            .precision-duplicate-wrap .precision-duplicate-icon-button {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                gap: 7px;
                border-radius: 9px !important;
                box-shadow: 0 5px 12px rgba(33, 111, 174, 0.10) !important;
                transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
            }

            .precision-duplicate-wrap .precision-duplicate-icon-button:hover {
                transform: translateY(-1px);
                box-shadow: 0 7px 16px rgba(33, 111, 174, 0.14) !important;
            }

            .precision-duplicate-wrap .precision-duplicate-icon-button .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
                line-height: 16px;
            }

            .precision-duplicate-wrap #precision_duplicate_generate_memberium_map {
                min-height: 32px;
                padding: 3px 11px !important;
                font-size: 12px;
                line-height: 1.35;
            }

            .precision-duplicate-wrap .precision-duplicate-preview-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                margin-top: 2px;
                margin-bottom: 8px;
            }

            .precision-duplicate-wrap .precision-duplicate-preview-actions .button {
                min-height: 44px;
                padding: 12px 18px !important;
                font-size: 14px;
                font-weight: 600;
            }

            .precision-duplicate-wrap #precision_duplicate_generate_submit {
                background: var(--ipg-blue) !important;
                border-color: var(--ipg-blue) !important;
            }

            .precision-duplicate-wrap #precision_duplicate_generate_submit:hover,
            .precision-duplicate-wrap #precision_duplicate_generate_submit:focus {
                background: var(--ipg-blue-dark) !important;
                border-color: var(--ipg-blue-dark) !important;
            }

            .precision-duplicate-wrap .precision-duplicate-preview-box {
                margin-top: 12px;
            }
        </style>
        <?php
    }

    public function render_bulk_tool_page() {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to access this tool.', 'ipg-intelligent-page-generator'));
        }

        $created = [];
        $errors  = [];

        $source_value = '';
        $mode_value = 'range';
        $titles_value = '';
        $range_start_value = 1;
        $range_end_value = 10;
        $title_pattern_value = 'Page {n}';
        $slug_pattern_value = 'page-{n}';
        $tag_pattern_value = '';
        $memberium_tag_map_value = '';
        $parent_value = 0;
        $menu_order_start_value = 0;
        $generation_status_value = 'draft';
        if (isset($_POST['precision_duplicate_rollback_last'])) {
            check_admin_referer('precision_duplicate_bulk_pages', 'precision_duplicate_bulk_nonce');

            $rollback_result = $this->rollback_recent_generation_batch();

            $messages[] = sprintf(
                /* translators: 1: number of draft pages deleted, 2: number of pages skipped. */
                esc_html__('Successfully deleted %1$d generated draft page(s). %2$d page(s) were skipped because they were no longer drafts.', 'ipg-intelligent-page-generator'),
                absint($rollback_result['deleted']),
                absint($rollback_result['skipped'])
            );
        }

        if (isset($_POST['precision_duplicate_bulk_submit'])) {
            check_admin_referer('precision_duplicate_bulk_pages', 'precision_duplicate_bulk_nonce');

            $source_value = isset($_POST['precision_duplicate_source_page']) ? sanitize_text_field(wp_unslash($_POST['precision_duplicate_source_page'])) : '';
            $mode_value = isset($_POST['precision_duplicate_generation_mode']) ? sanitize_key(wp_unslash($_POST['precision_duplicate_generation_mode'])) : 'range';
            $precision_duplicate_titles_sanitized = isset($_POST['precision_duplicate_titles']) ? sanitize_textarea_field(wp_unslash($_POST['precision_duplicate_titles'])) : '';

            $titles_value = $precision_duplicate_titles_sanitized;
            $range_start_value = isset($_POST['precision_duplicate_range_start']) ? absint($_POST['precision_duplicate_range_start']) : 0;
            $range_end_value = isset($_POST['precision_duplicate_range_end']) ? absint($_POST['precision_duplicate_range_end']) : 0;
            $title_pattern_value = isset($_POST['precision_duplicate_title_pattern']) ? sanitize_text_field(wp_unslash($_POST['precision_duplicate_title_pattern'])) : 'Page {n}';
            $slug_pattern_value = isset($_POST['precision_duplicate_slug_pattern']) ? sanitize_text_field(wp_unslash($_POST['precision_duplicate_slug_pattern'])) : 'page-{n}';
            // Normalize token braces so pasted/typed variants still validate before final slug sanitizing.
            $slug_pattern_value = str_replace(array('%7B', '%7D', '｛', '｝'), array('{', '}', '{', '}'), $slug_pattern_value);
            $tag_pattern_value = isset($_POST['precision_duplicate_tag_pattern']) ? sanitize_text_field(wp_unslash($_POST['precision_duplicate_tag_pattern'])) : '';
            $tag_pattern_value = $this->normalize_token_template($tag_pattern_value);
            $memberium_tag_map_value = isset($_POST['precision_duplicate_memberium_tag_map']) ? sanitize_textarea_field(wp_unslash($_POST['precision_duplicate_memberium_tag_map'])) : '';
            $memberium_tag_map = $this->parse_memberium_tag_id_map($memberium_tag_map_value);
            $parent_value = isset($_POST['precision_duplicate_parent_page']) ? absint($_POST['precision_duplicate_parent_page']) : 0;
            $menu_order_start_value = isset($_POST['precision_duplicate_menu_order_start']) ? intval($_POST['precision_duplicate_menu_order_start']) : 0;
            $generation_status_value = isset($_POST['precision_duplicate_generation_status']) ? sanitize_key(wp_unslash($_POST['precision_duplicate_generation_status'])) : 'draft';
            $generation_status_value = in_array($generation_status_value, array('draft', 'publish'), true) ? $generation_status_value : 'draft';

            $source_id = $this->resolve_source_page_input($source_value);
            $items = [];

            if (!$source_id || !get_post($source_id)) {
                $errors[] = esc_html__('Please enter a valid source page ID or page URL.', 'ipg-intelligent-page-generator');
            }

            if ($mode_value === 'range') {
                if (!$range_start_value || !$range_end_value || $range_end_value < $range_start_value) {
                    $errors[] = esc_html__('Please enter a valid range. The end number must be greater than or equal to the start number.', 'ipg-intelligent-page-generator');
                }

                if (empty($title_pattern_value) || !$this->template_has_supported_token($title_pattern_value)) {
                    $errors[] = esc_html__('The title pattern must include at least one variable: {n}, {prev}, {next}, {range_start}, or {range_end}.', 'ipg-intelligent-page-generator');
                }

                if (empty($slug_pattern_value) || !$this->template_has_supported_token($slug_pattern_value)) {
                    $errors[] = esc_html__('The slug pattern must include at least one variable: {n}, {prev}, {next}, {range_start}, or {range_end}.', 'ipg-intelligent-page-generator');
                }

                if (!empty($tag_pattern_value) && !$this->template_has_supported_token($tag_pattern_value)) {
                    $errors[] = esc_html__('The tag pattern must include at least one variable: {n}, {prev}, {next}, {range_start}, or {range_end}.', 'ipg-intelligent-page-generator');
                }

                if (!empty($memberium_tag_map_value)) {
                    if (empty($memberium_tag_map)) {
                        $errors[] = esc_html__('The Memberium tag ID map could not be read. Use one line per item, such as 503=10987.', 'ipg-intelligent-page-generator');
                    } else {
                        for ($check_n = $range_start_value; $check_n <= $range_end_value; $check_n++) {
                            if (empty($memberium_tag_map[$check_n])) {
                                $errors[] = sprintf(
                                    /* translators: %d: missing range number */
                                    esc_html__('The Memberium tag ID map is missing a tag ID for %d.', 'ipg-intelligent-page-generator'),
                                    absint($check_n)
                                );
                                break;
                            }
                        }
                    }
                }

                if (($range_end_value - $range_start_value) > 500) {
                    $errors[] = esc_html__('For safety, create no more than 501 pages at a time.', 'ipg-intelligent-page-generator');
                }

                if (empty($errors)) {
                    for ($n = $range_start_value; $n <= $range_end_value; $n++) {

                        $token_context = $this->build_token_context($n, $range_start_value, $range_end_value);

                        $items[] = [
                            'number' => $n,
                            'title'  => $this->replace_tokens($title_pattern_value, $token_context),
                            'slug'   => $this->ensure_copy_slug_suffix($this->replace_tokens($slug_pattern_value, $token_context)),
                            'tag'    => !empty($tag_pattern_value) ? sanitize_text_field($this->replace_tokens($tag_pattern_value, $token_context)) : '',
                            'memberium_access_tag_id' => !empty($memberium_tag_map_value) ? $this->get_memberium_tag_id_for_number($n, $memberium_tag_map) : '',
                            'context' => $token_context,
                        ];
                    }
                }
            } else {
                $titles = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $titles_value)));
                if (empty($titles)) {
                    $errors[] = esc_html__('Please enter at least one page title.', 'ipg-intelligent-page-generator');
                }

                if (empty($errors)) {
                    foreach ($titles as $index => $title) {
                        $items[] = [
                            'number' => $index + 1,
                            'title'  => sanitize_text_field($title),
                            'slug'   => $this->ensure_copy_slug_suffix($title),
                            'tag'    => '',
                            'memberium_access_tag_id' => '',
                            'context' => array(),
                        ];
                    }
                }
            }

            if (empty($errors)) {
                $generated_page_ids = array();
                foreach ($items as $index => $item) {
                    $new_id = $this->create_duplicate_page(
                        $source_id,
                        $item['title'],
                        [
                            'slug'       => $item['slug'],
                            'parent'     => $parent_value,
                            'menu_order'    => $menu_order_start_value + $index,
                            'token_context' => isset($item['context']) ? $item['context'] : array(),
                            'tag_name'      => isset($item['tag']) ? $item['tag'] : '',
                            'memberium_access_tag_id' => isset($item['memberium_access_tag_id']) ? $item['memberium_access_tag_id'] : '',
                            'post_status'  => $generation_status_value,
                            'force_copy_suffix' => true,
                        ]
                    );

                    if (is_wp_error($new_id)) {
                        $errors[] = sprintf(
                            /* translators: %s: page title */
                            esc_html__('Could not create page for "%s".', 'ipg-intelligent-page-generator'),
                            esc_html($item['title'])
                        );
                        continue;
                    }
                    $generated_page_ids[] = absint($new_id);
                    $created[] = $new_id;
                }
            }
        }

            if (!empty($created)) {
                $this->save_recent_generation_batch($created);
            }

        ?>
        <?php $this->print_tooltip_styles(); ?>
        <div class="wrap precision-duplicate-wrap">

        <?php if (!empty($messages)) : ?>
            <div class="notice notice-success is-dismissible ipg-intelligent-page-generator-admin-notice">
                <?php foreach ($messages as $message) : ?>
                    <p><?php echo esc_html($message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
            <div class="notice notice-error ipg-intelligent-page-generator-admin-notice">
                <?php foreach ($errors as $error) : ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


            <h1><?php echo esc_html__('IPG — Intelligent Page Generator: Intelligent Page Generation', 'ipg-intelligent-page-generator'); ?></h1>
<p class="precision-duplicate-intro"><?php echo esc_html__('Generate structured pages from one Elementor-safe source page. Use range creation, title patterns, slug patterns, tags, and optional Memberium tag ID mapping for protected membership workflows.', 'ipg-intelligent-page-generator'); ?></p>

            <?php if (!empty($created)) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            /* translators: %d: number of created pages */
                            'publish' === $generation_status_value ? esc_html__('Published %d page(s).', 'ipg-intelligent-page-generator') : esc_html__('Created %d draft page(s).', 'ipg-intelligent-page-generator'),
                            count($created)
                        );
                        ?>
                    </p>
                    <ul>
                        <?php foreach ($created as $created_id) : ?>
                            <li><a href="<?php echo esc_url(get_edit_post_link($created_id)); ?>"><?php echo esc_html(get_the_title($created_id)); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error is-dismissible">
                    <?php foreach ($errors as $error) : ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <!-- Legacy full-width how card removed for V2 two-column layout. -->

            <div class="precision-duplicate-two-column-dashboard precision-duplicate-roadmap-layout-ready" style="display:grid;grid-template-columns:minmax(0,1.42fr) minmax(340px,.82fr);gap:28px;align-items:start;width:100%;max-width:100%;">
                    <div class="precision-duplicate-main-column">
                        <form method="post" action="" class="precision-duplicate-builder-form" novalidate autocomplete="off">
                            <?php wp_nonce_field('precision_duplicate_bulk_pages', 'precision_duplicate_bulk_nonce'); ?>
                        <div class="precision-duplicate-card precision-duplicate-source-card">
                            <div class="precision-duplicate-card-header"><h2>Source + Generation Setup</h2><p>Choose the source page and define the range to generate.</p></div>
                            <div class="precision-duplicate-card-body"><table class="form-table" role="presentation"><tbody>
<tr>
                            <th scope="row"><label for="precision_duplicate_source_page"><?php echo esc_html__('Source Page URL or ID', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('This is the page that will be duplicated. You can paste a public page URL, WordPress edit URL, or numeric page ID.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="text" class="regular-text" id="precision_duplicate_source_page" name="precision_duplicate_source_page" value="<?php echo esc_attr($source_value); ?>" placeholder="https://example.com/my-page/ or 123" autocomplete="off" autocapitalize="off" spellcheck="false" aria-required="true">
                                <p class="description"><?php echo esc_html__('Paste the source page URL, numeric page ID, WordPress edit URL, or use AJAX search below.', 'ipg-intelligent-page-generator'); ?></p>
                                <div class="precision-duplicate-page-search-wrap" data-nonce="<?php echo esc_attr(wp_create_nonce('precision_duplicate_page_search')); ?>">
                                    <div class="precision-duplicate-page-search-row">
                                        <input type="search" id="precision_duplicate_page_search" class="regular-text" placeholder="<?php echo esc_attr__('Search pages by title or ID...', 'ipg-intelligent-page-generator'); ?>" autocomplete="off">
                                        <span class="spinner" id="precision_duplicate_page_search_spinner"></span>
                                    </div>
                                    <div class="precision-duplicate-page-search-results" id="precision_duplicate_page_search_results" aria-live="polite"></div>
                                    <p class="description"><?php echo esc_html__('Start typing a page title, then click a result to use it as the source page.', 'ipg-intelligent-page-generator'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Generation Mode', 'ipg-intelligent-page-generator'); ?><?php $this->render_tooltip(__('Choose Range + Patterns to generate numbered pages automatically, or Manual Title List to paste custom page titles.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <label><input type="radio" name="precision_duplicate_generation_mode" value="range" <?php checked($mode_value, 'range'); ?>> <?php echo esc_html__('Range + Patterns', 'ipg-intelligent-page-generator'); ?></label><br>
                                <label><input type="radio" name="precision_duplicate_generation_mode" value="manual" <?php checked($mode_value, 'manual'); ?>> <?php echo esc_html__('Manual Title List', 'ipg-intelligent-page-generator'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Range Creation', 'ipg-intelligent-page-generator'); ?></th>
                            <td>
                                <label for="precision_duplicate_range_start"><?php echo esc_html__('Start', 'ipg-intelligent-page-generator'); ?></label>
                                <input type="number" id="precision_duplicate_range_start" name="precision_duplicate_range_start" value="<?php echo esc_attr((string) $range_start_value); ?>" min="1" class="small-text">
                                <label for="precision_duplicate_range_end" style="margin-left:12px;"><?php echo esc_html__('End', 'ipg-intelligent-page-generator'); ?></label>
                                <input type="number" id="precision_duplicate_range_end" name="precision_duplicate_range_end" value="<?php echo esc_attr((string) $range_end_value); ?>" min="1" class="small-text">
                                <p class="description"><?php echo esc_html__('Example: 1 to 365 for a full lesson library.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        </tbody></table></div></div><div class="precision-duplicate-card precision-duplicate-naming-card"><div class="precision-duplicate-card-header"><h2>Naming + Structure</h2><p>Use variables to create titles, slugs, and optional WordPress tags.</p></div><div class="precision-duplicate-card-body">

<div class="precision-duplicate-context-vars">
    <strong><?php echo esc_html__('Available Variables:', 'ipg-intelligent-page-generator'); ?></strong>
    <code>{n}</code>
    <code>{prev}</code>
    <code>{next}</code>
    <code>{range_start}</code>
    <code>{range_end}</code>
</div>
<table class="form-table" role="presentation"><tbody>
<tr>
                            <th scope="row"><label for="precision_duplicate_title_pattern"><?php echo esc_html__('Title Pattern', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Controls the generated page titles. Example: Day Text {n}. You can also use {prev}, {next}, {range_start}, and {range_end}.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="text" class="regular-text" id="precision_duplicate_title_pattern" name="precision_duplicate_title_pattern" value="<?php echo esc_attr($title_pattern_value); ?>" placeholder="Workbook Lesson {n}">
                                <p class="description"><?php echo esc_html__('Use variables to build each generated title. Example: Workbook Lesson {n}.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="precision_duplicate_slug_pattern"><?php echo esc_html__('Slug Pattern', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Controls the URL slug for each generated page. Example: day-text-{n}. WordPress will automatically clean the final slug.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="text" class="regular-text" id="precision_duplicate_slug_pattern" name="precision_duplicate_slug_pattern" value="<?php echo esc_attr($slug_pattern_value); ?>" placeholder="workbook-lesson-{n}">
                                <p class="description"><?php echo esc_html__('Use variables to build each generated URL slug. Example: acim-workbook-lesson-{n}.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="precision_duplicate_tag_pattern"><?php echo esc_html__('Tag Pattern', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Optional. Adds a sequential WordPress tag to each generated page. Example: Text Day {n} creates Text Day 503, Text Day 504, and so on.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="text" class="regular-text" id="precision_duplicate_tag_pattern" name="precision_duplicate_tag_pattern" value="<?php echo esc_attr($tag_pattern_value); ?>" placeholder="Text Day {n}">
                                <p class="description"><?php echo esc_html__('Optional. Leave blank to skip tags. Example: Text Day {n}. Missing tags will be created automatically.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        </tbody></table></div></div><div class="precision-duplicate-card precision-duplicate-memberium-card"><div class="precision-duplicate-card-header"><h2>Memberium + Protection</h2><p>Map Keap tag IDs for protected page systems, with an optional sequential helper.</p></div><div class="precision-duplicate-card-body"><table class="form-table" role="presentation"><tbody>
<tr>
                            <th scope="row"><label for="precision_duplicate_memberium_tag_map"><?php echo esc_html__('Memberium Tag ID Map', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Optional. Overrides the copied Memberium Require Tag ID for each generated page. Paste one mapping per line, such as 503=10987.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <textarea class="large-text code" rows="6" id="precision_duplicate_memberium_tag_map" name="precision_duplicate_memberium_tag_map" placeholder="503=10987&#10;504=10989&#10;505=10991"><?php echo esc_textarea($memberium_tag_map_value); ?></textarea>
                                <p class="description"><?php echo esc_html__('Optional. If blank, Memberium protection is copied exactly from the source page. If filled, this overrides the copied source tag and each generated page receives its mapped Keap tag ID in Memberium Require Tag ID\'s.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php echo esc_html__('Sequential Tag Helper', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Optional helper. Use this when Keap tag IDs are sequential. It fills the editable Memberium Tag ID Map for review before generation.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <div class="precision-duplicate-sequential-helper" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
                                    <label>
                                        <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Start Page #', 'ipg-intelligent-page-generator'); ?></span>
                                        <input type="number" id="precision_duplicate_helper_start_number" class="small-text" placeholder="503">
                                    </label>
                                    <label>
                                        <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Start Tag ID', 'ipg-intelligent-page-generator'); ?></span>
                                        <input type="number" id="precision_duplicate_helper_start_tag_id" class="regular-text" style="width:120px;" placeholder="10987">
                                    </label>
                                    <label>
                                        <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Increment', 'ipg-intelligent-page-generator'); ?></span>
                                        <input type="number" id="precision_duplicate_helper_increment" class="small-text" value="1">
                                    </label>
                                    <button type="button" class="button ipg-generate-map-button" id="precision_duplicate_generate_memberium_map"><span class="dashicons dashicons-networking ipg-generate-map-icon" aria-hidden="true" style="color:#ffffff !important; fill:#ffffff !important;"></span><span class="ipg-generate-map-label"><?php echo esc_html__('Generate Map', 'ipg-intelligent-page-generator'); ?></span></button>
                                </div>
                                <p class="description ipg-sequential-helper-note"><?php echo esc_html__('This fills the Memberium Tag ID Map using the current range. Review and edit the map before generating pages, especially if Keap tags were created out of sequence.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        </tbody></table></div></div><div class="precision-duplicate-card"><button type="button" class="precision-duplicate-advanced-toggle"><h2>Advanced Options</h2><span><span class="precision-duplicate-toggle-label">Show</span> options</span></button><div class="precision-duplicate-advanced-body"><table class="form-table" role="presentation"><tbody>
<tr>
                            <th scope="row"><label for="precision_duplicate_parent_page"><?php echo esc_html__('Parent Page ID (optional)', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Optional. Enter a parent page ID if you want the generated pages placed underneath another page in WordPress.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="number" class="small-text" id="precision_duplicate_parent_page" name="precision_duplicate_parent_page" value="<?php echo esc_attr((string) $parent_value); ?>" min="0">
                                <p class="description"><?php echo esc_html__('Optional: assign generated pages under an existing parent page.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="precision_duplicate_menu_order_start"><?php echo esc_html__('Menu Order Start', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Optional. Sets the first menu order number. Each generated page increases by one.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <input type="number" class="small-text" id="precision_duplicate_menu_order_start" name="precision_duplicate_menu_order_start" value="<?php echo esc_attr((string) $menu_order_start_value); ?>">
                                <p class="description"><?php echo esc_html__('Optional: set a starting menu order for generated pages.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="precision_duplicate_titles"><?php echo esc_html__('Manual Page Titles', 'ipg-intelligent-page-generator'); ?></label><?php $this->render_tooltip(__('Used only with Manual Title List mode. Add one page title per line.', 'ipg-intelligent-page-generator')); ?></th>
                            <td>
                                <textarea class="large-text code" rows="8" id="precision_duplicate_titles" name="precision_duplicate_titles" placeholder="Day Text 451&#10;Day Text 452&#10;Day Text 453"><?php echo esc_textarea($titles_value); ?></textarea>
                                <p class="description"><?php echo esc_html__('Used only when Manual Title List is selected.', 'ipg-intelligent-page-generator'); ?></p>
                            </td>
                        </tr>
                    </tbody></table></div></div>
                        <div class="precision-duplicate-card precision-duplicate-generate-card precision-duplicate-preview-generate-card">
                            <div class="precision-duplicate-card-header"><h2>Preview + Generate</h2><p>Review your setup, preview the generation, and create draft or published pages.</p></div>
                            <div class="precision-duplicate-card-body">
                                <input type="hidden" name="precision_duplicate_generation_status" id="precision_duplicate_generation_status" value="<?php echo esc_attr($generation_status_value); ?>">
                                <div class="precision-duplicate-preview-actions">
                                    <button type="button" class="button button-secondary precision-duplicate-icon-button precision-duplicate-preview-button ipg-final-preview-action-button" id="precision_duplicate_preview_generation"><span><?php echo esc_html__('Preview Generation', 'ipg-intelligent-page-generator'); ?></span></button>
                                    <button type="submit" name="precision_duplicate_bulk_submit" class="button button-primary precision-duplicate-icon-button precision-duplicate-generate-button ipg-final-preview-action-button" id="precision_duplicate_generate_submit" formnovalidate="formnovalidate"><span><?php echo esc_html__('Generate Draft Pages', 'ipg-intelligent-page-generator'); ?></span></button>
                                </div>
                                <div id="precision_duplicate_preview_box" class="precision-duplicate-preview-box ipg-preview-modal-source" aria-hidden="true"></div>
                            </div>
                        </div>


</form>
                    </div>
                    <aside class="precision-duplicate-side-column">
                        <div class="precision-duplicate-side-card precision-duplicate-side-how" style="padding:24px 26px !important;">
                            <h2 style="margin:0 0 16px !important;">How It Works</h2>
                            <ol>
                                <li><strong>Select Source</strong><span>Choose the existing Elementor page that becomes the template.</span></li>
                                <li><strong>Define Generation</strong><span>Set the range or manual title list for page creation.</span></li>
                                <li><strong>Build Structure</strong><span>Use patterns for titles, slugs, tags, and page relationships.</span></li>
                                <li><strong>Preview First</strong><span>Review the generated names before creating draft pages.</span></li>
                                <li><strong>Generate Safely</strong><span>Create drafts or publish immediately, then use Recent Generations if rollback or export is needed.</span></li>
                            </ol>
                        </div>
                        <div class="precision-duplicate-side-card precision-duplicate-side-vars" style="padding:24px 26px !important;">
                            <h2 style="margin:0 0 16px !important;">Variable Reference</h2>
                            <div class="precision-duplicate-side-tokens" style="margin-bottom:16px !important;">
                                <code>{n}</code>
                                <code>{prev}</code>
                                <code>{next}</code>
                                <code>{range_start}</code>
                                <code>{range_end}</code>
                            </div>
                            <p>Use these in title, slug, tag, and protected content patterns.</p>
                        </div>
                        <div class="precision-duplicate-side-card precision-duplicate-side-examples" style="padding:24px 26px !important;">
                            <h2 style="margin:0 0 16px !important;">Pattern Examples</h2>
                            <div class="precision-duplicate-pattern-examples" style="margin-bottom:16px !important;">
                                <code>Day Text {n}</code>
                                <code>acim-text-day-{n}</code>
                                <code>Text Day {n}</code>
                            </div>
                            <p>These create clean sequential labels for large libraries.</p>
                        </div>
                        <div class="precision-duplicate-side-card precision-duplicate-side-recent" style="padding:0 !important;">
                            <?php $this->render_recent_generations_panel(); ?>
                        </div>
                        <div class="precision-duplicate-side-card precision-duplicate-side-tips" style="padding:24px 26px !important;">
                            <h2 style="margin:0 0 16px !important;">Smart Tips / Notes</h2>
                            <p>Use Preview Generation first, especially when using Memberium tag maps. Use Draft status until you confirm titles, slugs, and access tags.</p>
                        </div>
                    </aside>
                </div>


</div>
        <?php
    }

    private function resolve_source_page_input($input) {
        $input = trim((string) $input);

        if ($input === '') {
            return 0;
        }

        if (ctype_digit($input)) {
            return absint($input);
        }

        $url = esc_url_raw($input);
        if (!$url) {
            return 0;
        }

        $post_id = url_to_postid($url);
        if ($post_id) {
            return absint($post_id);
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            if (!empty($params['post']) && absint($params['post'])) {
                return absint($params['post']);
            }
            if (!empty($params['page_id']) && absint($params['page_id'])) {
                return absint($params['page_id']);
            }
        }

        return 0;
    }

    private function ensure_copy_slug_suffix($slug) {
        $slug = sanitize_title($slug);
        if ('' === $slug) {
            return '';
        }

        if (!preg_match('/(?:^|-)copy(?:-\d+)?$/', $slug)) {
            $slug .= '-copy';
        }

        return sanitize_title($slug);
    }

    private function create_duplicate_page($post_id, $new_title = '', $args = []) {
        $post_id = absint($post_id);
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'page') {
            return new WP_Error('precision_duplicate_invalid_page', __('The selected item is not a valid page.', 'ipg-intelligent-page-generator'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('precision_duplicate_permission', __('You do not have permission to duplicate this page.', 'ipg-intelligent-page-generator'));
        }

        $title = $new_title ? sanitize_text_field($new_title) : $post->post_title . ' Copy';
        if (!empty($args['force_copy_suffix']) && !preg_match('/\s+Copy(?:\s*\(\d+\))?$/i', $title)) {
            $title .= ' Copy';
        }
        if (!empty($args['slug'])) {
            $slug = !empty($args['force_copy_suffix']) ? $this->ensure_copy_slug_suffix($args['slug']) : sanitize_title($args['slug']);
        } else {
            $source_slug = $post->post_name ? $post->post_name : sanitize_title($post->post_title);
            $slug        = $this->ensure_copy_slug_suffix($source_slug);
        }
        $parent = isset($args['parent']) && absint($args['parent']) ? absint($args['parent']) : $post->post_parent;
        if ($slug) {
            $slug = wp_unique_post_slug($slug, 0, 'draft', $post->post_type, $parent);
        }
        $menu_order = isset($args['menu_order']) ? intval($args['menu_order']) : $post->menu_order;
        $token_context = isset($args['token_context']) && is_array($args['token_context']) ? $args['token_context'] : array();
        $tag_name = isset($args['tag_name']) ? sanitize_text_field($args['tag_name']) : '';
        $memberium_access_tag_id = isset($args['memberium_access_tag_id']) ? absint($args['memberium_access_tag_id']) : 0;
        $post_status = isset($args['post_status']) ? sanitize_key($args['post_status']) : 'draft';
        $post_status = in_array($post_status, array('draft', 'publish'), true) ? $post_status : 'draft';
        $post_content = !empty($token_context) ? $this->replace_tokens($post->post_content, $token_context) : $post->post_content;
        $post_excerpt = !empty($token_context) ? $this->replace_tokens($post->post_excerpt, $token_context) : $post->post_excerpt;

        $new_post = [
            'post_author'           => get_current_user_id(),
            'post_date'             => current_time('mysql'),
            'post_date_gmt'         => current_time('mysql', 1),
            'post_content'          => $post_content,
            'post_title'            => $title,
            'post_excerpt'          => $post_excerpt,
            'post_status'           => $post_status,
            'post_type'             => $post->post_type,
            'comment_status'        => $post->comment_status,
            'ping_status'           => $post->ping_status,
            'post_password'         => $post->post_password,
            'to_ping'               => $post->to_ping,
            'pinged'                => $post->pinged,
            'post_content_filtered' => $post->post_content_filtered,
            'post_parent'           => $parent,
            'menu_order'            => $menu_order,
        ];

        if ($slug) {
            $new_post['post_name'] = $slug;
        }

        $new = wp_insert_post($new_post, true);
        if (is_wp_error($new) || !$new) {
            return new WP_Error('precision_duplicate_insert_failed', __('The page could not be generated.', 'ipg-intelligent-page-generator'));
        }

        global $wpdb;

        if ($slug) {
            // Lock the duplicated draft slug immediately so the editor permalink field
            // receives original-slug-copy instead of recalculating from the title.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional post_name correction immediately after insert.
            $wpdb->update(
                $wpdb->posts,
                array('post_name' => $slug),
                array('ID' => $new),
                array('%s'),
                array('%d')
            );
            clean_post_cache($new);
        }

        $skip_meta = [
            '_edit_lock',
            '_edit_last',
            '_elementor_css',
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional exact raw copy. Elementor JSON must not be re-saved through meta APIs.
        $meta = $wpdb->get_results(
            $wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id)
        );

        foreach ($meta as $m) {
            if (in_array($m->meta_key, $skip_meta, true)) {
                continue;
            }

            $meta_value = $m->meta_value;

            // Replace variables inside common Elementor/WordPress content fields while
            // preserving the exact raw-copy approach for all other metadata.
            if (!empty($token_context) && in_array($m->meta_key, array('_elementor_data', '_wp_page_template'), true)) {
                $meta_value = $this->replace_tokens($meta_value, $token_context);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional exact raw insert. Elementor JSON must not be mutated.
            $wpdb->insert(
                $wpdb->postmeta,
                [
                    'post_id'    => $new,
                    'meta_key'   => $m->meta_key,
                    'meta_value' => $meta_value,
                ],
                ['%d', '%s', '%s']
            );
        }

        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($new, $terms, $taxonomy, false);
            }
        }

        if ($tag_name !== '') {
            wp_set_object_terms($new, array($tag_name), 'post_tag', true);
        }

        if ($memberium_access_tag_id) {
            $this->apply_memberium_access_tag_id($new, $memberium_access_tag_id);
        }

        wp_cache_delete($new, 'post_meta');
        clean_post_cache($new);

        $this->regenerate_elementor_css($new);
        update_post_meta($new, '_precision_duplicate_regenerate_elementor_css', '1');

        return $new;
    }

    public function duplicate() {
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) {
            wp_die(esc_html__('No page selected to duplicate.', 'ipg-intelligent-page-generator'));
        }

        check_admin_referer('precision_duplicate_' . $post_id);

        $new = $this->create_duplicate_page($post_id);
        if (is_wp_error($new)) {
            wp_die(esc_html($new->get_error_message()));
        }

        wp_safe_redirect(admin_url('post.php?post=' . absint($new) . '&action=edit'));
        exit;
    }
}

new Precision_Duplicate();



add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function( $links ) {
    $bulk_link = '<a href="' . esc_url( admin_url( 'tools.php?page=ipg-intelligent-page-generator' ) ) . '">Bulk Tool</a>';
    $links[] = $bulk_link;
    return $links;
});
