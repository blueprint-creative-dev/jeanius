<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use Dompdf\Dompdf;

// Ensure Dompdf is loaded
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

function send_results_pdf_from_dom() {
    if ( empty($_POST['html']) ) {
        wp_send_json_error("No HTML received.");
    }

    $raw_html = stripslashes($_POST['html']);

    $dompdf = new Dompdf();
    $dompdf->loadHtml($raw_html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/jeanius-report.pdf';
    file_put_contents($pdf_path, $dompdf->output());

    $user = wp_get_current_user();
    $to = $user->user_email;
    $subject = "Your Jeanius Report PDF";
    $body = "Please find the attached report.";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Add temporary filters
    add_filter('wp_mail_from_name', 'custom_jeanius_mail_name');

    function custom_jeanius_mail_name($name) {
        return 'Jeanius';
    }

    $sent = wp_mail($to, $subject, $body, $headers, [$pdf_path]);

    // Remove filters immediately after
    remove_filter('wp_mail_from_name', 'custom_jeanius_mail_name');
    
    unlink($pdf_path);

    if ( $sent ) {
        wp_send_json_success("PDF emailed successfully!");
    } else {
        wp_send_json_error("Failed to send PDF email.");
    }
}

// Register AJAX actions
add_action('wp_ajax_send_results_pdf_from_dom', 'send_results_pdf_from_dom');
add_action('wp_ajax_nopriv_send_results_pdf_from_dom', 'send_results_pdf_from_dom');

// don'show admin bar
add_action('after_setup_theme', 'remove_admin_bar_for_customers');
function remove_admin_bar_for_customers() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (in_array('customer', (array) $current_user->roles)) {
            show_admin_bar(false);
        }
    }
}