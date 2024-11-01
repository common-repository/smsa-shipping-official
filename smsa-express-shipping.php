<?php
/**
 * Plugin Name: SMSA Shipping (official)
 * Plugin URI: https://www.smsaexpress.com
 * Short Description: This plugin integrates SMSA Express Shipping for easy shipment tracking and management.
 * Description: Ship, Return, Print, and Track orders with SMSA Express. Easily integrate SMSA Express with WooCommerce to handle shipping logistics, generate AWB, and track orders in real-time.
 * Author: SMSA Express
 * Author URI: https://www.smsaexpress.com/about-us
 * Version: 2.2
 */
use setasign\Fpdi\Fpdi;

if (!defined('WPINC')) {
    die;
}

require_once('smsa-express-shipping-class.php');

// Activation redirect
add_action('activated_plugin', 'smsa_activation_redirect');
function smsa_activation_redirect($plugin) {
    if($plugin == plugin_basename(__FILE__)) {
        exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=smsa-express-integration')));
    }
}

// Enqueue the assets (styles and scripts)
function smsa_enqueue_assets($hook) {
    // Define the page slugs where the assets should be loaded
    $smsa_pages = array(
        'smsa-shipping-official/order-list.php',  // Order list page
        'smsa-shipping-official/create_shipment.php',         // Create shipment page
        'smsa-shipping-official/create_C2Bshipment.php',      // Create C2B shipment page
        'smsa-shipping-official/track_order.php'              // Track order page
    );

    // Check if we are on an SMSA-related admin page
    if (isset($_GET['page']) && in_array($_GET['page'], $smsa_pages)) {
        // Enqueue DataTables and Bootstrap CSS
        wp_enqueue_style('smsa_style', plugin_dir_url(__FILE__) . 'css/smsa-style.css');
        wp_enqueue_style('datatables_css', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css');
        
        // Enqueue DataTables and Bootstrap JavaScript
        wp_enqueue_script('datatables_js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array('jquery'), null, true);
        wp_enqueue_script('datatables_bootstrap_js', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', array('jquery', 'datatables_js'), null, true);
        
        // Enqueue the custom SMSA script
        wp_enqueue_script('smsa_script', plugin_dir_url(__FILE__) . 'js/smsa-script.js', array('jquery', 'datatables_js'), false, true);

        // Localize variables for use in JavaScript files
        wp_localize_script('smsa_script', 'smsa_vars', array(
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'admin_url'  => admin_url('/')
        ));
    }
}

// Only run this on the admin side
if (is_admin()) {
    add_action('admin_enqueue_scripts', 'smsa_enqueue_assets');
}


add_action('admin_menu', 'smsa_register_hidden_pages');
function smsa_register_hidden_pages() {
    // Ensure both 'Administrator' and 'Shop Manager' roles can access the pages
    $capability = 'manage_woocommerce'; // This is generally for shop managers and administrators

    // Track Order Page
    add_submenu_page(
        null, // Hide from menu
        'Track Order', // Page title
        '', // No menu title
        $capability, // Capability required to access the page
        plugin_dir_path(__FILE__) . 'track_order.php' // Slug for the page
    );

    // Create Shipment Page
    add_submenu_page(
        null,
        'Create Shipment',
        '',
        $capability,
        plugin_dir_path(__FILE__) . 'create_shipment.php'
    );

    // Create C2B Shipment Page
    add_submenu_page(
        null,
        'Create C2B Shipment',
        '',
        $capability,
        plugin_dir_path(__FILE__) . 'create_C2Bshipment.php'
        
    );
}




// Generate and print labels
add_action('wp_ajax_print_all_label', 'smsa_print_all_label');
function smsa_print_all_label() {
    $sett = get_option('woocommerce_smsa-express-integration_settings');
    require_once('fpdf/fpdf.php');
    require_once('fpdi/src/autoload.php');

    class ConcatPdf extends Fpdi {
        public $files = array();
        public function setFiles($files) {
            $this->files = $files;
        }
        public function concat() {
            foreach($this->files as $file) {
                $pageCount = $this->setSourceFile($file);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pageId = $this->ImportPage($pageNo);
                    $s = $this->getTemplatesize($pageId);
                    $this->AddPage($s['orientation'], $s);
                    $this->useImportedPage($pageId);
                }
            }
        }
    }

    $all_files = array();
    $ids = wp_parse_list($_POST['post_ids']);
    $total_count = count($ids);
    $not_exist = 0;

    foreach($ids as $id) {
        $awb = get_post_meta($id, 'smsa_awb_no', true);
        if($awb != NULL) {
            $url = 'https://ecomapis.smsaexpress.com/api/shipment/b2c/query/' . $awb;
            $args = array(
                'headers' => array('apikey' => $sett['smsa_account_no'])
            );
            $response = wp_remote_get($url, $args);
            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($json['waybills'][0]['awbFile'])) {
                $upload_dir = wp_upload_dir();
                $upload_base_path = $upload_dir['path'];
                if(count($json['waybills']) < 2) {
                    $data = base64_decode($json['waybills'][0]['awbFile']);
                    $name = $awb . '.pdf';
                    file_put_contents($upload_base_path . '/' . $name, $data);
                    array_push($all_files, $upload_base_path . '/' . $name);
                } else {
                    $temp_files = array();
                    for($i = 0; $i < count($json['waybills']); $i++) {
                        $data = base64_decode($json['waybills'][$i]['awbFile']);
                        $temp = $i . '_' . $json['waybills'][$i]['awb'] . '.pdf';
                        file_put_contents($upload_base_path . '/' . $temp, $data);
                        array_push($temp_files, $upload_base_path . '/' . $temp);
                    }
                    $name = $awb . '.pdf';
                    $path = $upload_base_path . '/' . $name;
                    $pdf = new ConcatPdf();
                    $pdf->setFiles($temp_files);
                    $pdf->concat();
                    $pdf->Output($upload_base_path . '/' . $name, 'F');
                    foreach($temp_files as $file) {
                        unlink($file);
                    }
                    array_push($all_files, $upload_base_path . '/' . $name);
                }
            }
        } else {
            $not_exist++;
        }
    }

    if($total_count != $not_exist) {
        $name = strtotime("now") . '.pdf';
        $public_url = $upload_dir['url'] . '/' . $name;
        $pdf = new ConcatPdf();
        $pdf->setFiles($all_files);
        $pdf->concat();
        $pdf->Output($upload_base_path . '/' . $name, 'F');
        foreach($all_files as $file) {
            unlink($file);
        }
        $ret = array('response' => 'success', 'msg' => $public_url);
        echo json_encode($ret);
        exit;
    } else {
        $msg = 'These orders were not shipped by SMSA Shipping.';
        $ret = array('response' => 'error', 'msg' => $msg);
        echo json_encode($ret);
        exit;
    }
}


// Generate individual shipment labels
add_action('wp_ajax_generate_label', 'smsa_generate_label');
function smsa_generate_label() {
    $sett = get_option('woocommerce_smsa-express-integration_settings');
    require_once('fpdf/fpdf.php');
    require_once('fpdi/src/autoload.php');

    if (isset($_POST['awb_no']) && !empty($_POST['awb_no'])) {
        $awb_no = sanitize_text_field($_POST['awb_no']);
        $url = 'https://ecomapis.smsaexpress.com/api/shipment/b2c/query/' . $awb_no;
        $args = array(
            'headers' => array('apikey' => $sett['smsa_account_no'])
        );
        $response = wp_remote_get($url, $args);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($json['waybills'][0]['awbFile'])) {
            $upload_dir = wp_upload_dir();
            $data = base64_decode($json['waybills'][0]['awbFile']);
            $name = $awb_no . '.pdf';
            file_put_contents($upload_dir['path'] . '/' . $name, $data);
            $public_url = $upload_dir['url'] . '/' . $name;
            $ret = array('response' => 'success', 'msg' => $public_url);
            echo json_encode($ret);
            exit;
        } else {
            $ret = array('response' => 'error', 'msg' => 'AWB file not found.');
            echo json_encode($ret);
            exit;
        }
    } else {
        $ret = array('response' => 'error', 'msg' => 'Invalid AWB number.');
        echo json_encode($ret);
        exit;
    }
}

// Delete labels
add_action('wp_ajax_delete_label', 'smsa_delete_label');
function smsa_delete_label() {
    $url = esc_url($_POST['attach_url']);
    unlink($_POST['attach_path']);
    $ret = array('response' => 'success');
    echo json_encode($ret);
    exit;
}

// Add "Track Shipment" button in WooCommerce order actions
add_filter('woocommerce_my_account_my_orders_actions', 'smsa_sv_add_my_account_order_actions', 10, 2);
function smsa_sv_add_my_account_order_actions($actions, $order) {
    $awb = get_post_meta($order->get_id(), 'smsa_awb_no', true);
    if ($awb != NULL) {
        $actions['smsa_track_link'] = array(
            'url' => 'https://smsaexpress.com/trackingdetails?tracknumbers=' . $awb,
            'name' => 'Track SMSA Shipment',
        );
    }
    return $actions;
}

// Track link opens in a new tab
add_action('woocommerce_after_account_orders', 'smsa_action_after_account_orders_js');
function smsa_action_after_account_orders_js() {
    ?>
    <script>
    jQuery(function($){
        $('a.smsa_track_link').each(function(){
            $(this).attr('target','_blank');
        });
    });
    </script>
    <?php
}

// Custom padding function
function smsa_mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null) {
    if (!$encoding) {
        $diff = strlen($input) - mb_strlen($input);
    } else {
        $diff = strlen($input) - mb_strlen($input, $encoding);
    }
    return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
}

// Add SMSA AWB meta field when a new order is created
add_action('woocommerce_checkout_update_order_meta', 'register_smsa_awb_meta_field');
function register_smsa_awb_meta_field($order_id) {
    if (!get_post_meta($order_id, 'smsa_awb_no', true)) {
        add_post_meta($order_id, 'smsa_awb_no', '', true);
    }
}


// Add custom AWB field to order admin page
add_action('woocommerce_admin_order_data_after_order_details', 'add_smsa_awb_no_custom_field');
function add_smsa_awb_no_custom_field($order) {
    woocommerce_wp_text_input( 
        array( 
            'id' => 'smsa_awb_no', 
            'label' => __('SMSA AWB Number', 'woocommerce'), 
            'placeholder' => 'No AWB Found!',
            'description' => __('SMSA AWB Number for the order.', 'woocommerce'),
            'type' => 'text',
            'desc_tip' => true,
            'value' => get_post_meta($order->get_id(), 'smsa_awb_no', true),
            'custom_attributes' => array('readonly' => 'readonly') // Make the field read-only
        )
    );
}

// Save custom AWB field data
add_action('woocommerce_process_shop_order_meta', 'save_smsa_awb_no_custom_field');
function save_smsa_awb_no_custom_field($post_id) {
    $smsa_awb_no = isset($_POST['smsa_awb_no']) ? sanitize_text_field($_POST['smsa_awb_no']) : '';
    update_post_meta($post_id, 'smsa_awb_no', $smsa_awb_no);
}

// Register "Shipped" order status
add_action('init', 'register_shipped_order_status');
function register_shipped_order_status() {
    register_post_status('wc-shipped', array(
        'label'                     => 'Shipped',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
    ));
}

// Add "Shipped" status to WooCommerce order statuses
add_filter('wc_order_statuses', 'add_shipped_to_order_statuses');
function add_shipped_to_order_statuses($order_statuses) {
    $new_order_statuses = array();
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-shipped'] = 'Shipped';
        }
    }
    return $new_order_statuses;
}

?>
