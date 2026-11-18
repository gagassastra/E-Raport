<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrService
{
    /** QR Code (SVG vektor, tanpa ekstensi GD) sebagai data URI. */
    public static function svgDataUri(string $text, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
