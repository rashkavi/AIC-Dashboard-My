<?php

use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

require_once("connection.php");
require_once("./vendor/autoload.php");

class SlipVerifier
{
    private string $googleKeyPath;

    public function __construct()
    {
        $this->googleKeyPath = Project::getGoogleVisionKeyPath();


        if (!file_exists($this->googleKeyPath)) {
            throw new Exception("Google Vision API key file not found.");
        }
    }

    public function verifySlip(string $referenceNumber, string $accountNumber, string $amount, string $fileUrl): array
    {
        try {
            $fileType = $this->checkFileType($fileUrl);

            if ($fileType === 'unsupported') {
                throw new Exception("Unsupported file type. Only JPG, JPEG, PNG and PDF allowed.");
            }

            $text = $fileType === 'pdf'
                ? $this->getPdfText($fileUrl)
                : $this->getImageText($fileUrl);

            $normalizedText = $this->normalizeText($text);
            $referenceNumber = $this->normalizeSearchValue($referenceNumber);
            $accountNumber = $this->normalizeSearchValue($accountNumber);
            $normalizedAmount = $this->normalizeAmount($amount);

            $referenceFound = str_contains($normalizedText, $referenceNumber);
            $accountFound = str_contains($normalizedText, $accountNumber);

            $details = $this->extractTransactionDetails($normalizedText);
            $amountFound = str_contains($normalizedAmount, $amount);

            return [
                'verified' => $referenceFound && $accountFound,
                'amount' => $amountFound ? $amount : null,
                'date' => $details['date'] ?? null,
                'time' => $details['time'] ?? null,
                'account' => $accountFound,
                'reference' => $referenceFound,
                'ocr_text' => $normalizedText
            ];
        } catch (Exception $e) {
            return [
                'verified' => false,
                'amount' => null,
                'date' => null,
                'time' => null,
                'account' => false,
                'reference' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function extractSlipDetails(string $fileUrl): array
    {
        try {
    
            if (
                empty($this->googleKeyPath) ||
                !file_exists($this->googleKeyPath)
            ) {
    
                return [
                    'success' => false,
                    'paid_date' => null,
                    'paid_time' => null,
                    'amount' => null,
                    'bank_account' => null,
                    'ocr_text' => null,
                    'error' => 'Google Vision credentials not configured'
                ];
            }
    
            $fileType = $this->checkFileType($fileUrl);
    
            if ($fileType === 'unsupported') {
                throw new Exception("Unsupported file type");
            }
    
            $text = $fileType === 'pdf'
                ? $this->getPdfText($fileUrl)
                : $this->getImageText($fileUrl);
    
            $normalizedText = $this->normalizeText($text);
    
            $details = $this->extractTransactionDetails($normalizedText);
    
            return [
                'verified' => true,
                'paid_date' => $details['date'] ?? null,
                'paid_time' => $details['time'] ?? null,
                'amount' => $details['amount'] ?? null,
                'bank_account' => $details['account'] ?? null,
                'ocr_text' => $normalizedText
            ];
    
        } catch (\Throwable $e) {
    
            return [
                'verified' => false,
                'paid_date' => null,
                'paid_time' => null,
                'amount' => null,
                'bank_account' => null,
                'ocr_text' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getImageText(string $imageUrl): string
    {
        $imageContent = $this->getFileContent($imageUrl);

        $client = new ImageAnnotatorClient([
            'credentials' => $this->googleKeyPath,
            'transport' => 'rest'
        ]);

        try {
            $image = (new Image())
                ->setContent($imageContent);

            $feature = (new Feature())
                ->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

            $annotateRequest = (new AnnotateImageRequest())
                ->setImage($image)
                ->setFeatures([$feature]);

            $batchRequest = (new BatchAnnotateImagesRequest())
                ->setRequests([$annotateRequest]);

            $batchResponse = $client->batchAnnotateImages($batchRequest);
            $responses = $batchResponse->getResponses();

            if (count($responses) === 0) {
                throw new Exception("No response from Google Vision API.");
            }

            $response = $responses[0];

            if ($response->hasError()) {
                throw new Exception($response->getError()->getMessage());
            }

            $annotation = $response->getFullTextAnnotation();

            if ($annotation === null || trim($annotation->getText()) === '') {
                throw new Exception("No text detected from image.");
            }

            return $annotation->getText();
        } finally {
            $client->close();
        }
    }
    private function getPdfText(string $pdfUrl): string
    {
        require_once('./vendor/autoload.php');

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdfUrl);

        return $pdf->getText();
    }

    private function getFileContent(string $fileUrl): string
    {
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {

            $content = file_get_contents($fileUrl);
        } else {
            $content = file_get_contents($fileUrl);
        }

        if ($content === false || empty($content)) {
            throw new Exception("Failed to read uploaded slip file.");
        }

        return $content;
    }

    private function normalizeText(string $text): string
    {
        $text = strtoupper($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function normalizeSearchValue(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', '', $value);
        return $value;
    }

    private function normalizeAmount(string $amount): float
    {
        $amount = preg_replace('/[^\d.]/', '', $amount);

        return (float) $amount;
    }

    private function extractTransactionDetails(string $text): array
    {
        $results = [
            'amount' => null,
            'date' => null,
            'time' => null,
            'account' => null
        ];

        preg_match('/(?:AMOUNT|TOTAL|PAYMENT|LKR|RS\.?)\s*[:=]?\s*(?:LKR|RS\.?)?\s*([\d,]+\.\d{2})/i', $text, $amountMatch);
        $results['amount'] = $amountMatch[1] ?? null;

        preg_match('/(\d{4}[\/\-.]\d{2}[\/\-.]\d{2})/', $text, $dateMatch1);
        preg_match('/(\d{2}[\/\-.]\d{2}[\/\-.]\d{4})/', $text, $dateMatch2);

        $results['date'] = $dateMatch1[1] ?? $dateMatch2[1] ?? null;

        preg_match('/(\d{1,2}:\d{2}\s*[AP]M?)/i', $text, $timeMatch);
        $results['time'] = $timeMatch[1] ?? null;

        preg_match('/(?:ACCOUNT\s*NO?|ACC\s*NO?|ACCOUNT)\s*[:=]?\s*(\d{6,})/i', $text, $accountMatch);
        $results['account'] = $accountMatch[1] ?? null;

        return $results;
    }

    public function checkFileType(string $filename): string
    {
        $extension = strtolower(pathinfo(parse_url($filename, PHP_URL_PATH), PATHINFO_EXTENSION));

        $imageExtensions = ['jpg', 'jpeg', 'png'];
        $pdfExtensions = ['pdf'];

        if (in_array($extension, $imageExtensions, true)) {
            return 'image';
        }

        if (in_array($extension, $pdfExtensions, true)) {
            return 'pdf';
        }

        return 'unsupported';
    }
}
