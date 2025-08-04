<script>
    // Passer les données PHP au JavaScript
    const activities = <?php echo json_encode($enrichedActivities, JSON_UNESCAPED_SLASHES); ?>;
    const photosDir = '<?php echo PHOTOS_DIR; ?>';
    const ACTIVITY_PRICING = <?php echo json_encode($ACTIVITY_PRICING ?? []); ?>;
    const DEFAULT_ACTIVITY_TYPE = '<?php echo DEFAULT_ACTIVITY_TYPE ?? 'PHOTO'; ?>';
    const MAIL_FRONT = <?php echo MAIL_FRONT ? 'true' : 'false'; ?>;

    // Configuration watermark
    const watermarkConfig = {
        enabled: <?php echo $watermarkConfig['WATERMARK_ENABLED'] ? 'true' : 'false'; ?>,
        text: '<?php echo $watermarkConfig['WATERMARK_TEXT']; ?>',
        opacity: <?php echo $watermarkConfig['WATERMARK_OPACITY']; ?>,
        size: '<?php echo $watermarkConfig['WATERMARK_SIZE']; ?>',
        color: '<?php echo $watermarkConfig['WATERMARK_COLOR']; ?>',
        angle: '<?php echo $watermarkConfig['WATERMARK_ANGLE']; ?>'
    };
</script>
<script src="js/print.js"></script>
<script src="js/cloud.js"></script>
<script src="js/lazy_loading.js"></script>
<script src="js/script.js"></script>