<?php
/**
 * Application Constants
 * Centralized configuration constants loaded from .env
 */

require_once __DIR__ . "/../env_loader.php";
loadEnv(__DIR__ . "/../.env");

// Database
define("DB_HOST", env("DB_HOST", "localhost"));
define("DB_NAME", env("DB_NAME", "manga_reader"));
define("DB_USER", env("DB_USER", "root"));
define("DB_PASS", env("DB_PASS", ""));

// Auth & Security
define("MAX_LOGIN_ATTEMPTS", (int) env("MAX_LOGIN_ATTEMPTS", 5));
define("LOGIN_LOCKOUT_MINUTES", (int) env("LOGIN_LOCKOUT_MINUTES", 15));
define("CRON_SECRET_KEY", env("CRON_SECRET_KEY", ""));

// Registration Rate Limiting
define("MAX_REGISTRATIONS_PER_WINDOW", (int) env("MAX_REGISTRATIONS_PER_WINDOW", 3));
define("REGISTRATION_WINDOW_MINUTES", (int) env("REGISTRATION_WINDOW_MINUTES", 60));

// Avatar Upload
define("AVATAR_UPLOAD_DIR", __DIR__ . "/../assets/avatars");
define("AVATAR_MAX_BYTES", 2 * 1024 * 1024); // 2MB