<?php
$weekdayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$monthNames = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$weekEnd = $weekDays[6];
$weekLabel = $weekStart->format('j') . ' ' . $monthNames[(int) $weekStart->format('n') - 1];
if ($weekStart->format('n Y') !== $weekEnd->format('n Y')) {
    $weekLabel .= ' - ' . $weekEnd->format('j') . ' ' . $monthNames[(int) $weekEnd->format('n') - 1];
} else {
    $weekLabel .= ' - ' . $weekEnd->format('j');
}

$eventsByDate = [];
foreach ($events as $event) {
    $dateKey = (string) ($event['date'] ?? '');
    if ($dateKey === '') {
        $dateKey = '9999-12-31';
    }

    $eventsByDate[$dateKey][] = $event;
}
ksort($eventsByDate);

$editingImagePath = '';
$editingImageUrl = $editingEvent ? safe_image_url($editingEvent['image'] ?? null) : null;
if ($editingEvent && !empty($editingEvent['image'])) {
    $editingAssetPath = safe_local_asset_path($editingEvent['image']);
    if ($editingAssetPath !== null) {
        $editingImagePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $editingAssetPath);
    }
}
?>

<div class="admin-shell">
    <header class="admin-topbar">
        <div>
            <p class="admin-kicker">Tableau de bord</p>
            <h1>HANGAR À SUCRE</h1>
            <p class="admin-subtitle">Gestion des événements en mode planning</p>
        </div>
        <div class="topbar-actions">
            <span class="stat-pill"><?php echo count($events); ?> événement(s)</span>
            <form method="POST" class="logout-form">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken ?? ''); ?>">
                <button type="submit" class="btn-logout">Déconnexion</button>
            </form>
        </div>
    </header>

    <section class="admin-calendar-panel" aria-labelledby="adminCalendarTitle">
        <?php if (!empty($adminNotice)): ?>
            <div class="error-msg"><?php echo e($adminNotice); ?></div>
        <?php endif; ?>
        <header class="admin-calendar-toolbar">
            <div>
                <p class="admin-calendar-kicker">PLANNING HEBDOMADAIRE</p>
                <h2 id="adminCalendarTitle">Calendrier</h2>
                <p class="admin-calendar-range"><?php echo e($weekLabel); ?></p>
            </div>
            <div class="admin-calendar-actions">
                <nav class="admin-calendar-nav" aria-label="Navigation par semaine">
                    <a href="admin.php?week=<?php echo e($weekPrevious); ?>" aria-label="Semaine précédente" title="Semaine précédente">‹</a>
                    <a href="admin.php?week=<?php echo e(date('Y-m-d')); ?>">Aujourd'hui</a>
                    <a href="admin.php?week=<?php echo e($weekNext); ?>" aria-label="Semaine suivante" title="Semaine suivante">›</a>
                </nav>
                <button class="admin-calendar-create" type="button" data-calendar-create data-date="<?php echo e($weekStart->format('Y-m-d')); ?>" data-time="09:00">+ Nouvel événement</button>
            </div>
        </header>

        <div class="admin-calendar-scroll" tabindex="0" aria-label="Planning hebdomadaire, défilement horizontal sur petit écran">
            <div class="admin-calendar-grid" style="--calendar-hour-height: <?php echo (int) $hourHeight; ?>px;">
                <div class="admin-calendar-heading-row">
                    <div class="admin-calendar-corner" aria-hidden="true"></div>
                    <?php foreach ($weekDays as $day): ?>
                        <?php $dayDate = $day->format('Y-m-d'); ?>
                        <div class="admin-calendar-day-heading<?php echo $dayDate === date('Y-m-d') ? ' is-today' : ''; ?>">
                            <span><?php echo e($weekdayNames[(int) $day->format('N') - 1]); ?></span>
                            <strong><?php echo e($day->format('j')); ?></strong>
                            <small><?php echo e($monthNames[(int) $day->format('n') - 1]); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="admin-calendar-allday-row">
                    <div class="admin-calendar-allday-label">Journée</div>
                    <?php foreach ($weekDays as $day): ?>
                        <?php $dayDate = $day->format('Y-m-d'); ?>
                        <div class="admin-calendar-allday-cell">
                            <?php foreach ($calendarEvents[$dayDate] ?? [] as $event): ?>
                                <?php if (!empty($event['allDay'])): ?>
                                    <a class="admin-calendar-all-day-event" href="admin.php?edit=<?php echo e($event['id']); ?>">
                                        <strong><?php echo e($event['title']); ?></strong>
                                        <?php if ($event['location'] !== ''): ?><span><?php echo e($event['location']); ?></span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <button class="admin-calendar-all-day-slot" type="button" data-calendar-create data-date="<?php echo e($dayDate); ?>" data-time="09:00" aria-label="Créer un événement le <?php echo e($weekdayNames[(int) $day->format('N') - 1] . ' ' . $day->format('j') . ' ' . $monthNames[(int) $day->format('n') - 1]); ?>"></button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="admin-calendar-body">
                    <div class="admin-calendar-time-axis" aria-hidden="true">
                        <?php for ($hour = $hourStart; $hour <= $hourEnd; $hour++): ?>
                            <div class="admin-calendar-hour-label"><?php echo sprintf('%02d:00', $hour); ?></div>
                        <?php endfor; ?>
                    </div>
                    <?php foreach ($weekDays as $day): ?>
                        <?php $dayDate = $day->format('Y-m-d'); ?>
                        <div class="admin-calendar-day-column<?php echo $dayDate === date('Y-m-d') ? ' is-today' : ''; ?>">
                            <?php for ($hour = $hourStart; $hour <= $hourEnd; $hour++): ?>
                                <button class="admin-calendar-hour-slot" type="button" style="--calendar-slot-index: <?php echo $hour - $hourStart; ?>;" data-calendar-create data-date="<?php echo e($dayDate); ?>" data-time="<?php echo sprintf('%02d:00', $hour); ?>" aria-label="Créer un événement le <?php echo e($weekdayNames[(int) $day->format('N') - 1] . ' ' . $day->format('j') . ' à ' . sprintf('%02d:00', $hour)); ?>"><span>+</span></button>
                            <?php endfor; ?>
                            <?php foreach ($calendarEvents[$dayDate] ?? [] as $event): ?>
                                <?php if (empty($event['allDay'])): ?>
                                    <a class="admin-calendar-event admin-calendar-event--<?php echo (int) $event['id'] % 5; ?>" href="admin.php?edit=<?php echo e($event['id']); ?>" style="--calendar-event-top: <?php echo e((string) $event['top']); ?>px; --calendar-event-height: <?php echo e((string) $event['height']); ?>px;">
                                        <?php if ($event['time'] !== ''): ?><time><?php echo e($event['time']); ?></time><?php endif; ?>
                                        <strong><?php echo e($event['title']); ?></strong>
                                        <?php if ($event['location'] !== ''): ?><span><?php echo e($event['location']); ?></span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <dialog class="admin-calendar-dialog" id="calendarEventDialog" aria-labelledby="calendarDialogTitle">
        <div class="admin-calendar-dialog-panel">
            <button class="admin-calendar-dialog-close" type="button" data-calendar-close aria-label="Fermer">&times;</button>
            <p class="admin-calendar-kicker">NOUVELLE ENTRÉE</p>
            <h2 id="calendarDialogTitle">Créer un événement</h2>
            <form action="admin.php" method="post" enctype="multipart/form-data" id="calendarEventForm" class="admin-calendar-event-form">
                <input type="hidden" name="action" value="save_event">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="return_to" value="admin_calendar">
                <input type="hidden" name="return_week" value="<?php echo e($weekStart->format('Y-m-d')); ?>">
                <input type="hidden" name="time" id="calendarStoredTime">

                <label class="admin-calendar-field admin-calendar-field--wide">
                    <span>Nom de l'événement</span>
                    <input name="title" type="text" maxlength="255" required placeholder="Ex. Concert du vendredi">
                </label>
                <div class="admin-calendar-form-row">
                    <label class="admin-calendar-field">
                        <span>Date</span>
                        <input name="date" id="calendarEventDate" type="date" required>
                    </label>
                    <label class="admin-calendar-field">
                        <span>Début</span>
                        <input id="calendarEventStart" type="time" value="09:00" required>
                    </label>
                    <label class="admin-calendar-field">
                        <span>Fin</span>
                        <input id="calendarEventEnd" type="time" value="10:00" required>
                    </label>
                </div>
                <div class="admin-calendar-form-row">
                    <label class="admin-calendar-field">
                        <span>Lieu</span>
                        <input name="location" type="text" maxlength="255" placeholder="Adresse ou salle">
                    </label>
                    <label class="admin-calendar-field">
                        <span>Prix</span>
                        <input name="price" type="text" maxlength="100" placeholder="Gratuit ou tarif">
                    </label>
                </div>
                <label class="admin-calendar-field admin-calendar-field--wide">
                    <span>Description</span>
                    <textarea name="description" rows="3" required placeholder="Quelques détails sur l'événement"></textarea>
                </label>
                <label class="admin-calendar-field admin-calendar-field--wide">
                    <span>Lien billetterie <small>(facultatif)</small></span>
                    <input name="ticketLink" type="url" maxlength="2048" placeholder="https://">
                </label>
                <label class="admin-calendar-field admin-calendar-field--wide">
                    <span>Adresse de l'image <small>(facultative)</small></span>
                    <input name="imageUrl" type="url" maxlength="1024" placeholder="https://">
                </label>
                <label class="admin-calendar-field admin-calendar-field--wide">
                    <span>Image <small>(facultative)</small></span>
                    <input name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                </label>
                <div class="admin-calendar-form-actions">
                    <button class="admin-calendar-cancel" type="button" data-calendar-close>Annuler</button>
                    <button class="admin-calendar-submit" type="submit">Créer l'événement</button>
                </div>
            </form>
        </div>
    </dialog>

    <div class="admin-workspace<?php echo $editingEvent ? '' : ' admin-workspace--events-only'; ?>">
        <section class="planner-panel">
            <div class="planner-header">
                <h2>Planning des événements</h2>
            </div>

            <?php if (empty($eventsByDate)): ?>
                <p class="empty-state">Aucun événement pour le moment.</p>
            <?php else: ?>
                <div class="planner-grid">
                    <?php foreach ($eventsByDate as $dateKey => $dayEvents): ?>
                        <?php
                        $dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
                        $dateLabel = $dateKey === '9999-12-31' ? 'Sans date' : date('d M Y', strtotime($dateKey));
                        $dayName = $dateKey === '9999-12-31' ? 'À planifier' : ($dayNames[(int) date('N', strtotime($dateKey))] ?? 'Jour');
                        ?>
                        <article class="day-column">
                            <header class="day-column-header">
                                <p class="day-name"><?php echo e($dayName); ?></p>
                                <p class="day-date"><?php echo e($dateLabel); ?></p>
                            </header>

                            <div class="day-events">
                                <?php foreach ($dayEvents as $event): ?>
                                    <?php
                                    $assetPath = safe_local_asset_path($event['image'] ?? null);
                                    $imagePath = $assetPath !== null
                                        ? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath)
                                        : '';
                                    $safeCoverPath = safe_image_url($event['image'] ?? null);
                                    $hasImage = $safeCoverPath !== null && (safe_http_url($event['image'] ?? null) !== null || ($assetPath !== null && is_file($imagePath)));
                                    ?>
                                    <div class="schedule-card">
                                        <div class="schedule-cover"<?php echo $hasImage && $safeCoverPath !== null ? ' style="background-image:url(\'' . e($safeCoverPath) . '\')"' : ''; ?>></div>
                                        <div class="schedule-body">
                                            <h3><?php echo e($event['title']); ?></h3>
                                            <?php if (!empty($event['time'])): ?>
                                                <p>🕐 <?php echo e($event['time']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($event['location'])): ?>
                                                <p>📍 <?php echo e($event['location']); ?></p>
                                            <?php endif; ?>
                                            <div class="event-actions">
                                                <a href="admin.php?edit=<?php echo e($event['id']); ?>" class="btn-edit">Éditer</a>
                                                <form method="POST" class="delete-form" data-confirm-delete>
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?php echo e($event['id']); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken ?? ''); ?>">
                                                    <button type="submit" class="btn-delete">Suppr.</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($editingEvent): ?>
        <aside class="editor-panel">
            <div class="form-box">
                <h2><?php echo $editingEvent ? 'Modifier événement' : 'Créer événement'; ?></h2>
                <?php if (!empty($adminNotice)): ?>
                    <div class="error-msg"><?php echo e($adminNotice); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_event">
                    <input type="hidden" name="event_id" value="<?php echo e($editingEvent['id'] ?? ''); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken ?? ''); ?>">

                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="title" required value="<?php echo e($editingEvent['title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" required value="<?php echo e($editingEvent['date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Heure</label>
                        <input type="text" name="time" placeholder="09:00 - 10:00" value="<?php echo e($editingEvent['time'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Lieu</label>
                        <input type="text" name="location" value="<?php echo e($editingEvent['location'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Prix</label>
                        <input type="text" name="price" value="<?php echo e($editingEvent['price'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"><?php echo e($editingEvent['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Lien billetterie</label>
                        <input type="url" name="ticketLink" value="<?php echo e($editingEvent['ticketLink'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Photo</label>
                        <input type="url" name="imageUrl" maxlength="1024" placeholder="Adresse de l'image (https://)" value="<?php echo e(safe_http_url($editingEvent['image'] ?? null) !== null ? $editingEvent['image'] : ''); ?>">
                        <label class="file-label">
                            Ajouter une image
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input" data-image-preview>
                        </label>
                        <div id="imagePreview">
                            <?php if ($editingImageUrl !== null && (safe_http_url($editingEvent['image'] ?? null) !== null || is_file($editingImagePath))): ?>
                                <img src="<?php echo e($editingImageUrl); ?>" class="image-preview">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit"><?php echo $editingEvent ? 'Mettre à jour' : 'Créer'; ?></button>
                        <?php if ($editingEvent): ?>
                            <a href="admin.php" class="btn-back">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </aside>
        <?php endif; ?>
    </div>
</div>
