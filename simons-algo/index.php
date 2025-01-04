<?php
// Get the 'view' and 'login' parameters from the URL
$view = $_GET['view'] ?? 'accounts'; // Default to 'accounts' if no view is specified
$login = $_GET['login'] ?? null; // Optional parameter for account-specific views

// Route to the appropriate view
switch ($view) {
    case 'openTradeDashboard':
        // Include the openTradeDashboard view only if login is provided
        if ($login) {
            include_once './views/openTradeDashboard.php';
        } else {
            echo "Error: Login parameter is required for the trade dashboard.";
        }
        break;

    case 'accounts':
    default:
        // Default to the accounts view
        include_once './views/accounts.php';
        break;
}
?>


