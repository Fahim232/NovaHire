<?php
/**
 * Database Connection Module
 * 
 * Establishes a global MySQLi connection to the database.
 * Prevents multiple redundant database connection attempts if $con is already set.
 */

// Check if a database connection handle ($con) is already active
if (!isset($con)) {
    // Disable mysqli exception throwing so connection errors don't trigger uncaught 500 error
    mysqli_report(MYSQLI_REPORT_OFF);

    // Database credentials configuration
    $host     = '127.0.0.1';
    $user     = 'root';
    $password = '';         // Default XAMPP MySQL password (empty)
    $database = 'projects'; // Target application database name

    // Attempt establishing connection: try port 3307 first (XAMPP configured port), fallback to 3306
    $con = @mysqli_connect($host, $user, $password, $database, 3307);
    if (!$con) {
        $con = @mysqli_connect($host, $user, $password, $database, 3306);
    }
    if (!$con) {
        $con = @mysqli_connect('localhost', $user, $password, $database, 3307);
    }
    if (!$con) {
        $con = @mysqli_connect('localhost', $user, $password, $database, 3306);
    }

    // Validate database connection success
    if (!$con) {
        // Log connection failure to error log for server debugging
        error_log("Database Connection Failure: " . mysqli_connect_error());

        // If request is an AJAX/API JSON call, return structured JSON error payload
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database Connection Failed']);
            exit();
        } else {
            // Render user-friendly alert for standard web browser views
            echo '<script>console.warn("Database Connection Unsuccessful! Please check database server.");</script>';
        }
    } else {
        // Set charset to utf8mb4 for full UTF-8 support (emojis, special characters) and optimized performance
        mysqli_set_charset($con, "utf8mb4");
    }
}
?>