<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo get_site_url(); ?>/wp-content/plugins/smsa-shipping-official/css/smsa-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div id="smsa-order">
    <img src="<?php echo get_site_url(); ?>/wp-content/plugins/smsa-shipping-official/images/logoEn.png" alt="Logo" width="150px" height="35px">
    <h3 class="wp-heading-inline">Tracking Manager</h3>
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('?page=smsa-shipping-official/order-list.php');?>" class="smsa_action"><i class="fa-solid fa-house"></i></a>
        </div>
        <div class="alignright actions custom">
            <!-- Add any custom actions here -->
        </div>
    </div>
    <?php
    if (!isset($_GET['awb_no'])) {
    ?>
        <div class="alert">Visit the order page to track the order</div>
    <?php 
    } else {
        $sett = get_option('woocommerce_smsa-express-integration_settings');

        $awb_no = sanitize_text_field($_GET['awb_no']);
        $url = 'https://ecomapis.smsaexpress.com/api/track/single/' . $awb_no;
        $args = array(
            'headers' => array(
                'apikey' => $sett['smsa_account_no'],
                'Content-Type' => 'application/json'
            )
        );

        $response = wp_remote_get($url, $args);
        $arr = json_decode($response['body'], true);

        if (isset($arr['AWB'])) {
        ?>
        <br>
        <h3>Details</h3>    
        <table id="trackTable"class="table table-bordered">
            <thead>
                <tr>
                    <th>AWB</th>
                    <th>Reference</th>
                    <th>COD Amount</th>
                    <th>From</th>
                    <th>To</th>
                </tr>    
            </thead>
            <tbody>
                <tr>
                    <td class="highlight"><?php echo esc_html($arr['AWB']); ?></td>
                    <td><?php echo esc_html($arr['Reference']); ?></td>
                    <td><?php echo esc_html($arr['CODAmount']); ?></td>
                    <td><?php echo esc_html($arr['OriginCity']); ?></td>
                    <td><?php echo esc_html($arr['DesinationCity']); ?></td>
                </tr>
            </tbody>
        </table>

        <h3>Events</h3>
        <table id="trackTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ($arr['Scans'] as $scan) {
                $location = !empty($scan['City']) ? esc_html($scan['City']) : 'System';
                $dateTime = date("d M Y, h:i A", strtotime($scan['ScanDateTime']));
                echo '<tr>';
                echo '<td>' . $location . '</td>';
                echo '<td>' . esc_html($scan['ScanDescription']) . '</td>';
                echo '<td>' . $dateTime . '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
        } else {
    ?>
        <div class='alert alert-danger'>
        <center><strong>Ops!</strong> Still Order Not Picked-Up by SMSA.</div></center>
    <?php
        }
    }
    ?>
</body>
</html>
