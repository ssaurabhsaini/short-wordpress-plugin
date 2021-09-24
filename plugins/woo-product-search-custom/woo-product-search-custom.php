<?php

/**
 * Woocommerce Product Search Custom
 *
 * @package           woo-product-search-custom
 * @author            Dilip Verma
 * @copyright         2021 DWS
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Woocommerce Product Search Custom
 * Plugin URI:        https://egge.pro/
 * Description:       This plugin provides a fast, flexible and relible search page for Woocommerce products with sevral filters.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Dilip Verma
 * Author URI:        https://egge.pro/
 * Text Domain:       wpsc
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://egge.pro/
 */

defined('ABSPATH') || exit;

if (!defined('WPSC_PLUGIN_FILE')) {
    define('WPSC_PLUGIN_FILE', __DIR__);
}

// require_once (WPSC_PLUGIN_FILE.'/classes/class-wpsc-admin-menu-class.php');
// require_once (WPSC_PLUGIN_FILE.'/classes/class-product-lists.php');

add_action('plugins_loaded', function () {
    WPSC_Admin_Menu_Class::get_instance();
});

// Activation and Deactivation hook
register_activation_hook(__FILE__, 'wpsc_activation_hook_callback');
register_deactivation_hook(__FILE__, 'wpsc_deactivation_hook_callback');

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * This callback will call on plugin activation.
 */
function wpsc_activation_hook_callback()
{
    flush_rewrite_rules();
}

/**
 * This callback will call on plugin deactivation.
 */
function wpsc_deactivation_hook_callback()
{
    flush_rewrite_rules();
}

if (!class_exists('WPSC_Admin_Menu_Class')) {
    class WPSC_Admin_Menu_Class
    {
        // class instance
        static $instance;

        // customer WP_List_Table object
        public $products_lists;

        public function __construct()
        {
            $this->wpscInit();
        }

        /**
         * Singleton instance
         *
         * @return instance
         */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Initiating hooks & filters
         *
         * @return void
         */
        public function wpscInit()
        {
            // Adding admin menu
            add_action('admin_menu', array($this, 'wpsc_admin_sub_menu_callback'));

            // Adding screen options
            add_filter('set-screen-option', array(__CLASS__, 'wpsc_set_screen'), 10, 3);
        }


        /**
         * Set screen options
         *
         * @param [type] $status
         * @param [type] $option
         * @param [type] $value
         * @return value
         */
        public static function wpsc_set_screen($status, $option, $value)
        {
            return $value;
        }

        /**
         * Adding admin sub-menu page under the Woocommerce product
         *
         * @return void
         */
        public function wpsc_admin_sub_menu_callback()
        {
            $hook = add_submenu_page(
                'edit.php?post_type=product',
                __('Product Search', 'wpsc'),
                __('Product Search', 'wpsc'),
                'manage_options',
                'wpsc-product-search',
                array($this, 'wpsc_admin_sub_menu_page_callback')
            );
            add_action("load-$hook", [$this, 'wpsc_screen_option']);
        }

        /**
         * Screen options
         */
        public function wpsc_screen_option()
        {

            $option = 'per_page';
            $args = [
                'label' => 'Products',
                'default' => 20,
                'option' => 'products_per_page'
            ];

            add_screen_option($option, $args);

            $this->products_lists = new Products_List();
        }

        /**
         * Admin sub-menu page callback.
         *
         * @return void
         */
        public function wpsc_admin_sub_menu_page_callback()
        {
            $product_list_obj = new Products_List();
            ?>

            <style type="text/css">
                tbody.wpsc-loader-tbody{
                    position: relative;
                }
                tbody.wpsc-loader-tbody:after {
                    content: "";
                    background: #fff;
                    position: absolute;
                    z-index: 999;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    opacity: 0.6;
                }
            </style>
            <div class="wrap">

                <h1 class="wp-heading-inline">Product Search</h1>
                <a href="https://egge.pro/wp-admin/post-new.php?post_type=product" class="page-title-action">Add New</a>
                <hr class="wp-header-end">
                <?php $product_list_obj->views(); ?>
                
                <form id="email-sent-list" method="get">

                    <input type="hidden" name="page" value="<?php echo !empty($_REQUEST['page']) ? $_REQUEST['page'] : ''; ?>" />
                    <input type="hidden" name="order" value="<?php echo !empty($_REQUEST['order']) ? $_REQUEST['order'] : ''; ?>" />
                    <input type="hidden" name="orderby" value="<?php echo !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : ''; ?>" />

                    <p class="search-box">
                        <label class="screen-reader-text" for="post-search-input">Search products:</label>
                        <input type="search" style="float: left;margin: 0 4px 0 0;width: 400px;" id="post-search-input-custom" name="s" value="" class="">
                        <input type="submit" id="search-submit" class="button" value="Search products">
                    </p>

                    <div id="ts-history-table" style="">
                        <?php
                        wp_nonce_field('ajax-custom-list-nonce', '_ajax_custom_list_nonce');
                        ?>
                    </div>

                </form>

            </div>

        <?php
        }
    }

    add_action('plugins_loaded', function () {
        WPSC_Admin_Menu_Class::get_instance();
    });
}

class Products_List extends WP_List_Table
{
    private $hidden_columns = array(
        'id'
    );
    
    public $product_count_by_status = array();

    /** Class constructor */
    public function __construct()
    {
        // global $status, $page;
        parent::__construct(
            array(
                'singular'  => '60s hit',
                'plural'    => '60s hits',
                'ajax'      => true
            )
        );
    }

    /**
     * Get views for product status
     */
    protected function get_views() { 
        $product_count_by_status = wp_count_posts('product');
        $total_items = $product_count_by_status->publish+$product_count_by_status->draft;

        $status_links = array(
            "all"       => __("<a class='p_all' data-status='all' href='#'>All</a>(".$total_items.")",'wpsc'),
            "published" => __("<a class='p_publish' data-status='publish' href='#'>Published</a>(".$product_count_by_status->publish.")",'wpsc'),
            "draft"     => __("<a class='p_draft' data-status='draft' href='#'>Draft</a>(".$product_count_by_status->draft.")",'wpsc'),
            "trashed"   => __("<a class='p_trash' data-status='trash' href='#'>Trashed</a>(".$product_count_by_status->trash.")",'wpsc')
        );
        return $status_links;
    }

    /**
     * Retrieve customer’s data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_products($per_page = 5, $page_number = 1)
    {
        if (!function_exists('wc_get_product_category_list')) {
            require_once plugin_dir_path(__DIR__) . 'woocommerce/includes/wc-product-functions.php';
        }
        
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $per_page,
            'offset'    => ($page_number - 1) * $per_page,
        );
        
        if( ! empty( $_REQUEST['order'] ) && ! empty($_REQUEST['orderby']) ) {
            
            switch( $_REQUEST['orderby'] ) {
                case 'name' : {
                    $args['orderby'] = 'title';
                    $args['order'] = $_REQUEST['order'];
                }
                break;
                
                case 'sku' : {
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = '_sku';
                    $args['order'] = $_REQUEST['order'];
                }
                break;
                
                case 'price' : {
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = '_price';
                    $args['order'] = $_REQUEST['order'];
                }
                break;
                case 'stock' : {
                    $args['orderby'] = '_stock_status';
                    $args['order'] = $_REQUEST['order'];
                }
                break;
                
                default: {
                    $args['orderby'] = $_REQUEST['orderby'];
                    $args['order'] = $_REQUEST['order'];
                }
                break;
            }
        }

        if( ! empty( $_REQUEST['status'] ) && in_array($_REQUEST['status'], array('publish', 'trash', 'draft')) ) {
            $args['post_status'] = $_REQUEST['status'];
        }

        if (isset($_REQUEST['search']) && !empty($_REQUEST['search'])) {
            $args['meta_query'] = array(
                "relation" => "OR",
                array(
                    'key' => '_sku',
                    'value' => $_REQUEST['search'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'post_title',
                    'value' => $_REQUEST['search'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'product_description_short',
                    'value' => $_REQUEST['search'],
                    'compare' => 'LIKE'
                )
            );
        } else {
            $args['meta_query'] = array();
        }

        if (isset($_REQUEST['filter-status']) && $_REQUEST['filter-status']) {
            // $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][] = array(
                'key' => '_stock_status',
                'value' => $_REQUEST['filter-status'],
                'compare' => '='
            );
        } else if (isset($_REQUEST['filter-status']) && $_REQUEST['filter-status']) {
            $args['meta_query'] = array(
                "relation" => 'OR',
                array(
                    'key' => '_stock_status',
                    'value' => $_REQUEST['filter-status'],
                    'compare' => '='
                )
            );
        }

        if (isset($_REQUEST['filter-cat']) && $_REQUEST['filter-cat']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy'      => 'product_cat',
                    'field'         => 'slug',
                    'terms'         => $_REQUEST['filter-cat'],
                )
            );
        } else {
            $args['tax_query'] = array();
        }

        if (isset($_REQUEST['filter-type']) && $_REQUEST['filter-type'] && count($args['tax_query']) > 0) {

            $args['tax_query']['relation'] = 'AND';
            $args['tax_query'][] = array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => $_REQUEST['filter-type'],
                'operator' => 'IN'
            );
        } else if (isset($_REQUEST['filter-type']) && $_REQUEST['filter-type']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => $_REQUEST['filter-type'],
                    'operator' => 'IN'
                )
            );
        }

        if (isset($args['meta_query']) && count($args['meta_query']) <= 0) {
            unset($args['meta_query']);
        }
        if (isset($args['tax_query']) && count($args['tax_query']) <= 0) {
            unset($args['tax_query']);
        }

        $loop = new WP_Query($args);

        $result = array();
        if (!is_wp_error($loop) && isset($loop->posts) && count($loop->posts) > 0) {
            foreach ($loop->posts as $post) {
                
                $product = wc_get_product($post->ID);
                $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'single-post-thumbnail');

                $p_status = '';
                if ($post->post_status == 'draft') {
                    $p_status = ' - <i>Draft</i>';
                }

                $result[] = [
                    'cb'        => '<input type="checkbox" />',
                    'ID'        => $post->ID,
                    'p_img'     => $image,
                    'name'      => $post->post_title . $p_status,
                    'product_status' => $post->post_status,
                    'sku'       => $product->get_sku(),
                    'stock'     => $product->get_stock_status(),
                    'price'     => $product->get_price(),
                    'category'  => wc_get_product_category_list($post->ID),
                ];
            }
        }
        return ["data" => $result, "found_posts" => $loop->found_posts, "max_num_pages" => $loop->max_num_pages];
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        // global $wpdb;

        // $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}customers";

        // return $wpdb->get_var($sql);
    }
    /** Text displayed when no customer data is available */
    public function no_items()
    {
        if (!empty($_REQUEST['is_init']) && $_REQUEST['is_init'] == 'yes') {
            _e('<span style="display:flex;justify-content:center;">Please search and filter for product listing.</span>', 'wpsc');
        } else {
            _e('<span style="display:flex;justify-content:center;">No products avaliable.</span>', 'wpsc');
        }
    }

    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_name($item)
    {
        $title = '<strong>' . $item['name'] . '</strong>';

        $actions = [
            'Edit' => sprintf('<a href="%s">Edit</a>', get_edit_post_link($item['ID'])),
            'View' => sprintf('<a href="%s">View</a>', get_permalink($item['ID']))
        ];

        return $title . $this->row_actions($actions);
    }

    /**
     * Render a column when no column specific method exists.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name': {
                    return '<a href="' . get_permalink($item['ID']) . '">' . $item[$column_name] . '</a>';
                }
                break;
            case 'sku':
            case 'category':
                return $item[$column_name];
                break;

            case 'price':
                return '$ ' . $item[$column_name];
                break;
            case 'stock': {
                    if ($item[$column_name] == 'instock') {
                        return '<mark class="instock">In stock</mark>';
                    } else {
                        return '<mark class="outofstock">Out of stock</mark>';
                    }
                }
                break;
            case 'p_img': {
                    if (!empty($item[$column_name][0])) {
                        return '<img src="' . $item[$column_name][0] . '" width="80" height="80" alt="png" />';
                    } else {
                        $placeholder = 'https://egge.pro/wp-content/uploads/woocommerce-placeholder.png';
                        return '<img src="' . $placeholder . '" width="80" height="80" alt="png" />';
                    }
                }
                break;
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            $item['ID']
        );
    }

    /**
     * Associative array of columns
     *
     * @return array
     */
    function get_columns()
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'p_img' => __('Image', 'wpsc'),
            'name' => __('Name', 'wpsc'),
            'sku' => __('SKU', 'wpsc'),
            'stock' => __('Stock', 'wpsc'),
            'price' => __('Price', 'wpsc'),
            'category' => __('Categories', 'wpsc'),

        ];

        return $columns;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('name', true),
            'sku' => array('sku', true),
            'stock' => array('stock', true),
            'price' => array('price', true),
            'category' => array('category', true),
        );

        return $sortable_columns;
    }

    /**
     * Add extra filters
     */
    function extra_tablenav($which)
    {
        if ($which == "top") {
            $product_types = wc_get_product_types();
            $product_stock_status = wc_get_product_stock_status_options();
            ?>
            <div class="alignleft actions bulkactions">
                <select name="filter-cat" id="filter-by-category">
                    <option value="0">Select a category</option>
                    <?php echo $this->get_category_parent_child_options(); ?>
                </select>

                <select name="filter-type" id="filter-by-type">
                    <option value="0">Filter by product type</option>
                    <?php foreach ($product_types as $pt_key => $pt_value) : ?>
                        <?php $selected = ($_REQUEST['filter-type'] == $pt_key) ? ' selected' : ''; ?>
                        <option value="<?php echo $pt_key; ?>" <?php echo $selected; ?>><?php echo $pt_value; ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="filter-status" id="filter-by-status">
                    <option value="0">Filter by stock status</option>
                    <?php foreach ($product_stock_status as $s_key => $s_value) : ?>
                        <?php $selected = ($_REQUEST['filter-status'] == $s_key) ? ' selected' : ''; ?>
                        <option value="<?php echo $s_key; ?>" <?php echo $selected; ?>><?php echo $s_value; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="wpsc-product-filter button" value="Filter">
                <input type="submit" class="wpsc-clear-filter button" value="Clear filters">
                <img class="wpsc-loader" style="display:none;" src="https://egge.pro/wp-content/plugins/woo-product-search-custom/assets/loader.gif" width="30px" height="30px"></img>
            </div>
            <?php
        }
    }

    /**
     * Get parent child categories
     */
    public function get_category_parent_child_options()
    {
        $selected_key = $_REQUEST['filter-cat'];
        $option_html = '';

        $taxonomy     = 'product_cat';
        $orderby      = 'name';
        $show_count   = 0;      // 1 for yes, 0 for no
        $pad_counts   = 0;      // 1 for yes, 0 for no
        $hierarchical = 1;      // 1 for yes, 0 for no  
        $title        = '';
        $empty        = 0;

        $args = array(
            'taxonomy'     => $taxonomy,
            'orderby'      => $orderby,
            'show_count'   => $show_count,
            'pad_counts'   => $pad_counts,
            'hierarchical' => $hierarchical,
            'title_li'     => $title,
            'hide_empty'   => $empty
        );
        $all_categories = get_categories($args);
        foreach ($all_categories as $cat) {
            if ($cat->category_parent == 0) {
                $category_id = $cat->term_id;

                $selected = ($selected_key == $cat->slug) ? 'selected' : '';

                $option_html .= '<option class="level-0" value="' . $cat->slug . '" ' . $selected . '>' . $cat->name . '</option>';

                $args2 = array(
                    'taxonomy'     => $taxonomy,
                    'child_of'     => 0,
                    'parent'       => $category_id,
                    'orderby'      => $orderby,
                    'show_count'   => $show_count,
                    'pad_counts'   => $pad_counts,
                    'hierarchical' => $hierarchical,
                    'title_li'     => $title,
                    'hide_empty'   => $empty
                );
                $sub_cats = get_categories($args2);
                if ($sub_cats) {
                    foreach ($sub_cats as $sub_category) {
                        $selected = ($selected_key == $sub_category->slug) ? 'selected' : '';
                        $option_html .= '<option class="level-1" value="' . $sub_category->slug . '" ' . $selected . ' >&nbsp;&nbsp;&nbsp;' . $sub_category->name . '</option>';
                    }
                }
            }
        }

        return $option_html;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {
        /**
         * records per page.
         */
        $products_per_page = get_user_meta(get_current_user_id(), 'products_per_page', true);

        if (is_integer((int)$products_per_page)) {
            $per_page = $products_per_page;
        } else {
            $per_page = 20;
        }

        /**
         * Get columns for table
         */
        $columns  = $this->get_columns();
        $hidden   = $this->hidden_columns;
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();

        /**
         * Get product data.
         */

        if ( !empty($_REQUEST['is_init']) && $_REQUEST['is_init'] == 'yes') {
            $query_results = self::get_products(1, $current_page);
            $data = $query_results['data'];
        } else {
            $query_results = self::get_products($per_page, $current_page);
            $data = $query_results['data'];
        }

        $this->items = $data;

        /**
         * set pagination args
         */
        $this->set_pagination_args(
            array(
                'total_items'    => $query_results['found_posts'],
                'per_page'       => $per_page,
                'total_pages'    => $query_results['max_num_pages'],
                'orderby'        => !empty($_REQUEST['orderby']) && '' != $_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'title',
                'order'          => !empty($_REQUEST['order']) && '' != $_REQUEST['order'] ? $_REQUEST['order'] : 'asc'
            )
        );
    }

    /**
     * @Override of display method
     */
    function display()
    {

        /**
         * Adds a nonce field
         */
        wp_nonce_field('ajax-custom-list-nonce', '_ajax_custom_list_nonce');

        /**
         * Adds field order and orderby
         */
        echo '<input type="hidden" id="order" name="order" value="' . $this->_pagination_args['order'] . '" />';
        echo '<input type="hidden" id="orderby" name="orderby" value="' . $this->_pagination_args['orderby'] . '" />';

        parent::display();
    }

    /**
     * @Override ajax_response method
     */
    function ajax_response()
    {

        check_ajax_referer('ajax-custom-list-nonce', '_ajax_custom_list_nonce');

        $this->prepare_items();
        // print_r($_REQUEST);
        extract($this->_args);
        extract($this->_pagination_args, EXTR_SKIP);

        ob_start();
        if (!empty($_REQUEST['no_placeholder']))
            $this->display_rows();
        else
            $this->display_rows_or_placeholder();
        $rows = ob_get_clean();

        ob_start();
        $this->print_column_headers();
        $headers = ob_get_clean();

        ob_start();
        $this->pagination('top');
        $pagination_top = ob_get_clean();

        ob_start();
        $this->pagination('bottom');
        $pagination_bottom = ob_get_clean();

        $response = array('rows' => $rows);
        $response['pagination']['top'] = $pagination_top;
        $response['pagination']['bottom'] = $pagination_bottom;
        $response['column_headers'] = $headers;

        if (isset($total_items))
            $response['total_items_i18n'] = sprintf(_n('1 item', '%s items', $total_items), number_format_i18n($total_items));

        if (isset($total_pages)) {
            $response['total_pages'] = $total_pages;
            $response['total_pages_i18n'] = number_format_i18n($total_pages);
        }

        die(json_encode($response));
    }
}

/**
 * Action wp_ajax for fetching products data using ajax
 */

function _wpsc_fetch_ajax_reponse_for_products_callback()
{
    $wp_list_table = new Products_List();
    $wp_list_table->ajax_response();
}

add_action('wp_ajax__wpsc_fetch_ajax_reponse_for_products', '_wpsc_fetch_ajax_reponse_for_products_callback');

/**
 * Action wp_ajax for fetching the first time table structure
 */

function _wpsc_display_product_search_callback()
{
    check_ajax_referer('ajax-custom-list-nonce', '_ajax_custom_list_nonce', true);

    $wp_list_table = new Products_List();
    $wp_list_table->prepare_items();

    ob_start();
    $wp_list_table->display();
    $display = ob_get_clean();

    die(json_encode(
            array(
                "display" => $display
            )
        ));
}

add_action('wp_ajax__wpsc_display_product_search', '_wpsc_display_product_search_callback');

/**
 * Admin ajax for WPSC plugin
 */

function wpsc_scripts_for_data_menipulation()
{
    $screen = get_current_screen();

    if ($screen->id != "product_page_wpsc-product-search")
        return;

    ?>
    <script type="text/javascript">
        (function($) {
            var initial_request = null;
            var currentRequest = null;
            var currentRequest1 = null;
            
            list = {

                /** 
                 * Display results
                 **/

                display: function(initT = '') {
                    if (initT == 'initial_req') {
                        data = {
                            _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                            action: '_wpsc_display_product_search',
                            is_init: 'yes'
                        }
                    } else {
                        data = {
                            _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                            action: '_wpsc_display_product_search',
                            is_init: 'no'
                        }
                    }
                    $(".wpsc-loader").show();

                    if (currentRequest1 != null) {
                        currentRequest1.abort();
                    }

                    currentRequest1 = $.ajax({
                        url: ajaxurl,
                        dataType: 'json',
                        data: data,
                        success: function(response) {
                            $(".wpsc-loader").hide();
                            $("#ts-history-table").html(response.display);

                            $("tbody").on("click", ".toggle-row", function(e) {
                                e.preventDefault();
                                $(this).closest("tr").toggleClass("is-expanded")
                            });

                            list.init();
                            if (!initial_request) {
                                $('#the-list').html(`<tr class="no-items"><td class="colspanchange" colspan="7"><span style="display:flex;justify-content:center;">Please search and filter for product listing.</span></td></tr>`);
                                initial_request = true;
                                $('.current-page').val('1');
                                $('.displaying-num').html('0 items');
                                $('.total-pages').html('1');
                                $(".tablenav-pages").hide();
                            } else {
                                $(".tablenav-pages").show();
                            }
                        }
                    });

                },

                init: function() {

                    var timer;
                    var delay = 500;

                    $('.tablenav-pages a').on('click', function(e) {
                        e.preventDefault();

                        let product_cat = $("#filter-by-category").val();
                        let product_type = $("#filter-by-type").val();
                        let product_status = $("#filter-by-status").val();

                        var query = this.search.substring(1);
                        let search = $("#post-search-input-custom").val();
                        var data = {
                            "filter-cat": product_cat,
                            "filter-type": product_type,
                            "filter-status": product_status,
                            search: search,
                            paged: list.__query(query, 'paged') || '1',
                            order: list.__query(query, 'order') || 'asc',
                            orderby: list.__query(query, 'orderby') || 'title'
                        };
                        list.update(data);
                    });

                    $('.manage-column.sortable a, .manage-column.sorted a').on('click', function(e) {
                        e.preventDefault();

                        let product_cat = $("#filter-by-category").val();
                        let product_type = $("#filter-by-type").val();
                        let product_status = $("#filter-by-status").val();

                        var query = this.search.substring(1);
                        let search = $("#post-search-input-custom").val();
                        var data = {
                            "filter-cat": product_cat,
                            "filter-type": product_type,
                            "filter-status": product_status,
                            search: search,
                            order: list.__query(query, 'order') || 'asc',
                            orderby: list.__query(query, 'orderby') || 'title'
                        };
                        list.update(data);
                    });

                    $('input[name=paged]').on('keyup', function(e) {

                        if (13 == e.which)
                            e.preventDefault();

                        var data = {
                            paged: parseInt($('input[name=paged]').val()) || '1',
                            order: $('input[name=order]').val() || 'asc',
                            orderby: $('input[name=orderby]').val() || 'title'
                        };

                        list.update(data);
                    });

                    $('#email-sent-list').on('submit', function(e) {
                        e.preventDefault();
                    });

                    var searchup = '';
                    var searchcompare = '';
                    // $("#post-search-input-custom").keyup(function(e) {
                    $(document).on("click", "#search-submit", function(e) {
                        searchup = $("#post-search-input-custom").val();
                        if (searchup && searchup.length > 0 && searchcompare != searchup) {

                            let data = {
                                "search": searchup,
                                _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                            };

                            searchcompare = searchup;
                            list.update(data);
                        }
                    });

                    $(document).on("click", ".wpsc-product-filter", function(e) {

                        let product_cat = $("#filter-by-category").val();
                        let product_type = $("#filter-by-type").val();
                        let product_status = $("#filter-by-status").val();
                        searchup = $("#post-search-input-custom").val();

                        let data = {
                            search: searchup,
                            "filter-cat": product_cat,
                            "filter-type": product_type,
                            "filter-status": product_status,
                            _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                        };
                        list.update(data);
                    });

                    $(document).on("click", ".wpsc-clear-filter", function(e) {
                        initial_request = null;
                        if (!initial_request) {
                            list.display('initial_req');
                        }
                        // $("#filter-by-category").val('0');
                        // $("#filter-by-type").val('0');
                        // $("#filter-by-status").val('0');
                        // $("#post-search-input-custom").val('');

                        // let data = {
                        //     search: '',
                        //     "filter-cat": 0,
                        //     "filter-type": 0,
                        //     "filter-status": 0,
                        //     _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                        // };
                        // list.update(data);
                    });

                    $('a.p_all, a.p_publish, a.p_draft, a.p_trash').on('click', function(e) {
                        let status = $(this).data("status");
                        let product_cat = $("#filter-by-category").val();
                        let product_type = $("#filter-by-type").val();
                        let product_status = $("#filter-by-status").val();
                        searchup = $("#post-search-input-custom").val();

                        let data = {
                            search: searchup,
                            "filter-cat": product_cat,
                            "filter-type": product_type,
                            "filter-status": product_status,
                            "status": status,
                            _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                        };
                        list.update(data);
                    });
                },

                /** AJAX call
                 *
                 * Send the call and replace table parts with updated version!
                 */
                update: function(data) {
                    $(".wpsc-loader").show();
                    
                    $('#the-list').addClass('wpsc-loader-tbody');

                    if (currentRequest != null) {
                        currentRequest.abort();
                    }
                    currentRequest = $.ajax({

                        url: ajaxurl,
                        data: $.extend({
                                _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                                action: '_wpsc_fetch_ajax_reponse_for_products',
                            },
                            data
                        ),
                        success: function(response) {

                            var response = $.parseJSON(response);

                            if (response.rows.length)
                                $('#the-list').html(response.rows);
                            if (response.column_headers.length)
                                $('thead tr, tfoot tr').html(response.column_headers);
                            if (response.pagination.bottom.length)
                                $('.tablenav.top .tablenav-pages').html($(response.pagination.top).html());
                            if (response.pagination.top.length)
                                $('.tablenav.bottom .tablenav-pages').html($(response.pagination.bottom).html());

                            list.init();
                            $(".wpsc-loader").hide();
                            $(".tablenav-pages").show();
                            $('#the-list').removeClass('wpsc-loader-tbody');
                        }
                    });
                },

                /**
                 * Filter the URL Query to extract variables
                 */
                __query: function(query, variable) {

                    var vars = query.split("&");
                    for (var i = 0; i < vars.length; i++) {
                        var pair = vars[i].split("=");
                        if (pair[0] == variable)
                            return pair[1];
                    }
                    return false;
                },
            }

            if (!initial_request) {
                list.display('initial_req');
            }
        })(jQuery);
    </script>
    <?php
}
add_action('admin_footer', 'wpsc_scripts_for_data_menipulation');