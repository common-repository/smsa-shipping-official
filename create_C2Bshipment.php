<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>
<body>

<div id="smsa-order">
    <img src="<?php echo get_site_url(); ?>/wp-content/plugins/smsa-shipping-official/images/logoEn.png" alt="Logo" width="150px" height="35px">
    <h3 class="wp-heading-inline">Returns Manager</h3>
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('?page=smsa-shipping-official/order-list.php');?>" class="smsa_action"><i class="fa-solid fa-house"></i></a>
        </div>
        <div class="alignright actions custom">
            <!-- Add any custom actions here -->
        </div>
    </div>
    <div class="container" style="margin-top:2%">
        <form action="#" style="width:100%" method="post">
        <?php
        // PHP code to handle form submission and shipment creation...

        if ($_POST) {
            // Store values after submission attempt
            $store_address = get_option('woocommerce_store_address');
            $store_address_2 = get_option('woocommerce_store_address_2');
            $store_city = get_option('woocommerce_store_city');
            $store_postcode = get_option('woocommerce_store_postcode');
            $store_raw_country = get_option('woocommerce_default_country');
            $site_title = get_bloginfo('name');
            
            if (strpos($store_raw_country, ':') !== false) {
                $split_country = explode(':', $store_raw_country);
                $store_country = $split_country[0];
                $store_state = $split_country[1];
            } else {
                $store_country = $store_raw_country;
                $store_state = "";
            }
            
            $sett = get_option('woocommerce_smsa-express-integration_settings');
        
            foreach ($_POST as $post) {
                if ($post['parcels'] > 0 && $post['parcels'] < 4 && $post['weight'] > 0 && $post['declaredValue'] > 0) {

                    $defaultServiceCode = ($store_country === $post['addressCountryCode']) ? 'EDCR' : 'EICR';

                    $shipper_data = array(
                        'ContactName' => $site_title,
                        'ContactPhoneNumber' => $sett['store_phone'],
                        'Coordinates' => '',
                        'Country' => $store_country,
                        'District' => $store_state,
                        'PostalCode' => $store_postcode,
                        'City' => $store_city,
                        'AddressLine1' => $store_address,
                        'AddressLine2' => $store_address_2
                    );
        
                    $consignee_data = array(
                        'ContactName' => ucwords($post['c_name']),
                        'ContactPhoneNumber' => $post['c_phone'],
                        'ContactPhoneNumber2' => '',
                        'Coordinates' => '',
                        'Country' => $post['addressCountryCode'],
                        'District' => $post['district'],
                        'PostalCode' => $post['postalCode'],
                        'City' => $post['addressCity'],
                        'AddressLine1' => $post['addressLine1'],
                        'AddressLine2' => $post['addressLine2'],
                        'ConsigneeID' => ''
                    );
        
                    $shipment_data = array(
                        'PickupAddress' => $consignee_data,
                        'ReturnToAddress' => $shipper_data,
                        'OrderNumber' => 'Ref_' . $post['order_id'],
                        'DeclaredValue' => (float)$post['declaredValue'],
                        'Parcels' => (int)$post['parcels'],
                        'ShipDate' => date('Y-m-d\TH:i:s'),
                        'ShipmentCurrency' => $post['shipmentCurrency'],
                        'SMSARetailID' => '0',
                        'WaybillType' => 'PDF',
                        'Weight' => (float)$post['weight'],
                        'WeightUnit' => 'KG',
                        'ContentDescription' => $post['shipmentContents'],
                        'ServiceCode' => $defaultServiceCode
                    );
        
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://ecomapis.smsaexpress.com/api/c2b/new',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => json_encode($shipment_data),
                        CURLOPT_HTTPHEADER => array(
                            'apikey: ' . $sett['smsa_account_no'],
                            'Content-Type: application/json'
                        ),
                    ));
        
                    $response = curl_exec($curl);
                    curl_close($curl);
        
                    $resp1 = json_decode($response);
        
                    if (isset($resp1->sawb)) {
                        update_post_meta($post['order_id'], 'rts_smsa_awb_no', $resp1->sawb);

                        echo "<div class='alert alert-success'>";
                        echo "<strong>Well done!</strong> Return request submitted successfully for order " . $post['reference'];
                        echo "</div>";
                    } elseif (isset($resp1->errors)) {
                        foreach ($resp1->errors as $key => $value) {
                            echo "<div class='alert alert-danger'>";
                                echo "<strong>Error!!</strong> Error (" . $post['reference'] . '): ' . $key . ' - ' . $value[0] . '<br>';
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='alert alert-danger'>";
                        echo "<strong>Error!!</strong> Error: " . $response;
                        echo "</div>";
                    }
                } else {
                    if ($post['declaredValue'] <= 0) {
                        echo "<div class='alert alert-danger'>";
                        echo "<strong>Error!!</strong> The Customs Declared Value must be greater than zero for order " . $post['reference'];
                        echo "</div>";
                    } elseif ($post['weight'] > 0) {
                        echo "<div class='alert alert-danger'>";
                        echo "<strong>Error!!</strong> Please ensure parcel count is between 1-3 for order " . $post['reference'];
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-danger'>";
                        echo "<strong>Error!</strong> Please ensure weight is greater than zero for order " . $post['reference'];
                        echo "</div>";
                    }
                }
            }
        }

        if (!isset($_GET['order_ids'])) {
            wp_redirect(admin_url('edit.php?post_type=shop_order'));
        } else {
            $i = 0;
            foreach ($_GET['order_ids'] as $order_id) {
                $awb_nn = get_post_meta($order_id, 'rts_smsa_awb_no', true);
                if ($awb_nn == "") {
                    $order = wc_get_order($order_id);
                    $order_data = $order->get_data();
                    $dv = $order_data['total'] - $order_data['total_tax'] - $order_data['shipping_total'];
                    $pay_method = $order->get_payment_method();
                    $amount = $pay_method == 'cod' ? $order->get_total() : 0;

                    $weight = 0;
                    $note = array();
                    
                    foreach ($order->get_items() as $item) {
                        if ($item['product_id'] > 0) {
                            $_product = $item->get_product();
                            if (!$_product->is_virtual() && $_product->get_weight() != "") {
                                $weight += $_product->get_weight() * $item['qty'];
                            }
                            $sku = $_product->get_sku(); // Fetch SKU
                            $quantity = $item->get_quantity(); // Fetch quantity
                            $note[] = $_product->get_name() . ' (SKU: ' . $sku . ', Qty: ' . $quantity . ')';
                        }
                    }

                    $weight_unit = get_option('woocommerce_weight_unit');
                    switch ($weight_unit) {
                        case 'lbs':
                            $weight *= 0.4535;
                            break;
                        case 'g':
                            $weight /= 1000;
                            break;
                        case 'oz':
                            $weight *= 0.0283495;
                            break;
                    }
                    $final_note = implode(",", $note);

                    $phone = $order->get_billing_phone() ?: get_post_meta($order_id, '_shipping_phone', true);

                    ?>
                    <div class="alert alert-secondary text-center">
                        <h3>RETURN FROM ADDRESS</h3>
                        <h3>عنوان إستلام المسترجعات</h3>
                        <h3>Order# <?php echo $order_id; ?></h3>
                    </div>
                    
                    <input type="hidden" name="<?php echo $i; ?>[order_id]" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="<?php echo $i; ?>[reference]" value="Ref_<?php echo $order_id; ?>">
                    <input type="hidden" name="<?php echo $i; ?>[shipmentCurrency]" value="<?php echo $order_data['currency']; ?>">
                    <div class="row">
                        <div class="col">
                            <label>Name</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[c_name]" value="<?php echo isset($post['c_name']) ? $post['c_name'] : $order_data['shipping']['first_name'] . ' ' . $order_data['shipping']['last_name']; ?>" required>
                        </div>
                        <div class="col">
                            <label>Phone</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[c_phone]" value="<?php echo isset($post['c_phone']) ? $post['c_phone'] : $phone; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Address Line1</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[addressLine1]" value="<?php echo isset($post['addressLine1']) ? $post['addressLine1'] : $order_data['shipping']['address_1']; ?>">
                        </div>
                        <div class="col">
                            <label>Address Line2</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[addressLine2]" value="<?php echo isset($post['addressLine2']) ? $post['addressLine2'] : $order_data['shipping']['address_2']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>City</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[addressCity]" value="<?php echo isset($post['addressCity']) ? $post['addressCity'] : $order_data['shipping']['city']; ?>" required>
                        </div>
                        <div class="col">
                            <label>District</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[district]" value="<?php echo isset($post['district']) ? $post['district'] : $order_data['shipping']['state']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Country Code</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[addressCountryCode]" value="<?php echo isset($post['addressCountryCode']) ? $post['addressCountryCode'] : $order_data['shipping']['country']; ?>" required>
                        </div>
                        <div class="col">
                            <label>Postal Code</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[postalCode]" value="<?php echo isset($post['postalCode']) ? $post['postalCode'] : $order_data['shipping']['postcode']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label class="pb-text">Customs Declared Value (<?php echo $order_data['currency']; ?>)</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[declaredValue]" value="<?php echo isset($post['declaredValue']) ? $post['declaredValue'] : $dv; ?>" required>
                        </div>
                        <div class="col">
                            <label>Number of parcel</label>
                            <input type="number" class="form-control" name="<?php echo $i; ?>[parcels]" value="<?php echo isset($post['parcels']) ? $post['parcels'] : 1; ?>" min="1" max="3">
                        </div>
                        <div class="col">
                            <label>Weight(KG)</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[weight]" value="<?php echo isset($post['weight']) ? $post['weight'] : $weight; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Products Description</label>
                            <input type="text" class="form-control" name="<?php echo $i; ?>[shipmentContents]" value="<?php echo isset($post['shipmentContents']) ? $post['shipmentContents'] : $final_note; ?>" required>                            </div>
                            </div>
                    </div>
                    <hr/>
                    <?php 
                    $i++;
                }
            }

            if ($i > 0) {
                echo '<button type="submit" class="smsa_action sub-btn">Submit Return Request</button>';
            } else {
                ?>
                <script>
                    $('.sub-btn').hide();
                </script>
                <?php 
                if (!$_POST) {
                    echo "<div class='alert alert-danger'>Return request was already submitted for this order!</div>";
                }
            }
        }
        ?>
    </form>
</div>
</body>
</html>
