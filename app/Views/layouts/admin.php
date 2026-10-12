<?php

$styles = $styles ?? ['css/admin.css'];
$scripts = $scripts ?? ['js/admin.js'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Admin - Laplateforme Events'); ?></title>
    <link rel="stylesheet" href="style.css">
    <?php foreach ($styles as $style): ?>
        <link rel="stylesheet" href="<?php echo e($style); ?>">
    <?php endforeach; ?>
</head>
<body class="<?php echo e($bodyClass ?? 'admin-page'); ?>">
    <?php require $viewFile; ?>

    <?php foreach ($scripts as $script): ?>
        <script src="<?php echo e($script); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
