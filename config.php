<?php
/**
 * Backward Compatibility Config Entry Point
 * Redirects to new modular config structure.
 * All existing `require_once "config.php"` calls continue to work.
 */

require_once __DIR__ . "/config/config.php";