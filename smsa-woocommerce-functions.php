<?php

function update_woocommerce_order_with_awb($order_id, $awb_number) {
    if ($order_id && $awb_number) {
        $order = wc_get_order($order_id);
        if ($order) {
            update_post_meta($order_id, 'smsa_awb_no', $awb_number); // Update AWB in custom field
            $order->add_order_note("SMSA AWB Number: " . $awb_number);
            $order->update_status('wc-shipped'); // Update to "Shipped" status
        }
    }
}



?>
