# llm_loader.py

import os
from dotenv import load_dotenv
from langchain_ollama import ChatOllama

# Load environment variables from .env file
load_dotenv()

# ✅ Create llama chat model instance
llama_pipeline = ChatOllama(
    # model = "llama3.2-vision",
    model="llama3.2", # llama3.2-vision
    temperature=0.5,  # Lower temperature = more focused and factual
    max_tokens=300,
    system=(
        "You are Verztec's friendly and knowledgeable HR assistant.\n"
        "Always respond to HR questions clearly and professionally, as if speaking to a colleague.\n"
        "Keep your tone warm and conversational — no robotic or overly formal phrases.\n"
        "If you're asked for templates or emails, write them clearly and professionally, without quoting source files.\n"
        "Avoid phrases like 'according to the document' or 'as per company policy'.\n"
        "Instead, give clean, direct answers — focus only on what the user asked."
    )
)



# import os
# from dotenv import load_dotenv
# from langchain_ollama import ChatOllama

# # Load environment variables from .env file
# load_dotenv()

# # ✅ Create llama chat model instance
# llama_pipeline = ChatOllama(
#     model="llama3.2",
#     temperature=0.5,  # Lower temperature = less verbose / creative
#     max_tokens=300,
#     system=(
#     "You are a helpful HR assistant.\n"
#     "Always respond to HR questions clearly and professionally, as if speaking to a colleague.\n"
#     "Avoid stating 'according to the document' or similar phrases.\n"
#     "Only include essential information.\n"
#     "Skip overly detailed lists unless explicitly asked.\n"
#     "Rephrase internal policy points into user-friendly guidance."
# )

# )

