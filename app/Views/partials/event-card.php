<?php

$dateValue = !empty($event['date']) ? strtotime((string) $event['date']) : false;
$ticketUrl = safe_http_url($event['ticketLink'] ?? null);
$day = $dateValue ? date('d', $dateValue) : '--';
$monthNames = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOU', 'SEP', 'OCT', 'NOV', 'DÉC'];
$month = $dateValue ? $monthNames[(int) date('n', $dateValue) - 1] : 'À VENIR';
$imageStyle = '';

if (!empty($event['image'])) {
    $safeImagePath = safe_image_url($event['image']);
    if ($safeImagePath !== null) {
        $imageStyle = "background-image: url('" . e($safeImagePath) . "')";
    }
}
?>
<div class="event-card">
    <div class="event-image"<?php echo $imageStyle !== '' ? ' style="' . $imageStyle . '"' : ''; ?>>
        <div class="event-date-badge">
            <span class="day"><?php echo e($day); ?></span>
            <span class="month"><?php echo e($month); ?></span>
        </div>
    </div>
    <div class="event-card-content">
        <h3><?php echo e($event['title'] ?? ''); ?></h3>
        <?php if (!empty($event['time'])): ?>
            <p class="event-time">🕐 <?php echo e($event['time']); ?></p>
        <?php endif; ?>
        <?php if (!empty($event['location'])): ?>
            <p class="event-location">📍 <?php echo e($event['location']); ?></p>
        <?php endif; ?>
        <?php if (!empty($event['price'])): ?>
            <p class="event-location">💰 <?php echo e($event['price']); ?></p>
        <?php endif; ?>
        <?php if (!empty($event['description'])): ?>
            <p class="event-description"><?php echo e($event['description']); ?></p>
        <?php endif; ?>
        <div class="event-spacer"></div>
        <?php if ($ticketUrl !== null): ?>
            <a href="<?php echo e($ticketUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn-reserver">🎫 RÉSERVER</a>
        <?php endif; ?>
    </div>
</div>
