<?php

$styles = $styles ?? ['style.css'];
$scripts = $scripts ?? ['js/index.js'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Hangar à Sucre'); ?></title>
    <?php foreach ($styles as $style): ?>
        <link rel="stylesheet" href="<?php echo e($style); ?>">
    <?php endforeach; ?>
</head>
<body class="<?php echo e($bodyClass ?? ''); ?>">
    <?php require app_view_path('partials/header.php'); ?>

    <?php require $viewFile; ?>

    <?php require app_view_path('partials/contact-modal.php'); ?>
    <?php require app_view_path('partials/footer.php'); ?>

    <?php foreach ($scripts as $script): ?>
        <script src="<?php echo e($script); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
