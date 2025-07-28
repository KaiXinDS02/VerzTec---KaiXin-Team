<?php
// Simple script to run the Verztec.sql file
$servername = "localhost";
$username = "root";
$password = "";

try {
    // Connect to MySQL server (without specifying database initially)
    $conn = new mysqli($servername, $username, $password);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to MySQL server successfully\n";
    
    // Read the SQL file
    $sqlFile = 'sql/Verztec.sql';
    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        die("Failed to read SQL file\n");
    }
    
    echo "Executing SQL file...\n";
    
    // Execute the SQL (this will drop and recreate the database)
    if ($conn->multi_query($sql)) {
        do {
            // Fetch any result sets
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        echo "✅ Database setup completed successfully!\n";
        echo "✅ All tables including conversations have been created.\n";
    } else {
        echo "❌ Error executing SQL: " . $conn->error . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
