<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Concrete's global helpers, stubbed so classes that translate or escape can be tested without a site.
if (!function_exists('t')) {
    function t(string $message, mixed ...$args): string
    {
        return $args === [] ? $message : vsprintf($message, $args);
    }
}

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
