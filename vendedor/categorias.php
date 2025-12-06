<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

header('Location: ../admin/categorias.php');
exit();
