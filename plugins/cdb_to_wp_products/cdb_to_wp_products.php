<?php
/*

Plugin Name: Remote DB to WordPress Woocommerce product sync
Plugin URI: https://www.saurabhsaini.ml/
Description: Custom functions for Sync Custom products in WP
Version: 1.0
Author: Saurabh Saini
Author URI: https://www.saurabhsaini.ml/
License: GPL-2.0+
Text Domain: cdb
Domain Path: /languages
*/

define( 'CDB_TO_WP_BASE_PATH', plugin_dir_path(__FILE__));
define( 'MAXIMUM_NUMBER_SYNC', 10 );
define( 'CDB_SERVERNAME', 'localhost' );
define( 'CDB_USERNAME', 'root' );
define( 'CDB_PASSWORD', '' );
define( 'CDB_DBNAME', 'db_name' );
global $wpdb;

define( 'PRODUCT_LOG_TABLE', $wpdb->prefix . 'shop_products' );
define( 'KIT_LOG_TABLE', $wpdb->prefix . 'shop_kits' );

add_action('plugin_loaded', 'create_db_connection');
function create_db_connection() {
    $conn = new mysqli(CDB_SERVERNAME, CDB_USERNAME, CDB_PASSWORD, CDB_DBNAME);
    if ($conn->connect_error) {
        wp_send_json_error( array( 'data' => false, 'message' => 'Connection faild: '.$conn->connect_error ) ); die;
    }
    $GLOBALS['conn'] = $conn;
}

// Adding admin menu
add_action('admin_menu', 'cdb_sync_products_submenu_page',99);
function cdb_sync_products_submenu_page() {
    add_submenu_page( 'edit.php?post_type=product', 'Custom DB to wp', 'Custom DB to wp', 'manage_options', 'cdb_to_wp_products', 'cdb_to_wp_products_callback' );
}

function cdb_to_wp_products_callback() {
    global $wpdb;

    if(!in_array('pa_oversize',get_taxonomies()) ) {
        $insert = process_add_attribute(
            array(
                'attribute_name' => 'oversize', 
                'attribute_label' => 'Oversize', 
                'attribute_type' => 'text', 
                'attribute_orderby' => 'menu_order', 
                'attribute_public' => false
            )
        );
    }

    $productTableName = PRODUCT_LOG_TABLE;
    $number_of_ajax_call = MAXIMUM_NUMBER_SYNC;
	
    $object = $wpdb->get_results( 
        $wpdb->prepare( 
            "SELECT 
                `cdb_product_id`
            FROM `$productTableName`
            WHERE `wp_product_id` = '0' OR `sync_end` = ''"
        ), ARRAY_A
    );

    $im_str = array();
    $total_products = count( $object );
    if ( is_array( $object ) &&  !empty( $object ) && count( $object ) > 0 ) {

        $count = 1;
        $limit = 40;
        $temp = array();

        foreach( $object as $single ) {
            
            $temp[] = $single['cdb_product_id'];
            if( $count == $limit || count( $object ) == $count ) {
                $im_str[] = implode(",",$temp);
                $count = 0;
                $temp = array();
            }
            $count++;
        }
        if( is_array($temp) && count($temp) > 0 ) {
            $im_str[] = implode(",",$temp);
        }

        $number_of_ajax_call = count($im_str);

	} else {
        echo '<span class="red">Products not found to import.</span>';
    }

    /**
     * Get kits
     */
    $kitTableName = KIT_LOG_TABLE;

    $kitsResults = $wpdb->get_results( 
        $wpdb->prepare( 
            "SELECT 
                `kit_number` 
            FROM 
                $kitTableName 
            WHERE `wp_product_id` = 0 AND `sync_end` = ''"
        ),ARRAY_A
    );
    $kitsResultsCount = count($kitsResults);
    
    $im_kit_str = array();
    $total_kit_products = count( $kitsResults );
    if ( is_array( $kitsResults ) &&  !empty( $kitsResults ) && count( $kitsResults ) > 0 ) {

        $kitcount = 1;
        $kitlimit = 20;
        $kittemp = array();

        foreach( $kitsResults as $single ) {
            
            $kittemp[] = $single['kit_number'];
            if( $kitcount == $kitlimit || count( $kitsResults ) == $kitcount ) {
                $im_kit_str[] = implode(",",$kittemp);
                $kitcount = 0;
                $kittemp = array();
            }
            $kitcount++;
        }
        if( is_array($kittemp) && count($kittemp) > 0 ) {
            $im_kit_str[] = implode(",",$kittemp);
        }
        // $im_kit_str = [];
        // $im_kit_str[] = 'BU300M2BRL64';
        $kit_number_of_ajax_call = count($im_kit_str);

	} else {
        echo '<span class="red">Products not found to import.</span>';
    }

    ?>
    <!-- Sync simple and variable products -->
    <div style="margin-top:60px;">
    	<input style="padding: 10px 10px 10px 10px;" type="button" value="Click to Start Product(Simple & Variable product) SYNC" id="sync_custom_product_to_wp" />
    </div>

    <!-- Sync bundle products -->
    <div style="margin-top:60px;">
    	<input style="padding: 10px 10px 10px 10px;" type="button" value="Click to Start Bundle product sync" id="sync_custom_bundle_products_to_wp" />
    </div>

    <div style="display:none" id="cdb_product_sync_progress" class="updated notice">

        <p id="cdb_sync_products_files_msg_completed"> 
            Importing products are on progress, 
            <span id="cdb_sync_products_files_msg">
            created: 0, updated: 0, failed: 0 out of <span class="total"><?php echo ($total_products); ?></span>
            </span>
            <img src="<?php echo plugin_dir_url( __FILE__ ).'img/loading.gif'; ?>" />
            <strong><span class="time-started"></span></strong>
        </p>
        <p class="test"></p>
    </div>
    
	<script type="text/javascript">
		jQuery( document ).ready(function() {
			var data_object = <?php echo json_encode($im_str); ?>;
			jQuery("#sync_custom_product_to_wp").click(function(e) {
                jQuery("#sync_custom_product_to_wp").prop('disabled', true);
				jQuery("#cdb_product_sync_progress").css("display","block");
                var currentdate = new Date(); 
                var datetime = currentdate.getDate() + "/" + (currentdate.getMonth()+1)  + "/"  + currentdate.getFullYear() + " @ "  + currentdate.getHours() + ":"  + currentdate.getMinutes() + ":" + currentdate.getSeconds();
                jQuery(".time-started").html("Sync started at: "+datetime);
                sync_products_to_wp(0);
            });
			var ending_loop = "<?php echo $number_of_ajax_call; ?>";
            var product_created = 0;
            var product_updated = 0;
            var product_failed = 0;
			function sync_products_to_wp(start_loop){
				if (start_loop < ending_loop ){
					jQuery.ajax({
						type: 'POST',
						url: "<?php echo admin_url('admin-ajax.php'); ?>",
						data: {'action': 'cdb_product_sync', 'custom_product_id': data_object[start_loop] },
						success: function (result) {
                            let totalproduct = 0;
                            if( result.data.created ) {
                                product_created += result.data.created;
                                totalproduct = parseInt(product_created) + parseInt(product_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+product_created+", updated: "+product_updated+", failed: "+product_failed+', total: '+totalproduct+' out of '+<?php echo ($total_products); ?>);
                            }
                            if( result.data.updated ) {
                                product_updated += result.data.updated;
                                totalproduct = parseInt(product_created) + parseInt(product_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+product_created+", Updated: "+product_updated+", Failed: "+product_failed+', total: '+totalproduct+' out of '+<?php echo ($total_products); ?>);
                            }
                            if( result.data.failed ) {
                                product_failed += result.data.failed;
                                totalproduct = parseInt(product_created) + parseInt(product_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+product_created+", Updated: "+product_updated+", Failed: "+product_failed+', total: '+totalproduct+' out of '+<?php echo ($total_products); ?>);
                            }
							// jQuery('#cdb_sync_products_files_msg').html((parseInt( start_loop)+1) +' products inserted out of '+<?php //echo ($total_products); ?>);
							start_loop++;
							sync_products_to_wp(start_loop);
						}
					});
				}
				else{
					// jQuery('#cdb_sync_products_files_msg_completed').html(<?php //echo ($total_products); ?>+" products inserted, Import completed.");
                    jQuery("#cdb_sync_products_files_msg_completed").html("Created: "+product_created+", Updated: "+product_updated+", Failed: "+product_failed+", Import completed.");
				}
			}

            // Sync Kit
            var syncIdObject = <?php echo json_encode($im_kit_str); ?>;
            jQuery("#sync_custom_bundle_products_to_wp").click(function(e) {
                alert('This will run after completing product import.');
                return false;
                jQuery(".total").html(<?php echo !empty($kitsResultsCount)?$kitsResultsCount:'0';?>);
                jQuery("#sync_custom_bundle_products_to_wp").prop('disabled', false);
				jQuery("#cdb_product_sync_progress").css("display","block");
                sync_kits_to_wp(0);
            });

            var endLoop = "<?php echo $kit_number_of_ajax_call; ?>";
            var kit_created = 0;
            var kit_updated = 0;
            var kit_failed = 0;
            var kit_skipped = 0;
            var failedLoop = 0;
            function sync_kits_to_wp( startLoop ) {
                if( startLoop < endLoop ) {
                    jQuery.ajax({
						type: 'POST',
						url: "<?php echo admin_url('admin-ajax.php'); ?>",
						data: { 'action': 'cdb_kit_sync', 'customKitId': syncIdObject[startLoop] },
						success: function (result) {
                            let totalkit = 0;
                            if( result.data.created ) {
                                kit_created += result.data.created;
                                totalkit = parseInt(kit_created) + parseInt(kit_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+kit_created+", updated: "+kit_updated+", failed: "+kit_failed+', skipped: '+kit_skipped+', total: '+totalkit+' out of '+<?php echo ($total_kit_products); ?>);
                            }
                            if( result.data.updated ) {
                                kit_updated += result.data.updated;
                                totalkit = parseInt(kit_created) + parseInt(kit_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+kit_created+", Updated: "+kit_updated+", Failed: "+kit_failed+', skipped: '+kit_skipped+', total: '+totalkit+' out of '+<?php echo ($total_kit_products); ?>);
                            }
                            if( result.data.failed ) {
                                kit_failed += result.data.failed;
                                totalkit = parseInt(kit_created) + parseInt(kit_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+kit_created+", Updated: "+kit_updated+", Failed: "+kit_failed+', skipped: '+kit_skipped+', total: '+totalkit+' out of '+<?php echo ($total_kit_products); ?>);
                            }

                            if( result.data.skipped ) {
                                kit_skipped += result.data.skipped;
                                totalkit = parseInt(kit_created) + parseInt(kit_updated)
                                jQuery("#cdb_sync_products_files_msg").html("created: "+kit_created+", Updated: "+kit_updated+", Failed: "+kit_failed+', skipped: '+kit_skipped+', total: '+totalkit+' out of '+<?php echo ($total_kit_products); ?>);
                            }
							startLoop++;
                            if( ! result.success ) { failedLoop++; jQuery(".failedRecord").html(failedLoop); }
							sync_kits_to_wp( startLoop );
						}
					});
                    
                } else {
                    jQuery("#cdb_sync_products_files_msg_completed").html("Created: "+kit_created+", Updated: "+kit_updated+", Failed: "+kit_failed+', skipped: '+kit_skipped+", Import completed.");
                }
            }
		});
	</script>
    <?php
}

add_action('wp_ajax_cdb_product_sync','cdb_product_sync_callback');
function cdb_product_sync_callback( ) {
    
    if( isset($_POST['action']) && $_POST['action'] == 'cdb_product_sync' ) {
        // Show error if custom porduct id is not valid
        if( empty($_POST['custom_product_id']) ) {
            wp_send_json_error( array( 'data' => false, 'message' => 'Invalid product id.' ) ); die;
        }
        $conn = $GLOBALS['conn'];
        $cbd_product_ids = explode(",",$_POST['custom_product_id']);

        $product_log = array();
        $failed = 1;
        $created = 1;
        $updated = 1;
        foreach($cbd_product_ids as $cbd_product_id) {
            $sql = "SELECT
                `shop_products`.`product_id`,
                `shop_products`.`product_part_number`,
                `shop_products`.`product_core_number`,
                `shop_products`.`product_description_short`,
                `shop_products`.`product_description_long`,
                `shop_products`.`product_name`,
                `shop_products`.`product_unpublished`,
                `shop_products`.`product_width`,
                `shop_products`.`product_length`,
                `shop_products`.`product_height`,
                `shop_products`.`product_line`,
                `shop_products`.`product_class`,
                `shop_products`.`product_price_msrp`,
                `shop_products`.`product_price_dealer`,
                `shop_products`.`product_price_jobber`,
                `shop_products`.`product_dim_weight`,
                `shop_products`.`product_weight`,
                `shop_products`.`product_inventory`,
                `shop_products`.`product_volume`,
                `shop_products`.`product_reference_number`,
                `shop_products`.`product_lookups`,
                `shop_products`.`product_part_notes`,
                `shop_products`.`product_popcode`,
                `shop_products`.`product_price_cost`,
                `shop_products`.`product_price_adist`,
                `shop_products`.`product_price_wdist`,
                `shop_products`.`product_price_core`,
                `shop_products`.`product_sef`,
                `shop_products`.`product_reseller`,
                GROUP_CONCAT(
                    DISTINCT `application_id` SEPARATOR ','
                ) AS `vymme_applications_ids`,
                GROUP_CONCAT(
                    DISTINCT `kit_number` SEPARATOR ','
                ) AS `kit_numbers`,
                GROUP_CONCAT(
                    DISTINCT `shop_products_categories_assign`.`category_id` SEPARATOR ','
                ) AS `category_ids`
            FROM
                `shop_products`
            LEFT JOIN `shop_product_applications_assign` ON `shop_product_applications_assign`.`product_id` = `shop_products`.`product_id`
            LEFT JOIN `shop_products_kits_assign` ON `shop_products_kits_assign`.`part_number` = `shop_products`.`product_part_number` 
            LEFT JOIN `shop_products_categories_assign` ON `shop_products_categories_assign`.`product_id` = `shop_products`.`product_id`
            WHERE `shop_products`.`product_id` = $cbd_product_id";

            $result = $conn->query($sql);
        
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                if( isset($row['product_id']) && $row['product_id'] != null && $row['product_id'] != '' ) {
    
                    // Processing single order
                    $created_product_id = create_products_in_woo( $row );

                    if( is_wp_error( $created_product_id['post_id'] ) ) {
                        $product_log['failed'] = $failed;
                        $failed++;
                    } else {
                        if( isset($created_product_id['is_updated']) && $created_product_id['is_updated'] == 'yes' ) {
                            $product_log['updated'] = $updated;
                            $updated++;
                        } else {
                            $product_log['created'] = $created;
                            $created++;
                        }
                    }
                }
            }
        }
        wp_send_json_success( $product_log );
        // $conn->close();
    }
}

/** Product creation and other support functions */
// Creating woocommerce products and saving meta into them.
function create_products_in_woo( $row ) {
    global $wpdb;
    $table_name = PRODUCT_LOG_TABLE;
    $product_by_cid = get_posts(
        array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_key' => 'product_id',
            'meta_value' => $row['product_id'],
            'fields' => 'ids'
        )
    );
    $is_updated = 'no';

    if( is_array($product_by_cid) && isset($product_by_cid[0]) && $product_by_cid[0] != '' ) {
        $post_id = $product_by_cid[0];
        $is_updated = 'yes';
    } else {
        if( isset($row['product_unpublished']) && $row['product_unpublished'] == 1 ) {
            $status = 'draft';
        } else {
            $status = 'publish';
        }

        $post_args = array(
            'post_title' => sanitize_text_field( $row['product_name'] ), // The product's Title
            'post_type' => 'product',
            // 'post_status' => 'publish',
            'post_excerpt' => $row['product_description_short'],
            'post_slug' => $row['product_sef'],
            'post_content' => wp_strip_all_tags($row['product_description_long']),
            'post_status' => $status
        );
    
        $post_id = wp_insert_post( $post_args );
        if( ! is_wp_error( $post_id ) ) {

        } else {
            
        }
        $is_updated = 'no';
    }

    // If the post was created okay, let's try update the WooCommerce values.
    if ( ! empty( $post_id ) && function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        
        // Creating DB log
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name 
                SET 
                    `wp_product_id` = %d, 
                    `sync_start` = 'start' 
                WHERE `cdb_product_id` = %d",
                $post_id,
                $row['product_id']
            )
        );

        // update product meta
        update_meta_data($post_id, $row);

        if( isset($row['product_unpublished']) && $row['product_unpublished'] == 0 ) {
            // Getting image data from shop_product_images and updating first image to product image and rest as product gallery
            get_image_urls_by_product_id($row['product_id'], $post_id);
        }

        $spce_data = get_specs_options( $row['product_id'] );
        if ($spce_data->num_rows > 0) {
            while($spce_data_single = $spce_data->fetch_assoc()) {
                // Product specification
                if( ! empty($row['product_id']) && !empty($spce_data_single['product_id']) && $row['product_id'] == $spce_data_single['product_id'] ) {
                    add_row(
                        'product_specification', 
                        array(
                            'select_specification' => $spce_data_single['spec_title'],
                            'details' => $spce_data_single['spec_value']
                        ),
                        $post_id
                    );
                }
            }
        }

        // Adding acf fields of part and kit number
        if( ! empty($row['product_part_number']) ) {
            update_post_meta($post_id, 'product_kitpart_number_part_number', $row['product_part_number']);
        }

        // Adding acf fields of part and kit number
        if( ! empty($row['kit_numbers']) ) {
            update_post_meta($post_id, 'product_kitpart_number_kit_number', $row['kit_numbers']);
        }

        
        // Get VYMME
        $VYMME_data = get_VYMME( $row['product_id'] );
        if ($VYMME_data->num_rows > 0) {

            while($VYMME_data_single = $VYMME_data->fetch_assoc()) {
                if( ! empty($row['product_id']) && !empty($VYMME_data_single['product_id']) && $row['product_id'] == $VYMME_data_single['product_id'] ) {

                    if( empty($VYMME_data_single['vehicle_year']) ) {
                        $VYMME_data_single['vehicle_year'] = 'YEAR';
                    }

                    if( empty($VYMME_data_single['vehicle_make']) ) {
                        $VYMME_data_single['vehicle_make'] = 'MAKE';
                    }

                    // if( empty($VYMME_data_single['vehicle_model']) ) {
                    //     $VYMME_data_single['vehicle_model'] = 'MODEL';
                    // }

                    if( empty($VYMME_data_single['vehicle_engine']) ) {
                        $VYMME_data_single['vehicle_engine'] = 'ENGINE';
                    }
                    
                    createTaxonomyParentChildRelationForVYMME( 
                        array(
                            $VYMME_data_single['vehicle_year'],
                            $VYMME_data_single['vehicle_make'],
                            // $VYMME_data_single['vehicle_model'],
                            $VYMME_data_single['vehicle_engine']
                        ), 
                        $post_id
                    );
                }
            }
        }


        if( ! empty($row['product_featured']) && $row['product_featured'] != 0 ) {
            update_post_meta( $post_id, '_featured', 'yes' );
        }

        if( ! empty($row['product_core_number']) ) {
            update_post_meta( $post_id, '_sku', $row['product_core_number'] );
        }

        if( ! empty( $row['product_weight'] ) ) {
            $product->set_weight( $row['product_weight'] );
        }

        if( ! empty( $row['product_width'] ) ) {
            $product->set_width( $row['product_width'] );
        }

        if( ! empty( $row['product_length'] ) ) {
            $product->set_length( $row['product_length'] );
        }

        if( ! empty( $row['product_height'] ) ) {
            $product->set_height( $row['product_height'] );
        }
        
        if( ! empty( $row['product_inventory'] ) ) {
            $product->set_stock( $row['product_inventory'] );
        } else {
            $row['product_inventory'] = '';
        }
        
        if( !empty( $row['product_price_msrp'] ) ) {
            $product->set_regular_price( $row['product_price_msrp'] ); // Be sure to use the correct decimal price.
        }

        $category_ids = $row['category_ids'];
        $conn = $GLOBALS['conn'];
        $categories_data = $conn->query("SELECT `category_id`,`category_parent_id`,`category_sef`,`category_name`,`category_description`,`category_unpublished` FROM `shop_categories` WHERE `category_id` IN($category_ids)");
        if( $categories_data->num_rows > 0 ) {
            while( $singleCategory = $categories_data->fetch_assoc() ) {
                if( ! empty( $singleCategory['category_sef'] ) && $singleCategory['category_sef'] != null  ) {
                    // Assign category
                    $term = term_exists( $singleCategory['category_sef'], 'product_cat' );
                    if( $term !== 0 && $term !== null ) {
                        $product->set_category_ids( array($term['term_id']) );
                    } else {
                        $term = egge_create_categories( $singleCategory );
                        if( $term != null ) {
                            $product->set_category_ids( array($term['term_id']) );
                        }
                    }
                }
            }
        }

        $product->save();

        $oversize_data = get_oversize_data( $row['product_id'] );
        if( $oversize_data->num_rows > 0 ) {
            wp_set_object_terms($product->get_id(), 'variable', 'product_type');
            
            $oversizeData = array();
            while( $singleOverSizeData = $oversize_data->fetch_assoc() ) {
                $oversizeData[] = $singleOverSizeData['oversize_value'];

                $parent_id = $product->get_id(); // Or get the variable product id dynamically

                // The variation data
                $variation_data =  array(
                    'attributes' => array(
                        'oversize'  => $singleOverSizeData['oversize_value']
                    ),
                    'sku'           => '',
                    'regular_price' => $row['product_price_msrp'],
                    'stock_qty'     => $row['product_inventory'],
                );

                if( (float)$row['product_price_sale'] != 0 ) {
                    $variation_data['sale_price'] = $row['product_price_sale'];
                }

                // The function to be run
                cdb_create_product_variation( $parent_id, $variation_data );
            }

            $yourRawAttributeList = array('oversize'  => $oversizeData);
            $attribs = cdb_generate_attributes_list_for_product($yourRawAttributeList);

            $p = new WC_Product_Variable($product->get_id());

            $p->set_props(array(
                'attributes'        => $attribs,
                //Set any other properties of the product here you want - price, name, etc.
            ));

            $postID = $p->save();
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name 
                SET 
                    `sync_end` = 'end' 
                WHERE `cdb_product_id` = %d",
                $row['product_id']
            )
        );
        /* Log Created */
    }
    return array('post_id' => $post_id, 'is_updated' => $is_updated);
} 

// Functions
function egge_create_categories( $row ) {
    $parent_term_id = '';
    if( isset($row['category_parent_id']) && $row['category_parent_id'] > 0) {
       
        $parent_term_slug = get_parent_category_id_by_custom_category($row['category_parent_id']);
        if(!empty($parent_term_slug) && $parent_term_slug != null) {
            $parent_term_idt = term_exists($parent_term_slug['category_sef'], 'product_cat');
            if( $parent_term_idt ) {
                $parent_term_id = $parent_term_idt['term_id'];
            } else {
                $wp_insert_term_res_parent = wp_insert_term(
                    $parent_term_slug['category_name'],   // the term 
                    'product_cat', // the taxonomy
                    array(
                        'description' => $parent_term_slug['category_description'],
                        'slug'        => $parent_term_slug['category_sef'],
                        'parent'      => '',
                    )
                );
                if( ! is_wp_error( $wp_insert_term_res_parent ) ) {
                    $parent_term_id = $wp_insert_term_res_parent['term_id'];
                    cdb_category_meta_update( $parent_term_id, $row );
                }
            }
        }
    }
    $wp_insert_term_res = wp_insert_term(
        $row['category_name'],   // the term 
        'product_cat', // the taxonomy
        array(
            'description' => $row['category_description'],
            'slug'        => $row['category_sef'],
            'parent'      => $parent_term_id,
        )
    );

    if( ! is_wp_error( $wp_insert_term_res ) ) {

        $term_id = $wp_insert_term_res['term_id'];
        cdb_category_meta_update( $term_id, $row );
        return $wp_insert_term_res;
    } else {
        return null;
    }
    return null;
}

function cdb_category_meta_update( $term_id, $data ) {
    update_term_meta( $term_id, 'category_id', $data['category_id'] );
    update_term_meta( $term_id, 'category_unpublished', $data['category_unpublished'] );
}

function get_parent_category_id_by_custom_category($category_parent_id) {
    $conn = $GLOBALS['conn'];
    $sql = "SELECT *
            FROM `shop_categories` 
            WHERE `shop_categories`.`category_id` = $category_parent_id";

    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row;
}

// Get specs data
function get_specs_options( $product_id ) {
    $conn = $GLOBALS['conn'];
    $sql = "SELECT 
        `shop_specs`.`spec_title`, 
        `shop_specs_assign`.`spec_value`,
        `shop_specs_assign`.`product_id` 
    FROM 
        `shop_specs_assign` 
    LEFT JOIN `shop_specs` ON `shop_specs`.`spec_id` = `shop_specs_assign`.`spec_id`
    WHERE `shop_specs_assign`.`product_id` = $product_id";

    $result = $conn->query($sql);
    return $result;
}

// Get V year, make, model
function get_VYMME( $sql_product_id ) {
    $conn = $GLOBALS['conn'];
    $sql = "SELECT 
        `shop_applications`.`application_id`,
        `shop_applications`.`vehicle_make`,
        `shop_applications`.`vehicle_model`,
        `shop_applications`.`vehicle_year`,
        `shop_applications`.`vehicle_engine`,
        `shop_product_applications_assign`.`product_id`
    FROM 
        `shop_product_applications_assign` 
    LEFT JOIN `shop_applications` ON `shop_applications`.`application_id` = `shop_product_applications_assign`.`application_id`
    WHERE `shop_product_applications_assign`.`product_id` = $sql_product_id";

    $result = $conn->query($sql);
    return $result;
}

// Update product meta in the products
function update_meta_data($wp_product_id, $meta_values) {
    $product_col = array(
        "product_id",
        "product_part_number",
        "product_core_number",
    );

    foreach($product_col as $single_product_col) {
        update_post_meta($wp_product_id, $single_product_col, $meta_values[$single_product_col]);
    }
}

// Save VYMME
function createTaxonomyParentChildRelationForVYMME( $termsArray, $productId, $taxonomy = 'product_ymm' ) {
	$parentTermId = null;
    $termids = array();
	foreach( $termsArray as $termName ) {

		if( $parentTermId ) {
			$isTermExist = term_exists( $termName, $taxonomy,$parentTermId );
		} else {
			$isTermExist = term_exists( $termName, $taxonomy );
		}

		if( $isTermExist ) {
			$parentTermId = $isTermExist['term_id'];
            $termids[] = (int)$parentTermId;
			continue;
		} else if( ! empty($termName) && ! $isTermExist ) {
			$newTermObject = wp_insert_term(
				$termName,
				$taxonomy,
				array(
					'description' => 'VYMME',
					'slug'        => $termName,
					'parent'      => $parentTermId,
				)
			);

			if( ! is_wp_error( $newTermObject ) ) {
				$parentTermId = $newTermObject['term_id'];
                $termids[] = (int)$parentTermId;
			}
		}
	}
    wp_set_object_terms( $productId, $termids, 'product_ymm', true );
}

function get_image_urls_by_product_id( $product_id, $wpProductId ) {
    // if not valid product id
    if( ! $product_id ) {
        return;
    }

    $conn = $GLOBALS['conn'];

    $sqlQuery = "SELECT 
        `shop_products`.`product_id`,
        `shop_products_images`.*
    FROM 
        `shop_products`, 
        `shop_products_images`
    WHERE
        `shop_products`.`product_id` = `shop_products_images`.`product_id` 
        AND
        `shop_products`.`product_id` = $product_id";

    $productImagesResult = $conn->query($sqlQuery);

    if( $productImagesResult->num_rows > 0 ) {

        $smallBaseUrl   = 'https://egge.com/media/images/shop/products/small/';
        $mediumBaseUrl  = 'https://egge.com/media/images/shop/products/medium/';
        $largeBaseUrl   = 'https://egge.com/media/images/shop/products/large/';

        $imageArray = array();
        $check_first = 1;
        while( $singleProductImage = $productImagesResult->fetch_assoc() ) {
            $imageArray['small'][] = $smallBaseUrl . $singleProductImage['image_name'];
            $imageArray['medium'][] = $mediumBaseUrl. $singleProductImage['image_name'];
            $imageArray['large'][] = $largeBaseUrl . $singleProductImage['image_name'];

            if( $check_first ) {
                $attachment_id = add_featured_image_to_product( $wpProductId, $largeBaseUrl . $singleProductImage['image_name'], $singleProductImage['image_name'], true );   
                $attach_id = $attachment_id;
                $check_first = 0;
                set_post_thumbnail( $wpProductId, $attach_id );
            } else {
                $attachment_id = add_featured_image_to_product( $wpProductId, $largeBaseUrl . $singleProductImage['image_name'], $singleProductImage['image_name'], false );   
            }
        }
        update_post_meta($wpProductId, 'images_from_custom_table',$imageArray);
    }
}

function add_featured_image_to_product( $post_id, $image_url, $image_name, $is_feartured = false ) {
    // Create the attachment
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attach_id = media_sideload_image($image_url, $post_id, $image_name, 'id');

    if( $is_feartured ) {
        return $attach_id;
    } else {
        if (is_null(get_post_meta($post_id,"_product_image_gallery"))) {
            add_post_meta($post_id,"_product_image_gallery",$attach_id);
        } else {
            $images_meta = get_post_meta($post_id,"_product_image_gallery",true);             
            update_post_meta($post_id,"_product_image_gallery",$images_meta.",".$attach_id);
        }
        return $attach_id;
    }
}

// Get V year, make, model
function get_oversize_data( $sql_product_id ) {
    $conn = $GLOBALS['conn'];
    $sql = "SELECT
        `product_id`,
        `shop_products_oversize`.*
    FROM
        `shop_products`
    JOIN `shop_products_oversize` ON `shop_products_oversize`.`product_part_number` = `shop_products`.`product_part_number`
    WHERE `product_id` = $sql_product_id";

    $result = $conn->query($sql);
    return $result;
}

function cdb_new_line_message( $message ) {
    $message .= '

';
    return $message;
}

function cdb_create_product_variation( $product_id, $variation_data ){
    // Get the Variable product object (parent)
    $product = wc_get_product($product_id);

    $variation_post = array(
        'post_title'  => $product->get_name(),
        'post_name'   => 'product-'.$product_id.'-variation',
        'post_status' => 'publish',
        'post_parent' => $product_id,
        'post_type'   => 'product_variation',
        'guid'        => $product->get_permalink()
    );

    // Creating the product variation
    $variation_id = wp_insert_post( $variation_post );

    // Get an instance of the WC_Product_Variation object
    $variation = new WC_Product_Variation( $variation_id );

    // Iterating through the variations attributes
    foreach ($variation_data['attributes'] as $attribute => $term_name )
    {
        $taxonomy = 'pa_'.$attribute; // The attribute taxonomy

        // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
        if( ! taxonomy_exists( $taxonomy ) ){
            register_taxonomy(
                $taxonomy,
               'product_variation',
                array(
                    'hierarchical' => false,
                    'label' => ucfirst( $attribute ),
                    'query_var' => true,
                    'rewrite' => array( 'slug' => sanitize_title($attribute) ), // The base slug
                ),
            );
        }

        // Check if the Term name exist and if not we create it.
        if( ! term_exists( $term_name, $taxonomy ) )
            wp_insert_term( $term_name, $taxonomy ); // Create the term

        $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug

        // Get the post Terms names from the parent variable product.
        $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

        // Check if the post term exist and if not we set it in the parent variable product.
        if( ! in_array( $term_name, $post_term_names ) )
            wp_set_post_terms( $product_id, $term_name, $taxonomy, true );

        // Set/save the attribute data in the product variation
        $res = update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );
    }

    ## Set/save all other data

    // SKU
    if( ! empty( $variation_data['sku'] ) )
        $variation->set_sku( $variation_data['sku'] );

    // Prices
    if( empty( $variation_data['sale_price'] ) ){
        $variation->set_price( $variation_data['regular_price'] );
    } else {
        $variation->set_price( $variation_data['sale_price'] );
        $variation->set_sale_price( $variation_data['sale_price'] );
    }
    $variation->set_regular_price( $variation_data['regular_price'] );

    // Stock
    if( ! empty($variation_data['stock_qty']) ){
        $variation->set_stock_quantity( $variation_data['stock_qty'] );
        $variation->set_manage_stock(true);
        $variation->set_stock_status('');
    } else {
        $variation->set_manage_stock(false);
    }
    
    $variation->set_weight(''); // weight (reseting)

    $variation->save(); // Save the data
}

function cdb_create_global_attribute($name, $slug) {
    $taxonomy_name = wc_attribute_taxonomy_name( $slug );

    if (taxonomy_exists($taxonomy_name)) {
        return wc_attribute_taxonomy_id_by_name($slug);
    }

    $attribute_id = wc_create_attribute( array(
        'name'         => $name,
        'slug'         => $slug,
        'type'         => 'select',
        'order_by'     => 'menu_order',
        'has_archives' => false,
    ) );

    //Register it as a wordpress taxonomy for just this session. Later on this will be loaded from the woocommerce taxonomy table.
    register_taxonomy(
        $taxonomy_name,
        apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
        apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy_name, array(
            'labels'       => array(
                'name' => $name,
            ),
            'hierarchical' => true,
            'show_ui'      => false,
            'query_var'    => true,
            'rewrite'      => false,
        ) )
    );

    //Clear caches
    delete_transient( 'wc_attribute_taxonomies' );

    return $attribute_id;
}

function cdb_generate_attributes_list_for_product($rawDataAttributes) {
    $attributes = array();
    $pos = 0;

    foreach ($rawDataAttributes as $name => $values)
    {
        if (empty($name) || empty($values)) continue;

        if (!is_array($values)) $values = array($values);

        $attribute = new WC_Product_Attribute();
        $attribute->set_id( 0 );
        $attribute->set_position($pos);
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $pos++;

        //Look for existing attribute:
        $existingTaxes = wc_get_attribute_taxonomies();

        //attribute_labels is in the format: array("slug" => "label / name")
        $attribute_labels = wp_list_pluck( $existingTaxes, 'attribute_label', 'attribute_name' );
        $slug = array_search( $name, $attribute_labels, true );

        if (!$slug)
        {
            //Not found, so create it:
            $slug = wc_sanitize_taxonomy_name($name);
            $attribute_id = cdb_create_global_attribute($name, $slug);
        }
        else
        {
            //Otherwise find it's ID
            $taxonomies = wp_list_pluck($existingTaxes, 'attribute_id', 'attribute_name');

            if (!isset($taxonomies[$slug]))
            {
                //logg("Could not get wc attribute ID for attribute ".$name. " (slug: ".$slug.") which should have existed!");
                continue;
            }

            $attribute_id = (int)$taxonomies[$slug];
        }

        $taxonomy_name = wc_attribute_taxonomy_name($slug);

        $attribute->set_id( $attribute_id );
        $attribute->set_name( $taxonomy_name );
        $attribute->set_options($values);

        $attributes[] = $attribute;
    }


    return $attributes;
}

function process_add_attribute($attribute) {
    global $wpdb;

    if (empty($attribute['attribute_type'])) { $attribute['attribute_type'] = 'text';}
    if (empty($attribute['attribute_orderby'])) { $attribute['attribute_orderby'] = 'menu_order';}
    if (empty($attribute['attribute_public'])) { $attribute['attribute_public'] = 0;}

    if ( empty( $attribute['attribute_name'] ) || empty( $attribute['attribute_label'] ) ) {
            return new WP_Error( 'error', __( 'Please, provide an attribute name and slug.', 'woocommerce' ) );
    } elseif ( ( $valid_attribute_name = valid_attribute_name( $attribute['attribute_name'] ) ) && is_wp_error( $valid_attribute_name ) ) {
            return $valid_attribute_name;
    } elseif ( taxonomy_exists( wc_attribute_taxonomy_name( $attribute['attribute_name'] ) ) ) {
            return new WP_Error( 'error', sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'woocommerce' ), sanitize_title( $attribute['attribute_name'] ) ) );
    }

    $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );

    do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );

    flush_rewrite_rules();
    delete_transient( 'wc_attribute_taxonomies' );

    return true;
}

function valid_attribute_name( $attribute_name ) {
    if ( strlen( $attribute_name ) >= 28 ) {
            return new WP_Error( 'error', sprintf( __( 'Slug "%s" is too long (28 characters max). Shorten it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
    } elseif ( wc_check_if_attribute_name_is_reserved( $attribute_name ) ) {
            return new WP_Error( 'error', sprintf( __( 'Slug "%s" is not allowed because it is a reserved term. Change it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
    }

    return true;
}


// Ajax callback for sync kit
add_action('wp_ajax_cdb_kit_sync','cdb_kit_sync_callback');
function cdb_kit_sync_callback( ) {
    
    if( isset($_POST['action']) && $_POST['action'] == 'cdb_kit_sync' ) {
        global $wpdb;
        $kit_table_name = KIT_LOG_TABLE;
        // Create MYSQL connection
        $conn = new mysqli(CDB_SERVERNAME, CDB_USERNAME, CDB_PASSWORD, CDB_DBNAME);
        if ($conn->connect_error) {
            wp_send_json_error( array( 'data' => false, 'message' => 'Connection faild: '.$conn->connect_error ) ); die;
        }
        $GLOBALS['conn'] = $conn;

         // Show error if custom porduct id is not valid
         if( empty($_POST['customKitId']) ) {
            wp_send_json_error( array( 'data' => false, 'message' => 'Invalid kit id.' ) ); die;
        }
        
        $temp = array();

        $kit_numbers = explode(",",$_POST['customKitId']);

        $kit_logs = array();
        $kit_logs['created'] = 0;
        $kit_logs['updated'] = 0;
        $kit_logs['failed'] = 0;
        $kit_logs['skipped'] = 0;
        
        foreach($kit_numbers as $kit_number) {
            // Get kit by kit number
            $kitData = get_kit_by_kit_number( $kit_number );
            $temp[] = $kit_number;
            if( ! empty($kitData) ) {
                
                $wpPosts = get_posts(
                    array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'meta_query' => array(
                            array(
                                'key'   => 'product_kitpart_number_kit_number',
                                'value' => $kit_number,
                                'compare' => 'LIKE'
                            ),
                        ),
                        'fields' => 'ids',
                        'post_status' => array('publish'), //, 'draft'
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_type',
                                'field'    => 'slug',
                                'terms'    => 'woosb',
                                'operator' => 'NOT IN'
                            ),
                        ),
                    )
                );
                
                if( is_wp_error( $wpPosts ) || count($wpPosts) < 1 ) {
                    $kit_logs['skipped']++;
                    continue;
                } else {
                    $kit_logs[$kit_number]['sub_products'] = "Kit sub products: " .implode(",",$wpPosts);
                }
    
                $cdb_ids = array();
                foreach( $wpPosts as $singleId ) {
                    $cdb_ids[get_post_meta($singleId,'product_id', true)] = $singleId;
                }
               
                $existingPosts = get_posts(
                    array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'meta_key' => 'product_kitpart_number_kit_number',
                        'meta_value' => $kit_number,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_type',
                                'field'    => 'slug',
                                'terms'    => 'woosb', 
                            ),
                        ),
                        'fields' => 'ids'
                    )
                );
    
                if( ! empty($existingPosts) && is_array($existingPosts) && count($existingPosts) > 0 ) {
                    
                    $kidProductId = $existingPosts[0];
                    $kit_logs['updated']++;
                    
                     // Creating DB log
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $kit_table_name 
                            SET 
                                `wp_product_id` = %d, 
                                `sync_start` = 'start' 
                            WHERE `kit_number` = %s",
                            $kidProductId,
                            $kit_number
                        )
                    );
                } else {
                    if( isset($kitData['kit_unpublished']) && $kitData['kit_unpublished'] == 1 ) {
                        $status = 'draft';
                    } else {
                        $status = 'publish';
                    }
        
                    $postData = array(
                        'post_type' => 'product',
                        'post_title' => sanitize_text_field( $kitData['kit_name'] ),
                        'post_status' => $status,
                        'post_excerpt' => $kitData['kit_description'],
                        'post_content' => wp_strip_all_tags($kitData['kit_description']),
                        'post_slug' => $kitData['kit_sef'],
                        '_featured' => $kitData['kit_featured']
                    );
        
                    // Create kit product
                    $kidProductId = create_kit_product( $postData );

                    if( ! is_wp_error($kidProductId) ) {
                        $kit_logs['created']++;
                    } else {
                        $kit_logs['failed']++;
                        continue;
                    }

                    if( ! empty($kit_number) ) {
                        update_post_meta( $kidProductId, '_sku', $kit_number );
                    }

                    // Creating DB log
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $kit_table_name 
                            SET 
                                `wp_product_id` = %d, 
                                `sync_start` = 'start' 
                            WHERE `kit_number` = %s",
                            $kidProductId,
                            $kit_number
                        )
                    );
                }
    
                // Get product and quantity assignment in kit
                $cdb_product_with_quantity = get_products_by_kit_number( $kit_number );
    
                $woosb_price = 0;
                $woosb_ids = array();
                while( $singleData = $cdb_product_with_quantity->fetch_assoc() ) {
                    if( ! empty($singleData['product_id']) && ! empty($singleData['assign_quantity']) && ! empty($cdb_ids[$singleData['product_id']]) ) {
                        $woosb_ids[] = $cdb_ids[$singleData['product_id']].'/'.$singleData['assign_quantity'];
                        $product = wc_get_product( $cdb_ids[$singleData['product_id']] );
                        $woosb_price += (float)$product->get_price()*$singleData['assign_quantity'];
                    }
                }
                
                $woosb_ids_with_quantities = implode(",",$woosb_ids);
    
                // update kit number
                update_post_meta($kidProductId, 'product_kitpart_number_kit_number', $kit_number);
    
                // update kit post meta
                update_kit_meta_data($kidProductId, $kitData);
    
                // update bundle product meta
                $bundleProductMeta = array(
                    'woosb_ids' => $woosb_ids_with_quantities,
                    'woosb_disable_auto_price' => 'off',
                    'woosb_discount' => '0',
                    'woosb_discount_amount' => '',
                    'woosb_shipping_fee' => 'whole',
                    'woosb_optional_products' => '',
                    'woosb_manage_stock' => '',
                    'woosb_custom_price' => '',
                    'woosb_limit_each_min' => '',
                    'woosb_limit_each_max' => '',
                    'woosb_limit_each_min_default' => '',
                    'woosb_limit_whole_min' => '',
                    'woosb_before_text' => '',
                    'woosb_limit_whole_max' => '',
                    'woosb_after_text' => ''
                );
    
                update_bundle_product_meta_fields( $kidProductId, $bundleProductMeta );
    
                // Update VYMME
                $kit_VYMME = get_VYMME_by_kit_number( $kit_number );
                if( $kit_VYMME->num_rows > 0 ) {
                    while($kit_VYMME_single = $kit_VYMME->fetch_assoc()) {
                        if( ! empty($kit_VYMME_single['application_id']) && !empty($kit_VYMME_single['assigned_application_id']) && $kit_VYMME_single['application_id'] == $kit_VYMME_single['assigned_application_id'] ) {
        
                            if( empty($kit_VYMME_single['vehicle_year']) ) {
                                $kit_VYMME_single['vehicle_year'] = 'YEAR';
                            }
        
                            if( empty($kit_VYMME_single['vehicle_make']) ) {
                                $kit_VYMME_single['vehicle_make'] = 'MAKE';
                            }
        
                            if( empty($kit_VYMME_single['vehicle_model']) ) {
                                $kit_VYMME_single['vehicle_model'] = 'MODEL';
                            }
        
                            if( empty($kit_VYMME_single['vehicle_engine']) ) {
                                $kit_VYMME_single['vehicle_engine'] = 'ENGINE';
                            }
                            
                            createTaxonomyParentChildRelationForVYMME( 
                                array(
                                    $kit_VYMME_single['vehicle_year'],
                                    $kit_VYMME_single['vehicle_make'],
                                    $kit_VYMME_single['vehicle_model'],
                                    $kit_VYMME_single['vehicle_engine']
                                ), 
                                $kidProductId
                            );
                        }
                    }
                }

                $product = wc_get_product( $kidProductId );
                
                if( !empty( $woosb_price ) ) {
                    $product->set_regular_price( $woosb_price ); // Be sure to use the correct decimal price.
                }
                $product->save();

                do_action( "save_post_product", $kidProductId, $product, true );
    
                // Creating DB log
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $kit_table_name 
                        SET 
                            `sync_end` = 'end' 
                        WHERE `kit_number` = %s",
                        $kit_number
                    )
                );
            }

        }
        wp_send_json_success( $kit_logs );
        $conn->close();
    }
}

function get_products_by_kit_number( $kit_number ) {
    $conn = $GLOBALS['conn'];

    $result = $conn->query(
        "SELECT
            `shop_products`.`product_id`,
            `assign_quantity`
        FROM
            `shop_products`
        LEFT JOIN `shop_products_kits_assign` ON `shop_products_kits_assign`.`part_number` = `shop_products`.`product_part_number`
        WHERE
            `shop_products_kits_assign`.`kit_number` = '$kit_number'
        ORDER BY
            `shop_products_kits_assign`.`part_number`"
    );
    return $result;
}

function get_kit_by_kit_number( $kit_number ) {
    $conn = $GLOBALS['conn'];

    $result = $conn->query(
        "SELECT
            *
        FROM
            `shop_kits`
        WHERE
            `kit_number` = '$kit_number'"
    );
    if( $result->num_rows > 0 ) {
        return $result->fetch_assoc();
    } else {
        return 0;
    }
}

function create_kit_product( $postData ) {
    $post_id = wp_insert_post( 
        array( 
            'post_type' => $postData['post_type'],
            'post_status' => $postData['post_status'],
            'post_title' => $postData['post_title'],
            'post_excerpt' => $postData['post_excerpt'],
            'post_content' => $postData['post_content'],
            'post_slug' => $postData['post_slug'],
        )
    );


    if(!is_wp_error($post_id)) {
        //the post is valid
        assign_kit_product_category( $post_id );
    
        if( ! empty($postData['_featured']) && $postData['_featured'] == 1 ) {
            update_post_meta( $post_id, '_featured', 'yes' );
        } else {
            update_post_meta( $post_id, '_featured', 'no' );
        }
    
        // Then we use the product ID to set all the posts meta
        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');
        update_post_meta( $post_id, '_backorders', 'no' );
    
        $classname = WC_Product_Factory::get_product_classname( $post_id, 'woosb' );
        $product   = new $classname( $post_id );
    
        $product->save();
        return $post_id;
    }else{
        return $post_id;
    }
}

function update_kit_meta_data( $kit_product_id, $kit_meta_data ) {
    $kit_meta_keys = array(
        'kit_id',
        'kit_number',
        'kit_sef',
        'kit_factory',
        'kit_configuration',
        'kit_cylinders',
        'kit_oem_bore',
        'kit_meta_title',
        'kit_meta_desc',
        'kit_meta_keys',
        'part_1',
        'part_2',
        'part_3',
        'part_4',
        'part_5',
        'part_6',
        'part_7',
        'part_8',
        'part_9',
        'part_10',
        'part_11',
        'part_12',
        'part_13',
        'part_14',
        'part_15',
        'part_16',
        'part_17',
        'part_18',
        'part_19',
        'part_20',
        'part_21',
        'part_22',
        'part_23',
        'kit_unpublished',
        'kit_featured',
        'kit_deleted',
        'kit_created_date',
        'kit_modified_date'
    );

    foreach( $kit_meta_keys as $meta_key => $meta_value ) {
        update_post_meta($kit_product_id, $kit_meta_data[$meta_value], $kit_meta_data[$meta_value]);
    }
}

// update bundle product meta
function update_bundle_product_meta_fields( $post_id, $data ) {
    if ( isset( $data['woosb_ids'] ) && ! empty( $data['woosb_ids'] ) ) {
        update_post_meta( $post_id, 'woosb_ids', WPCleverWoosb_Helper::woosb_clean_ids( $data['woosb_ids'] ) );
    }

    if ( isset( $data['woosb_disable_auto_price'] ) ) {
        update_post_meta( $post_id, 'woosb_disable_auto_price', 'on' );
    } else {
        update_post_meta( $post_id, 'woosb_disable_auto_price', 'off' );
    }

    if ( isset( $data['woosb_discount'] ) ) {
        update_post_meta( $post_id, 'woosb_discount', sanitize_text_field( $data['woosb_discount'] ) );
    } else {
        update_post_meta( $post_id, 'woosb_discount', 0 );
    }

    if ( isset( $data['woosb_discount_amount'] ) ) {
        update_post_meta( $post_id, 'woosb_discount_amount', sanitize_text_field( $data['woosb_discount_amount'] ) );
    } else {
        update_post_meta( $post_id, 'woosb_discount_amount', 0 );
    }

    if ( isset( $data['woosb_shipping_fee'] ) ) {
        update_post_meta( $post_id, 'woosb_shipping_fee', sanitize_text_field( $data['woosb_shipping_fee'] ) );
    }

    if ( isset( $data['woosb_optional_products'] ) ) {
        update_post_meta( $post_id, 'woosb_optional_products', 'on' );
    } else {
        update_post_meta( $post_id, 'woosb_optional_products', 'off' );
    }

    if ( isset( $data['woosb_manage_stock'] ) ) {
        update_post_meta( $post_id, 'woosb_manage_stock', 'on' );
    } else {
        update_post_meta( $post_id, 'woosb_manage_stock', 'off' );
    }

    if ( isset( $data['woosb_custom_price'] ) && ( $data['woosb_custom_price'] !== '' ) ) {
        update_post_meta( $post_id, 'woosb_custom_price', addslashes( $data['woosb_custom_price'] ) );
    } else {
        delete_post_meta( $post_id, 'woosb_custom_price' );
    }

    if ( isset( $data['woosb_limit_each_min'] ) ) {
        update_post_meta( $post_id, 'woosb_limit_each_min', sanitize_text_field( $data['woosb_limit_each_min'] ) );
    }

    if ( isset( $data['woosb_limit_each_max'] ) ) {
        update_post_meta( $post_id, 'woosb_limit_each_max', sanitize_text_field( $data['woosb_limit_each_max'] ) );
    }

    if ( isset( $data['woosb_limit_each_min_default'] ) ) {
        update_post_meta( $post_id, 'woosb_limit_each_min_default', 'on' );
    } else {
        update_post_meta( $post_id, 'woosb_limit_each_min_default', 'off' );
    }

    if ( isset( $data['woosb_limit_whole_min'] ) ) {
        update_post_meta( $post_id, 'woosb_limit_whole_min', sanitize_text_field( $data['woosb_limit_whole_min'] ) );
    }

    if ( isset( $data['woosb_limit_whole_max'] ) ) {
        update_post_meta( $post_id, 'woosb_limit_whole_max', sanitize_text_field( $data['woosb_limit_whole_max'] ) );
    }

    if ( isset( $data['woosb_before_text'] ) && ( $data['woosb_before_text'] !== '' ) ) {
        update_post_meta( $post_id, 'woosb_before_text', addslashes( $data['woosb_before_text'] ) );
    } else {
        delete_post_meta( $post_id, 'woosb_before_text' );
    }

    if ( isset( $data['woosb_after_text'] ) && ( $data['woosb_after_text'] !== '' ) ) {
        update_post_meta( $post_id, 'woosb_after_text', addslashes( $data['woosb_after_text'] ) );
    } else {
        delete_post_meta( $post_id, 'woosb_after_text' );
    }
}

// Get VYMME by kit number
function get_VYMME_by_kit_number( $kit_number ) {
    $conn = $GLOBALS['conn'];

    $result = $conn->query(
        "SELECT
            `shop_kits`.`kit_number`,
            `shop_applications`.*,
            `shop_kit_applications_assign`.`application_id` AS assigned_application_id
        FROM
            `shop_kit_applications_assign`
        JOIN `shop_kits` ON `shop_kits`.`kit_id` = `shop_kit_applications_assign`.`kit_id` AND `shop_kits`.`kit_number` = '".$kit_number."'
        JOIN `shop_applications` ON `shop_applications`.`application_id` = `shop_kit_applications_assign`.`application_id`"
    );

    return $result;
}

// create and assign kit product category
function assign_kit_product_category( $product_id ) {
    $term = term_exists( 'kit-products', 'product_cat' );
    if ( $term !== 0 && $term !== null ) {
        wp_set_object_terms( $product_id, (int)$term['term_id'], 'product_cat' );
    } else {
        $term = wp_insert_term(
            'Kit Products',
            'product_cat',
            array(
                'description' => '',
                'slug'        => 'kit-products',
                'parent'      => '',
            )
        );
        wp_set_object_terms( $product_id, (int)$term['term_id'], 'product_cat' );
    }
}

function kit_product_log( $meesage ) {
    file_put_contents( CDB_TO_WP_BASE_PATH . '/sync_log/kit_import_log.txt', cdb_new_line_message( $meesage ), FILE_APPEND );
}