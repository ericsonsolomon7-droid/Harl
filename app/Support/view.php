<?php
declare(strict_types=1);

namespace Bookstore\Support;

function escapeHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function limitText(string $value, int $maxLength): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    if (preg_match('/\A.{0,' . $maxLength . '}/us', $value, $matches) !== 1) {
        return '';
    }

    return $matches[0];
}

function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

    return $characters === false ? 0 : count($characters);
}

function formatGhs(string|int|float $amount): string
{
    return 'GH₵ ' . number_format((float) $amount, 2);
}

function bookCoverPath(mixed $path): ?string
{
    if (!is_string($path) || str_contains($path, '..')) {
        return null;
    }

    $normalized = str_replace('\\', '/', $path);
    if (!preg_match('#\A(?:assets/covers|uploads/books)/[A-Za-z0-9._/-]+\.(?:jpe?g|png|webp)\z#iD', $normalized)) {
        return null;
    }

    return $normalized;
}
