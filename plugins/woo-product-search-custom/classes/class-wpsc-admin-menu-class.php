<?php 

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

    
}