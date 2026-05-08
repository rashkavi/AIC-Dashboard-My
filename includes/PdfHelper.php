<?php

class PdfHelper
{
    /**
     * Write text into letter boxes (one character per box).
     *
     * @param Fpdi $pdf       The FPDI/FPDF instance
     * @param string $text    The text to write
     * @param float $startX   Starting X coordinate
     * @param float $startY   Starting Y coordinate
     * @param float $xStep    Horizontal spacing between boxes
     * @param float $yStep    Vertical spacing between rows
     * @param int $maxPerRow  Number of boxes per row
     */
    public static function writeInBoxes($pdf, $text, $startX, $startY, $xStep, $yStep, $maxPerRow, $forceUpper = false)
    {
        $chars = str_split($forceUpper ? strtoupper($text) : $text);
        $x = $startX;
        $y = $startY;
        $count = 0;

        foreach ($chars as $ch) {
            $pdf->SetXY($x, $y);
            $pdf->Write(0, $ch);

            // Move to next box
            $x += $xStep;
            $count++;

            // If reached max per row → new line
            if ($count % $maxPerRow === 0) {
                $x = $startX;
                $y += $yStep;
            }
        }
    }

    public static function writeInBoxesWithBorder(
        $pdf,
        $text,
        $startX,
        $startY,
        $boxW,
        $boxH,
        $maxPerRow,
        $rowCount,
        $forceUpper = false
    ) {
        $chars = str_split($forceUpper ? strtoupper($text) : $text);
        $charCount = count($chars);

        $x = $startX;
        $y = $startY;

        $pdf->SetDrawColor(163, 163, 163);

        $pdf->SetFillColor(255, 255, 255);

        $currentChar = 0;

        for ($row = 0; $row < $rowCount; $row++) {
            $x = $startX;

            for ($col = 0; $col < $maxPerRow; $col++) {
                $pdf->Rect($x, $y, $boxW, $boxH, 'DF');

                if ($currentChar < $charCount) {
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($boxW, $boxH, $chars[$currentChar], 0, 0, 'C');
                    $currentChar++;
                }

                $x += $boxW;
            }

            $y += $boxH;
        }
    }

    public static function writeInBoxesWithSpacing(
        $pdf,
        $text,
        $startX,
        $startY,
        $boxW,
        $boxH,
        $maxPerRow,
        $rowCount,
        $colGap = 2,   // gap between boxes (X-axis)
        $rowGap = 2,   // gap between rows (Y-axis)
        $forceUpper = false
    ) {
        $chars = str_split($forceUpper ? strtoupper($text) : $text);
        $charCount = count($chars);

        $x = $startX;
        $y = $startY;

        $pdf->SetDrawColor(36, 36, 36);
        $pdf->SetFillColor(255, 255, 255);

        $currentChar = 0;

        for ($row = 0; $row < $rowCount; $row++) {
            $x = $startX;

            for ($col = 0; $col < $maxPerRow; $col++) {
                // Draw box
                $pdf->Rect($x, $y, $boxW, $boxH, 'DF');

                // Place character
                if ($currentChar < $charCount) {
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($boxW, $boxH, $chars[$currentChar], 0, 0, 'C');
                    $currentChar++;
                }

                // Move to next column (with gap)
                $x += $boxW + $colGap;
            }

            // Move to next row (with gap)
            $y += $boxH + $rowGap;
        }
    }

    public static function RoundedImage($pdf, $file, $x, $y, $w, $h, $r = 4)
    {
        // FPDF/FPDI default mm scale factor
        $k = 72 / 25.4;
    
        // Page height in user unit (mm)
        $hp = method_exists($pdf, 'GetPageHeight') ? $pdf->GetPageHeight() : 297;
    
        // Save graphics state
        $pdf->_out('q');
    
        // Convert to PDF points
        $xPt = $x * $k;
        $yPt = ($hp - $y) * $k;
        $wPt = $w * $k;
        $hPt = $h * $k;
        $rPt = $r * $k;
    
        $arc = 4 / 3 * (sqrt(2) - 1);
    
        $pdf->_out(sprintf('%.2F %.2F m', $xPt + $rPt, $yPt));
        $pdf->_out(sprintf('%.2F %.2F l', $xPt + $wPt - $rPt, $yPt));
        $pdf->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $xPt + $wPt - $rPt * (1 - $arc),
            $yPt,
            $xPt + $wPt,
            $yPt - $rPt * (1 - $arc),
            $xPt + $wPt,
            $yPt - $rPt
        ));
        $pdf->_out(sprintf('%.2F %.2F l', $xPt + $wPt, $yPt - $hPt + $rPt));
        $pdf->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $xPt + $wPt,
            $yPt - $hPt + $rPt * (1 - $arc),
            $xPt + $wPt - $rPt * (1 - $arc),
            $yPt - $hPt,
            $xPt + $wPt - $rPt,
            $yPt - $hPt
        ));
        $pdf->_out(sprintf('%.2F %.2F l', $xPt + $rPt, $yPt - $hPt));
        $pdf->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $xPt + $rPt * (1 - $arc),
            $yPt - $hPt,
            $xPt,
            $yPt - $hPt + $rPt * (1 - $arc),
            $xPt,
            $yPt - $hPt + $rPt
        ));
        $pdf->_out(sprintf('%.2F %.2F l', $xPt, $yPt - $rPt));
        $pdf->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $xPt,
            $yPt - $rPt * (1 - $arc),
            $xPt + $rPt * (1 - $arc),
            $yPt,
            $xPt + $rPt,
            $yPt
        ));
        $pdf->_out('W n');
    
        // Draw image in normal user units
        $pdf->Image($file, $x, $y, $w, $h);
    
        // Restore graphics state
        $pdf->_out('Q');
    }
}
