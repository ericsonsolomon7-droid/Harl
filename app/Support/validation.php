<?php
declare(strict_types=1);

namespace Bookstore\Support;

function normalizeGhanaPhone(string $phone): ?string
{
    if (!preg_match('/^\+?[0-9\s().-]+$/D', $phone)) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits)) {
        return null;
    }

    if (preg_match('/^0[0-9]{9}$/D', $digits)) {
        return '+233' . substr($digits, 1);
    }
    if (preg_match('/^233[0-9]{9}$/D', $digits)) {
        return '+' . $digits;
    }
    if (preg_match('/^[0-9]{9}$/D', $digits)) {
        return '+233' . $digits;
    }

    return null;
}
