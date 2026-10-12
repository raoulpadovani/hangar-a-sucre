<section class="agenda-section" id="agenda">
    <h2>NOS ÉVÉNEMENTS</h2>

    <div class="events-grid" id="eventsContainer">
        <?php if (empty($events)): ?>
            <div class="events-message events-message--empty">📭 Aucun événement</div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <?php require app_view_path('partials/event-card.php'); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($isAdmin)): ?>
        <div class="agenda-admin-cta">
            <p class="agenda-admin-cta-text">Vous êtes organisateur d'événements?</p>
            <a href="admin.php" class="agenda-admin-cta-link">🔐 Accès Admin</a>
        </div>
    <?php endif; ?>
</section>
