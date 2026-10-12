<?php

require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = event_repository_connection();
echo json_encode(event_repository_list_events($pdo, true));
