<?php
require_once __DIR__ . '/config.php';
session_destroy();
redirect(BASE_URL . '/index.php');
