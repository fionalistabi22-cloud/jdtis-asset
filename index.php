<?php
// JTDIS Asset Management System - Main Index

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header("Location: pages/dashboard.php");
    exit;
} else {
    // Redirect to login
    header("Location: pages/login.php");
    exit;
}
?>