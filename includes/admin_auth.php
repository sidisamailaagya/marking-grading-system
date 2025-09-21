<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin']) || empty($_SESSION['admin']['admin_id'])) {
    header('Location: ../login.php?err=auth');
    exit;
}
?>