jQuery(document).ready(function($) {

    // Handle Create Shipment click
    $('#create-all').click(function() {
        var selectedOrders = [];
        $("input[name='id[]']:checked").each(function() {
            selectedOrders.push('&order_ids[]=' + $(this).val());
        });
        if (selectedOrders.length < 1) {
            alert("Please select an order first.");
        } else {
            var url = smsa_vars.admin_url + "admin.php?page=smsa-shipping-official/create_shipment.php" + selectedOrders.join("");
            window.open(url, "_self");
        }
    });

    // Handle Create Return click
    $('#create-all-c2b').click(function() {
        var selectedOrders = [];
        $("input[name='id[]']:checked").each(function() {
            selectedOrders.push('&order_ids[]=' + $(this).val());
        });
        if (selectedOrders.length < 1) {
            alert("Please select an order first.");
        } else {
            var url = smsa_vars.admin_url + "admin.php?page=smsa-shipping-official/create_C2Bshipment.php" + selectedOrders.join("");
            window.open(url, "_self");
        }
    });

    // Handle Print Label click
    $('#print-all').click(function() {
        var selectedOrders = [];
        $("input[name='id[]']:checked").each(function() {
            selectedOrders.push($(this).val());
        });
        if (selectedOrders.length < 1) {
            alert("Please select an order first.");
        } else {
            $(this).html('Processing...');
            var data = {
                action: 'print_all_label',
                post_ids: selectedOrders
            };
            $.post(smsa_vars.ajaxurl, data, function(response) {
                var json = $.parseJSON(response);
                if (json.response === "success") {
                    var win = window.open(json.msg, '_blank');
                    if (win) {
                        win.focus();
                        setTimeout(function() {
                            win.print();
                        }, 2000);
                    } else {
                        alert('Please allow popups for this website');
                    }
                    setTimeout(function() {
                        var deleteData = {
                            action: 'delete_label',
                            attach_url: json.msg,
                            attach_path: json.path
                        };
                        $.post(smsa_vars.ajaxurl, deleteData, function() {});
                    }, 5000);
                } else {
                    alert(json.msg);
                }
                $('#print-all').html('<i class="fas fa-print"></i> Print Label');
            });
        }
    });

    // Initialize DataTables
    $('#example').DataTable({
        "pageLength": 50,  // Adjust the number of records per page
        "ordering": true,
        "pagingType": "full_numbers"
    });

    // Handle select-all functionality
    $('#cb-select-all-1').click(function() {
        var isChecked = $(this).prop("checked");
        $("input[name='id[]']").prop('checked', isChecked);
    });

    // Handle individual checkbox clicks to update the "select all" checkbox state
    $("input[name='id[]']").click(function() {
        var allChecked = $("input[name='id[]']").length === $("input[name='id[]']:checked").length;
        $('#cb-select-all-1').prop('checked', allChecked);
    });

    // Sync button functionality to reload the page
    $('#sync-page').click(function() {
        location.reload();
    });

});
