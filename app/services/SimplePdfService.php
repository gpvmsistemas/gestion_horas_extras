<?php

/** Generador PDF compacto, sin dependencias externas, para reportes tabulares internos. */
class SimplePdfService {
    public function download($filename, $title, array $headers, array $rows, array $meta = []) {
        $lines = [$title];
        foreach ($meta as $key => $value) $lines[] = $key . ': ' . $value;
        $lines[] = str_repeat('-', 110);
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', 110);
        foreach ($rows as $row) $lines[] = implode(' | ', array_map([$this, 'scalar'], (array)$row));
        $pdf = $this->build($lines);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    public function build(array $lines) {
        $pages = array_chunk($lines, 47);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageIds = []; $next = 4;
        foreach ($pages as $pageLines) {
            $pageId = $next++; $contentId = $next++; $pageIds[] = $pageId;
            $stream = "BT\n/F1 8 Tf\n35 805 Td\n";
            foreach ($pageLines as $i => $line) {
                if ($i) $stream .= "0 -16 Td\n";
                $stream .= '(' . $this->escape($this->limit($line, 145)) . ") Tj\n";
            }
            $stream .= "ET\n";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length " . strlen($stream) . ">>\nstream\n{$stream}endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($id) => "$id 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects); $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $body) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$body\nendobj\n"; }
        $xref = strlen($pdf); $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i=1;$i<=$max;$i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        return $pdf . "trailer\n<< /Size " . ($max+1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }

    private function scalar($value) { return is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE); }
    private function limit($value, $max) { $value = preg_replace('/\s+/u', ' ', trim((string)$value)); return mb_strimwidth($value, 0, $max, '...', 'UTF-8'); }
    private function escape($value) { $v = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$value); return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $v); }
}
