<?php

namespace App\Services\Billing;

use Illuminate\Support\Str;

class PdfCanvas
{
    /** @var array<int, string> */
    private array $pages = [];

    /** @var array<string, array{data: string, width: int, height: int}> */
    private array $images = [];

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

    public function section(string $label): void
    {
        $this->ensure(40);
        $this->cursor -= 10;
        $this->rect(42, $this->cursor - 7, 4, 16, '#2B65B1');
        $this->text(55, $this->cursor - 2, Str::upper($label), 10, '#173452', true);
        $this->cursor -= 26;
    }

    public function note(string $value, string $color = '#44546A'): void
    {
        $lines = $this->wrap($value, 103);
        $this->ensure(max(18, count($lines) * 13 + 8));
        foreach ($lines as $line) {
            $this->text(42, $this->cursor, $line, 9, $color);
            $this->cursor -= 13;
        }
        $this->cursor -= 7;
    }

    /** @param array<string, string> $items */
    public function details(array $items): void
    {
        foreach (array_chunk($items, 2, true) as $pair) {
            $this->ensure(44);
            $this->rect(42, $this->cursor - 30, 511, 40, '#F4F7FC');
            $index = 0;
            foreach ($pair as $label => $value) {
                $x = 55 + ($index * 252);
                $this->text($x, $this->cursor - 2, Str::upper($label), 7.5, '#67809F', true);
                $this->text($x, $this->cursor - 19, Str::limit($value, 46), 10, '#173452', true);
                $index++;
            }
            $this->cursor -= 44;
        }
    }

    /** @param array<int, string> $headings @param array<int, float> $widths */
    public function tableHeader(array $headings, array $widths): void
    {
        $this->ensure(29);
        $this->rect(42, $this->cursor - 19, 511, 27, '#173452');
        $x = 50;
        foreach ($headings as $index => $heading) {
            $this->text($x, $this->cursor - 10, Str::upper($heading), 7.5, '#FFFFFF', true);
            $x += $widths[$index];
        }
        $this->cursor -= 30;
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
        $height = max(29, $maxLines * 12 + 12);
        $this->ensure($height + 2);
        $this->rect(42, $this->cursor - $height + 7, 511, $height, $alternate ? '#F4F7FC' : '#FFFFFF');
        $this->line(42, $this->cursor - $height + 7, 553, $this->cursor - $height + 7, '#E4EAF2');
        $x = 50;
        foreach ($wrapped as $index => $lines) {
            foreach ($lines as $lineIndex => $line) {
                $this->text($x, $this->cursor - 9 - $lineIndex * 12, $line, 8.5, '#243B53');
            }
            $x += $widths[$index];
        }
        $this->cursor -= $height;
    }

    public function total(string $label, string $amount, bool $prominent = false): void
    {
        $this->ensure($prominent ? 39 : 26);
        if ($prominent) {
            $this->rect(297, $this->cursor - 27, 256, 35, '#E9F2FF');
        }
        $this->text(309, $this->cursor - 11, $label, $prominent ? 11 : 9, '#173452', $prominent);
        $this->text(540, $this->cursor - 11, $amount, $prominent ? 12 : 9, '#173452', true, true);
        $this->cursor -= $prominent ? 39 : 26;
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
