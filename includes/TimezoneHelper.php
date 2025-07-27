<?php
/**
 * Timezone Utility - Maps countries to their respective timezones
 * and provides functions for timezone conversion
 */

class TimezoneHelper {
    
    // Map countries to their respective timezones
    private static $countryTimezones = [
        'Singapore' => 'Asia/Singapore',
        'Thailand' => 'Asia/Bangkok',
        'Malaysia' => 'Asia/Kuala_Lumpur',
        'Indonesia' => 'Asia/Jakarta', // WIB (UTC+7) - Western Indonesia Time
        'Indonesia (Central)' => 'Asia/Makassar', // WITA (UTC+8) - Central Indonesia Time  
        'Indonesia (Eastern)' => 'Asia/Jayapura', // WIT (UTC+9) - Eastern Indonesia Time
        'Philippines' => 'Asia/Manila',
        'Vietnam' => 'Asia/Ho_Chi_Minh',
        'Cambodia' => 'Asia/Phnom_Penh',
        'Laos' => 'Asia/Vientiane',
        'Myanmar' => 'Asia/Yangon',
        'Brunei' => 'Asia/Brunei',
        'Japan' => 'Asia/Tokyo',
        'South Korea' => 'Asia/Seoul',
        'China' => 'Asia/Shanghai',
        'India' => 'Asia/Kolkata',
        'Australia' => 'Australia/Sydney',
        'United States' => 'America/New_York',
        'United Kingdom' => 'Europe/London',
        'Germany' => 'Europe/Berlin',
        'France' => 'Europe/Paris'
        // Add more countries as needed
    ];
    
    /**
     * Get timezone for a specific country
     */
    public static function getTimezoneByCountry($country) {
        return self::$countryTimezones[$country] ?? 'UTC';
    }
    
    /**
     * Convert UTC timestamp to user's timezone
     */
    public static function convertToUserTimezone($utcTimestamp, $userCountry, $format = 'Y-m-d H:i:s') {
        try {
            $timezone = self::getTimezoneByCountry($userCountry);
            $date = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone($timezone));
            return $date->format($format);
        } catch (Exception $e) {
            // Fallback to UTC if conversion fails
            return $utcTimestamp;
        }
    }
    
    /**
     * Get current time in user's timezone
     */
    public static function getCurrentTimeInUserTimezone($userCountry, $format = 'Y-m-d H:i:s') {
        try {
            $timezone = self::getTimezoneByCountry($userCountry);
            $date = new DateTime('now', new DateTimeZone($timezone));
            return $date->format($format);
        } catch (Exception $e) {
            return date($format); // Fallback to server time
        }
    }
    
    /**
     * Get time-based greeting based on user's timezone
     */
    public static function getGreeting($userCountry) {
        try {
            $timezone = self::getTimezoneByCountry($userCountry);
            $date = new DateTime('now', new DateTimeZone($timezone));
            $hour = (int)$date->format('H');
            
            if ($hour >= 5 && $hour < 12) {
                return 'Good Morning';
            } elseif ($hour >= 12 && $hour < 17) {
                return 'Good Afternoon';
            } elseif ($hour >= 17 && $hour < 21) {
                return 'Good Evening';
            } else {
                return 'Good Night';
            }
        } catch (Exception $e) {
            return 'Hello'; // Fallback greeting
        }
    }
    
    /**
     * Format timestamp for display with timezone abbreviation
     */
    public static function formatForDisplay($utcTimestamp, $userCountry, $showTimezone = true) {
        try {
            $timezone = self::getTimezoneByCountry($userCountry);
            $date = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone($timezone));
            
            $formatted = $date->format('M j, Y g:i A');
            
            if ($showTimezone) {
                $timezoneAbbr = $date->format('T');
                $formatted .= ' ' . $timezoneAbbr;
            }
            
            return $formatted;
        } catch (Exception $e) {
            return $utcTimestamp;
        }
    }
    
    /**
     * Get user's timezone abbreviation
     */
    public static function getTimezoneAbbreviation($userCountry) {
        try {
            $timezone = self::getTimezoneByCountry($userCountry);
            $date = new DateTime('now', new DateTimeZone($timezone));
            return $date->format('T');
        } catch (Exception $e) {
            return 'UTC';
        }
    }
}
?>
