<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSessao();
$_SESSION = [];
session_destroy();
redirect('/modules/auth/login.php');
