# VerzTec Project - KaiXin Team

A comprehensive web application with integrated AI chatbot for VerzTec document management and employee assistance.

## 🚀 Quick Start

### Easy Setup (Recommended)
1. **Clone the repository:**
   ```bash
   git clone [your-repo-url]
   cd VerzTec---KaiXin-Team
   ```

2. **Start everything with one click:**
   - Double-click `start-application.bat`
   - Wait 30-60 seconds for all services to start
   - Open http://localhost:8080

3. **Stop everything:**
   - Double-click `stop-application.bat`

## 📋 Project Structure

```
├── 📁 admin/                  # Admin panel files
├── 📁 chatbot/                # AI Chatbot (FastAPI) - Git Subtree
│   ├── main.py               # FastAPI server
│   ├── requirements.txt      # Python dependencies
│   ├── chatbot/             # Core modules
│   ├── data/                # Training data
│   └── static/              # Web assets
├── 📁 css/                    # Stylesheets
├── 📁 files/                  # Uploaded documents
├── 📁 js/                     # JavaScript files
├── 📁 Images/                 # Static images
├── 📄 chatbot.html           # Chatbot web interface
├── 📄 home.php               # Main homepage
├── 📄 login.php              # User authentication
├── 📄 docker-compose.yml     # Docker configuration
├── 📄 start-application.bat  # Easy startup script
└── 📄 stop-application.bat   # Easy stop script
```

## 🛠️ Services & Ports

| Service | Port | Purpose | Status |
|---------|------|---------|--------|
| **Main Website** | 8080 | PHP Web App | 🐳 Docker |
| **AI Chatbot** | 8000 | FastAPI Server | 🐍 Python |
| **OnlyOffice** | 8081 | Document Editor | 🐳 Docker |
| **MySQL Database** | 3306 | Data Storage | 🐳 Docker |

## 💬 Features

### Main Website
- ✅ User authentication and management
- ✅ File upload and management
- ✅ Document preview and editing
- ✅ Admin panel with user controls
- ✅ Announcement system

### AI Chatbot
- 🤖 RAG-based document Q&A
- 📚 VerzTec policy and procedure knowledge
- 🔍 Intelligent document search
- 📄 PDF reference linking
- 💬 Natural conversation interface

## 🔧 Development Setup

### Prerequisites
- Docker Desktop (running)
- Python 3.8+
- Git

### Manual Setup
1. **Start Docker services:**
   ```bash
   docker-compose up -d
   ```

2. **Setup chatbot:**
   ```bash
   cd chatbot
   pip install -r requirements.txt
   python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
   ```

3. **Access services:**
   - Website: http://localhost:8080
   - Chatbot: http://localhost:8000
   - OnlyOffice: http://localhost:8081

## 🔄 Git Workflow

### For Team Members
```bash
# Get latest changes
git pull origin dev

# Start application
./start-application.bat  # Windows
# or manually: docker-compose up -d && cd chatbot && python -m uvicorn main:app --reload

# Work on your features...

# Commit and push
git add .
git commit -m "Your changes"
git push origin dev
```

### Updating the Chatbot (Maintainers only)
```bash
# Pull updates from the original chatbot repository
git subtree pull --prefix=chatbot https://github.com/juliazhou1415/Verztec_Chatbot----Ollama----new-data.git main --squash
```

## 📖 Usage Guide

### For End Users
1. Go to http://localhost:8080
2. Login with your credentials
3. Navigate using the menu:
   - **Home**: Dashboard and announcements
   - **Chatbot**: AI assistant for questions
   - **Files**: Document management
   - **Admin**: User management (admin only)

### For Developers
1. **Website code**: Edit PHP files in the root directory
2. **Chatbot code**: Edit Python files in the `chatbot/` directory
3. **Styling**: Modify CSS files in the `css/` directory
4. **Database**: Use phpMyAdmin or connect to localhost:3306

## 🐛 Troubleshooting

### Common Issues

**🔴 "Connection refused" when accessing website:**
```bash
# Check if Docker is running
docker ps

# Restart if needed
docker-compose down
docker-compose up -d
```

**🔴 Chatbot not responding:**
```bash
# Check chatbot status
cd chatbot
python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

**🔴 Port conflicts:**
- Edit `docker-compose.yml` to change ports
- Or stop conflicting services

**🔴 Database connection issues:**
- Wait for MySQL container to fully start (30-60 seconds)
- Check logs: `docker-compose logs db`

### Getting Help
1. Check the logs: `docker-compose logs`
2. Restart services: `./stop-application.bat` then `./start-application.bat`
3. Contact the development team

## 🤝 Contributing

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make your changes
3. Test locally using the startup scripts
4. Commit: `git commit -m "Add your feature"`
5. Push: `git push origin feature/your-feature`
6. Create a Pull Request

## 📝 License

This project is for VerzTec internal use only.

---

**Need help?** Check the troubleshooting section above or contact the KaiXin development team.
