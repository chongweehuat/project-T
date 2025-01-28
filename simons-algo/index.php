<?php
// Get the 'view' and 'account_id' parameters from the URL
$view = $_GET['view'] ?? 'accounts'; // Default to 'accounts' if no view is specified
$account_id = $_GET['account_id'] ?? null; // Optional parameter for account-specific views
$group_id = $_GET['group_id'] ?? null; // Optional parameter for group-specific views

// Route to the appropriate view
switch ($view) {
    case 'tradeManager':
        if ($account_id) {
            include_once './views/tradeManager.php';
        } else {
            echo "Error: account_id parameter is required for the trade dashboard.";
        }
        break;

    case 'openTradesListing':
        if ($group_id) {
            include_once './views/openTradesListing.php';
        } else {
            echo "Error: group_id parameter is required for Open Trades Listing.";
        }
        break;

    case 'accounts':
    default:
        include_once './views/accounts.php';
        break;
}
?>
