<?php
// Copy this file to settings.local.php and edit the copy.
// Only include the values you want to override from settings.php.
// settings.local.php is ignored by Git and must stay private.
return [
    'timezone' => 'America/Chicago',
    // Set a unique password of at least 10 characters to enable /admin.php.
    'admin_password' => '',
    // Public contact address; leave blank to show no address.
    'admin_email' => '',
    'server_news' => 'Welcome to our town!',
    // Set false if players share an IP address (household, school, or office).
    'ip_check' => true,
];
