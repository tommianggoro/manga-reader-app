<?php
/**
 * Shortcut Toast Hint
 * Used by: reader.php
 */
?>
<!-- Toast Shortcut Hint -->
<div class="toast shortcut-toast" id="shortcutToast">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <strong class="text-primary"><i class="bi bi-keyboard me-1"></i> Keyboard Shortcuts</strong>
        <button type="button" class="btn-close btn-close-white btn-sm" onclick="document.getElementById('shortcutToast').style.display='none'"></button>
    </div>
    <ul class="list-unstyled mb-0 small text-secondary">
        <li><kbd>&rarr;</kbd> / <kbd>D</kbd> : Next Chapter / Page</li>
        <li><kbd>&larr;</kbd> / <kbd>A</kbd> : Prev Chapter / Page</li>
        <li><kbd>Space</kbd> : Scroll Turun</li>
        <li><kbd>Home</kbd> / <kbd>End</kbd> : Puncak / Dasar Halaman</li>
    </ul>
</div>