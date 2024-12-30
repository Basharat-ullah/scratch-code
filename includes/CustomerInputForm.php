<?php
/**
 *  Customer Details form
 */


if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('customer_details_input', 'customer_details_input_form');

function customer_details_input_form() {
    global $wpdb;
    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';
    $table_customer_details = $wpdb->prefix . 'customer_scratch_details';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_details_nonce'])) {
        if (!wp_verify_nonce($_POST['customer_details_nonce'], 'customer_details_form')) {
            return '<p>Invalid request.</p>';
        }

        $scratch_code = sanitize_text_field($_POST['scratch_code']);
        $full_name = sanitize_text_field($_POST['full_name']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $cnic = sanitize_text_field($_POST['cnic']);
        $address = sanitize_text_field($_POST['address']);

        $scratch_code_row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_scratch_codes WHERE ScratchCode = %s", $scratch_code)
        );

        if (!$scratch_code_row) {
            return '<p>The scratch code is not valid.</p>';
        }

        if ($scratch_code_row->CodeAvailable != 0) {
            return '<p>This code is already used. You cannot use it again.</p>';
        }

        $inserted = $wpdb->insert(
            $table_customer_details,
            [
                'Scratch_Codes_ID' => $scratch_code_row->id,
                'FullName' => $full_name,
                'PhoneNumber' => $phone_number,
                'CNIC' => $cnic,
                'Address' => $address,
                'date_created' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return '<p>Failed to save your details. Please try again.</p>';
        }
	$customer_scratch_details_id = $wpdb->insert_id;
        $updated = $wpdb->update(
            $table_scratch_codes,
            ['CodeAvailable' => 1,
        'Customer_Scratch_Details_ID' => $customer_scratch_details_id,],
            ['id' => $scratch_code_row->id],
            ['%d'],
            ['%d']
        );

        if ($updated === false) {
            return '<p>Failed.</p>';
        }

        echo '<script>window.location.href="' . home_url('/thank-you/') . '";</script>';

    }
    ob_start();
    ?>
    <form id="customer-details-form" method="POST">
        <label for="scratch_code">Scratch Code:</label>
        <input type="text" id="scratch_code" name="scratch_code" required>
        <small id="scratch_code_error" style="color: red;"></small><br>

        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name" required><br>

        <label for="phone_number">Phone Number:</label>
        <input type="text" id="phone_number" name="phone_number" placeholder="03XXXXXXXXX" maxlength="12"  oninput="formatPhoneNumber(this)" required><br>

        <label for="cnic">CNIC:</label>
        <input type="text" id="cnic" name="cnic" placeholder="Enter your CNIC" maxlength="15"  oninput="formatCNIC(this)" required ><br>

        <label for="address">Address:</label>
        <textarea id="address" name="address" required></textarea><br>

        <?php wp_nonce_field('customer_details_form', 'customer_details_nonce'); ?>
        <button type="submit">Submit</button>
    </form>
    <script>
        document.getElementById('scratch_code').addEventListener('blur', function () {
            const scratchCodeInput = this.value;
            const errorElement = document.getElementById('scratch_code_error');

            if (scratchCodeInput === '') {
                errorElement.textContent = 'Scratch code is required.';
                return;
            }

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'validate_scratch_code',
                    scratch_code: scratchCodeInput
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    errorElement.textContent = data.message;
                } else {
                    errorElement.textContent = ''; // Clear error if valid.
                }
            })
            .catch(error => {
                errorElement.textContent = 'An error occurred while validating the code.';
            });
        });
		
		
function formatPhoneNumber(input) {
    // Remove all non-numeric characters
    let value = input.value.replace(/\D/g, '');

    // Insert a hyphen after the 4th character
    if (value.length > 4) {
        value = value.slice(0, 4) + '-' + value.slice(4);
    }

    // Set the value back to the input
    input.value = value;
    
    // Limit to 11 characters (including hyphen)
    if (value.replace('-', '').length > 12) {
        input.value = value.slice(0, 13); // 11 digits + 1 hyphen
    }
}



function formatCNIC(input) {
    // Remove all non-numeric characters
    let value = input.value.replace(/\D/g, '');

    // Add hyphens in the correct positions
    if (value.length > 5) {
        value = value.slice(0, 5) + '-' + value.slice(5);
    }
    if (value.length > 13) {
        value = value.slice(0, 13) +  '-' + value.slice(13);
    }

    // Limit to 15 characters total (including hyphens)
    if (value.length > 15) {
        value = value.slice(0, 15);
    }

    // Update the input field
    input.value = value;
}


    </script>
    <?php
    return ob_get_clean();
}
add_action('wp_ajax_validate_scratch_code', 'validate_scratch_code_ajax');
add_action('wp_ajax_nopriv_validate_scratch_code', 'validate_scratch_code_ajax');

function validate_scratch_code_ajax() {
    global $wpdb;
    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';

    $scratch_code = sanitize_text_field($_POST['scratch_code']);
    $response = ['valid' => false, 'message' => ''];

    $scratch_code_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_scratch_codes WHERE ScratchCode = %s", $scratch_code)
    );

    if (!$scratch_code_row) {
        $response['message'] = 'The scratch code is not valid.';
    } elseif ($scratch_code_row->CodeAvailable != 0) {
        $response['message'] = 'This code is already used. You cannot use it again.';
    } else {
        $response['valid'] = true;
    }

    wp_send_json($response);
}
?>
