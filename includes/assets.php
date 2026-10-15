<?php
declare(strict_types=1);

// Content fingerprints change only when the deployed file changes.
function asset_url(string $path): string
{
    $hash = hash_file('sha256', dirname(__DIR__) . '/' . $path);
    if ($hash === false) {
        throw new RuntimeException('Asset unavailable');
    }
    return htmlspecialchars($path . '?v=' . substr($hash, 0, 16), ENT_QUOTES, 'UTF-8');
}
