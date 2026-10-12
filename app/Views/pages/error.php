<main class="error-main">
    <section class="error-content" aria-labelledby="error-title">
        <div class="error-art" role="img" aria-label="Erreur <?php echo e($statusCode); ?> : <?php echo e($errorTitle); ?>">
            <div class="error-art__number" aria-hidden="true"><?php echo e($statusCode); ?></div>
            <p class="error-art__label" aria-hidden="true">ERROR</p>
            <p class="error-art__message" aria-hidden="true"><?php echo e(strtoupper($errorTitle)); ?></p>
            <span class="error-art__rule" aria-hidden="true"></span>
            <nav class="error-actions" aria-label="Navigation">
                <a class="error-button error-button--primary" href="index.php">Retour à l’accueil <span aria-hidden="true">↗</span></a>
                <a class="error-button error-button--secondary" href="agenda.php">Voir l’agenda <span aria-hidden="true">→</span></a>
            </nav>
        </div>
    </section>
</main>
