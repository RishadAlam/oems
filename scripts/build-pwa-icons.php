<?php

declare(strict_types=1);

$destination = dirname(__DIR__) . '/public/assets/icons';
if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
    fwrite(STDERR, "Could not create the PWA icon directory.\n");
    exit(1);
}

$roundedRectangle = static function ($image, int $left, int $top, int $right, int $bottom, int $radius, int $color): void {
    imagefilledrectangle($image, $left + $radius, $top, $right - $radius, $bottom, $color);
    imagefilledrectangle($image, $left, $top + $radius, $right, $bottom - $radius, $color);
    imagefilledellipse($image, $left + $radius, $top + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $right - $radius, $top + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $left + $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $right - $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
};

foreach ([192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);
    if ($image === false) {
        fwrite(STDERR, "Could not allocate the PWA icon.\n");
        exit(1);
    }
    imageantialias($image, true);
    $accent = imagecolorallocate($image, 49, 87, 213);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $accent);
    $scale = $size / 34;
    $box = static fn (float $value): int => (int) round($value * $scale);
    $roundedRectangle($image, $box(7), $box(7), $box(16), $box(12), $box(2.5), $white);
    $roundedRectangle($image, $box(22), $box(7), $box(27), $box(16), $box(2.5), $white);
    $roundedRectangle($image, $box(18), $box(22), $box(27), $box(27), $box(2.5), $white);
    $roundedRectangle($image, $box(7), $box(18), $box(12), $box(27), $box(2.5), $white);
    imagefilledellipse($image, $box(17), $box(17), $box(5), $box(5), $white);
    if (!imagepng($image, $destination . '/oems-' . $size . '.png', 9)) {
        fwrite(STDERR, "Could not write the PWA icon.\n");
        exit(1);
    }
    imagedestroy($image);
}

fwrite(STDOUT, "Built deterministic OEMS 192px and 512px PWA icons.\n");
