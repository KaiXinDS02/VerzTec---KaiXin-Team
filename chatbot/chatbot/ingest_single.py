#!/usr/bin/env python3
"""
usage: python ingest_single.py policy.pdf
       (give either 'policy.pdf', 'policy.docx', or just 'policy')
"""

import sys
import re
from docx import Document
from config import (
    EMBEDDING_MODEL_NAME, CHUNK_SIZE, CHUNK_OVERLAP,
    CLEANED_DIR, VECTORSTORE_DIR, PDF_DIR  # ✅ added PDF_DIR
)
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain_community.vectorstores import FAISS
from langchain_community.embeddings import HuggingFaceEmbeddings

import os
os.environ["HF_HOME"] = "/tmp/huggingface"
os.environ["TRANSFORMERS_CACHE"] = "/tmp/huggingface"
os.environ["HF_DATASETS_CACHE"] = "/tmp/huggingface"

# ---------- Helpers ----------------------------------------------------------
def read_docx(path):
    doc = Document(path)
    return "\n".join(p.text for p in doc.paragraphs)

def clean_text(txt):
    return re.sub(r"\*|\d+\.", "", txt).strip()

def load_clean_file(base):
    """Return text content and original cleaned filename"""
    txt_path = os.path.join(CLEANED_DIR, base + ".txt")
    docx_path = os.path.join(CLEANED_DIR, base + ".docx")
    if os.path.isfile(txt_path):
        with open(txt_path, encoding="utf-8") as f:
            return f.read(), base + ".txt"
    if os.path.isfile(docx_path):
        return read_docx(docx_path), base + ".docx"
    raise FileNotFoundError(f"cleaned file not found for base='{base}'")

import mysql.connector

DB_CONFIG = {
    'host': 'db',
    'user': 'user',
    'password': 'password',
    'database': 'Verztec'
}

def get_visibility_for_file(file_base_name):
    connection = mysql.connector.connect(**DB_CONFIG)
    cursor = connection.cursor(dictionary=True)
    # Get file ID (assuming file_name in DB is like 'filename.pdf')
    # Remove .pdf, .docx, .txt (case-insensitive) from filename for matching
    cursor.execute(
        "SELECT id FROM files WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(filename, '.pdf', ''), '.docx', ''), '.doc', ''), '.txt', ''), '.xlsx', '')) = %s",
        (file_base_name.lower(),)
    )
    file_row = cursor.fetchone()
    if not file_row:
        cursor.close()
        connection.close()
        return {"visibility_scope": "UNKNOWN", "category": None}

    file_id = file_row["id"]

    # Get visibility settings
    cursor.execute("SELECT visibility_scope, category FROM file_visibility WHERE file_id = %s", (file_id,))
    rows = cursor.fetchall()
    cursor.close()
    connection.close()

    if not rows:
        return {"visibility_scope": "UNKNOWN", "category": None}

    # If ALL, return directly
    if any(row["visibility_scope"] == "ALL" for row in rows):
        return {"visibility_scope": "ALL", "category": None}

    # If COUNTRY or DEPARTMENT
    for row in rows:
        if row["visibility_scope"] == "COUNTRY":
            return {"visibility_scope": "COUNTRY", "category": row["category"]}


# ---------- Main -------------------------------------------------------------
if len(sys.argv) != 2:
    print("Usage: ingest_single.py <filename or base_name>")
    sys.exit(1)

raw_arg = sys.argv[1]
base_name = os.path.splitext(raw_arg)[0]  # drop extension if any

text, cleaned_fname = load_clean_file(base_name)
text = clean_text(text)

splitter = RecursiveCharacterTextSplitter(chunk_size=CHUNK_SIZE,
                                          chunk_overlap=CHUNK_OVERLAP)
chunks = splitter.create_documents([text])

# 🔗 Match with original file (for metadata)
matched_file = None
for f in os.listdir(PDF_DIR):
    filename_wo_ext, _ = os.path.splitext(f)
    if base_name.lower() == filename_wo_ext.lower():
        matched_file = f
        break

source_file = matched_file if matched_file else f"{base_name}.docx"

# 🏷️ Determine document type
if "cover" in base_name.lower():
    doc_type = "cover_page"
elif any(term in base_name.lower() for term in ["digital meeting", "online meeting", "virtual meeting"]):
    doc_type = "digital"
elif "etiquette" in base_name.lower() or "physical" in base_name.lower():
    doc_type = "physical"
else:
    doc_type = "general"

visibility = get_visibility_for_file(base_name)

# 🧷 Tag metadata
for chunk in chunks:
    chunk.metadata["source"] = source_file
    chunk.metadata["title"] = base_name.replace("_", " ").lower().strip()
    chunk.metadata["doc_type"] = doc_type  
    chunk.metadata["visibility_scope"] = visibility["visibility_scope"] # RBAC (Charmaine)
    chunk.metadata["category"] = visibility["category"] # RBAC (Charmaine)

# 🧠 Embedding and indexing
embeddings = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME)

if os.path.exists(VECTORSTORE_DIR):
    vs = FAISS.load_local(VECTORSTORE_DIR, embeddings, allow_dangerous_deserialization=True)
    vs.add_documents(chunks)
else:
    vs = FAISS.from_documents(chunks, embeddings)

vs.save_local(VECTORSTORE_DIR)
print(f"✅ indexed {cleaned_fname} ({len(chunks)} chunks)")
