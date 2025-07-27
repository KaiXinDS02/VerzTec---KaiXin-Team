<?php
// Test script to verify timezone functionality
session_start();
require_once __DIR__ . '/includes/TimezoneHelper.php';

// Test data
$test_countries = [
    'Singapore', 
    'Thailand', 
    'Malaysia', 
    'Indonesia', 
    'Indonesia (Central)', 
    'Indonesia (Eastern)', 
    'Philippines',
    'Japan', 
    'Australia'
];
$test_utc_timestamp = '2025-01-15 12:00:00'; // UTC timestamp

echo "<h2>Timezone Conversion Test</h2>";
echo "<p><strong>UTC Timestamp:</strong> $test_utc_timestamp</p><br>";

foreach ($test_countries as $country) {
    echo "<h3>Country: $country</h3>";
    echo "<p><strong>Timezone:</strong> " . TimezoneHelper::getTimezoneByCountry($country) . "</p>";
    echo "<p><strong>Converted Time:</strong> " . TimezoneHelper::convertToUserTimezone($test_utc_timestamp, $country) . "</p>";
    echo "<p><strong>Formatted Display:</strong> " . TimezoneHelper::formatForDisplay($test_utc_timestamp, $country) . "</p>";
    echo "<p><strong>Current Time:</strong> " . TimezoneHelper::getCurrentTimeInUserTimezone($country) . "</p>";
    echo "<p><strong>Greeting:</strong> " . TimezoneHelper::getGreeting($country) . "</p>";
    echo "<p><strong>Timezone Abbreviation:</strong> " . TimezoneHelper::getTimezoneAbbreviation($country) . "</p>";
    echo "<hr>";
}
?>
