# Announcement App

## Requirements
- PHP 8.x
- MySQL server
- Fonts folder must remain in the same directory

## Setup Instructions
1. Import the database:
   - Open MySQL Workbench
   - Run the script in `verztec.sql` to create the database and table

2. Start a local server:
   - Place the project folder in your web server's root (e.g., XAMPP `htdocs/`)
   - Access the project via `http://localhost/announcement-app/display.php`

3. Make sure to adjust DB login in the PHP files if needed:
   ```php
   $conn = mysqli_connect("localhost", "root", "YOUR_PASSWORD", "verztec");
