<?php
require_once __DIR__ . '/_admin.php';
$_SESSION = []; session_destroy();
header('Location: login.php');
