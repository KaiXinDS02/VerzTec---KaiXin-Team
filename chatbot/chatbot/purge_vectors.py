# purging_vectors.py (Charmaine)
# Remove every chunk that came from a particular file. (usage: python purge_vectors.py policy.pdf)


# -------------------- Imports --------------------
import sys
from pathlib import Path
from config import VECTORSTORE_DIR, EMBEDDING_MODEL_NAME
from langchain_community.vectorstores import FAISS
from langchain_community.embeddings import HuggingFaceEmbeddings
import os


# -------------------- Environment Configuration --------------------
# Set HuggingFace cache locations to temporary directory
os.environ["HF_HOME"] = "/tmp/huggingface"
os.environ["TRANSFORMERS_CACHE"] = "/tmp/huggingface"
os.environ["HF_DATASETS_CACHE"] = "/tmp/huggingface"


# -------------------- Argument Validation --------------------
# Ensure exactly one argument is provided
if len(sys.argv) != 2:
    print("Usage: purge_vectors.py <filename-or-base>")
    sys.exit(1)

file_arg = Path(sys.argv[1])      # Extract path from input argument
base_stem = file_arg.stem         # Get filename without extension


# -------------------- Check Vector Store Availability --------------------
# Exit early if the vector store directory does not exist
if not Path(VECTORSTORE_DIR).exists():
    print("Vector store not found; nothing to purge.")
    sys.exit(0)



# -------------------- Load Vector Store --------------------
# Initialize embedding model and load existing FAISS vector store
embeddings = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME)
vs = FAISS.load_local(
    VECTORSTORE_DIR,
    embeddings,
    allow_dangerous_deserialization=True
)

# -------------------- Identify Chunks to Delete --------------------
# Display total number of stored documents before deletion
print(f"Docs before purge: {len(vs.docstore._dict)}")

# Filter documents where metadata source matches the given base filename
delete_ids = [
    doc_id for doc_id, doc in vs.docstore._dict.items()
    if Path(doc.metadata.get("source", "")).stem == base_stem
]



# -------------------- Conditional Deletion --------------------
# If no matches found, exit without making changes
if not delete_ids:
    print(f"No vectors found for '{base_stem}'; index unchanged.")
    sys.exit(0)

# Use FAISS's built-in delete method to remove matching vectors
vs.delete(delete_ids)

# Show updated number of documents
print(f"Docs after purge: {len(vs.docstore._dict)}")

# Save changes back to the vector store
vs.save_local(VECTORSTORE_DIR)

# Final confirmation log
print(f"✅ Purged {len(delete_ids)} chunks for '{base_stem}'")