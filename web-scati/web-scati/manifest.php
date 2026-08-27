<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

// Manifest do PWA gerado dinamicamente (não é um .json estático) porque os
// caminhos de start_url/scope/ícones precisam respeitar BASE_URL, que muda
// conforme onde o sistema é instalado (raiz do domínio ou uma subpasta como
// /web-scati) — um manifest.json fixo quebraria num dos dois casos.
header('Content-Type: application/manifest+json');

$manifest = [
    'name' => 'Web SCATI',
    'short_name' => 'SCATI',
    'description' => 'Sistema de Controle de Ativos de TI',
    'start_url' => BASE_URL . '/index.php',
    'scope' => BASE_URL . '/',
    'id' => BASE_URL . '/',
    'display' => 'standalone',
    'background_color' => '#eef1f6',
    'theme_color' => '#1e3a5f',
    'lang' => 'pt-BR',
    'icons' => [
        ['src' => BASE_URL . '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
