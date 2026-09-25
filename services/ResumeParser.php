<?php

require_once __DIR__ . "/../vendor/autoload.php";

class ResumeParser {
    public static function extractText($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Resume file not found.");
        }

        $parser = new \Smalot\PdfParser\Parser();

        $pdf = $parser->parseFile($filePath);

        $text = $pdf->getText();

        return trim($text);
    }
}
?>