<?php

function app_base_path(string $path = ''): string
{
    return __DIR__ . '/..' . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function app_view_path(string $view): string
{
    return app_base_path('Views/' . ltrim($view, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function safe_http_url(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    $parts = parse_url($value);
    if (
        !is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        return null;
    }

    return $value;
}

// Chemin d'asset restreint aux sous-dossiers autorisés pour éviter la traversée de répertoires
function safe_local_asset_path(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || preg_match('/[\x00-\x1F%]/', $value) === 1) {
        return null;
    }

    $normalizedPath = ltrim(str_replace('\\', '/', $value), '/');
    $segments = explode('/', $normalizedPath);
    $allowedFolders = ['event_uploads', 'image', 'galerie', 'logo'];

    if (
        count($segments) < 2
        || $segments[0] !== 'assets'
        || !in_array($segments[1], $allowedFolders, true)
        || in_array('', $segments, true)
        || in_array('.', $segments, true)
        || in_array('..', $segments, true)
    ) {
        return null;
    }

    return implode('/', $segments);
}

function safe_local_asset_url(mixed $value): ?string
{
    $path = safe_local_asset_path($value);
    if ($path === null) {
        return null;
    }

    return implode('/', array_map(static fn(string $segment): string => rawurlencode($segment), explode('/', $path)));
}

function safe_image_url(mixed $value): ?string
{
    $localUrl = safe_local_asset_url($value);
    if ($localUrl !== null) {
        return $localUrl;
    }

    $externalUrl = safe_http_url($value);
    if ($externalUrl === null || preg_match('/[\'"()\\\\\x00-\x20]/', $externalUrl) === 1) {
        return null;
    }

    return $externalUrl;
}

function render_public_page(string $contentView, array $data = []): void
{
    $viewFile = app_view_path($contentView);

    if (!is_file($viewFile)) {
        throw new RuntimeException('Vue introuvable: ' . $contentView);
    }

    $pageTitle = $data['pageTitle'] ?? 'Hangar à Sucre';
    $bodyClass = $data['bodyClass'] ?? '';
    $styles = $data['styles'] ?? ['style.css'];
    $scripts = $data['scripts'] ?? ['js/index.js'];

    extract($data, EXTR_SKIP);

    require app_view_path('layouts/public.php');
}

function render_admin_page(string $contentView, array $data = []): void
{
    $viewFile = app_view_path($contentView);

    if (!is_file($viewFile)) {
        throw new RuntimeException('Vue introuvable: ' . $contentView);
    }

    $pageTitle = $data['pageTitle'] ?? 'Admin - Hangar à Sucre';
    $bodyClass = $data['bodyClass'] ?? 'admin-page';
    $styles = $data['styles'] ?? ['css/admin.css'];
    $scripts = $data['scripts'] ?? ['js/admin.js'];

    extract($data, EXTR_SKIP);

    require app_view_path('layouts/admin.php');
}
