<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function send_results_pdf_from_dom()
{
    if (empty($_POST['html'])) {
        wp_send_json_error("No HTML received.");
    }

    $raw_html = stripslashes($_POST['html']);

    $raw_html = preg_replace('/<a[^>]*id=["\']sendPdfBtn["\'][^>]*>.*?<\/a>/is', '', $raw_html);

    // Extract <header id="result-page-header"> from HTML
    if (preg_match('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', $raw_html, $matches)) {
        $pdf_header_html = $matches[0];
    } else {
        $pdf_header_html = '';
    }

    // Remove original header from normal flow so it’s not duplicated
    $raw_html = preg_replace('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', '', $raw_html);

    $extra_css = '
    <style>
        @font-face {
            font-family: "Bebas Neu Pro Regular";
            font-weight: 400;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-regular.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX EB";
            font-weight: 800;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-expeb.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
            font-display: swap;
            src: url("fonts/bebasneuepro-expebit.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX MD";
            font-weight: 500;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-expmd.ttf") format("truetype");
        }
        @font-face {
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
            font-display: swap;
            src: url("fonts/MacklinSans-BoldItalic.ttf") format("truetype");
        }
        @font-face {
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
            font-display: swap;
            src: url("fonts/MacklinSans-ExtraBoldIt.ttf") format("truetype");
        }
        @page {
            margin-top: 150px;
            margin-left: 0;
            margin-right: 0;
            margin-bottom: 70px;
        }

        .no-pdf {
            display: none !important;
        }

        body {
            margin: 0;
            padding: 0;
        }
        #result-page-header {
            background-color: #87B9E1;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        #result-page-header .header-container {
            padding: 20px 20px 20px 60px;
        }
        #result-page-header td {
            vertical-align: middle;
        }
        .pdf-fixed-header {
            position: fixed;
            top: -150px;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .pdf-footer {
            position: fixed;
            bottom: -70px;
            left: 0;
            right: 0;
            height: 30px;
            font-size: 12px;
            color: #555;
            text-align: center;
            border-top: 1px solid #ccc;
            line-height: 30px;
        }
        .no-break {
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        h2:not(.title) {
            color: #003d7c;
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
            margin-bottom: 0;
        }

        #loading {
            font-size: 1.1rem;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 0;
        }

        th,
        td {
            border: none;
            padding: 6px;
        }

        ul {
            margin-left: 1.2em;
        }

        header {
            background-color: #8bb9dc;
        }

        .logo img {
            width: 75%;
        }

        .header-container {
            padding: 20px 20px 20px 60px;
        }

        .header-container th,
        .header-container td {
            border: none;
        }

        .info {
            text-align: right;
        }

        .info .info-item {
            border-bottom: 3px dotted #1c76bd;
            padding: 5px 0;
        }

        .info .info-item:last-child {
            margin-bottom: 0;
        }

        .info .item-title {
            font-family: "Bebas Neu Pro Regular";
            text-align: right;
            color: #1c76bd;
            font-size: 12px;
            font-weight: 400;
        }

        .info .name,
        .info .date,
        .info .advisor {
            color: #1c76bd;
            font-size: 12px;
            padding-left: 0;
            font-family: "Bebas Neu Pro EX EB";
            font-weight: 800;
        }

        .intro-section .stake-content {
            position: relative;
            width: 700px;
            margin: 40px auto -40px;
            text-align: center
        }

        .intro-section .stake-content img {
            width: 700px;
            height: 330px;
            object-fit: contain;
            margin: 0 auto;
        }

        .intro-section .stake-content ul {
            padding: 0;
            list-style: none;
            width: 100%;
            margin: 0;
            position: absolute;
            top: 0
        }

        .intro-section .stake-content ul li {
            position: absolute;
            text-align: center;
            color: #ef4136;
            font-weight: 700;
            font-style: italic;
            display: inline-block
            width: 100px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .intro-section .stake-content ul li:first-child {
            top: 40px;
            left: -8px;
        }

        .intro-section .stake-content ul li:nth-child(2) {
            top: 16px;
            left: 113px;
        }

        .intro-section .stake-content ul li:nth-child(3) {
            top: -7px;
            left: 220px;
        }

        .intro-section .stake-content ul li:nth-child(4) {
            top: -27px;
            left: 333px;
        }

        .intro-section .stake-content ul li:nth-child(5) {
            top: -7px;
            left: 420px;
        }

        .intro-section .stake-content ul li:nth-child(6) {
            top: 16px;
            left: 521px;
        }

        .intro-section .stake-content ul li:last-child {
            top: 40px;
            left: 636px;
        }

        .transcendent-section .transcendent-content {
            position: relative
        }

        .transcendent-section .transcendent-content .bg-img {
            height: 330px;
            margin-bottom: 30px
        }

        .transcendent-section .transcendent-content .bg-img img {
            width: 100%;
            height: 100%;
            object-fit: contain
        }

        .transcendent-section .transcendent-content .hidden {
            display: none
        }

        .transcendent-section .transcendent-content .labels {
            position: absolute;
            top: 100px;
            left: 0;
            padding: 0;
            list-style: none;
            margin: 0;
            width: 100%
        }

        .transcendent-section .transcendent-content .labels li {
            position: absolute;
            color: #00406c;
            font-weight: 700;
            font-style: italic;
            font-size: 27px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .transcendent-section .transcendent-content .labels li:first-child {
            left: 60%
        }

        .transcendent-section .transcendent-content .labels li:nth-child(2) {
            left: 42%;
            top: 38px
        }

        .transcendent-section .transcendent-content .labels li:last-child {
            left: 25%;
            top: 95px
        }

        .sum-of-jeanious-section .bg-wrapper {
            width: 500px;
            height: 500px;
            margin: 20px auto 0;
        }

        .sum-of-jeanious-section .bg-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain
        }

        .ribbon-bar h2 {
            background-color: #00406b;
            color: white;
            font-family: "Bebas Neu Pro EX EB Italic";
            padding: 0 20px 6px 60px;
            max-width: 50%;
            border-top-right-radius: 25px;
            font-weight: 800;
            font-style: italic;
            font-size: 26px;
        }

        .report-section {
            margin-bottom: 25px;
            font-family: "Bebas Neu Pro EX MD";
            font-size: 14px;
            color: #000000;
            font-weight: 500;
        }

        .report-section p {
            padding: 0 60px;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .report-section i,
        .report-section em,
        .report-section p:not(.section-title) strong {
            font-size: 16px;
            font-family: "Macklin Sans EX Bold Italic";
            color: #231F20;
            font-weight: 800;
            font-style: italic;
        }

        .report-section p.center-align {
            text-align: center;
        }

        .report-section h2.title,
        .report-section h3,
        .report-section h4,
        .report-section h5,
        .report-section h6 {
            padding: 0 60px;
            color: #231F20;
            margin: 0;
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
        }

        .report-section h2.title {
            font-size: 21px;
        }

        .report-section h5 {
            font-size: 16px;
            line-height: 1.14;
            letter-spacing: -0.03px;
        }

        .labels-data ul {
            padding: 0 60px;
            margin: 0;
            list-style: none;
        }

        .blue-box {
            padding: 30px;
            background-color: #bbd6eb;
            border-radius: 20px;
            margin: 0 20px;
            border: 2px solid #1c75bb;
            margin-top: 30px;
        }

        .blue-box .title {
            font-size: 16px;
            color: #231F20;
            font-family: "Macklin Sans EX Bold Italic";
            text-transform: capitalize;
            font-weight: 800;
            font-style: italic;
        }

        .college-info-wrapper h2.title {
            font-size: 24px;
            color: #000000;
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic {
            margin-top: 30px;
        }

        .college-info-wrapper .essay-topic .color-blue {
            margin-bottom: 0;
            font-family: "Macklin Sans Bold Italic";
            Text Transform: Capitalize;
            color: #1c76bd;
            font-size: 15px;
            line-height: 1.2;
            font-weight: 700;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic .section-title {
            color: #f04136;
            font-family: "Macklin Sans Bold Italic";
            font-size: 15px;
            padding-left: 80px;
            margin-bottom: 10px;
            margin-top: 10px;
            font-weight: 700;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic .rationale-text {
            padding-left: 80px;
        }

        .college-info-wrapper .essay-topic .writing-outline {
            margin: 15px 0 20px 0;
            padding: 0 0 0 150px;
            list-style: none;
        }

        .college-info-wrapper .essay-topic .writing-outline li {
            position: relative;
            margin-bottom: 25px;
        }

        .college-info-wrapper .essay-topic .writing-outline li .bullet {
            position: absolute;
            left: -35px;
            top: 0;
            height: 25px;
            width: 25px;
        }

        .college-info-wrapper .essay-topic .writing-outline li:last-child {
            margin-bottom: 0;
        }

        .college-info-wrapper .essay-topic .tailoring-tips {
            margin: 15px 0 20px 0;
            padding: 0 0 0 150px;
            list-style: none;
        }

        #result-footer {
            background-color: #a4c2e1;
            padding: 30px 0;
            text-align: center;
        }

        .help-box {
            background-color: #00406b;
            color: #fff;
            width: 480px;
            margin: 0 auto;
            border-radius: 25px;
            padding: 45px;
            position: relative;
        }

        .speech-bubble {
            border: 5px solid #9dc1ec;
            border-radius: 25px;
            padding: 15px 20px 25px;
            position: relative;
            margin-bottom: 50px;
        }

        .speech-bubble h2 {
            color: #ff5c1b;
            margin: 0 0 15px;
            font-size: 80px;
            padding: 0;
            border: none;
            line-height: 1;
        }

        .speech-bubble p {
            font-size: 22px;
            line-height: 1.1;
            margin: 0;
            color: #ffffff;
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
        }

        .call-section p {
            margin: 0 0 5px;
            line-height: 1;
        }

        .call-section p.call {
            color: #9ec2ee;
            font-family: "Macklin Sans Bold Italic";
            font-size: 30px;
            font-weight: 700;
            font-style: italic;
        }

        .call-section .phone-number {
            font-size: 46px;
            font-family: "Bebas Neu Pro EX EB";
            color: #ffffff;
            line-height: 1;
            font-weight: 800;
        }

        .call-section .advising-text {
            font-size: 22px;
            line-height: 1.1;
            color: #ffffff;
            margin: 30px 0;
        }

        .call-section .advising-text strong {
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
        }

        .result-footer-logo {
            max-width: 60%;
            margin: 0 auto;
        }

        .color-blue i,
        .color-blue em,
        .color-blue strong {
            color: #1c76c3;
            font-size: 15px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .color-red i,
        .color-red em,
        .color-red strong {
            color: #f04136;
            font-size: 15px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        span.bold {
            font-family: "Macklin Sans EX Bold Italic";
            font-size: 16px;
            color: #000000;
            font-weight: 800;
            font-style: italic;
        }

        .cta-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
    ';

    $pdf_header_block = '<div class="pdf-fixed-header">' . $pdf_header_html . '</div>';

    $pdf_footer_block = '<div class="pdf-footer"></div>';

    $html = '
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        ' . $extra_css . '
    </head>
    <body>
        ' . $pdf_header_block . $pdf_footer_block . $raw_html . '
    </body>
    </html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('fontDir', __DIR__ . '/fonts');
    $options->set('isPhpEnabled', true);
    $options->setChroot(get_home_path());

    $dompdf = new Dompdf($options);

    // Important: This makes relative paths in CSS work
    $dompdf->setBasePath(home_url());

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Page numbers
    $canvas = $dompdf->getCanvas();
    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $text = "Page $pageNumber of $pageCount";
        $font = $fontMetrics->get_font("Arial", "normal");
        $size = 10;
        $width = $fontMetrics->get_text_width($text, $font, $size);
        $x = ($canvas->get_width() - $width) / 2; // center horizontally
        $y = $canvas->get_height() - 17; // distance from bottom
        $canvas->text($x, $y, $text, $font, $size);
    });

    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/jeanius-report.pdf';
    file_put_contents($pdf_path, $dompdf->output());

    $current_user = wp_get_current_user();
    
    $to = $current_user->user_email;
    $subject = "Your Jeanius Report PDF";
    $body = "Please find the attached report.";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    add_filter('wp_mail_from_name', function () {
        return 'Jeanius';
    });

    $sent = wp_mail($to, $subject, $body, $headers, [$pdf_path]);

    unlink($pdf_path);

    if ($sent) {
        wp_send_json_success("PDF emailed successfully!");
    } else {
        wp_send_json_error("Failed to send PDF email.");
    }
}

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

/**
 * Trigger assessment regeneration when the admin clicks the regenerate button.
 *
 * Hooks into ACF's save action for `jeanius_assessment` posts. When the
 * specific button field (`field_68af564391c91`) is submitted, all ACF fields
 * except a small allow‑list are cleared and the assessment regeneration helper
 * is invoked.
 */
function jeanius_maybe_regenerate_assessment( $post_id ) {
    // Run only for our custom post type.
    if ( get_post_type( $post_id ) !== 'jeanius_assessment' ) {
        return;
    }

    // Ensure the regenerate button was pressed.
    if ( empty( $_POST['acf']['field_68af564391c91'] ) ) {
        return;
    }

    $keep = [
        'dob',
        'consent_granted',
        'share_with_parent',
        'parent_email',
        'stage_data',
        'full_stage_data',
        'target_colleges',
    ];

    // Remove all other ACF fields for this post.
    $fields = get_field_objects( $post_id );
    if ( $fields ) {
        foreach ( $fields as $field ) {
            if ( ! in_array( $field['name'], $keep, true ) ) {
                delete_field( $field['key'], $post_id );
            }
        }
    }

    // Call helper to regenerate the assessment contents.
    if ( function_exists( '\\Jeanius\\regenerate_assessment' ) ) {
        \Jeanius\regenerate_assessment( $post_id );
    }

    // Optional admin notice confirming regeneration.
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>Assessment regeneration triggered.</p></div>';
    } );
}
add_action( 'acf/save_post', 'jeanius_maybe_regenerate_assessment', 20 );
