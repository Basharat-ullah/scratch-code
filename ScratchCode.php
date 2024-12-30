<?php
/**
 * Plugin Name: Scratch Code
 * Description: This WordPress plugin manages scratch codes and customer details with built-in validation to ensure only valid, unique scratch codes are accepted which are inserted. Invalid or unavailable or used codes are rejected in real-time and during form submission. The plugin includes a shortcode for customer submition..
 * Version: 1.1.1
 * Author: Basharat Ullah
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'scratch_code_plugin_activate');

function scratch_code_plugin_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';
    $table_customer_details = $wpdb->prefix . 'customer_scratch_details';

    $sql_scratch_codes = "CREATE TABLE IF NOT EXISTS $table_scratch_codes (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ScratchCode VARCHAR(255) NOT NULL,
        CodeAvailable TINYINT(1) DEFAULT 0,
        Customer_Scratch_Details_ID BIGINT(20) UNSIGNED,
        date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_customer_details = "CREATE TABLE IF NOT EXISTS $table_customer_details (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        Scratch_Codes_ID BIGINT(20) UNSIGNED NOT NULL,
        FullName VARCHAR(255) NOT NULL,
        PhoneNumber VARCHAR(20) NOT NULL,
        CNIC VARCHAR(50) NOT NULL,
        Address TEXT NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (Scratch_Codes_ID) REFERENCES $table_scratch_codes(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_scratch_codes);
    dbDelta($sql_customer_details);
}

add_action('admin_menu', 'scratch_code_plugin_admin_menu');

function scratch_code_plugin_admin_menu() {
    add_menu_page(
        'Scratch Code Management', 
        'Scratch Codes', 
        'manage_options', 
        'scratch-code-management', 
        'scratch_code_management_page', 
        'dashicons-list-view', 
        20
    );

    add_submenu_page(
        'scratch-code-management', 
        'View Scratch Codes', 
        'Scratch Codes', 
        'manage_options', 
        'scratch-code-management', 
        'scratch_code_management_page'
    );

    add_submenu_page(
        'scratch-code-management', 
        'View Customer Details', 
        'Customer Details', 
        'manage_options', 
        'customer-details', 
        'customer_details_page'
    );
}

function scratch_code_management_page() {
    global $wpdb;

    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';
    $table_customer_details = $wpdb->prefix . 'customer_scratch_details';
    $data = $wpdb->get_results(
        "SELECT 
            sc.id, 
            sc.ScratchCode, 
            sc.CodeAvailable, 
            sc.Customer_Scratch_Details_ID, 
            sc.date_updated,
            cd.FullName AS CustomerName,
            cd.CNIC AS NIC
        FROM $table_scratch_codes sc
        LEFT JOIN $table_customer_details cd 
        ON sc.Customer_Scratch_Details_ID = cd.id
        LIMIT 100",
        ARRAY_A
    );

    echo '<div class="wrap"><h1>Scratch Codes</h1>';

    if (empty($data)) {
        echo '<p>No scratch codes available.</p>';
    } else {
        echo '<table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Scratch Code</th>
                    <th>Code Available</th>
                    <th>Customer Details</th>
                    <th>Date Updated</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data as $row) {
            echo '<tr>
                <td>' . esc_html($row['id']) . '</td>
                <td>' . esc_html($row['ScratchCode']) . '</td>
                <td>' . esc_html($row['CodeAvailable']) . '</td>
                <td>' . esc_html($row['CustomerName'].'-'.$row['NIC'] ?? 'N/A') . '</td>
                <td>' . esc_html($row['date_updated']) . '</td>
            </tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}

function customer_details_page() {
    global $wpdb;

    $table_customer_details = $wpdb->prefix . 'customer_scratch_details';
    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';
    $data = $wpdb->get_results(
        "SELECT 
            cd.id, 
            sc.ScratchCode, 
            cd.FullName, 
            cd.PhoneNumber, 
            cd.CNIC, 
            cd.Address 
        FROM $table_customer_details cd
        LEFT JOIN $table_scratch_codes sc
        ON cd.Scratch_Codes_ID = sc.id
        LIMIT 100",
        ARRAY_A
    );

    echo '<div class="wrap"><h1>Customer Details</h1>';
    echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom: 20px;">
    <input type="hidden" name="action" value="export_customer_csv">
    <button type="submit" class="button button-secondary">Export to CSV</button>
    </form>';
    if (empty($data)) {
        echo '<p>No customer details available.</p>';
    } else {
        echo '<table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Scratch Code</th>
                    <th>Full Name</th>
                    <th>Phone Number</th>
                    <th>CNIC</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data as $row) {
            echo '<tr>
                <td>' . esc_html($row['id']) . '</td>
                <td>' . esc_html($row['ScratchCode'] ?? 'N/A') . '</td>
                <td>' . esc_html($row['FullName']) . '</td>
                <td>' . esc_html($row['PhoneNumber']) . '</td>
                <td>' . esc_html($row['CNIC']) . '</td>
                <td>' . esc_html($row['Address']) . '</td>
            </tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';


}

add_action('admin_post_export_customer_csv', 'export_customer_csv');
function export_customer_csv() {
    global $wpdb;

    $table_customer_details = $wpdb->prefix . 'customer_scratch_details';
    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';

    $data = $wpdb->get_results(
        "SELECT 
            cd.id, 
            sc.ScratchCode, 
            cd.FullName, 
            cd.PhoneNumber, 
            cd.CNIC, 
            cd.Address, 
            cd.date_created 
        FROM $table_customer_details cd
        LEFT JOIN $table_scratch_codes sc
        ON cd.Scratch_Codes_ID = sc.id",
        ARRAY_A
    );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customer_details.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Scratch Code', 'Full Name', 'Phone Number', 'CNIC', 'Address', 'Date Created']);

    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

add_action('admin_post_export_scratch_codes_csv', 'export_scratch_codes_csv');
function export_scratch_codes_csv() {
    global $wpdb;

    $table_scratch_codes = $wpdb->prefix . 'scratch_codes';
    $data = $wpdb->get_results(
        "SELECT id, ScratchCode, CodeAvailable, Customer_Scratch_Details_ID, date_updated FROM $table_scratch_codes",
        ARRAY_A
    );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="scratch_codes.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Scratch Code', 'Code Available', 'Customer Scratch Details ID', 'Date Updated']);

    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}


require_once plugin_dir_path(__FILE__) . 'includes/ScratchCodeInput.php';
require_once plugin_dir_path(__FILE__) . 'includes/CustomerInputForm.php';
