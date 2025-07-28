# 🚀 VerzTec Team Deployment Guide

## Quick Start for Team Members

### Prerequisites
- Docker Desktop installed and running
- Git (to clone the repository)

### 1. Clone & Setup
```bash
git clone [your-repository-url]
cd VerzTec---KaiXin-Team
```

### 2. One-Click Deployment
**Windows:**
```bash
deploy-for-team.bat
```

**Manual Deployment:**
```bash
# Start all services
docker-compose up -d

# Wait for database to initialize (15 seconds)
# Then run migration
docker exec web php /var/www/html/docker_migration.php
```

### 3. Access the Application
- **Main Application:** http://localhost:8080
- **Database:** localhost:3306 (user: `user`, password: `password`)
- **OnlyOffice:** http://localhost:8081

### 4. Using the Conversation System
1. Login to VerzTec at http://localhost:8080
2. Navigate to the conversation/chat section
3. Start new conversations using the ChatGPT-style interface
4. Your conversations are automatically saved and organized

## 🔧 Development Commands

### Start Services
```bash
docker-compose up -d
```

### Stop Services
```bash
docker-compose down
```

### View Logs
```bash
# All services
docker-compose logs

# Specific service
docker-compose logs web
docker-compose logs db
```

### Database Management
```bash
# Connect to database
docker exec -it [db-container-name] mysql -u user -p

# Run migration manually
docker exec web php /var/www/html/docker_migration.php
```

## 📁 Important Files

### For Team Development
- `conversation_manager.php` - Backend API for conversations
- `docker_migration.php` - Database setup script
- `connect.php` - Database configuration (already set for Docker)

### Docker Configuration
- `docker-compose.yml` - Main container orchestration
- `Dockerfile` - Web server configuration

## 🛠️ Troubleshooting

### Container Issues
```bash
# Restart all containers
docker-compose restart

# Rebuild containers
docker-compose up --build
```

### Database Issues
```bash
# Check database status
docker exec web php test_db_connection.php

# Reset database (⚠️ This will delete all data)
docker-compose down -v
docker-compose up -d
```

### Port Conflicts
If ports 8080, 3306, or 8081 are already in use:
1. Stop other services using those ports
2. Or modify `docker-compose.yml` to use different ports

## 🔒 Security Notes

- Default database credentials are in `docker-compose.yml`
- Change passwords for production deployment
- JWT secret should be updated for production

## 📞 Support

If you encounter issues:
1. Check Docker Desktop is running
2. Ensure no port conflicts
3. Run `docker-compose logs` to see error messages
4. Try rebuilding with `docker-compose up --build`

---

**Happy coding! 🎉**
