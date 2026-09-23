<?php

namespace App\Services\Billing;

use Illuminate\Support\Str;

class PdfCanvas
{
    /** @var array<int, string> */
    private array $pages = [];

    /** @var array<string, array{data: string, width: int, height: int}> */
    private array $images = [];

    /** @var array<int, string> */
    private array $lastTableHeadings = [];

    /** @var array<int, float> */
    private array $lastTableWidths = [];

    private float $cursor = 728;

    public function __construct(private readonly string $title, private readonly string $number)
    {
        $this->newPage();
    }

    public function newPage(): void
    {
        $this->pages[] = '';
        $this->cursor = 728;
        $this->rect(0, 780, 595, 62, '#10243D');
        $this->text(42, 808, 'ACServ ERP', 17, '#FFFFFF', true);
        $this->text(42, 792, 'SERVICE MANAGEMENT / CUSTOMER RECORD', 8, '#C6D8F5');
        $this->text(553, 807, Str::upper($this->title), 12, '#FFFFFF', true, true);
        $this->text(553, 791, $this->number, 9, '#C6D8F5', false, true);
        $this->rect(42, 754, 511, 2, '#D9E5F5');
    }

    public function ensure(float $height): void
    {
        if ($this->cursor - $height < 73) {
            $this->newPage();
        }
    }

    public function section(string $label, float $minimumContentHeight = 0): void
    {
        $this->ensure(35 + $minimumContentHeight);
        $this->cursor -= 7;
        $this->rect(42, $this->cursor - 7, 4, 16, '#2B65B1');
        $this->text(55, $this->cursor - 2, Str::upper($label), 10, '#173452', true);
        $this->cursor -= 25;
    }

    /**
     * @param  array<string, string>  $meta
     * @param  array<int, array<int, string>>  $items
     * @param  array<string, string>  $totals
     */
    public function invoiceLayout(array $meta, array $items, array $totals, ?string $signature, ?string $notes): void
    {
        $this->rect(42, 633, 302, 104, '#F3F7FC');
        $this->rect(351, 633, 202, 104, '#F3F7FC');
        $this->text(55, 718, 'BILL TO', 8, '#2B65B1', true);
        $this->text(55, 698, $this->fitted($meta['customer'], 275, 12), 12, '#173452', true);
        $this->text(55, 680, $this->fitted($meta['contact'], 275, 8.5), 8.5, '#44546A');
        foreach (array_slice($this->wrap($meta['address'], 55), 0, 2) as $index => $line) {
            $this->text(55, 662 - ($index * 13), $this->fitted($line, 275, 8.5), 8.5, '#44546A');
        }
        $this->text(364, 718, 'INVOICE DETAILS', 8, '#2B65B1', true);
        $this->text(364, 698, $this->fitted($this->number, 177, 11), 11, '#173452', true);
        $this->text(364, 680, $this->fitted('Issued: '.$meta['issued'].'  |  Due: '.$meta['due'], 177, 8.2), 8.2);
        $this->text(364, 662, $this->fitted('Status: '.$meta['status'], 177, 8.5), 8.5, '#173452', true);
        $this->text(364, 646, $this->fitted('GSTIN: '.$meta['gstin'], 177, 8), 8, '#44546A');

        $this->rect(42, 585, 511, 38, '#EAF2FC');
        $this->text(54, 607, $this->fitted($meta['job'].'  |  '.$meta['service'], 485, 9), 9, '#173452', true);
        $this->text(54, 592, $this->fitted('Technician: '.$meta['technician'].'  |  Equipment: '.$meta['equipment'], 485, 8), 8, '#44546A');
        $this->text(42, 566, 'ITEMIZED CHARGES', 9, '#173452', true);
        $this->rect(42, 536, 511, 22, '#173452');
        $this->text(50, 543, 'SERVICE / ITEM', 7.5, '#FFFFFF', true);
        $this->text(291, 543, 'QTY', 7.5, '#FFFFFF', true);
        $this->text(343, 543, 'RATE INR', 7.5, '#FFFFFF', true);
        $this->text(411, 543, 'TAX', 7.5, '#FFFFFF', true);
        $this->text(540, 543, 'AMOUNT INR', 7.5, '#FFFFFF', true, true);

        if ($items === []) {
            $items = [['No charge lines recorded', '-', '-', '-', '-']];
        } elseif (count($items) > 14) {
            $remaining = array_slice($items, 13);
            $amount = array_reduce($remaining, fn (float $sum, array $item): float => $sum + (float) str_replace(',', '', $item[4]), 0.0);
            $items = array_slice($items, 0, 13);
            $items[] = [count($remaining).' more items - full list in ERP', '-', '-', '-', number_format($amount, 2)];
        }
        foreach ($items as $index => $item) {
            $y = 536 - ($index * 16);
            $this->rect(42, $y - 16, 511, 16, $index % 2 === 0 ? '#F4F7FC' : '#FFFFFF');
            $this->line(42, $y - 16, 553, $y - 16, '#E6EDF5');
            $this->text(50, $y - 11, $this->fitted($item[0], 226, 8), 8, '#243B53');
            $this->text(291, $y - 11, $this->fitted($item[1], 43, 8), 8, '#243B53');
            $this->text(343, $y - 11, $this->fitted($item[2], 60, 8), 8, '#243B53');
            $this->text(411, $y - 11, $this->fitted($item[3], 41, 8), 8, '#243B53');
            $this->text(540, $y - 11, $this->fitted($item[4], 80, 8), 8, '#243B53', false, true);
        }

        $this->rect(42, 205, 254, 91, '#F3F7FC');
        $this->text(55, 278, 'PAYMENT RECORD', 8, '#2B65B1', true);
        foreach (array_slice($this->wrap($meta['payment'], 44), 0, 2) as $index => $line) {
            $this->text(55, 258 - ($index * 14), $this->fitted($line, 227, 8.5), 8.5, '#173452');
        }
        $this->text(55, 219, $this->fitted($meta['payment_count'].' payment record(s)  |  Job '.$meta['job'], 228, 8), 8, '#657993');

        $this->rect(310, 166, 243, 130, '#F3F7FC');
        $totalY = [279, 260, 241, 219, 196, 177];
        foreach (array_values($totals) as $index => $value) {
            $label = array_keys($totals)[$index];
            if ($label === 'Grand total') {
                $this->rect(320, 207, 223, 27, '#DDEBFA');
            }
            $emphasis = in_array($label, ['Grand total', 'Balance due'], true);
            $this->text(322, $totalY[$index], $label, $emphasis ? 9.5 : 8.5, '#173452', $emphasis);
            $this->text(540, $totalY[$index], $value, $emphasis ? 10 : 8.5, '#173452', $emphasis, true);
        }

        $this->rect(42, 82, 254, 114, '#F3F7FC');
        $this->text(55, 179, 'CUSTOMER COMPLETION SIGNATURE', 8, '#2B65B1', true);
        $this->drawImage($signature, 58, 96, 220, 72);
        $this->text(55, 87, 'Acknowledged on completion of service', 7.5, '#657993');
        $this->rect(310, 82, 243, 73, '#F3F7FC');
        $this->text(322, 138, 'NOTES', 8, '#2B65B1', true);
        $noteLines = $this->wrap($notes ?: 'Thank you for choosing ACServ ERP.', 48);
        if (count($noteLines) > 3) {
            $noteLines[2] = Str::limit($noteLines[2], 28, '...').' (more in ERP)';
        }
        foreach (array_slice($noteLines, 0, 3) as $index => $line) {
            $this->text(322, 121 - ($index * 13), $this->fitted($line, 219, 8), 8, '#44546A');
        }
    }

    public function note(string $value, string $color = '#44546A'): void
    {
        $lines = $this->wrap($value, 103);
        foreach ($lines as $line) {
            $this->ensure(18);
            $this->text(42, $this->cursor, $line, 8.5, $color);
            $this->cursor -= 12;
        }
        $this->cursor -= 5;
    }

    /** @param array<string, string> $items */
    public function details(array $items): void
    {
        foreach (array_chunk($items, 2, true) as $pair) {
            $this->ensure(38);
            $this->rect(42, $this->cursor - 27, 511, 34, '#F4F7FC');
            $index = 0;
            foreach ($pair as $label => $value) {
                $x = 55 + ($index * 252);
                $this->text($x, $this->cursor - 2, Str::upper($label), 7.5, '#67809F', true);
                $this->text($x, $this->cursor - 18, Str::limit($value, 46), 9, '#173452', true);
                $index++;
            }
            $this->cursor -= 38;
        }
    }

    /** @param array<int, string> $headings @param array<int, float> $widths */
    public function tableHeader(array $headings, array $widths): void
    {
        $this->ensure(53);
        $this->lastTableHeadings = $headings;
        $this->lastTableWidths = $widths;
        $this->rect(42, $this->cursor - 17, 511, 24, '#173452');
        $x = 50;
        foreach ($headings as $index => $heading) {
            $this->text($x, $this->cursor - 9, Str::upper($heading), 7.5, '#FFFFFF', true);
            $x += $widths[$index];
        }
        $this->cursor -= 27;
    }

    /** @param array<int, string> $cells @param array<int, float> $widths */
    public function tableRow(array $cells, array $widths, bool $alternate = false): void
    {
        $wrapped = [];
        $maxLines = 1;
        foreach ($cells as $index => $cell) {
            $lines = $this->wrap($cell, max(6, (int) floor(($widths[$index] - 9) / 4.7)));
            $wrapped[] = $lines;
            $maxLines = max($maxLines, count($lines));
        }
        $height = max(24, $maxLines * 11 + 10);
        $previousPage = count($this->pages);
        $this->ensure($height + 2);
        if (count($this->pages) !== $previousPage && $this->lastTableHeadings !== []) {
            $this->tableHeader($this->lastTableHeadings, $this->lastTableWidths);
        }
        $this->rect(42, $this->cursor - $height + 6, 511, $height, $alternate ? '#F4F7FC' : '#FFFFFF');
        $this->line(42, $this->cursor - $height + 6, 553, $this->cursor - $height + 6, '#E4EAF2');
        $x = 50;
        foreach ($wrapped as $index => $lines) {
            foreach ($lines as $lineIndex => $line) {
                $this->text($x, $this->cursor - 8 - $lineIndex * 11, $line, 8, '#243B53');
            }
            $x += $widths[$index];
        }
        $this->cursor -= $height;
    }

    public function total(string $label, string $amount, bool $prominent = false): void
    {
        $this->ensure($prominent ? 34 : 22);
        if ($prominent) {
            $this->rect(297, $this->cursor - 24, 256, 31, '#E9F2FF');
        }
        $this->text(309, $this->cursor - 9, $label, $prominent ? 10 : 8.5, '#173452', $prominent);
        $this->text(540, $this->cursor - 9, $amount, $prominent ? 11 : 8.5, '#173452', true, true);
        $this->cursor -= $prominent ? 34 : 22;
    }

    public function imageCard(string $title, ?string $bytes, string $caption = '', float $height = 200): void
    {
        $this->ensure($height + 48);
        $this->rect(42, $this->cursor - $height - 23, 511, $height + 39, '#F4F7FC');
        $this->text(54, $this->cursor, Str::upper($title), 9, '#173452', true);
        $image = $bytes ? $this->registerImage($bytes) : null;
        if ($image !== null) {
            $maxWidth = 485;
            $maxHeight = $height - 17;
            $scale = min($maxWidth / $image['width'], $maxHeight / $image['height']);
            $width = $image['width'] * $scale;
            $displayHeight = $image['height'] * $scale;
            $x = 297.5 - ($width / 2);
            $y = $this->cursor - $height - 4 + (($maxHeight - $displayHeight) / 2);
            $this->append(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q'."\n", $width, $displayHeight, $x, $y, $image['name']));
        } else {
            $this->text(54, $this->cursor - 43, 'Image not available', 9, '#708399');
        }
        $this->cursor -= $height + 26;
        if ($caption !== '') {
            $this->note($caption);
        }
    }

    /** @param array<int, array{title: string, bytes: ?string, caption: string}> $cards */
    public function mediaGrid(array $cards, float $height): void
    {
        foreach (array_chunk($cards, 2) as $row) {
            $this->ensure($height + 14);
            foreach ($row as $index => $card) {
                $x = $index === 0 ? 42 : 300;
                $this->rect($x, $this->cursor - $height + 6, 253, $height, '#F3F7FC');
                $this->text($x + 12, $this->cursor - 9, $this->fitted(Str::upper($card['title']), 229, 8), 8, '#173452', true);
                $this->drawImage($card['bytes'], $x + 13, $this->cursor - $height + 31, 227, $height - 55);
                $this->text($x + 12, $this->cursor - $height + 18, $this->fitted($card['caption'], 228, 7.4), 7.4, '#657993');
            }
            $this->cursor -= $height + 12;
        }
    }

    public function output(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $imageNumbers = [];
        foreach ($this->images as $name => $image) {
            $number = count($objects) + 1;
            $imageNumbers[$name] = $number;
            $objects[$number] = '<< /Type /XObject /Subtype /Image /Width '.$image['width'].' /Height '.$image['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($image['data'])." >>\nstream\n".$image['data']."\nendstream";
        }
        $pageNumbers = [];
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => $commands) {
            $stream = gzcompress($commands.$this->footer($index + 1, $pageCount), 6);
            $contentNumber = count($objects) + 1;
            $objects[$contentNumber] = '<< /Length '.strlen($stream).' /Filter /FlateDecode >>'."\nstream\n".$stream."\nendstream";
            $pageNumber = count($objects) + 1;
            $resources = '/Font << /F1 3 0 R /F2 4 0 R >>';
            if ($imageNumbers !== []) {
                $resources .= ' /XObject << ';
                foreach ($imageNumbers as $name => $number) {
                    $resources .= '/'.$name.' '.$number.' 0 R ';
                }
                $resources .= '>>';
            }
            $objects[$pageNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << '.$resources.' >> /Contents '.$contentNumber.' 0 R >>';
            $pageNumbers[] = $pageNumber;
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $number): string => $number.' 0 R', $pageNumbers)).'] /Count '.$pageCount.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($objects as $number => $object) {
            $pdf .= str_pad((string) $offsets[$number], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

    private function footer(int $page, int $total): string
    {
        $number = Str::ascii($this->number);
        $number = str_replace(['\\', '(', ')'], ['', '', ''], $number);

        return "0.84 0.88 0.94 rg 42 55 511 1 re f\n"
            ."BT /F1 8 Tf 0.37 0.44 0.54 rg 42 42 Td (ACServ ERP  |  {$number}) Tj ET\n"
            ."BT /F1 8 Tf 0.37 0.44 0.54 rg 493 42 Td (Page {$page} of {$total}) Tj ET\n";
    }

    private function text(float $x, float $y, string $value, float $size = 10, string $color = '#173452', bool $bold = false, bool $right = false): void
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value) ?? '';
        if ($right) {
            $x -= mb_strwidth($value) * $size * 0.48;
        }
        [$red, $green, $blue] = $this->rgb($color);
        $font = $bold ? 'F2' : 'F1';
        $this->append(sprintf('BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td %s Tj ET'."\n", $font, $size, $red, $green, $blue, $x, $y, $this->escape($value)));
    }

    private function rect(float $x, float $y, float $width, float $height, string $color): void
    {
        [$red, $green, $blue] = $this->rgb($color);
        $this->append(sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f'."\n", $red, $green, $blue, $x, $y, $width, $height));
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color): void
    {
        [$red, $green, $blue] = $this->rgb($color);
        $this->append(sprintf('%.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S'."\n", $red, $green, $blue, $x1, $y1, $x2, $y2));
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255];
    }

    private function escape(string $value): string
    {
        return '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($value)).')';
    }

    private function fitted(string $value, float $width, float $size): string
    {
        $characters = max(5, (int) floor($width / ($size * 0.53)));

        return Str::limit(Str::ascii($value), $characters, '...');
    }

    private function drawImage(?string $bytes, float $x, float $y, float $maxWidth, float $maxHeight): void
    {
        $image = $bytes ? $this->registerImage($bytes) : null;
        if ($image === null) {
            $this->text($x, $y + ($maxHeight / 2), 'Image not available', 8, '#708399');

            return;
        }
        $scale = min($maxWidth / $image['width'], $maxHeight / $image['height']);
        $width = $image['width'] * $scale;
        $height = $image['height'] * $scale;
        $this->append(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q'."\n", $width, $height, $x + (($maxWidth - $width) / 2), $y + (($maxHeight - $height) / 2), $image['name']));
    }

    /** @return array<int, string> */
    private function wrap(string $value, int $characters): array
    {
        $value = trim(preg_replace('/\s+/', ' ', Str::ascii($value)) ?? '');

        return $value === '' ? ['-'] : explode("\n", wordwrap($value, $characters, "\n", true));
    }

    /** @return array{name: string, width: int, height: int}|null */
    private function registerImage(string $bytes): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 1200 / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        ob_start();
        imagejpeg($image, null, 78);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($image);
        if ($jpeg === false) {
            return null;
        }
        $name = 'Im'.(count($this->images) + 1);
        $this->images[$name] = ['data' => $jpeg, 'width' => $width, 'height' => $height];

        return ['name' => $name, 'width' => $width, 'height' => $height];
    }

    private function append(string $command): void
    {
        $index = array_key_last($this->pages);
        $this->pages[$index] .= $command;
    }
}
