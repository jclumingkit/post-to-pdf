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

// add font, run once
// $playfairDisplaySemiBoldFont = plugin_dir_path(__FILE__) . 'fonts/PlayfairDisplay-SemiBold.ttf';
// TCPDF_FONTS::addTTFfont($playfairDisplaySemiBoldFont, 'TrueTypeUnicode', '', 96);

function add_pdf_download_button($content) {
    if (is_single()) {
        $buttonStyles = "display: flex; 
        justify-content: center; 
        align-items: center; 
        font-size: 20px; 
        width: 300px; 
        height: 43px; 
        color: #ffffff; 
        background-color: #7421C4; 
        border-radius: 45px;";

        $downloadUrl = esc_url(add_query_arg('download_pdf', 'true'));

        $pdf_button = '<a style="' . $buttonStyles . '" href="' . $downloadUrl . '" class="pdf-download-button">Download</a>';
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
        $toc .= ($index + 1) . '. ' . trim($h2->textContent) . "\n\n";
    }

    // Update the post content with anchor IDs added
    $content = $dom->saveHTML();

    // Return both the TOC and the modified content with anchors
    return $toc;
}

function generate_pdf() {
    if (isset($_GET['download_pdf']) && is_single()) {
        $post = get_post();
        require_once(plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php');

        class CustomPDF extends TCPDF {
            public function Footer() {
                $this->SetY(-15);
                $this->SetLineWidth(0.5);
                $this->SetDrawColor(235, 223, 243);
                $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());

                $this->SetY(-12);
                $image_file = plugin_dir_path(__FILE__) . 'images/footer-logo.png';
                $this->Image($image_file, 10, $this->GetY(), 30);
                
                $this->SetFont('Montserrat Medium', '', 12);
                $pageNumber = sprintf('%02d', $this->PageNo() - 2);
                $this->SetX($this->getPageWidth() - 10);
                $this->Cell(1, 6, $pageNumber, 0, 0, 'R');
            }
        }

        // Initialize PDF
        $pdf = new CustomPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(get_the_author_meta('display_name', $post->post_author));
        $pdf->SetTitle($post->post_title);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setPrintHeader(false);

        // Cover Page
        $pdf->AddPage();
        $pdf->setPrintFooter(false);
        $bMargin = $pdf->getBreakMargin();
        $auto_page_break = $pdf->getAutoPageBreak();
        $pdf->SetAutoPageBreak(false, 0);
        $coverPageBackgroundImage = plugin_dir_url(__FILE__) . 'images/cover-page-background.png';
        $pdf->Image($coverPageBackgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, true);
        $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
        $pdf->setPageMark();

        $pdf->SetFont('Montserrat Medium', '', 14);
        $pdf->SetTextColor(178, 107, 255);
        $pdf->SetXY(20, 70);
        $pdf->MultiCell(170, 20, "Kellé’s Thorpe Financial Guide", 0, 'C', 0, 1, '', '', true);

        $pdf->SetFont('PlayfairDisplay', 'B', 30);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 80);
        $pdf->MultiCell(170, 20, $post->post_title, 0, 'C', 0, 1, '', '', true);

        // Table of Content Page
        $pdf->AddPage();
        $pdf->setPrintFooter(false);
        $bMargin = $pdf->getBreakMargin();
        $auto_page_break = $pdf->getAutoPageBreak();
        $pdf->SetAutoPageBreak(false, 0);
        $tocPageBackgroundImage = plugin_dir_url(__FILE__) . 'images/toc-background.png';
        $pdf->Image($tocPageBackgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, true);
        $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
        $pdf->setPageMark();

        $pdf->SetFont('PlayfairDisplay', 'B', 35);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 50);
        $pdf->Cell(0, 10, 'Table of content', 0, 1, 'L');
        $pdf->Ln(10);

        $toc = generate_custom_toc();
        $pdf->SetFont('Montserrat Medium', '', 14);
        $pdf->SetXY(20, 80);
        $pdf->MultiCell(0, 10, isset($toc) ? $toc : 'No Table of Contents found.', 0, 'L', 0, 1);

        // Post Content Page
        $pdf->AddPage();
        $pdf->setPrintFooter(true);
        $content = explode("Table of Contents", $post->post_content)[1];
        $styles = '
            <style>
                body, p, span, li, div {
                    font-family: "Montserrat";
                    font-size: 12px;
                }

                h2, h3, h4, h5, h6 {
                    font-family: "PlayfairDisplay SemiBold";
                }

                h2 {
                    font-size: 20px;
                }

                h3 {
                    font-size: 16px;
                }

                h4 {
                    font-size: 14px;
                }
            </style>
        ';
        $content = $styles . $content;
        $pdf->SetTextColor(0, 0, 0);
        $pdf->writeHTML($content, true, false, true, false, '');

        $pdf->Output(sanitize_title($post->post_title) . '.pdf', 'D');
        
        exit;
    }
}
add_action('template_redirect', 'generate_pdf');

