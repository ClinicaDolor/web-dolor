<?php

namespace App\Services;

/** Server-side, opt-in policy for the local-only Vosk microphone diagnostic. */
final class LocalVoiceDiagnosticPolicy
{
    public const ORIGIN = 'https://clinica.test:8443';
    public const WEBSOCKET_URL = 'wss://clinica.test:8767/diagnostic';

    /** Returns runtime paths only when the exact local diagnostic configuration is enabled. */
    public static function enabledRuntime(): ?array
    {
        $profile = getenv('USERPROFILE');
        if (!is_string($profile) || $profile === '' || !is_dir($profile) || is_link($profile)) return null;
        $runtime = rtrim($profile, "\\/") . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local'
            . DIRECTORY_SEPARATOR . 'ClinicaDolor' . DIRECTORY_SEPARATOR . 'vosk-realtime';
        $resolvedRuntime = realpath($runtime);
        if ($resolvedRuntime === false || is_link($runtime)) return null;
        $configPath = $resolvedRuntime . DIRECTORY_SEPARATOR . 'diagnostic.json';
        $keyPath = $resolvedRuntime . DIRECTORY_SEPARATOR . 'ticket.key';
        if (!is_file($configPath) || is_link($configPath) || !is_file($keyPath) || is_link($keyPath) || filesize($keyPath) !== 32) return null;
        $configText = file_get_contents($configPath);
        $config = is_string($configText) ? json_decode($configText, true) : null;
        if (!is_array($config)
            || ($config['version'] ?? null) !== 1
            || ($config['enabled'] ?? null) !== true
            || ($config['mode'] ?? null) !== 'isolated_https'
            || ($config['origin'] ?? null) !== self::ORIGIN) return null;

        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        $serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
        $httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $serverAddress = (string)($_SERVER['SERVER_ADDR'] ?? '');
        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');
        if (!$https || $serverName !== 'clinica.test' || $httpHost !== 'clinica.test:8443' || $serverPort !== '8443'
            || !self::isLoopback($serverAddress) || !self::isLoopback($remoteAddress)) return null;

        return ['root' => $resolvedRuntime, 'ticket_key' => $keyPath, 'origin' => self::ORIGIN];
    }

    public static function validRequestOrigin(array $runtime): bool
    {
        return (($runtime['origin'] ?? null) === self::ORIGIN)
            && (($_SERVER['HTTP_ORIGIN'] ?? '') === self::ORIGIN)
            && (strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'same-origin');
    }

    private static function isLoopback(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
            && in_array($address, ['127.0.0.1', '::1'], true);
    }
}
