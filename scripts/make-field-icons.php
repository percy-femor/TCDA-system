<?php

$source = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'fisc-logo.jpg';
$out = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'icons';
if (! is_dir($out)) {
    mkdir($out, 0755, true);
}

$src = imagecreatefromjpeg($source);
if (! $src) {
    fwrite(STDERR, "Could not read {$source}\n");
    exit(1);
}

$sw = imagesx($src);
$sh = imagesy($src);
$side = min($sw, $sh);
$sx = (int) (($sw - $side) / 2);
$sy = (int) (($sh - $side) / 2);

function logoIcon(GdImage $src, int $sx, int $sy, int $side, int $size, float $padRatio): GdImage
{
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 43, 43, 43);
    imagefilledrectangle($im, 0, 0, $size, $size, $bg);
    $pad = (int) round($size * $padRatio);
    $inner = $size - ($pad * 2);
    imagecopyresampled($im, $src, $pad, $pad, $sx, $sy, $inner, $inner, $side, $side);

    return $im;
}

$jobs = [
    ['field-180.png', 180, 0.0],
    ['field-192.png', 192, 0.0],
    ['field-512.png', 512, 0.0],
    ['field-192-maskable.png', 192, 0.12],
    ['field-512-maskable.png', 512, 0.12],
];

foreach ($jobs as [$name, $size, $pad]) {
    $im = logoIcon($src, $sx, $sy, $side, $size, $pad);
    imagepng($im, $out.DIRECTORY_SEPARATOR.$name);
    imagedestroy($im);
}

imagedestroy($src);
echo "Wrote ".count($jobs)." FISC icons to {$out}\n";
