<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='admin'){
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';
?>
