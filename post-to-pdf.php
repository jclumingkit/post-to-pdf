<?php
/*
Plugin Name: Post to PDF
Description: Allows users to download a blog post as a PDF.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once(plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php');

// add font
$playfairDisplayBoldFont = plugin_dir_path(__FILE__) . 'fonts/PlayfairDisplay-Bold.ttf';
$montserratMediumFont = plugin_dir_path(__FILE__) . 'fonts/Montserrat-Medium.ttf';

TCPDF_FONTS::addTTFfont($playfairDisplayBoldFont, 'TrueTypeUnicode', '', 96);
TCPDF_FONTS::addTTFfont($montserratMediumFont, 'TrueTypeUnicode', '', 96);

function add_pdf_download_button($content) {
    if (is_single()) {
        $pdf_button = '<a href="' . esc_url(add_query_arg('download_pdf', 'true')) . '" class="pdf-download-button">Download as PDF</a>';
        return $content . $pdf_button;
    }
    return $content;
}
add_filter('the_content', 'add_pdf_download_button');

function generate_custom_toc() {
    // Get the raw post content
    $post = get_post();
    $content = apply_filters('the_content', $post->post_content);

    // Initialize DOMDocument and load the post content
    $dom = new DOMDocument();
    // Suppress errors due to malformed HTML
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Find all H2 tags
    $toc = '';
    foreach ($dom->getElementsByTagName('h2') as $index => $h2) {
        // Add the TOC item with numbering and line breaks
        $toc .= ($index + 1) . '. ' . trim($h2->textContent) . "\n";
    }

    // Update the post content with anchor IDs added
    $content = $dom->saveHTML();

    // Return both the TOC and the modified content with anchors
    return $toc;
}

function generate_pdf() {
    if (isset($_GET['download_pdf']) && is_single()) {
        $post = get_post();

        // Load TCPDF
        require_once(plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php');

        // Initialize TCPDF
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(get_the_author_meta('display_name', $post->post_author));
        $pdf->SetTitle($post->post_title);

        // Set margins
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);

        // Add cover page with background color
        $pdf->AddPage();

        // Add background image on the cover page
        // get the current page break margin
        $bMargin = $pdf->getBreakMargin();
        // get current auto-page-break mode
        $auto_page_break = $pdf->getAutoPageBreak();
        // disable auto-page-break
        $pdf->SetAutoPageBreak(false, 0);
        // set bacground image
        $coverPageBackgroundImage = plugin_dir_url(__FILE__) . 'images/cover-page-background.png';
        $pdf->Image($coverPageBackgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, true);
        // restore auto-page-break status
        $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
        // set the starting point for the page content
        $pdf->setPageMark();

        // Subtitle
        $pdf->SetFont('Montserrat Medium', '', 14);
        $pdf->SetTextColor(178, 107, 255);
        $pdf->SetXY(20, 70);
        $pdf->MultiCell(170, 20, "Kellé’s Thorpe Financial Guide", 0, 'C', 0, 1, '', '', true);

        // Post Title
        $pdf->SetFont('PlayfairDisplay', 'B', 30);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 80);
        $pdf->MultiCell(170, 20, $post->post_title, 0, 'C', 0, 1, '', '', true);

        // Table of Contents Page
        $pdf->AddPage();
        // get the current page break margin
        $bMargin = $pdf->getBreakMargin();
        // get current auto-page-break mode
        $auto_page_break = $pdf->getAutoPageBreak();
        // disable auto-page-break
        $pdf->SetAutoPageBreak(false, 0);
        // set bacground image
        $tocPageBackgroundImage = plugin_dir_url(__FILE__) . 'images/toc-background.png';
        $pdf->Image($tocPageBackgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, true);
        // restore auto-page-break status
        $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
        // set the starting point for the page content
        $pdf->setPageMark();

        // Title
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('PlayfairDisplay', 'B', 18);
        $pdf->Cell(0, 10, 'Table of Contents', 0, 1, 'L');
        $pdf->Ln(10);

        // Table of Contents
        $toc = generate_custom_toc();

        // Check if the TOC is found
        if (isset($toc)) {
            $pdf->SetFont('PlayfairDisplay', 'B', 14);
            $pdf->MultiCell(0, 10, $toc, 0, 'L', 0, 1);
        } else {
            // If TOC is not found
            $pdf->SetFont('PlayfairDisplay', 'B', 14);
            $pdf->MultiCell(0, 10, 'No Table of Contents found.', 0, 'L', 0, 1);
        }

        // Content Page
        $pdf->AddPage();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('PlayfairDisplay', 'B', 20);
        $pdf->MultiCell(0, 10, $post->post_title, 0, 'C', 0, 1);
        $pdf->Ln(10);

        // Blog Post Content
        $pdf->SetFont('helvetica', '', 12);
        $pdf->writeHTML($post->post_content, true, false, true, false, '');

        // Output PDF
        $pdf->Output(sanitize_title($post->post_title) . '.pdf', 'D');

        exit;
    }
}
add_action('template_redirect', 'generate_pdf');

function pdf_download_enqueue_styles() {
    wp_enqueue_style('pdf-download-style', plugins_url('/css/style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'pdf_download_enqueue_styles');

