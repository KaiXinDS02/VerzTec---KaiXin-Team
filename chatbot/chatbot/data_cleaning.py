import os
import re
import pdfplumber
import docx2txt
import sys

# 🔹 Folder paths
input_folder = "/var/www/html/chatbot/data/pdfs"  # Rename this if needed
output_folder = "/var/www/html/chatbot/data/Cleaned"
os.makedirs(output_folder, exist_ok=True)

# 🔹 Clean text function
def clean_text(text):
    # Remove excess whitespace (e.g. tabs or double spaces)
    text = re.sub(r"\n{2,}", "\n", text)                 # Remove double newlines
    text = re.sub(r"[ \t]{2,}", " ", text)               # Reduce spaces/tabs
    text = re.sub(r"(\n\s*)+", "\n", text)               # Clean up leading spaces per line
    lines = [line.strip() for line in text.splitlines()]
    return "\n".join(lines).strip()

# 🔹 Save to .txt
def save_cleaned_text(original_filename, text):
    txt_filename = os.path.splitext(original_filename)[0] + ".txt"
    output_path = os.path.join(output_folder, txt_filename)
    with open(output_path, "w", encoding="utf-8") as out_file:
        out_file.write(text)
    print(f"✅ Saved: {txt_filename}")

# 🔹 Process PDF files (text + tables)
def process_pdf(file_path, filename):
    try:
        with pdfplumber.open(file_path) as pdf:
            full_text = ""
            for page in pdf.pages:
                text = page.extract_text() or ""

                # Extract tables (if any)
                tables = page.extract_tables()
                table_text = ""
                for table in tables:
                    for row in table:
                        row_text = " | ".join(cell.strip() if cell else "-" for cell in row)
                        table_text += row_text + "\n"

                full_text += text + "\n" + table_text + "\n"

        cleaned_text = clean_text(full_text)
        if not cleaned_text:
            print(f"⚠️ No text extracted from PDF: {filename}")
        save_cleaned_text(filename, cleaned_text)

    except Exception as e:
        print(f"❌ Error processing PDF {filename}: {e}")

# 🔹 Process Word DOCX using docx2txt (includes text boxes & headers)
def process_docx(file_path, filename):
    try:
        text = docx2txt.process(file_path)
        cleaned_text = clean_text(text)

        if not cleaned_text:
            print(f"⚠️ No text extracted from DOCX: {filename}")

        save_cleaned_text(filename, cleaned_text)

    except Exception as e:
        print(f"❌ Error reading DOCX {filename}: {e}")

# function to process file
def process_document(filename):
    file_path = os.path.join(input_folder, filename)

    if not os.path.isfile(file_path):
        print(f"❌ File not found: {file_path}")
        return

    if filename.lower().endswith(".pdf"):
        print(f"📄 Extracting from PDF: {filename}")
        process_pdf(file_path, filename)

    elif filename.lower().endswith(".docx") and not filename.startswith("~$"):
        print(f"📄 Extracting from Word: {filename}")
        process_docx(file_path, filename)

    else:
        print(f"⚠️ Unsupported file type: {filename}")

# 🔹 Entry point
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("❌ Missing filename argument.")
        sys.exit(1)

    filename_arg = sys.argv[1]
    process_document(filename_arg)