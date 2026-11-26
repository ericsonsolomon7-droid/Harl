<?php
declare(strict_types=1);

namespace Bookstore\Support;

function setFlashMessage(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error'], true)) {
        throw new \InvalidArgumentException('Unsupported flash message type.');
    }

    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function consumeFlashMessage(): ?array
{
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    if (
        !is_array($message)
        || !isset($message['type'], $message['message'])
        || !in_array($message['type'], ['success', 'error'], true)
        || !is_string($message['message'])
    ) {
        return null;
    }

    return $message;
}
