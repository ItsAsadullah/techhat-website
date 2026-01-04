<?php
/* ===============================
   Application Config
   =============================== */

define('APP_NAME', 'TechHat');
define('BASE_URL', ''); // local & live safe

/* ===============================
   Database Config
   =============================== */

define('DB_HOST', 'localhost');
define('DB_NAME', 'techhat_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_TIMEZONE', '+00:00');

/* ===============================
   Session & Security
   =============================== */

define('SESSION_NAME', 'techhat_session');
define('SESSION_LIFETIME', 7200); // 2 hours
define('CSRF_SESSION_KEY', 'csrf_token');

/* ===============================
   Business Rules
   =============================== */

define('FREE_SHIP_THRESHOLD', 999);
