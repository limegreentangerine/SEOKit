<?php

namespace SeoKit\Support;

final class Files
{
    /**
     * Public URL of a Concrete file entity (or its approved version), or an empty string.
     */
    public static function url(mixed $file): string
    {
        if (is_object($file) && method_exists($file, 'getApprovedVersion')) {
            $file = $file->getApprovedVersion();
        }

        return is_object($file) && method_exists($file, 'getURL') ? (string) $file->getURL() : '';
    }
}
