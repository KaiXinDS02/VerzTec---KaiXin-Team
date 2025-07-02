# VerzTec Chatbot Integration

This directory contains the VerzTec chatbot that has been integrated from a separate repository using git subtree.

## Overview

The chatbot is a FastAPI-based application that uses Ollama for language model inference and provides a RAG (Retrieval-Augmented Generation) system for answering questions based on VerzTec documents.

## Prerequisites

1. **Python 3.8+** installed on your system
2. **Ollama** installed and running locally
3. **Git** for version control

## Setup Instructions

### 1. Install Python Dependencies

Navigate to the chatbot directory and install the required packages:

```bash
cd chatbot
pip install -r requirements.txt
```

### 2. Install and Setup Ollama

1. Download and install Ollama from [https://ollama.ai](https://ollama.ai)
2. Start the Ollama service
3. Pull the required model (the chatbot will specify which model it needs)

### 3. Run the Chatbot

Start the FastAPI server:

```bash
python main.py
```

Or using uvicorn directly:

```bash
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

The chatbot will be available at `http://localhost:8000`

## Features

- **Document-based Q&A**: Answers questions based on VerzTec documents
- **RAG System**: Uses vector search to find relevant information
- **Personal Question Filtering**: Redirects personal questions appropriately
- **File Serving**: Serves PDF documents and static files
- **Chat History**: Maintains conversation context

## Directory Structure

```
chatbot/
├── main.py              # FastAPI application entry point
├── requirements.txt     # Python dependencies
├── chatbot/            # Core chatbot modules
├── data/               # Document data for RAG
├── models/             # Model-related files
├── static/             # Static web assets
└── question_log.txt    # Log of questions asked
```

## Integration Details

This chatbot has been integrated using **git subtree**, which means:

- ✅ It maintains its own directory structure
- ✅ It doesn't conflict with existing project files
- ✅ It can be updated independently
- ✅ Team members get the full chatbot when they pull the repository

## Updating the Chatbot

If the original chatbot repository receives updates, you can pull them using:

```bash
git subtree pull --prefix=chatbot https://github.com/juliazhou1415/Verztec_Chatbot----Ollama----new-data.git main --squash
```

## Troubleshooting

### Common Issues

1. **Port already in use**: Change the port in the uvicorn command
2. **Ollama not running**: Make sure Ollama service is started
3. **Missing dependencies**: Run `pip install -r requirements.txt` again
4. **Model not found**: Check if the required Ollama model is pulled

### Logs

Check `question_log.txt` for chatbot interaction logs.

## Team Workflow

1. **Pull latest changes**: `git pull origin dev`
2. **Navigate to chatbot**: `cd chatbot`
3. **Install dependencies**: `pip install -r requirements.txt`
4. **Start chatbot**: `python main.py`
5. **Access at**: `http://localhost:8000`

## Support

For chatbot-specific issues, refer to the original repository:
https://github.com/juliazhou1415/Verztec_Chatbot----Ollama----new-data

For integration issues, contact the development team.
