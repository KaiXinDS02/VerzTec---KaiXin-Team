#!/usr/bin/env python3
"""
Remove every chunk that came from a particular file.
usage:
    python purge_vectors.py policy.pdf
"""
import sys
from pathlib import Path
from config import VECTORSTORE_DIR, EMBEDDING_MODEL_NAME
from langchain_community.vectorstores import FAISS
from langchain_community.embeddings import HuggingFaceEmbeddings

import os
os.environ["HF_HOME"] = "/tmp/huggingface"
os.environ["TRANSFORMERS_CACHE"] = "/tmp/huggingface"
os.environ["HF_DATASETS_CACHE"] = "/tmp/huggingface"

if len(sys.argv) != 2:
    print("Usage: purge_vectors.py <filename-or-base>")
    sys.exit(1)

file_arg = Path(sys.argv[1])
base_stem = file_arg.stem

if not Path(VECTORSTORE_DIR).exists():
    print("Vector store not found; nothing to purge.")
    sys.exit(0)

embeddings = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME)
vs = FAISS.load_local(
    VECTORSTORE_DIR,
    embeddings,
    allow_dangerous_deserialization=True
)

# Print number of docs before purge
print(f"Docs before purge: {len(vs.docstore._dict)}")

delete_ids = [
    doc_id for doc_id, doc in vs.docstore._dict.items()
    if Path(doc.metadata.get("source", "")).stem == base_stem
]

if not delete_ids:
    print(f"No vectors found for '{base_stem}'; index unchanged.")
    sys.exit(0)

# Use the official delete method
vs.delete(delete_ids)

# Print number of docs after purge
print(f"Docs after purge: {len(vs.docstore._dict)}")

# Persist changes using only the official method
vs.save_local(VECTORSTORE_DIR)

print(f"✅ Purged {len(delete_ids)} chunks for '{base_stem}'")