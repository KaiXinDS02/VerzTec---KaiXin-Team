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

# 🧷 Tag metadata
for c in chunks:
    c.metadata["source"] = source_file
    c.metadata["title"] = base_name.replace("_", " ").lower().strip()
    c.metadata["doc_type"] = doc_type  

# 🧠 Embedding and indexing
embeddings = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME)

if os.path.exists(VECTORSTORE_DIR):
    vs = FAISS.load_local(VECTORSTORE_DIR, embeddings, allow_dangerous_deserialization=True)
    vs.add_documents(chunks)
else:
    vs = FAISS.from_documents(chunks, embeddings)

vs.save_local(VECTORSTORE_DIR)
print(f"✅ indexed {cleaned_fname} ({len(chunks)} chunks)")
