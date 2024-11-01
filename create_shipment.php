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
    <h3 class="wp-heading-inline">Shipping Manager</h3>
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
            require_once plugin_dir_path(__FILE__) . 'smsa-woocommerce-functions.php';

            $store_raw_country = get_option('woocommerce_default_country');
            $store_country = '';
            $store_state = '';

            if (strpos($store_raw_country, ':') !== false) {
                $split_country = explode(':', $store_raw_country);
                $store_country = $split_country[0];
                $store_state = $split_country[1];
            } else {
                $store_country = $store_raw_country;
            }

            if ($_POST) {
                $store_address = get_option('woocommerce_store_address');
                $store_address_2 = get_option('woocommerce_store_address_2');
                $store_city = get_option('woocommerce_store_city');
                $store_postcode = get_option('woocommerce_store_postcode');
                $site_title = get_bloginfo('name');

                $sett = get_option('woocommerce_smsa-express-integration_settings');

                foreach ($_POST as $post) {
                    $addressCountryCode = isset($post['addressCountryCode']) ? $post['addressCountryCode'] : '';

                    if ($post['parcels'] > 0 && $post['parcels'] < 4 && $post['weight'] > 0 && $post['declaredValue'] > 0) {
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
                            'Country' => $addressCountryCode, 
                            'District' => $post['district'],
                            'PostalCode' => $post['postalCode'],
                            'City' => $post['addressCity'],
                            'AddressLine1' => $post['addressLine1'],
                            'AddressLine2' => $post['addressLine2'],
                            'ConsigneeID' => ''
                        );

                        $defaultServiceCode = ($store_country === $addressCountryCode) ? 'EDDL' : 'EIDL';

                        $shipment_data = array(
                            'ConsigneeAddress' => $consignee_data,
                            'ShipperAddress' => $shipper_data,
                            'OrderNumber' => 'Ref_' . $post['order_id'],
                            'DeclaredValue' => (float)$post['declaredValue'],
                            'CODAmount' => (float)$post['cod'],
                            'Parcels' => (int)$post['parcels'],
                            'ShipDate' => date('Y-m-d\TH:i:s'),
                            'ShipmentCurrency' => $post['shipmentCurrency'],
                            'SMSARetailID' => '0',
                            'WaybillType' => 'PDF',
                            'Weight' => (float)$post['weight'],
                            'WeightUnit' => 'KG',
                            'ContentDescription' => $post['shipmentContents'],
                            'VatPaid' => $post['vatPaid'] === 'true',
                            'DutyPaid' => $post['dutyPaid'] === 'true',
                            'ServiceCode' => isset($post['serviceCode']) ? $post['serviceCode'] : $defaultServiceCode
                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => 'https://ecomapis.smsaexpress.com/api/shipment/b2c/new',
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
                            update_post_meta($post['order_id'], 'smsa_awb_no', $resp1->sawb);
                            update_woocommerce_order_with_awb($post['order_id'], $resp1->sawb);

                            echo "<div class='alert alert-success'>";
                            echo "<strong>Well done!</strong> AWB number generated successfully for order " . $post['reference'];
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
                    $awb_nn = get_post_meta($order_id, 'smsa_awb_no', true);
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

                        $defaultServiceCode = ($store_country === $order_data['shipping']['country']) ? 'EDDL' : 'EIDL';
                        ?>
                        <div class="alert alert-secondary text-center">
                        <h3>DELIVERY ADDRESS</h3>
                        <h3>عنوان التوصيل</h3>
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
                                <label class="pb-text">Currency</label>
                                <select class="form-control" name="<?php echo $i; ?>[shipmentCurrency]">
                                    <?php
                                    $available_currencies = ['SAR', 'OMR', 'BHD', 'AED', 'KWD', 'JOD', 'EGP', 'QAR'];
                                    $shipment_currency = isset($post['shipmentCurrency']) ? $post['shipmentCurrency'] : $order_data['currency'];

                                    foreach ($available_currencies as $currency) {
                                        echo '<option value="' . $currency . '"' . ($currency === $shipment_currency ? ' selected' : '') . '>' . $currency . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col">
                                <label class="pb-text">Customs Declared Value (<?php echo $shipment_currency; ?>)</label>
                                <input type="text" class="form-control" name="<?php echo $i; ?>[declaredValue]" value="<?php echo isset($post['declaredValue']) ? $post['declaredValue'] : $dv; ?>" required>
                            </div>
                            <div class="col">
                                <label class="pb-text">Total Cash on Delivery (<?php echo $shipment_currency; ?>)</label>
                                <input type="text" class="form-control" name="<?php echo $i; ?>[cod]" value="<?php echo isset($post['cod']) ? $post['cod'] : $amount; ?>" required>
                            </div>
                        </div>
                        <div class="row">
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
                        
                        <!-- Conditional Dropdowns -->
                        <div id="conditional-dropdowns">
                            <div class="row">
                                <div class="col-md-4">
                                    <label>Vat Payment</label>
                                    <select class="form-control" name="<?php echo $i; ?>[vatPaid]">
                                        <option value="false" <?php echo isset($post['vatPaid']) && $post['vatPaid'] === 'false' ? 'selected' : ''; ?>>Bill Consignee</option>
                                        <option value="true" <?php echo isset($post['vatPaid']) && $post['vatPaid'] === 'true' ? 'selected' : ''; ?>>Bill Shipper</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Duty Payment</label>
                                    <select class="form-control" name="<?php echo $i; ?>[dutyPaid]">
                                        <option value="false" <?php echo isset($post['dutyPaid']) && $post['dutyPaid'] === 'false' ? 'selected' : ''; ?>>Bill Consignee</option>
                                        <option value="true" <?php echo isset($post['dutyPaid']) && $post['dutyPaid'] === 'true' ? 'selected' : ''; ?>>Bill Shipper</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Service Type</label>
                                    <select class="form-control" name="<?php echo $i; ?>[serviceCode]">
                                        <option value="EDDL" <?php echo $defaultServiceCode === 'EDDL' ? 'selected' : ''; ?>>ECOM Delivery Lite (EDDL)</option>
                                        <option value="EDDH">ECOM Delivery Heavy (EDDH)</option>
                                        <option value="EIDL" <?php echo $defaultServiceCode === 'EIDL' ? 'selected' : ''; ?>>ECOM International Delivery Lite (EIDL)</option>
                                        <option value="EIED">ECOM International Economy Delivery (EIED)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr/>
                        <?php 
                        $i++;
                    }
                }

                if ($i > 0) {
                    echo '<button type="submit" class="smsa_action" >Create Shipment</button>';
                } else {
                    ?>
                    <script>
                        $('.sub-btn').hide();
                    </script>
                    <?php 
                    if (!$_POST) {
                        echo "<div class='alert alert-danger'>";
                        echo "<strong>Error!!</strong> Shipment is already created for this order!";
                        echo "</div>";
                    }
                }
            }
            ?>
        </form>
    </div>
</div>

</body>
</html>
