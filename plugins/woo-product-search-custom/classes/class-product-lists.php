<?php

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