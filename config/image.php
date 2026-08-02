<?php

/**
 * Image processing service configuration.
 *
 * Configures the default driver engine and rendering parameters used by
 * Framework\Core\Image\Image and the image() helper function.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver Engine
    |--------------------------------------------------------------------------
    | Supported options: 'auto', 'imagick', 'gd'.
    | When 'auto' is specified, the service will attempt to utilize the higher
    | performance Imagick extension if installed, with a seamless fallback to GD.
    */
    'driver' => env('IMAGE_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Default Quality
    |--------------------------------------------------------------------------
    | Default compression quality percentage (0 - 100) applied during saving
    | or encoding images when an explicit quality parameter is omitted.
    */
    'quality' => (int) env('IMAGE_QUALITY', 90),

    /*
    |--------------------------------------------------------------------------
    | Default Font Path for Text Overlays
    |--------------------------------------------------------------------------
    | Absolute file path to a standard TrueType Font (.ttf) to be used when
    | annotating images with text overlays without specifying a font.
    */
    'default_font' => env('IMAGE_DEFAULT_FONT', null),

];
