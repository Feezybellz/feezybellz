<?php

namespace Framework\Core\Support;

class Str
{
    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', ucwords($value));
        return strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
    }

    /**
     * Convert a string to camelCase.
     */
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        $title = preg_replace('!['.preg_quote($separator).']+!u', '', $title);
        $title = preg_replace('![^-\pL\pN\s]+!u', '', strtolower($title));
        $title = preg_replace('![-\s]+!u', $separator, $title);
        return trim($title, $separator);
    }

    /**
     * Generate a UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function random(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }

    
}