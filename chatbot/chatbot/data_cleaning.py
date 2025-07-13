# data_cleaning.py (Charmaine)
#  -------------------- Imports --------------------
import os
import re
import pdfplumber
import docx2txt
import sys

# -------------------- Folder Configuration --------------------
# Input folder containing source PDF and DOCX files
input_folder = "/var/www/html/chatbot/data/pdfs"  


# Output folder to save cleaned text files
output_folder = "/var/www/html/chatbot/data/Cleaned"
os.makedirs(output_folder, exist_ok=True)  # Create output folder if it doesn't exist



# -------------------- Text Cleaning Function --------------------
# Clean text by removing unnecessary whitespace, tabs, and redundant newlines
def clean_text(text):
    text = re.sub(r"\n{2,}", "\n", text)                 # Remove multiple consecutive newlines
    text = re.sub(r"[ \t]{2,}", " ", text)               # Replace multiple spaces/tabs with a single space
    text = re.sub(r"(\n\s*)+", "\n", text)               # Remove spaces at the beginning of lines
    lines = [line.strip() for line in text.splitlines()] # Strip each line individually
    return "\n".join(lines).strip()                      # Return cleaned text



# -------------------- Save Function --------------------
# Save cleaned text to .txt file in the output folder
def save_cleaned_text(original_filename, text):
    txt_filename = os.path.splitext(original_filename)[0] + ".txt"
    output_path = os.path.join(output_folder, txt_filename)
    with open(output_path, "w", encoding="utf-8") as out_file:
        out_file.write(text)
    print(f"✅ Saved: {txt_filename}")



# -------------------- PDF Processing --------------------
# Process PDF files using pdfplumber to extract text and table data
def process_pdf(file_path, filename):
    try:
        with pdfplumber.open(file_path) as pdf:
            full_text = ""
            for page in pdf.pages:
                text = page.extract_text() or ""

                # Extract text and tables from each page
                tables = page.extract_tables()
                table_text = ""
                for table in tables:
                    for row in table:
                        # Format table rows as pipe-separated values
                        row_text = " | ".join(cell.strip() if cell else "-" for cell in row)
                        table_text += row_text + "\n"

                full_text += text + "\n" + table_text + "\n"

        # Clean and save the extracted content
        cleaned_text = clean_text(full_text)
        if not cleaned_text:
            print(f"⚠️ No text extracted from PDF: {filename}")
        save_cleaned_text(filename, cleaned_text)

    except Exception as e:
        print(f"❌ Error processing PDF {filename}: {e}")

# -------------------- DOCX Processing --------------------
# Process Word DOCX files using docx2txt, including text boxes and headers
def process_docx(file_path, filename):
    try:
        text = docx2txt.process(file_path)
        cleaned_text = clean_text(text)

        if not cleaned_text:
            print(f"⚠️ No text extracted from DOCX: {filename}")

        save_cleaned_text(filename, cleaned_text)

    except Exception as e:
        print(f"❌ Error reading DOCX {filename}: {e}")




# -------------------- Dispatcher Function --------------------
# Determine file type and call appropriate processing function
def process_document(filename):
    file_path = os.path.join(input_folder, filename)

    if not os.path.isfile(file_path):
        print(f"❌ File not found: {file_path}")
        return
    
    # Handle PDF files
    if filename.lower().endswith(".pdf"):
        print(f"📄 Extracting from PDF: {filename}")
        process_pdf(file_path, filename)

    # Handle DOCX files (ignore temporary files starting with "~$")
    elif filename.lower().endswith(".docx") and not filename.startswith("~$"):
        print(f"📄 Extracting from Word: {filename}")
        process_docx(file_path, filename)

    # Unsupported file types
    else:
        print(f"⚠️ Unsupported file type: {filename}")




# -------------------- Entry Point --------------------
# Entry point for script execution
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("❌ Missing filename argument.")
        sys.exit(1)

    filename_arg = sys.argv[1]
    process_document(filename_arg)