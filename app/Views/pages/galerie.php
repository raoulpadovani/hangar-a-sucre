<?php

$galleryDirectory = __DIR__ . '/../../../assets/galerie';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$galleryImages = [];

if (is_dir($galleryDirectory)) {
    $files = scandir($galleryDirectory);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $absolutePath = $galleryDirectory . DIRECTORY_SEPARATOR . $file;
        if (!is_file($absolutePath)) {
            continue;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $galleryImages[] = $file;
    }

    sort($galleryImages, SORT_NATURAL | SORT_FLAG_CASE);

    $uniqueImages = [];
    $seenImageHashes = [];
    foreach ($galleryImages as $image) {
        $absolutePath = $galleryDirectory . DIRECTORY_SEPARATOR . $image;
        $imageHash = hash_file('sha256', $absolutePath);

        if ($imageHash === false) {
            error_log('Unable to hash gallery image: ' . $absolutePath);
            $uniqueImages[] = $image;
            continue;
        }

        if (isset($seenImageHashes[$imageHash])) {
            continue;
        }

        $seenImageHashes[$imageHash] = true;
        $uniqueImages[] = $image;
    }

    $galleryImages = $uniqueImages;
}
?>

<main class="galerie-container">
    <div class="gallery-grid">
        <?php foreach ($galleryImages as $image): ?>
            <div class="gallery-item">
                <img src="assets/galerie/<?php echo e(rawurlencode($image)); ?>" alt="Photo événement">
            </div>
        <?php endforeach; ?>
    </div>
</main>
