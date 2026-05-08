<?php

require_once("connection.php");

class SlipVerifier
{
    private $tesseractPath;
    private $tessdataPath;

    public function __construct()
    {
        $this->tesseractPath = './assets/plugins/tesseract/tesseract.exe';
        $this->tessdataPath = './assets/plugins/tesseract/tessdata';
        putenv('TESSDATA_PREFIX=' . $this->tessdataPath);
    }

    public function verifySlip(string $referenceNumber, string $accountNumber, string $fileUrl): array
    {
        try {
            // Verify the file exists at the URL
            $headers = get_headers($fileUrl, 1);
            if (strpos($headers[0], '200') === false) {
                throw new Exception("File not found at URL: $fileUrl");
            }

            // Get text content from file
            $fileExt = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
            $text = $fileExt === 'pdf' ? $this->getPdfText($fileUrl) : $this->getImageText($fileUrl);

            // Normalize text for searching
            $normalizedText = preg_replace('/\s+/', ' ', $text);
            $normalizedText = strtoupper($normalizedText);
            $referenceNumber = strtoupper(trim($referenceNumber));
            $accountNumber = strtoupper(trim($accountNumber));

            // Check if reference exists anywhere in the text
            $referenceFound = strpos($normalizedText, $referenceNumber) !== false;

            // Check if account number exists anywhere in the text
            $accountFound = strpos($normalizedText, $accountNumber) !== false;

            // Extract transaction details
            $details = $this->extractTransactionDetails($normalizedText);

            return [
                'verified' => $referenceFound && $accountFound,
                'amount' => $details['amount'] ?? null,
                'date' => $details['date'] ?? null,
                'account' => $accountFound,
                'reference' => $referenceFound,
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'amount' => null,
                'date' => null,
                'account' => false,
                'reference' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getPdfText(string $pdfUrl): string
    {
        require_once('./vendor/autoload.php');
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdfUrl);
        return $pdf->getText();
    }

    private function getImageText(string $imageUrl): string
    {
        if (!file_exists($this->tesseractPath)) {
            throw new Exception('OCR processing not available - Tesseract not found');
        }

        // 1. Download the image first
        $tempImagePath = tempnam(sys_get_temp_dir(), 'ocr_img_');
        file_put_contents($tempImagePath, file_get_contents($imageUrl));

        // 2. Verify the image was downloaded
        if (!file_exists($tempImagePath) || filesize($tempImagePath) === 0) {
            throw new Exception('Failed to download image from URL');
        }

        // 3. Prepare output file
        $tempOutputFile = tempnam(sys_get_temp_dir(), 'ocr_out_');
        $cmd = sprintf(
            '"%s" "%s" "%s" --psm 6 -l eng --tessdata-dir "%s" 2>&1',
            $this->tesseractPath,
            escapeshellarg($tempImagePath),
            escapeshellarg($tempOutputFile),
            escapeshellarg($this->tessdataPath)
        );

        // 4. Execute OCR
        exec($cmd, $output, $returnVar);

        // 5. Clean up image file
        @unlink($tempImagePath);

        if ($returnVar !== 0) {
            @unlink($tempOutputFile . '.txt');
            throw new Exception('OCR processing failed: ' . implode("\n", $output));
        }

        // 6. Get results
        $text = file_get_contents($tempOutputFile . '.txt');
        @unlink($tempOutputFile . '.txt');

        if (empty($text)) {
            throw new Exception('OCR returned empty text - check image quality and format');
        }

        return $text;
    }
    private function extractTransactionDetails(string $text): array
    {
        // Amount pattern (matches various amount formats)
        preg_match('/
            (?:AMOUNT|TOTAL|PAYMENT|LKR|RS?\.?)\s*[:=]?\s*
            (?:LKR\s*)?
            ([\d,]+\.\d{2})
        /ix', $text, $amountMatch);

        // First try to match combined date-time format (YYYY-MM-DD HH:MM AM/PM)
        preg_match('/
        (?:DATE|TIME|DATE\s*TIME|DATE\s*&\s*TIME)\s*[:=]?\s*
        (\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}\s*[AP]M)
    /ix', $text, $combinedMatch);

        if (!empty($combinedMatch[1])) {
            // Split the combined format into date and time components
            $parts = explode(' ', $combinedMatch[1]);
            return [
                'date' => $parts[0],  // YYYY-MM-DD
                'time' => $parts[1] . ' ' . $parts[2]  // HH:MM AM/PM
            ];
        }

        // Fall back to separate date and time patterns if combined not found
        $results = [];

        // Date pattern (matches various date formats)
        preg_match('/
        (?:DATE|ON|PAYMENT\s*DATE)\s*[:=]?\s*
        (\d{2,4}[\.\/-]\d{2}[\.\/-]\d{2,4})
    /ix', $text, $dateMatch);
        $results['date'] = $dateMatch[1] ?? null;

        // Time pattern (matches various time formats)
        preg_match('/
        (?:TIME|AT|PAYMENT\s*TIME)\s*[:=]?\s*
        (\d{1,2}:\d{2}\s*[AP]M?)
    /ix', $text, $timeMatch);
        $results['time'] = $timeMatch[1] ?? null;

        // Account number pattern
        preg_match('/
            (?:ACCOUNT\s*NO?|ACC\s*NO?|ACCOUNT)\s*[:=]?\s*
            (\d{6,})
        /ix', $text, $accountMatch);

        return [
            'amount' => $amountMatch[1] ?? null,
            'date' => $dateMatch[1] ?? null,
            'time' => $timeMatch[1] ?? null,
            'account' => $accountMatch[1] ?? null
        ];
    }

    public function checkFileType(string $filename): string
    {
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Define allowed file types
        $imageExtensions = ['jpg', 'jpeg', 'png'];
        $pdfExtensions = ['pdf'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $pdfExtensions)) {
            return 'pdf';
        } else {
            return 'unsupported';
        }
    }
}
