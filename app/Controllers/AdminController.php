<?php

require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Models/EventsRepository.php';

// Hash bcrypt par défaut pour "admin123" ; définir EVENT_ADMIN_PASSWORD_HASH pour le remplacer en production
const ADMIN_DEFAULT_PASSWORD_HASH = '$2y$10$hghI9DO0DGpVzQs1cyfW4eQQygbAcM/lLuikWyO7vphuKvTmfInge';

// getenv() ne voit pas les fastcgi_param Nginx (uniquement $_SERVER) ; on vérifie les trois sources
function admin_getenv(string $name): ?string
{
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }

    return null;
}

function admin_controller(): void
{
    admin_start_secure_session();

    if (!admin_is_ip_allowed()) {
        page_controller_error('403');

        return;
    }

    $envHash = admin_getenv('EVENT_ADMIN_PASSWORD_HASH');
    $adminPasswordHash = $envHash !== null && password_get_info($envHash)['algoName'] !== 'unknown'
        ? $envHash
        : ADMIN_DEFAULT_PASSWORD_HASH;

    $adminSessionHash = hash('sha256', $adminPasswordHash);
    $isAuthenticated = isset($_SESSION['admin_authenticated'], $_SESSION['admin_auth_hash'])
        && $_SESSION['admin_authenticated'] === true
        && is_string($_SESSION['admin_auth_hash'])
        && hash_equals($adminSessionHash, $_SESSION['admin_auth_hash']);
    if (!$isAuthenticated) {
        unset($_SESSION['admin_authenticated'], $_SESSION['admin_auth_hash']);
    }

    $loginError = null;
    $adminNotice = $_SESSION['admin_notice'] ?? null;
    unset($_SESSION['admin_notice']);
    $csrfToken = admin_csrf_token();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
        if (!admin_request_csrf_is_valid()) {
            http_response_code(403);
            echo 'Requête invalide.';

            return;
        }

        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    }

    $pdo = event_repository_connection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $passwordInput = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (!admin_request_csrf_is_valid()) {
            $loginError = 'Session invalide. Recharge la page puis réessaie.';
        } elseif (!admin_begin_login_attempt()) {
            $loginError = 'Trop de tentatives. Réessaie dans 15 minutes.';
        } elseif (password_verify($passwordInput, $adminPasswordHash)) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_auth_hash'] = $adminSessionHash;
            $isAuthenticated = true;
            admin_clear_failed_login_attempts();
        } else {
            $loginError = 'Mot de passe incorrect';
        }
    }

    if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_event') {
        admin_handle_save_event($pdo);
    }

    if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
        admin_handle_delete_event($pdo);
    }

    $editingEvent = null;
    if ($isAuthenticated && isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId === false) {
            page_controller_error('404');

            return;
        }

        $editingEvent = event_repository_get_event($pdo, $editId);
        if ($editingEvent === null) {
            page_controller_error('404');

            return;
        }
    }

    if (!$isAuthenticated) {
        render_admin_page('admin/login.php', [
            'loginError' => $loginError,
            'csrfToken' => $csrfToken,
        ]);

        return;
    }

    $events = event_repository_list_events($pdo, false);
    $requestedWeek = is_string($_GET['week'] ?? null) ? $_GET['week'] : date('Y-m-d');
    $calendarData = admin_calendar_data($events, $requestedWeek);

    render_admin_page('admin/dashboard.php', [
        'events' => $events,
        'editingEvent' => $editingEvent,
        'adminNotice' => $adminNotice,
        'csrfToken' => $csrfToken,
        ...$calendarData,
    ]);
}

function admin_request_csrf_is_valid(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && admin_verify_csrf($token);
}

function admin_handle_save_event(PDO $pdo): void
{
    if (!admin_request_csrf_is_valid()) {
        http_response_code(403);
        echo 'Requête invalide.';
        exit;
    }

    $eventIdInput = $_POST['event_id'] ?? '';
    $eventId = null;
    if ($eventIdInput !== '') {
        $eventId = filter_var($eventIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false) {
            http_response_code(422);
            echo 'Identifiant d’événement invalide.';
            exit;
        }
    }

    $eventData = admin_validate_event_input($_POST);
    if ($eventData === null) {
        $_SESSION['admin_notice'] = 'Les données de l’événement sont invalides. Vérifie les champs et réessaie.';
        header('Location: admin.php' . ($eventId !== null ? '?edit=' . $eventId : ''));
        exit;
    }

    if ($eventData['imageUrl'] !== '') {
        $eventData['image'] = $eventData['imageUrl'];
    }
    unset($eventData['imageUrl']);

    $saveError = null;
    if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploaded = admin_handle_upload($_FILES['image'], $saveError);
        if ($uploaded !== null) {
            $eventData['image'] = $uploaded;
        }
    }

    if ($saveError !== null) {
        $_SESSION['admin_notice'] = $saveError;
        header('Location: admin.php' . ($eventId !== null ? '?edit=' . $eventId : ''));
        exit;
    }

    event_repository_save_event($pdo, $eventData, $eventId);

    $returnWeek = is_string($_POST['return_week'] ?? null) ? $_POST['return_week'] : '';
    $returnDate = DateTimeImmutable::createFromFormat('!Y-m-d', $returnWeek);
    if (($_POST['return_to'] ?? '') === 'admin_calendar' && $returnDate && $returnDate->format('Y-m-d') === $returnWeek) {
        header('Location: admin.php?week=' . rawurlencode($returnWeek));
        exit;
    }

    header('Location: admin.php');
    exit;
}

function admin_handle_delete_event(PDO $pdo): void
{
    if (!admin_request_csrf_is_valid()) {
        http_response_code(403);
        echo 'Requête invalide.';
        exit;
    }

    $deleteId = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($deleteId === false) {
        http_response_code(422);
        echo 'Identifiant d’événement invalide.';
        exit;
    }

    event_repository_delete_event($pdo, $deleteId);
    header('Location: admin.php');
    exit;
}

// Upload restreint aux formats image courants, nom de fichier régénéré pour éviter l'injection de chemin
function admin_handle_upload(array $imageUpload, ?string &$saveError): ?string
{
    $fileError = (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileError !== UPLOAD_ERR_OK) {
        $saveError = admin_upload_error_message($fileError);

        return null;
    }

    $uploadDirPublic = 'assets/event_uploads/';
    $uploadDirFs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'event_uploads' . DIRECTORY_SEPARATOR;
    $temporaryPath = (string) ($imageUpload['tmp_name'] ?? '');
    $uploadSize = (int) ($imageUpload['size'] ?? 0);

    if ($uploadSize <= 0 || $uploadSize > 100 * 1024 * 1024) {
        $saveError = 'L’image dépasse la taille maximale de 100 Mo.';

        return null;
    }

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        $saveError = 'Le fichier envoyé n’est pas un téléversement valide.';

        return null;
    }

    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
        $saveError = 'Impossible de préparer le dossier des images.';

        return null;
    }

    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo((string) ($imageUpload['name'] ?? ''), PATHINFO_EXTENSION));
    $mimeType = '';

    if (function_exists('finfo_open')) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo !== false) {
            $mimeType = finfo_file($fileInfo, $temporaryPath) ?: '';
            finfo_close($fileInfo);
        }
    }

    if (!isset($allowedMimeTypes[$extension]) || $allowedMimeTypes[$extension] !== $mimeType) {
        $saveError = 'Le fichier sélectionné n’est pas une image valide.';

        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($temporaryPath, $uploadDirFs . $filename)) {
        $saveError = 'Échec du téléversement de l’image.';

        return null;
    }

    return $uploadDirPublic . $filename;
}

function admin_validate_event_input(array $input): ?array
{
    $limits = [
        'title' => 255,
        'date' => 10,
        'time' => 20,
        'location' => 255,
        'description' => 10000,
        'ticketLink' => 2048,
        'price' => 100,
        'imageUrl' => 1024,
    ];
    $eventData = [];

    foreach ($limits as $field => $maximumLength) {
        $value = $input[$field] ?? '';
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (mb_strlen($value, 'UTF-8') > $maximumLength) {
            return null;
        }

        $eventData[$field] = $value;
    }

    if ($eventData['title'] === '' || $eventData['date'] === '') {
        return null;
    }

    $eventDate = DateTimeImmutable::createFromFormat('!Y-m-d', $eventData['date']);
    if (!$eventDate || $eventDate->format('Y-m-d') !== $eventData['date']) {
        return null;
    }

    if ($eventData['time'] !== '' && preg_match('/^((?:[01]\d|2[0-3]):[0-5]\d)(?: - ((?:[01]\d|2[0-3]):[0-5]\d))?$/D', $eventData['time'], $timeParts) !== 1) {
        return null;
    }

    if ($eventData['ticketLink'] !== '' && safe_http_url($eventData['ticketLink']) === null) {
        return null;
    }

    if ($eventData['imageUrl'] !== '' && (safe_http_url($eventData['imageUrl']) === null || safe_image_url($eventData['imageUrl']) === null)) {
        return null;
    }

    return $eventData;
}

function admin_calendar_data(array $events, string $requestedWeek): array
{
    $requestedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedWeek);
    $weekStart = $requestedDate && $requestedDate->format('Y-m-d') === $requestedWeek
        ? $requestedDate->modify('monday this week')
        : new DateTimeImmutable('monday this week');
    $weekEnd = $weekStart->modify('+6 days');
    $hourStart = 8;
    $hourEnd = 22;
    $hourHeight = 56;
    $calendarEvents = [];

    foreach ($events as $event) {
        $eventDate = (string) ($event['date'] ?? '');
        if ($eventDate < $weekStart->format('Y-m-d') || $eventDate > $weekEnd->format('Y-m-d')) {
            continue;
        }

        preg_match_all('/(?<!\d)([01]?\d|2[0-3]):([0-5]\d)(?!\d)/', (string) ($event['time'] ?? ''), $timeMatches, PREG_SET_ORDER);
        $isAllDay = $timeMatches === [];
        if ($isAllDay) {
            $startMinutes = $hourStart * 60;
            $endMinutes = $startMinutes + 60;
        } else {
            $startMinutes = ((int) $timeMatches[0][1] * 60) + (int) $timeMatches[0][2];
            $endMinutes = isset($timeMatches[1])
                ? ((int) $timeMatches[1][1] * 60) + (int) $timeMatches[1][2]
                : $startMinutes + 60;
            if ($endMinutes <= $startMinutes) {
                $endMinutes = $startMinutes + 60;
            }
        }

        $gridStart = $hourStart * 60;
        $gridEnd = ($hourEnd + 1) * 60;
        if ($startMinutes < $gridStart || $startMinutes >= $gridEnd) {
            $isAllDay = true;
        }

        $visibleStart = max($startMinutes, $gridStart);
        $visibleEnd = min($endMinutes, $gridEnd);
        $top = (($visibleStart - $gridStart) / 60) * $hourHeight;
        $height = max(24, ((max($visibleStart + 30, $visibleEnd) - $visibleStart) / 60) * $hourHeight - 4);
        $calendarEvents[$eventDate][] = [
            ...$event,
            'allDay' => $isAllDay,
            'top' => $top,
            'height' => $height,
        ];
    }

    $weekDays = [];
    for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
        $weekDays[] = $weekStart->modify('+' . $dayOffset . ' days');
    }

    return [
        'weekStart' => $weekStart,
        'weekDays' => $weekDays,
        'calendarEvents' => $calendarEvents,
        'weekPrevious' => $weekStart->modify('-7 days')->format('Y-m-d'),
        'weekNext' => $weekStart->modify('+7 days')->format('Y-m-d'),
        'hourStart' => $hourStart,
        'hourEnd' => $hourEnd,
        'hourHeight' => $hourHeight,
    ];
}

function admin_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux pour la configuration serveur.',
        UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été envoyé que partiellement. Réessaie.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas écrire le fichier sur le disque.',
        UPLOAD_ERR_EXTENSION => 'Une extension PHP a bloqué l\'upload du fichier.',
        default => 'Erreur lors de l\'envoi du fichier.',
    };
}

function admin_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    $isHttps = admin_is_https();
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

function admin_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf(string $token): bool
{
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';

    return is_string($sessionToken) && $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}

// Par défaut, seul localhost peut accéder à l'admin ; définir EVENT_ADMIN_ALLOWED_IPS pour ouvrir à d'autres IP
function admin_is_ip_allowed(): bool
{
    $allowedIpsRaw = trim((string) (admin_getenv('EVENT_ADMIN_ALLOWED_IPS') ?? ''));
    $clientIp = admin_client_ip();

    if ($allowedIpsRaw === '') {
        return in_array($clientIp, ['127.0.0.1', '::1'], true);
    }

    if ($clientIp === '') {
        return false;
    }

    $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsRaw)));

    return in_array($clientIp, $allowedIps, true);
}

function admin_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function admin_begin_login_attempt(): bool
{
    $clientIp = admin_client_ip();
    if ($clientIp === '') {
        return false;
    }

    $result = admin_update_login_attempt_state(static function (array &$state) use ($clientIp): bool {
        $now = time();
        $windowSeconds = 15 * 60;
        $entry = $state[$clientIp] ?? [
            'count' => 0,
            'first_attempt_at' => $now,
            'blocked_until' => 0,
        ];

        if ((int) ($entry['blocked_until'] ?? 0) > $now) {
            return false;
        }

        if (($now - (int) ($entry['first_attempt_at'] ?? $now)) > $windowSeconds) {
            $entry['count'] = 0;
            $entry['first_attempt_at'] = $now;
            $entry['blocked_until'] = 0;
        }

        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        if ($entry['count'] >= 5) {
            $entry['blocked_until'] = $now + $windowSeconds;
        }

        $state[$clientIp] = $entry;

        return true;
    });

    // Erreur de stockage (permissions/verrou) : on n'assimile pas ça à un rate-limit et on laisse passer
    if ($result === null) {
        error_log('admin_begin_login_attempt: échec de lecture/écriture de ' . admin_login_attempt_state_file());

        return true;
    }

    return $result === true;
}

function admin_clear_failed_login_attempts(): void
{
    $clientIp = admin_client_ip();
    if ($clientIp === '') {
        return;
    }

    admin_update_login_attempt_state(static function (array &$state) use ($clientIp): bool {
        unset($state[$clientIp]);

        return true;
    });
}

function admin_login_attempt_state_file(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin_login_attempts.json';
}

function admin_update_login_attempt_state(callable $update): mixed
{
    $path = admin_login_attempt_state_file();
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        return null;
    }

    $file = @fopen($path, 'c+');
    if ($file === false) {
        return null;
    }

    if (!flock($file, LOCK_EX)) {
        fclose($file);

        return null;
    }

    try {
        rewind($file);
        $contents = stream_get_contents($file);
        $decoded = is_string($contents) && $contents !== '' ? json_decode($contents, true) : [];
        $state = is_array($decoded) ? $decoded : [];
        $result = $update($state);
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!is_string($json) || !ftruncate($file, 0)) {
            return null;
        }

        rewind($file);
        if (fwrite($file, $json) === false || !fflush($file)) {
            return null;
        }

        return $result;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
