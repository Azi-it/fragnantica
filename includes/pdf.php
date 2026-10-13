<?php
// a small PDF writer, just enough for the order receipt: Helvetica text, lines,
// filled boxes and several pages. No library needed.
// positions are in points (72 points = 1 inch), measured from the TOP-LEFT corner of an A4 page.

class SimplePdf
{
    const WIDTH = 595.28;
    const HEIGHT = 841.89;

    // width of each character from space (32) to ~ (126), in 1/1000 of the font size
    // (from the standard Adobe font metrics), used to right-align and fit text
    const HELVETICA = array(
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    );
    const HELVETICA_BOLD = array(
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
    );

    private $pages = array();
    private $title = '';

    public function setTitle($title)
    {
        $this->title = (string)$title;
    }

    public function addPage()
    {
        $this->pages[] = '';
    }

    // the built-in PDF fonts use the Windows-1252 character set; anything else becomes "?"
    private static function toAnsi($text)
    {
        return mb_convert_encoding(str_replace(array("\r", "\n", "\t"), ' ', (string)$text), 'Windows-1252', 'UTF-8');
    }

    private static function escape($ansi)
    {
        return strtr($ansi, array('\\' => '\\\\', '(' => '\\(', ')' => '\\)'));
    }

    private static function color($rgb)
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    private function draw($operators)
    {
        if (empty($this->pages)) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1] .= $operators . "\n";
    }

    public static function textWidth($text, $size, $bold = false)
    {
        $widths = $bold ? self::HELVETICA_BOLD : self::HELVETICA;
        $ansi = self::toAnsi($text);
        $total = 0;
        for ($i = 0, $n = strlen($ansi); $i < $n; $i++) {
            $c = ord($ansi[$i]);
            $total += ($c >= 32 && $c <= 126) ? $widths[$c - 32] : 556;
        }
        return $total * $size / 1000;
    }

    // $align: 'left' (x is the start), 'right' (x is the end) or 'center'
    public function text($x, $y, $text, $size = 10, $bold = false, $align = 'left', $rgb = array(0, 0, 0))
    {
        if ($align === 'right') {
            $x -= self::textWidth($text, $size, $bold);
        } else if ($align === 'center') {
            $x -= self::textWidth($text, $size, $bold) / 2;
        }
        $this->draw(sprintf('BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            self::color($rgb), $bold ? 'F2' : 'F1', $size, $x, self::HEIGHT - $y, self::escape(self::toAnsi($text))));
    }

    public function line($x1, $y1, $x2, $y2, $width = 0.5, $rgb = array(200, 200, 200))
    {
        $this->draw(sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S',
            self::color($rgb), $width, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2));
    }

    public function fillRect($x, $y, $w, $h, $rgb)
    {
        $this->draw(sprintf('%s rg %.2F %.2F %.2F %.2F re f', self::color($rgb), $x, self::HEIGHT - $y - $h, $w, $h));
    }

    // shorten text with "..." so it fits in $maxWidth
    public static function fit($text, $maxWidth, $size, $bold = false)
    {
        $text = (string)$text;
        if (self::textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }
        while ($text !== '' && self::textWidth($text . '...', $size, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }
        return rtrim($text) . '...';
    }

    // split text into lines no wider than $maxWidth
    public static function wrap($text, $maxWidth, $size, $bold = false)
    {
        $lines = array();
        $current = '';
        foreach (preg_split('/\s+/', trim((string)$text)) as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && self::textWidth($try, $size, $bold) > $maxWidth) {
                $lines[] = $current;
                $current = self::fit($word, $maxWidth, $size, $bold);
            } else {
                $current = self::fit($try, $maxWidth, $size, $bold);
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    // the finished PDF file as a string
    public function output()
    {
        if (empty($this->pages)) {
            $this->addPage();
        }

        // 1 = catalog, 2 = page list, 3 and 4 = fonts, 5 = document info, then a page and its content per page
        $objects = array(
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Title (' . self::escape(self::toAnsi($this->title)) . ') /Producer (' . self::escape(self::toAnsi(MAIL_FROM_NAME)) . ')'
                . ' /CreationDate (D:' . date('YmdHis') . ') >>',
        );
        $kids = array();
        $next = 6;
        foreach ($this->pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::WIDTH, self::HEIGHT, $contentId);
            $filter = '';
            if (function_exists('gzcompress')) {
                $content = gzcompress($content);
                $filter = ' /Filter /FlateDecode';
            }
            $objects[$contentId] = '<< /Length ' . strlen($content) . $filter . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array();
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        // the cross-reference table: where each object starts in the file
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R /Info 5 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}
