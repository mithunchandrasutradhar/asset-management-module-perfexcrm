<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Asset labels (QR code or Code 128 barcode) as a PDF, using the TCPDF
 * library bundled with Perfex. Layout, size and printed fields come from
 * Setup → Settings → Asset Management → Labels:
 * - single: one label per page, page = label size (thermal label printers);
 * - sheet:  A4 grid of labels with light cut guides (office printers).
 */
class Ams_labels
{
    private const SHEET_MARGIN = 8;

    private const SHEET_GAP = 2;

    private const PAD = 1.5;

    /** @param array $assets rows with asset_tag, name, serial_no */
    public function pdf(array $assets)
    {
        $w      = max(20, min(150, (float) get_option('ams_label_width')));
        $h      = max(10, min(150, (float) get_option('ams_label_height')));
        $single = get_option('ams_label_layout') !== 'sheet';

        $pdf = $single
            ? new TCPDF($w > $h ? 'L' : 'P', 'mm', [$w, $h], true, 'UTF-8', false)
            : new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(get_option('companyname'));
        $pdf->SetTitle(_l('ams_labels'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);

        $cols    = max(1, (int) floor((210 - 2 * self::SHEET_MARGIN + self::SHEET_GAP) / ($w + self::SHEET_GAP)));
        $rows    = max(1, (int) floor((297 - 2 * self::SHEET_MARGIN + self::SHEET_GAP) / ($h + self::SHEET_GAP)));
        $perPage = $cols * $rows;

        foreach (array_values($assets) as $i => $asset) {
            if ($single) {
                $pdf->AddPage();
                $this->label($pdf, (object) $asset, 0, 0, $w, $h, false);
                continue;
            }
            if ($i % $perPage === 0) {
                $pdf->AddPage();
            }
            $slot = $i % $perPage;
            $x    = self::SHEET_MARGIN + ($slot % $cols) * ($w + self::SHEET_GAP);
            $y    = self::SHEET_MARGIN + intdiv($slot, $cols) * ($h + self::SHEET_GAP);
            $this->label($pdf, (object) $asset, $x, $y, $w, $h, true);
        }

        return $pdf;
    }

    private function label($pdf, $a, $x, $y, $w, $h, $guides)
    {
        $pad = self::PAD;
        if ($guides) {
            $pdf->SetDrawColor(210, 210, 210);
            $pdf->SetLineWidth(0.1);
            $pdf->Rect($x, $y, $w, $h);
        }

        $style = ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false];

        if (get_option('ams_label_code') === 'barcode') {
            $barH  = max(5, $h * 0.42);
            $pdf->write1DBarcode($a->asset_tag, 'C128', $x + $pad, $y + $h - $pad - $barH, $w - 2 * $pad, $barH, 0.4, $style + ['stretch' => true, 'fitwidth' => true], 'N');
            $textX = $x + $pad;
            $textY = $y + $pad;
            $textW = $w - 2 * $pad;
            $textH = $h - $barH - 3 * $pad;
        } else {
            $size    = min($h, $w) - 2 * $pad;
            $content = get_option('ams_label_qr_content') === 'tag' ? $a->asset_tag : ams_scan_url($a->asset_tag);
            $pdf->write2DBarcode($content, 'QRCODE,M', $x + $pad, $y + $pad, $size, $size, $style, 'N');
            $textX = $x + 2 * $pad + $size;
            $textY = $y + $pad;
            $textW = $w - ($textX - $x) - $pad;
            $textH = $h - 2 * $pad;
        }
        if ($textW < 5 || $textH < 2) {
            return;
        }

        $company = trim((string) get_option('ams_label_company_text')) ?: (string) get_option('companyname');
        $lines   = [];
        if (get_option('ams_label_show_company') == '1' && $company !== '') {
            $lines[] = ['text' => $company, 'bold' => false, 'scale' => 0.8];
        }
        $lines[] = ['text' => $a->asset_tag, 'bold' => true, 'scale' => 1.15];
        if (get_option('ams_label_show_name') == '1' && ! empty($a->name)) {
            $lines[] = ['text' => $a->name, 'bold' => false, 'scale' => 0.9];
        }
        if (get_option('ams_label_show_serial') == '1' && ! empty($a->serial_no)) {
            $lines[] = ['text' => 'S/N ' . $a->serial_no, 'bold' => false, 'scale' => 0.8];
        }

        $logo = FCPATH . 'uploads/company/' . get_option('company_logo');
        $logoH = 0;
        if (get_option('ams_label_show_logo') == '1' && get_option('company_logo') && is_file($logo) && preg_match('/\.(png|jpe?g)$/i', $logo)) {
            $logoH = min($textH * 0.3, 6);
            $pdf->Image($logo, $textX, $textY, 0, $logoH, '', '', '', true, 300, '', false, false, 0, 'LT');
            $textY += $logoH + 0.5;
            $textH -= $logoH + 0.5;
        }

        // Font size (pt) so every line fits the text box height (1 pt = 0.3528 mm, line height 1.2).
        $units  = array_sum(array_column($lines, 'scale')) * 1.2 * 0.3528;
        $base   = max(4, min(11, $textH / $units));

        $pdf->SetTextColor(0, 0, 0);
        foreach ($lines as $line) {
            $size = $base * $line['scale'];
            $lh   = $size * 1.2 * 0.3528;
            $pdf->SetFont('dejavusans', $line['bold'] ? 'B' : '', $size);
            $pdf->SetXY($textX, $textY);
            // Stretch 1 = shrink horizontally if the text is wider than the label.
            $pdf->Cell($textW, $lh, $line['text'], 0, 0, 'L', false, '', 1);
            $textY += $lh;
        }
    }
}
