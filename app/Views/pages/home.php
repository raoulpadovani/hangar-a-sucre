<section id="accueil" class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-bg"></div>
    <div class="hero-content">
        <p class="hero-tag">VOS ÉVÉNEMENTS, NOTRE PASSION.</p>
        <h1>LE HANGAR <br>A SUCRE</h1>
        <div class="hero-divider"></div>
        <p class="hero-description">Des expériences uniques, des ambiances inoubliables, des souvenirs qui restent.</p>
        <a href="agenda.php" class="btn-primary">DÉCOUVRIR L'AGENDA &rarr;</a>
    </div>
</section>

<section class="derniers-evenements">
    <div class="section-header">
        <h2>DERNIERS ÉVÉNEMENTS</h2>
        <a href="galerie.php" class="view-all">VOIR LA GALERIE</a>
    </div>
    <div class="gallery-grid">
        <img src="assets/image/event (1).png" alt="Event 1">
        <img src="assets/image/event (3).png" alt="Event 2">
        <img src="assets/image/event (2).png" alt="Event 3">
        <img src="assets/image/event (4).png" alt="Event 4">
        <img src="assets/image/event (1).jpg" alt="Event 5">
        <img src="assets/image/event (3).jpg" alt="Event 6">
    </div>
</section>

<section id="agenda" class="agenda">
    <div class="section-header">
        <h2>AGENDA</h2>
        <a href="agenda.php" class="view-all">VOIR TOUS LES ÉVÉNEMENTS</a>
    </div>

    <div class="events-grid" id="eventsContainer">
        <?php if (empty($events)): ?>
            <div class="events-message events-message--empty">Aucun événement</div>
        <?php else: ?>
            <?php foreach (array_slice($events, 0, 3) as $event): ?>
                <?php require app_view_path('partials/event-card.php'); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section id="plaquette" class="telechargements">
    <h2>PLAQUETTE</h2>
    <p class="telechargements-subtitle">Découvrez notre univers et nos prestations en un coup d'œil.</p>

    <div class="plaquette-card">
        <div class="plaquette-icon">📄</div>
        <div class="plaquette-info">
            <h3>Plaquette Hangar à Sucre</h3>
            <p>Présentation complète de nos événements, prestations et références.</p>
        </div>
        <a href="plaquette.pdf?v=<?php echo e(filemtime(dirname(__DIR__, 3) . '/plaquette.pdf')); ?>" class="btn-plaquette" download>
            <span>⬇️</span> TÉLÉCHARGER LA PLAQUETTE
        </a>
    </div>
</section>
