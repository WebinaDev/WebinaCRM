<?php
// File: includes/class-pdf-reporter.php

require_once WEBINOCRM_PLUGIN_DIR . 'includes/lib/fpdf/fpdf.php';

class WebinoCRM_PDF_Reporter extends FPDF {

    function __construct($orientation='P', $unit='mm', $size='A4') {
        parent::__construct($orientation, $unit, $size);
        // Add a Unicode font (like Vazirmatn)
        // Make sure you have the vazirmatn.php, vazirmatn.z, and vazirmatn.ctg.z files in the font directory
        $this->AddFont('Vazirmatn', '', 'Vazirmatn-Regular.php', true);
    }
    
    // Page header
    function Header() {
        // Logo
        $logo_path = WEBINOCRM_PLUGIN_DIR . 'assets/images/logo.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 10, 8, 33);
        }
        
        // Set font to our Persian font
        $this->SetFont('Vazirmatn', '', 15);
        
        // Move to the right
        $this->Cell(80);
        
        // Title
        $header_text = 'گزارش کار روزانه'; // No need for iconv
        $this->Cell(30, 10, $header_text, 1, 0, 'C');
        
        // Line break
        $this->Ln(20);
    }

    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        
        // Page number
        $page_num_text = 'Page ' . $this->PageNo() . '/{nb}';
        $this->Cell(0, 10, $page_num_text, 0, 0, 'C');
    }

    // A method to create the task table
    function TaskTable($header, $data) {
        // Column widths
        $w = array(40, 85, 30, 35); // Adjusted width for project
        
        // Set font for header
        $this->SetFont('Vazirmatn', '', 12);
        
        // Header
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(.3);

        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Set font for data
        $this->SetFont('Vazirmatn', '', 10);
        $this->SetFillColor(255);
        
        // Data
        foreach($data as $row) {
            // Set text direction to RTL for all cells in the row
            $this->setRTL(true);
            
            $this->Cell($w[0], 6, $row[0], 'LR');
            $this->Cell($w[1], 6, $row[1], 'LR');
            $this->Cell($w[2], 6, $row[2], 'LR', 0, 'L'); // Keep alignment L for English-like text
            $this->Cell($w[3], 6, $row[3], 'LR', 0, 'L');
            $this->Ln();
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}