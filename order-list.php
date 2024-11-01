<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>
<body>
<?php
$query = new WC_Order_Query(array(
    'limit' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'ids',
    'type' => 'shop_order',
));
$orders = $query->get_orders();
?>
<div id="smsa-order">
    <img src="<?php echo get_site_url(); ?>/wp-content/plugins/smsa-shipping-official/images/logoEn.png" alt="Logo" width="150px" height="35px">
    <h3 class="wp-heading-inline">Orders Dashboard</h3>
    <div class="container" style="margin-top:2%; width: 100%;">
    <div class="alert alert-dismissible alert-warning">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <h4 class="alert-heading">New Update!</h4>
  <p class="mb-0">This plugin uses an API key, Sign in to your SMSA E-Commerce dashboard and navigate to API documents. <a href="https://ecom.smsaexpress.com/" class="alert-link" target="_blank">SMSA E-Commerce Platform</a>.</p>
</div>
        <table id="example" class="table table-striped wp-list-table widefat fixed striped table-view-list orders wc-orders-list-table wc-orders-list-table-shop_order">
            <thead>
            <tr>
                    
                    <th colspan="8" class="table-actions">
                        <button id="sync-page" type="button" class="button sync-button">
                            <i class="fas fa-sync"></i> Sync Orders
                        </button>

                        <button id="create-all" type="button" class="button">
                            <i class="fas fa-box"></i> Create Shipment
                        </button>
                        <button id="create-all-c2b" type="button" class="button">
                            <i class="fas fa-arrow-left"></i> Create Return
                        </button>
                        <button id="print-all" type="button" class="button">
                            <i class="fas fa-print"></i> Print Label
                        </button>
                    </th>
                </tr>
                <tr>
                    <th><input id="cb-select-all-1" type="checkbox"></th>
                    <th>Details</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th style="width: 10%;">COD</th> <!-- Adjusted the width of COD column -->
                    <th style="width: auto;">Actions</th>
                    <th>Delivery AWB</th>
                    <th>Return AWB</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order1) {

                    $order = wc_get_order($order1);
                     // Skip orders with status "checkout-draft", "on-hold", "failed", or "cancelled"
                    $skip_statuses = array('checkout-draft', 'on-hold', 'failed', 'cancelled');
                    if (in_array($order->get_status(), $skip_statuses)) {
                    continue;
                    }
                    
                    $order_id = $order->get_id();
                    $f_date = date("d M, Y", strtotime($order->get_date_created()));

                    $pay_method = $order->get_payment_method();
                    $amount = $pay_method == 'cod' ? $order->get_total() : 0;

                    $smsa_awb_no = get_post_meta($order_id, 'smsa_awb_no', true);
                    $rts_smsa_awb_no = get_post_meta($order_id, 'rts_smsa_awb_no', true);
                    ?>
                <tr>
    <td>
        <input id="cb-select-19" class="order_check" type="checkbox" name="id[]" value="<?php echo $order_id; ?>">
    </td>
    <td>
        <a href="admin.php?page=wc-orders&action=edit&id=<?php echo $order_id; ?>">
            Order# <?php echo $order_id . " | Ship To: " . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . " | " . $order->get_billing_city() . " | " . $order->get_billing_country(); ?>
        </a>
    </td>
    <td><?php echo $f_date; ?></td>
    <td><?php echo ucwords($order->get_status()); ?></td>
    <td><?php echo $amount;?></td>
    <td>
        <?php if (empty($smsa_awb_no)) { ?>
            <a href="<?php echo admin_url('admin.php?page=smsa-shipping-official/create_shipment.php&order_ids[]=' . esc_attr($order_id)); ?>" class="smsa_action" title="Ship"><i class="fa-solid fa-truck-fast"></i></a>
        <?php } ?>
        <?php if (empty($rts_smsa_awb_no)) { ?>
            &nbsp;&nbsp;&nbsp;<a href="<?php echo admin_url('admin.php?page=smsa-shipping-official/create_C2Bshipment.php&order_ids[]=' . esc_attr($order_id)); ?>" class="smsa_action"  title="Return"><i class="fa-solid fa-rotate-left"></i></a>
        <?php }?>
    </td>
    <td>
        <?php if (!empty($smsa_awb_no)) { ?>
            <a href="<?php echo admin_url('admin.php?page=smsa-shipping-official/track_order.php&awb_no=' . esc_attr($smsa_awb_no)); ?>" title="Track Shipment"><?php echo esc_html($smsa_awb_no); ?></a>
        <?php } ?>
    </td>
    <td>
        <?php if (!empty($rts_smsa_awb_no)) { ?>
            <a href="<?php echo admin_url('admin.php?page=smsa-shipping-official/track_order.php&awb_no=' . esc_attr($rts_smsa_awb_no)); ?>" title="Track Return"><?php echo esc_html($rts_smsa_awb_no); ?></a>
        <?php } ?>
    </td>
</tr>

                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>