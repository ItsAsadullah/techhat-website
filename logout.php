<?php
require_once 'core/auth.php';
logout();
header('Location: index.php');
exit;
?>