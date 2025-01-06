<?php
// Get the 'view' and 'login' parameters from the URL
$view = $_GET['view'] ?? 'accounts'; // Default to 'accounts' if no view is specified
$account_id = $_GET['account_id'] ?? null; // Optional parameter for account-specific views

// Route to the appropriate view
switch ($view) {
    case 'openTradeDashboard':
        // Include the openTradeDashboard view only if login is provided
        if ($account_id) {
            include_once './views/openTradeDashboard.php';
        } else {
            echo "Error: account_id parameter is required for the trade dashboard.";
        }
        break;

    case 'accounts':
    default:
        // Default to the accounts view
        include_once './views/accounts.php';
        break;
}
?>


