# ingest_single.py (charmaine)
# usage: python ingest_single.py policy.pdf (give either 'policy.pdf', 'policy.docx', or just 'policy')

# -------------------- Imports --------------------
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
import mysql.connector
import os



# -------------------- Environment Variables for HuggingFace --------------------
os.environ["HF_HOME"] = "/tmp/huggingface"
os.environ["TRANSFORMERS_CACHE"] = "/tmp/huggingface"
os.environ["HF_DATASETS_CACHE"] = "/tmp/huggingface"



# -------------------- Helper Functions --------------------

# Read .docx files and join all paragraph text
def read_docx(path):
    doc = Document(path)
    return "\n".join(p.text for p in doc.paragraphs)

# Clean text by removing asterisks and list markers
def clean_text(txt):
    return re.sub(r"\*|\d+\.", "", txt).strip()

# Load pre-cleaned .txt or .docx file from CLEANED_DIR
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

# RBAC Helper Function: Get visibility scope and category for a given file
DB_CONFIG = {
    'host': 'db',
    'user': 'user',
    'password': 'password',
    'database': 'Verztec'
}

def get_visibility_for_file(file_base_name):
    # Connect to MySQL to retrieve file visibility
    connection = mysql.connector.connect(**DB_CONFIG)
    cursor = connection.cursor(dictionary=True)

    # Match filename (base name) in DB by stripping known extensions
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

    # Retrieve visibility scope and category from file_visibility table
    cursor.execute("SELECT visibility_scope, category FROM file_visibility WHERE file_id = %s", (file_id,))
    rows = cursor.fetchall()
    cursor.close()
    connection.close()

    if not rows:
        return {"visibility_scope": "UNKNOWN", "category": None}

    # If visibility is set to ALL, no restriction needed
    if any(row["visibility_scope"] == "ALL" for row in rows):
        return {"visibility_scope": "ALL", "category": None}

    # If restricted by COUNTRY, return the appropriate category
    for row in rows:
        if row["visibility_scope"] == "COUNTRY":
            return {"visibility_scope": "COUNTRY", "category": row["category"]}




# -------------------- Main Script --------------------

# Validate command-line argument
if len(sys.argv) != 2:
    print("Usage: ingest_single.py <filename or base_name>")
    sys.exit(1)

raw_arg = sys.argv[1]
base_name = os.path.splitext(raw_arg)[0]  # drop extension if any

# Load and clean text content
text, cleaned_fname = load_clean_file(base_name)
text = clean_text(text)

# Split text into manageable chunks for embedding
splitter = RecursiveCharacterTextSplitter(chunk_size=CHUNK_SIZE,
                                          chunk_overlap=CHUNK_OVERLAP)
chunks = splitter.create_documents([text])


# Attempt to match original file for metadata
matched_file = None
for f in os.listdir(PDF_DIR):
    filename_wo_ext, _ = os.path.splitext(f)
    if base_name.lower() == filename_wo_ext.lower():
        matched_file = f
        break

# Set source file (fallback to .docx if no PDF match)
source_file = matched_file if matched_file else f"{base_name}.docx"

# Classify document type based on filename
if "cover" in base_name.lower():
    doc_type = "cover_page"
elif any(term in base_name.lower() for term in ["digital meeting", "online meeting", "virtual meeting"]):
    doc_type = "digital"
elif "etiquette" in base_name.lower() or "physical" in base_name.lower():
    doc_type = "physical"
else:
    doc_type = "general"

# Retrieve RBAC metadata for visibility
visibility = get_visibility_for_file(base_name)


# Attach metadata to each chunk for indexing
for chunk in chunks:
    chunk.metadata["source"] = source_file
    chunk.metadata["title"] = base_name.replace("_", " ").lower().strip()
    chunk.metadata["doc_type"] = doc_type  
    chunk.metadata["visibility_scope"] = visibility["visibility_scope"]
    chunk.metadata["category"] = visibility["category"] 

# Generate embeddings and save to vectorstore
embeddings = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME)

if os.path.exists(VECTORSTORE_DIR):
    # Load existing vectorstore and append new chunks
    vs = FAISS.load_local(VECTORSTORE_DIR, embeddings, allow_dangerous_deserialization=True)
    vs.add_documents(chunks)
else:
    vs = FAISS.from_documents(chunks, embeddings)


# Save updated vectorstore
vs.save_local(VECTORSTORE_DIR)

# Confirmation log
print(f"✅ indexed {cleaned_fname} ({len(chunks)} chunks)")
