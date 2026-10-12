<?php

function page_controller_home(): void
{
    $pdo = event_repository_connection();
    $events = event_repository_list_events($pdo, true);

    render_public_page('pages/home.php', [
        'pageTitle' => 'Hangar à Sucre',
        'bodyClass' => 'home-page',
        'events' => $events,
    ]);
}

function page_controller_agenda(): void
{
    admin_start_secure_session();
    $pdo = event_repository_connection();
    $events = event_repository_list_events($pdo, true);

    render_public_page('pages/agenda.php', [
        'pageTitle' => 'Hangar à Sucre',
        'bodyClass' => 'agenda-page',
        'events' => $events,
        'isAdmin' => !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true,
        'styles' => ['style.css', 'css/agenda.css'],
    ]);
}

function page_controller_gallery(): void
{
    render_public_page('pages/galerie.php', [
        'pageTitle' => 'Hangar à Sucre',
        'bodyClass' => 'gallery-page',
    ]);
}

function page_controller_legal_notice(): void
{
    render_public_page('pages/mentions-legales.php', [
        'pageTitle' => 'Mentions légales - Hangar à Sucre',
        'bodyClass' => 'legal-page',
        'styles' => ['style.css', 'css/legal.css?v=2'],
    ]);
}

function page_controller_privacy(): void
{
    render_public_page('pages/confidentialite.php', [
        'pageTitle' => 'Confidentialité - Hangar à Sucre',
        'bodyClass' => 'legal-page',
        'styles' => ['style.css', 'css/legal.css?v=2'],
    ]);
}

function page_controller_error(string $statusCode = '404'): void
{
    $errors = [
        '403' => [
            'title' => 'Accès réservé',
            'message' => 'Cette partie du site est réservée à notre équipe.',
        ],
        '404' => [
            'title' => 'Page introuvable',
            'message' => 'La page que vous recherchez n’existe pas ou a été déplacée.',
        ],
    ];
    $statusCode = isset($errors[$statusCode]) ? $statusCode : '404';

    http_response_code((int) $statusCode);
    render_public_page('pages/error.php', [
        'pageTitle' => $errors[$statusCode]['title'] . ' - Hangar à Sucre',
        'bodyClass' => 'error-page',
        'statusCode' => $statusCode,
        'errorTitle' => $errors[$statusCode]['title'],
        'errorMessage' => $errors[$statusCode]['message'],
        'styles' => ['style.css', 'css/error.css'],
    ]);
}
