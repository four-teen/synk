<?php
session_start();

require_once __DIR__ . '/backend/auth_useraccount.php';

synk_logout_session();

header('Location: index.php');
exit;
