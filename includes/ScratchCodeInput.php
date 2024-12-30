<?php
/**
 * Add scratch codes form in the WordPress admin dashboard.
 */


add_action('admin_menu', 'bulk_scratch_code_input_menu');

function bulk_scratch_code_input_menu() {
    add_submenu_page(
        'scratch-code-management',
        'Enter Multipe Scratch Codes', 
        'Code Input',              
        'manage_options',          
        'bulk-scratch-code-input', 
        'bulk_scratch_code_input_page'     );
}

function bulk_scratch_code_input_page() {
    global $wpdb;

    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scratch_code_nonce'])) {
        if (!wp_verify_nonce($_POST['scratch_code_nonce'], 'scratch_code_form')) {
            echo '<div class="notice notice-error"><p>Invalid request.</p></div>';
            return;
        }

        if (isset($_POST['scratch_codes']) && !empty($_POST['scratch_codes'])) {
            $scratch_codes_input = sanitize_textarea_field($_POST['scratch_codes']);
            $scratch_codes = preg_split('/[\r\n]+/', $scratch_codes_input);

            $scratch_codes = array_map('sanitize_text_field', array_filter($scratch_codes, 'strlen'));

            if (empty($scratch_codes)) {
                echo '<div class="notice notice-error"><p>Please enter valid scratch codes.</p></div>';
                return;
            }

            $batch_size = 1000;
            $batch = [];
            $counter = 0;
            $failed = 0;

            foreach ($scratch_codes as $scratch_code) {
                $batch[] = [
                    'ScratchCode' => $scratch_code,
                    'CodeAvailable' => 0,
                    'Customer_Scratch_Details_ID' => null,
                    'date_updated' => current_time('mysql')
                ];
                if (count($batch) >= $batch_size) {
                    $result = insert_batch($wpdb, $table_scratch_codes, $batch);
                    if (!$result) {
                        $failed += count($batch);
                    }
                    $batch = [];
                    $counter += $batch_size;
                }
            }

            if (!empty($batch)) {
                $result = insert_batch($wpdb, $table_scratch_codes, $batch);
                if (!$result) {
                    $failed += count($batch);
                }
                $counter += count($batch);
            }

            if ($failed > 0) {
                echo '<div class="notice notice-warning"><p>' . esc_html($counter) . ' scratch codes Inserted successfully, but ' . esc_html($failed) . ' failed to insert.</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($counter) . ' scratch codes uploaded successfully!</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Please enter valid scratch codes.</p></div>';
        }
    }

    
    ?>
    <div class="wrap">
        <h1>Enter Multipe Scratch Codes</h1>
        <form method="POST">
            <label for="scratch_codes">Enter Scratch Codes (one per line):</label><br>
            <textarea id="scratch_codes" name="scratch_codes" rows="10" cols="50" required></textarea><br><br>
            <?php wp_nonce_field('scratch_code_form', 'scratch_code_nonce'); ?>
            <button type="submit" class="button button-primary">Submit</button>
        </form>
    </div>
    <?php
}

function insert_batch($wpdb, $table_name, $batch) {
    $placeholders = [];
    $values = [];
    foreach ($batch as $record) {
        $placeholders[] = "( %s, %d, %d, %s )"; // 4 columns in the table
        $values[] = $record['ScratchCode'];
        $values[] = $record['CodeAvailable'];
        $values[] = $record['Customer_Scratch_Details_ID'];
        $values[] = $record['date_updated'];
    }

    $sql = "INSERT INTO $table_name (ScratchCode, CodeAvailable, Customer_Scratch_Details_ID, date_updated) VALUES " . implode(', ', $placeholders);
    
    $prepared_query = $wpdb->prepare($sql, ...$values);

    $result = $wpdb->query($prepared_query);

    if ($result === false) {
        error_log("Error inserting scratch codes: " . $wpdb->last_error);
        return false;
    }
    return true;
}
