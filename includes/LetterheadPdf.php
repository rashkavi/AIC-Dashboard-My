<?php
/**
 * classes/LetterheadPdf.php
 *
 * Pure-PHP PDF writer (no Composer / no external library).
 * Takes page 1 of an existing letterhead PDF (e.g. letterHead.pdf), keeps it
 * exactly as the page background, and writes text on top of it.
 *
 * Needs only the PHP zlib extension (enabled by default).
 * Fonts: built-in PDF fonts Times-Roman / Times-Bold (no font files needed).
 *
 * Coordinates are in points (1 mm = 2.835 pt), measured from the TOP-LEFT.
 */
class LetterheadPdf
{
    // ---- source letterhead -------------------------------------------------
    private string $src;
    private array  $xref = [];          // objNum => ['t'=>1,'off'=>int] | ['t'=>2,'stm'=>int,'idx'=>int]
    private array  $trailer = [];
    private array  $objStmCache = [];

    // ---- output ------------------------------------------------------------
    private array  $objects = [];       // newNum => body string
    private array  $map = [];           // oldNum => newNum (imported objects)
    private int    $nextNum = 5;        // 1 catalog, 2 pages, 3 F1, 4 F2
    private ?int   $formNum = null;     // letterhead form XObject
    private array  $mediaBox = [0, 0, 595.28, 841.89];
    private array  $pages = [];         // list of content strings
    private int    $cur = -1;

    // ---- layout ------------------------------------------------------------
    private float  $left = 50, $right = 50, $y = 100;
    private string $title = '', $author = '';

    private const FONTS = ['R' => 'F1', 'B' => 'F2'];
    private static array $W = [];

    public function __construct(string $letterheadPath)
    {
        if (!is_file($letterheadPath)) {
            throw new RuntimeException("Letterhead PDF not found: $letterheadPath");
        }
        $this->src = file_get_contents($letterheadPath);
        if (strncmp($this->src, '%PDF', 4) !== 0) {
            throw new RuntimeException('Letterhead file is not a PDF');
        }
        $this->loadXref();
        if (isset($this->trailer['Encrypt'])) {
            throw new RuntimeException('Letterhead PDF is encrypted/protected — save an unprotected copy.');
        }
        $this->importFirstPageAsForm();
        self::initWidths();
    }

    // =======================================================================
    //  Public API
    // =======================================================================

    public function setMargins(float $left, float $top, float $right): void
    {
        $this->left = $left; $this->right = $right; $this->y = $top;
    }

    public function setInfo(string $title, string $author = ''): void
    {
        $this->title = $title; $this->author = $author;
    }

    /** New page with the letterhead drawn as background. */
    public function addPage(?float $top = null): void
    {
        $this->pages[] = "q /LH Do Q\n";
        $this->cur = count($this->pages) - 1;
        if ($top !== null) $this->y = $top;
    }

    public function getY(): float { return $this->y; }
    public function moveDown(float $pt): void { $this->y += $pt; }

    public function contentWidth(): float
    {
        return ($this->mediaBox[2] - $this->mediaBox[0]) - $this->left - $this->right;
    }

    /**
     * Write a paragraph made of runs: [['plain text ', 'R'], ['bold text', 'B'], ...]
     * $align: left | center | right | justify
     */
    public function paragraph(array $runs, string $align = 'justify', float $size = 11,
                              float $lineHeight = 1.45, float $spaceAfter = 9): void
    {
        $maxW  = $this->contentWidth();
        $lines = $this->wrap($runs, $size, $maxW);
        $lh    = $size * $lineHeight;
        $last  = count($lines) - 1;

        foreach ($lines as $i => $words) {
            $this->y += $lh;
            $baseline = $this->y;

            $wordWidths = array_map(fn($w) => $this->wordWidth($w, $size), $words);
            $space      = $this->strWidth(' ', 'R', $size);
            $lineW      = array_sum($wordWidths) + $space * max(0, count($words) - 1);
            $gap        = $space;
            $x          = $this->left;

            if ($align === 'center')      $x += ($maxW - $lineW) / 2;
            elseif ($align === 'right')   $x += $maxW - $lineW;
            elseif ($align === 'justify' && $i < $last && count($words) > 1) {
                $gap = $space + ($maxW - $lineW) / (count($words) - 1);
            }

            foreach ($words as $k => $word) {
                $wx = $x;
                foreach ($word as [$txt, $font]) {
                    $this->drawText($wx, $baseline, $txt, $font, $size);
                    $wx += $this->strWidth($txt, $font, $size);
                }
                $x += $wordWidths[$k] + $gap;
            }
        }
        $this->y += $spaceAfter;
    }

    /** Single line of text at an absolute X (relative to left margin) on the current line. */
    public function cells(array $cells, float $size = 11, float $rowHeight = 26): void
    {
        $this->y += $rowHeight;
        foreach ($cells as [$text, $font, $xOffset]) {
            $this->drawText($this->left + $xOffset, $this->y, (string) $text, $font, $size);
        }
    }

    /** Thin horizontal line across the content width (optional use). */
    public function line(float $offsetY = 0, float $width = 0.5): void
    {
        $pageH = $this->mediaBox[3];
        $y = $pageH - ($this->y + $offsetY);
        $this->pages[$this->cur] .= sprintf("%s w %s %s m %s %s l S\n", self::n($width),
            self::n($this->mediaBox[0] + $this->left), self::n($y),
            self::n($this->mediaBox[2] - $this->right), self::n($y));
    }

    /** Return the finished PDF as a binary string. */
    public function output(): string
    {
        if (!$this->pages) $this->addPage();

        $kids = [];
        foreach ($this->pages as $content) {
            $data   = gzcompress($content);
            $cNum   = $this->nextNum++;
            $this->objects[$cNum] = "<< /Length " . strlen($data) . " /Filter /FlateDecode >>\nstream\n$data\nendstream";
            $pNum   = $this->nextNum++;
            $mb     = implode(' ', array_map([self::class, 'n'], $this->mediaBox));
            $this->objects[$pNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [$mb]"
                . " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /LH {$this->formNum} 0 R >>"
                . " /ProcSet [/PDF /Text /ImageB /ImageC /ImageI] >> /Contents $cNum 0 R >>";
            $kids[] = "$pNum 0 R";
        }

        $this->objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $this->objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";
        $this->objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>";
        $this->objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>";

        $infoNum = $this->nextNum++;
        $this->objects[$infoNum] = "<< /Title " . self::pdfString($this->title)
            . " /Author " . self::pdfString($this->author)
            . " /Producer (LetterheadPdf) /CreationDate (D:" . date('YmdHis') . ") >>";

        ksort($this->objects);
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($this->objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= "$num 0 obj\n$body\nendobj\n";
        }
        $size = max(array_keys($this->objects)) + 1;
        $xrefPos = strlen($out);
        $out .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $out .= "trailer\n<< /Size $size /Root 1 0 R /Info $infoNum 0 R >>\nstartxref\n$xrefPos\n%%EOF\n";
        return $out;
    }

    /** Send the PDF to the browser. */
    public function stream(string $fileName, bool $download = false): void
    {
        $pdf = $this->output();
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
    }

    // =======================================================================
    //  Text helpers
    // =======================================================================

    private function drawText(float $x, float $yTop, string $utf8, string $font, float $size): void
    {
        if ($utf8 === '') return;
        $pdfX = $this->mediaBox[0] + $x;
        $pdfY = $this->mediaBox[3] - $yTop;
        $f    = self::FONTS[$font] ?? 'F1';
        $this->pages[$this->cur] .= sprintf("BT 0 g /%s %s Tf %s %s Td %s Tj ET\n",
            $f, self::n($size), self::n($pdfX), self::n($pdfY), self::pdfString($utf8));
    }

    /** Split runs into words (each word = list of [text, font] pieces) and wrap into lines. */
    private function wrap(array $runs, float $size, float $maxW): array
    {
        $words = []; $current = [];
        foreach ($runs as [$text, $font]) {
            $parts = preg_split('/(\s+)/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $p) {
                if ($p === '') continue;
                if (preg_match('/^\s+$/u', $p)) {
                    if ($current) { $words[] = $current; $current = []; }
                } else {
                    $current[] = [$p, $font];
                }
            }
        }
        if ($current) $words[] = $current;

        $lines = []; $line = []; $lineW = 0;
        $space = $this->strWidth(' ', 'R', $size);
        foreach ($words as $w) {
            $ww = $this->wordWidth($w, $size);
            $needed = $line ? $lineW + $space + $ww : $ww;
            if ($line && $needed > $maxW) {
                $lines[] = $line; $line = [$w]; $lineW = $ww;
            } else {
                $line[] = $w; $lineW = $needed;
            }
        }
        if ($line) $lines[] = $line;
        return $lines;
    }

    private function wordWidth(array $word, float $size): float
    {
        $w = 0;
        foreach ($word as [$t, $f]) $w += $this->strWidth($t, $f, $size);
        return $w;
    }

    public function strWidth(string $utf8, string $font, float $size): float
    {
        $s = self::toWinAnsi($utf8);
        $tbl = self::$W[$font] ?? self::$W['R'];
        $w = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) $w += $tbl[ord($s[$i])];
        return $w * $size / 1000;
    }

    private static function toWinAnsi(string $utf8): string
    {
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $utf8);
        if ($s === false) $s = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
        return $s;
    }

    private static function pdfString(string $utf8): string
    {
        $s = self::toWinAnsi($utf8);
        return '(' . strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']) . ')';
    }

    private static function n(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.3F', $v), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }

    private static function initWidths(): void
    {
        if (self::$W) return;
        $r = '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,250,333,408,500,500,833,778,180,333,333,500,564,250,333,250,278,500,500,500,500,500,500,500,500,500,500,278,278,564,564,564,444,921,722,667,667,722,611,556,722,722,333,389,722,611,889,722,722,556,722,667,556,611,722,722,944,722,722,611,333,278,333,469,500,333,444,500,444,500,444,333,500,500,278,278,500,278,778,500,500,500,500,333,389,278,500,500,722,500,500,444,480,200,480,541,350,500,350,333,500,444,1000,500,500,333,1000,556,333,889,350,611,350,350,333,333,444,444,350,500,1000,333,980,389,333,722,350,444,722,250,333,500,500,500,500,200,500,333,760,276,500,564,333,760,333,400,564,300,300,333,500,453,250,333,300,310,500,750,750,750,444,722,722,722,722,722,722,889,667,611,611,611,611,333,333,333,333,722,722,722,722,722,722,722,564,722,722,722,722,722,722,556,500,444,444,444,444,444,444,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,564,500,500,500,500,500,500,500,500';
        $b = '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,250,333,555,500,500,1000,833,278,333,333,500,570,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,570,570,570,500,930,722,667,722,722,667,611,778,778,389,500,778,667,944,722,778,611,778,722,556,667,722,722,1000,722,722,667,333,278,333,581,500,333,500,556,444,556,444,333,500,556,278,333,556,278,833,556,500,556,556,444,389,333,556,500,722,500,500,444,394,220,394,520,350,500,350,333,500,500,1000,500,500,333,1000,556,333,1000,350,667,350,350,333,333,500,500,350,500,1000,333,1000,389,333,722,350,444,722,250,333,500,500,500,500,220,500,333,747,300,500,570,333,747,333,400,570,300,300,333,556,540,250,333,300,330,500,750,750,750,500,722,722,722,722,722,722,1000,722,667,667,667,667,389,389,389,389,722,722,778,778,778,778,778,570,778,722,722,722,722,722,611,556,500,500,500,500,500,500,722,444,444,444,444,444,278,278,278,278,500,556,500,500,500,500,500,570,500,556,556,556,556,500,556,500';
        self::$W['R'] = array_map('intval', explode(',', $r));
        self::$W['B'] = array_map('intval', explode(',', $b));
    }

    // =======================================================================
    //  Letterhead import (minimal PDF reader)
    // =======================================================================

    private function importFirstPageAsForm(): void
    {
        $root    = $this->deref($this->trailer['Root'] ?? null);
        $pagesV  = $this->deref($root[1]['Pages'] ?? null);
        $page    = $this->findFirstPage($pagesV, []);
        if (!$page) throw new RuntimeException('No page found in letterhead PDF');
        [$dict, $inherited] = $page;

        $attr = fn($k) => $dict[$k] ?? $inherited[$k] ?? null;

        $mb = $this->deref($attr('MediaBox'));
        if ($mb && $mb[0] === 'array') {
            $this->mediaBox = array_map(fn($v) => (float) $this->deref($v)[1], $mb[1]);
        }

        // Concatenate page content streams
        $content = '';
        $c = $attr('Contents');
        $list = [];
        if ($c && $c[0] === 'ref')  { $obj = $this->readObject($c[1]); $obj[0][0] === 'array' ? $list = $obj[0][1] : $list = [$c]; }
        elseif ($c && $c[0] === 'array') $list = $c[1];
        foreach ($list as $ref) {
            [$sd, $raw] = $this->readObject($ref[1]);
            $content .= $this->decodeStream($sd, $raw) . "\n";
        }

        $res = $attr('Resources');
        $resStr = $res ? $this->serialize($res) : '<< >>';
        $bbox = implode(' ', array_map([self::class, 'n'], $this->mediaBox));
        $data = gzcompress($content);

        $this->formNum = $this->nextNum++;
        $this->objects[$this->formNum] = "<< /Type /XObject /Subtype /Form /FormType 1 /BBox [$bbox]"
            . " /Resources $resStr /Filter /FlateDecode /Length " . strlen($data) . " >>\nstream\n$data\nendstream";
    }

    private function findFirstPage($node, array $inherited): ?array
    {
        $node = $this->deref($node);
        if (!$node || $node[0] !== 'dict') return null;
        $d = $node[1];
        foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $k) {
            if (isset($d[$k])) $inherited[$k] = $d[$k];
        }
        $type = isset($d['Type']) ? $this->deref($d['Type'])[1] : '';
        if ($type === 'Page' || (!isset($d['Kids']) && isset($d['Contents']))) return [$d, $inherited];
        $kids = $this->deref($d['Kids'] ?? null);
        if ($kids && $kids[0] === 'array') {
            foreach ($kids[1] as $kid) {
                $r = $this->findFirstPage($kid, $inherited);
                if ($r) return $r;
            }
        }
        return null;
    }

    /** Serialize a parsed value; references are imported (with all dependencies) and renumbered. */
    private function serialize(array $v): string
    {
        switch ($v[0]) {
            case 'dict':
                $s = '<<';
                foreach ($v[1] as $k => $val) {
                    if ($k === 'Parent' || $k === 'P') continue;     // never pull in the page tree
                    $s .= ' /' . $k . ' ' . $this->serialize($val);
                }
                return $s . ' >>';
            case 'array':
                return '[' . implode(' ', array_map([$this, 'serialize'], $v[1])) . ']';
            case 'ref':
                return $this->importObject($v[1]) . ' 0 R';
            case 'name':
                return '/' . $v[1];
            default:
                return $v[1];   // num, str, hex, bool, null (raw)
        }
    }

    private function importObject(int $old): int
    {
        if (isset($this->map[$old])) return $this->map[$old];
        $new = $this->nextNum++;
        $this->map[$old] = $new;
        [$val, $raw] = $this->readObject($old);
        if ($raw !== null && $val[0] === 'dict') {
            $d = $val[1];
            $d['Length'] = ['num', (string) strlen($raw)];
            $this->objects[$new] = $this->serialize(['dict', $d]) . "\nstream\n" . $raw . "\nendstream";
        } else {
            $this->objects[$new] = $this->serialize($val);
        }
        return $new;
    }

    private function deref($v)
    {
        $guard = 0;
        while ($v && $v[0] === 'ref' && $guard++ < 32) $v = $this->readObject($v[1])[0];
        return $v;
    }

    // ---- xref ---------------------------------------------------------------

    private function loadXref(): void
    {
        $p = strrpos($this->src, 'startxref');
        if ($p === false) throw new RuntimeException('Invalid PDF: startxref not found');
        $offset = (int) trim(substr($this->src, $p + 9, 20));
        $seen = [];
        while ($offset > 0 && !isset($seen[$offset])) {
            $seen[$offset] = true;
            $trailer = $this->readXrefSection($offset);
            if (!$this->trailer) $this->trailer = $trailer;
            if (isset($trailer['XRefStm'])) $this->readXrefSection((int) $trailer['XRefStm'][1]);
            $offset = isset($trailer['Prev']) ? (int) $trailer['Prev'][1] : 0;
        }
        if (!$this->trailer) throw new RuntimeException('Invalid PDF: trailer not found');
    }

    private function readXrefSection(int $offset): array
    {
        $pos = $offset;
        $this->skipWs($this->src, $pos);
        if (substr($this->src, $pos, 4) === 'xref') {
            $pos += 4;
            while (true) {
                $this->skipWs($this->src, $pos);
                if (substr($this->src, $pos, 7) === 'trailer') {
                    $pos += 7;
                    $t = $this->parseValue($this->src, $pos);
                    return $t[1];
                }
                if (!preg_match('/\G(\d+)\s+(\d+)/', $this->src, $m, 0, $pos)) break;
                $pos += strlen($m[0]);
                [$start, $count] = [(int) $m[1], (int) $m[2]];
                for ($i = 0; $i < $count; $i++) {
                    $this->skipWs($this->src, $pos);
                    $line = substr($this->src, $pos, 18);
                    $pos += 18;
                    $num = $start + $i;
                    if (!isset($this->xref[$num]) && substr($line, 17, 1) === 'n') {
                        $this->xref[$num] = ['t' => 1, 'off' => (int) substr($line, 0, 10)];
                    }
                }
            }
            throw new RuntimeException('Invalid PDF: broken xref table');
        }

        // Cross-reference stream (PDF 1.5+)
        [$dictV, $raw] = $this->readObjectAt($pos);
        $d    = $dictV[1];
        $data = $this->decodeStream($d, $raw);
        $w    = array_map(fn($x) => (int) $x[1], $d['W'][1]);
        $idx  = isset($d['Index']) ? array_map(fn($x) => (int) $x[1], $d['Index'][1]) : [0, (int) $d['Size'][1]];
        $rowLen = array_sum($w);
        $p = 0;
        for ($k = 0; $k < count($idx); $k += 2) {
            for ($i = 0; $i < $idx[$k + 1]; $i++) {
                $f = [];
                foreach ($w as $len) {
                    $val = 0;
                    for ($b = 0; $b < $len; $b++) $val = ($val << 8) | ord($data[$p++] ?? "\0");
                    $f[] = $val;
                }
                $type = $w[0] === 0 ? 1 : $f[0];
                $num = $idx[$k] + $i;
                if (isset($this->xref[$num])) continue;
                if ($type === 1)      $this->xref[$num] = ['t' => 1, 'off' => $f[1]];
                elseif ($type === 2)  $this->xref[$num] = ['t' => 2, 'stm' => $f[1], 'idx' => $f[2]];
            }
        }
        return $d;
    }

    // ---- objects --------------------------------------------------------------

    /** @return array [value, rawStreamBytes|null] */
    private function readObject(int $num): array
    {
        $e = $this->xref[$num] ?? null;
        if (!$e) return [['null', 'null'], null];
        if ($e['t'] === 1) return $this->readObjectAt($e['off']);

        // object inside an object stream
        $stm = $e['stm'];
        if (!isset($this->objStmCache[$stm])) {
            [$sd, $raw] = $this->readObject($stm);
            $data  = $this->decodeStream($sd[1], $raw);
            $n     = (int) $sd[1]['N'][1];
            $first = (int) $sd[1]['First'][1];
            preg_match_all('/\d+/', substr($data, 0, $first), $m);
            $offs = [];
            for ($i = 0; $i < $n; $i++) $offs[] = (int) $m[0][$i * 2 + 1];
            $this->objStmCache[$stm] = [$data, $first, $offs];
        }
        [$data, $first, $offs] = $this->objStmCache[$stm];
        $pos = $first + ($offs[$e['idx']] ?? 0);
        return [$this->parseValue($data, $pos), null];
    }

    private function readObjectAt(int $offset): array
    {
        $pos = $offset;
        if (!preg_match('/\G\s*\d+\s+\d+\s+obj/', $this->src, $m, 0, $pos)) {
            throw new RuntimeException("Invalid PDF object at offset $offset");
        }
        $pos += strlen($m[0]);
        $val = $this->parseValue($this->src, $pos);
        $this->skipWs($this->src, $pos);
        if ($val[0] === 'dict' && substr($this->src, $pos, 6) === 'stream') {
            $pos += 6;
            if ($this->src[$pos] === "\r") $pos++;
            if ($this->src[$pos] === "\n") $pos++;
            $lenV = $this->deref($val[1]['Length'] ?? null);
            $len  = $lenV ? (int) $lenV[1] : -1;
            $chk  = $len >= 0 ? ltrim(substr($this->src, $pos + $len, 20)) : '';
            if ($len < 0 || strncmp($chk, 'endstream', 9) !== 0) {
                $end = strpos($this->src, 'endstream', $pos);
                $len = $end - $pos;
                $raw = rtrim(substr($this->src, $pos, $len), "\r\n");
                return [$val, $raw];
            }
            return [$val, substr($this->src, $pos, $len)];
        }
        return [$val, null];
    }

    private function decodeStream(array $dict, string $raw): string
    {
        if (($dict[0] ?? null) === 'dict') $dict = $dict[1];
        $filters = [];
        $f = $this->deref($dict['Filter'] ?? null);
        if ($f) $filters = $f[0] === 'array' ? array_map(fn($x) => $x[1], $f[1]) : [$f[1]];
        $parms = $this->deref($dict['DecodeParms'] ?? null);

        foreach ($filters as $i => $name) {
            if ($name === 'FlateDecode' || $name === 'Fl') {
                $out = @zlib_decode($raw);
                if ($out === false) $out = @gzinflate(substr($raw, 2));
                if ($out === false) throw new RuntimeException('Could not decompress a letterhead stream');
                $raw = $out;
                $p = $parms;
                if ($p && $p[0] === 'array') $p = $this->deref($p[1][$i] ?? null);
                if ($p && $p[0] === 'dict' && isset($p[1]['Predictor']) && (int) $p[1]['Predictor'][1] >= 10) {
                    $cols = isset($p[1]['Columns']) ? (int) $p[1]['Columns'][1] : 1;
                    $raw = self::pngUnpredict($raw, $cols);
                }
            } elseif ($name === 'ASCII85Decode' || $name === 'A85') {
                $raw = self::ascii85Decode($raw);
            } elseif ($name === 'ASCIIHexDecode' || $name === 'AHx') {
                $hex = preg_replace('/[^0-9A-Fa-f]/', '', strstr($raw . '>', '>', true));
                $raw = hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
            } else {
                throw new RuntimeException("Unsupported filter /$name in letterhead content stream");
            }
        }
        return $raw;
    }

    private static function ascii85Decode(string $in): string
    {
        $in = preg_replace('/\s+/', '', $in);
        if (strncmp($in, '<~', 2) === 0) $in = substr($in, 2);
        if (($e = strpos($in, '~>')) !== false) $in = substr($in, 0, $e);
        $out = ''; $tuple = []; $n = strlen($in);
        for ($i = 0; $i < $n; $i++) {
            $c = $in[$i];
            if ($c === 'z' && !$tuple) { $out .= "\0\0\0\0"; continue; }
            $tuple[] = ord($c) - 33;
            if (count($tuple) === 5) {
                $v = 0; foreach ($tuple as $t) $v = $v * 85 + $t;
                $out .= pack('N', $v); $tuple = [];
            }
        }
        if ($tuple) {
            $cnt = count($tuple);
            while (count($tuple) < 5) $tuple[] = 84;
            $v = 0; foreach ($tuple as $t) $v = $v * 85 + $t;
            $out .= substr(pack('N', $v), 0, $cnt - 1);
        }
        return $out;
    }

    private static function pngUnpredict(string $data, int $cols): string
    {
        $out = ''; $prev = str_repeat("\0", $cols); $rowLen = $cols + 1;
        for ($i = 0, $n = strlen($data); $i + $rowLen <= $n; $i += $rowLen) {
            $type = ord($data[$i]); $row = substr($data, $i + 1, $cols); $cur = '';
            for ($j = 0; $j < $cols; $j++) {
                $x = ord($row[$j]); $a = $j > 0 ? ord($cur[$j - 1]) : 0; $b = ord($prev[$j]); $c = $j > 0 ? ord($prev[$j - 1]) : 0;
                switch ($type) {
                    case 1: $x += $a; break;
                    case 2: $x += $b; break;
                    case 3: $x += ($a + $b) >> 1; break;
                    case 4:
                        $pp = $a + $b - $c; $pa = abs($pp - $a); $pb = abs($pp - $b); $pc = abs($pp - $c);
                        $x += ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                        break;
                }
                $cur .= chr($x & 0xFF);
            }
            $out .= $cur; $prev = $cur;
        }
        return $out;
    }

    // ---- tokenizer --------------------------------------------------------------

    private function skipWs(string $s, int &$p): void
    {
        $n = strlen($s);
        while ($p < $n) {
            $c = $s[$p];
            if ($c === '%') { while ($p < $n && $s[$p] !== "\n" && $s[$p] !== "\r") $p++; }
            elseif (strpos(" \t\r\n\f\0", $c) !== false) $p++;
            else break;
        }
    }

    private function parseValue(string $s, int &$p): array
    {
        $this->skipWs($s, $p);
        $c = $s[$p] ?? '';

        if ($c === '<' && ($s[$p + 1] ?? '') === '<') {
            $p += 2; $d = [];
            while (true) {
                $this->skipWs($s, $p);
                if (substr($s, $p, 2) === '>>') { $p += 2; break; }
                if ($p >= strlen($s)) break;
                $key = $this->parseValue($s, $p);
                $val = $this->parseValue($s, $p);
                $d[$key[1]] = $val;
            }
            return ['dict', $d];
        }
        if ($c === '[') {
            $p++; $a = [];
            while (true) {
                $this->skipWs($s, $p);
                if (($s[$p] ?? ']') === ']') { $p++; break; }
                $a[] = $this->parseValue($s, $p);
            }
            return ['array', $a];
        }
        if ($c === '/') {
            preg_match('/\G\/[^\s\/\[\]<>(){}%]*/', $s, $m, 0, $p);
            $p += strlen($m[0]);
            return ['name', substr($m[0], 1)];
        }
        if ($c === '(') {
            $start = $p; $depth = 0; $n = strlen($s);
            for (; $p < $n; $p++) {
                $ch = $s[$p];
                if ($ch === '\\') { $p++; continue; }
                if ($ch === '(') $depth++;
                elseif ($ch === ')' && --$depth === 0) { $p++; break; }
            }
            return ['str', substr($s, $start, $p - $start)];
        }
        if ($c === '<') {
            $end = strpos($s, '>', $p);
            $raw = substr($s, $p, $end - $p + 1);
            $p = $end + 1;
            return ['hex', $raw];
        }
        if (preg_match('/\G(\d+)\s+(\d+)\s+R(?![A-Za-z])/', $s, $m, 0, $p)) {
            $p += strlen($m[0]);
            return ['ref', (int) $m[1], (int) $m[2]];
        }
        if (preg_match('/\G[+\-]?(\d+\.?\d*|\.\d+)/', $s, $m, 0, $p)) {
            $p += strlen($m[0]);
            return ['num', $m[0]];
        }
        if (preg_match('/\G(true|false|null)/', $s, $m, 0, $p)) {
            $p += strlen($m[0]);
            return [$m[1] === 'null' ? 'null' : 'bool', $m[1]];
        }
        // unknown token — skip one char to avoid infinite loops
        $p++;
        return ['null', 'null'];
    }
}
