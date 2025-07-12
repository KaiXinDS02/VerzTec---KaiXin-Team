# main.py

import os
import re
import traceback
from datetime import datetime
from urllib.parse import quote
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from chatbot.rag_chain import load_chain
from chatbot.llm_loader import llama_pipeline
from chatbot.config import MAX_ANSWER_WORDS, PDF_DIR
from rapidfuzz import fuzz
import mysql.connector

app = FastAPI()

# CORS for frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

qa_chain, vectorstore = load_chain()
chat_history = []

app.mount("/static", StaticFiles(directory="static"), name="static")
app.mount("/pdfs", StaticFiles(directory=PDF_DIR), name="pdfs")

class Question(BaseModel):
    user_id: int
    question: str

def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
    words = answer.split()
    if len(words) <= max_words:
        return answer
    truncated = " ".join(words[:max_words])
    truncated = re.sub(r'([.!?])[^.!?]*$', r'\1', truncated.strip())
    return truncated + "..."

def is_rejection_response(text: str) -> bool:
    text = text.lower()
    patterns = [
        r"i'?m not (qualified|able|equipped) to provide (a )?response",
        r"document (does not|doesn’t) (address|mention).*(personal|family)",
        r"recommend (seeking|speaking|getting).*(help|support|advice)",
        r"i can’t provide (guidance|support|advice)",
        r"this is beyond (my|the document's) scope",
        r"not able to help (with )?(that|this question)",
    ]
    return any(re.search(p, text) for p in patterns)

def is_personal_question(question: str) -> bool:
    personal_keywords = [
        "father", "mother", "brother", "sister", "family", "boyfriend", "girlfriend",
        "relationship", "love", "hate", "angry", "feel", "emotional", "personal", "sad",
        "mental health", "feeling", "friend", "mean"
    ]
    return any(word in question.lower() for word in personal_keywords)

def is_hr_query(question: str, use_fuzzy=True) -> bool:
    keywords = [
        "leave", "policy", "hr", "human resource", "benefits", "meeting", "procedure",
        "onboarding", "offboarding", "sop", "salary", "promotion", "resignation",
        "complaint", "roles", "pantry", "email etiquette", "company policy", "form",
        "employee", "attendance", "audit", "feedback", "payroll", "document", "workflow",
        "cover page", "quality manual", "quality procedure", "controlled copy", "uncontrolled copy"
    ]
    question = question.lower()
    for kw in keywords:
        if kw in question:
            return True
        if use_fuzzy and fuzz.partial_ratio(kw, question) >= 80:
            return True
    return False

def is_hr_question_via_llm(query: str) -> bool:
    prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

    Question: "{query}"

    Respond with only "Yes" or "No"."""
    result = llama_pipeline.invoke(prompt)
    return "yes" in result.content.lower()

def save_chat_to_db(user_id, question, answer):
    try:
        # Establish a connection to the MySQL database
        connection = mysql.connector.connect(
            host="localhost",
            user="user",
            password="password",
            database="Verztec"
        )
        cursor = connection.cursor()
        query = "INSERT INTO chat_history (user_id, question, answer) VALUES (%s, %s, %s)"
        cursor.execute(query, (user_id, question, answer))
        connection.commit()
        cursor.close()
        connection.close()
    except Exception as e:
        print("Database error:", e)

##################################🔐 START - RBAC (Charmaine)##################################

# helper function to fetch the user's role and country for RBAC (Charmaine)
def get_user_role_and_country(user_id):
    try:
        # Establish a connection to the MySQL database
        connection = mysql.connector.connect(
            host="localhost",  
            user="user",
            password="password",
            database="Verztec"
        )

        # Create a cursor with dictionary output to access column names easily
        cursor = connection.cursor(dictionary=True)

        # Execute a query to retrieve the user's role and country from the 'users' table
        cursor.execute("SELECT role, country FROM users WHERE user_id = %s", (user_id,))
        result = cursor.fetchone()

        # Close the cursor and database connection to release resources
        cursor.close()
        connection.close()

        # If a user was found, return their role and country
        if result:
            return result["role"], result["country"]
        else:
            return None, None
    
    # ⚠️ Log any error encountered during the process
    except Exception as e:
        print("Error fetching user role/country:", e)
        return None, None

##################################🔐 END - RBAC (Charmaine)##################################

@app.post("/chat")
def chat(question: Question):
    print("❓ User Question:", question.question)

    if is_personal_question(question.question):
        return {
            "answer": (
                "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
                "For personal matters, I would recommend speaking to someone you trust or seek professional help."
            ),
            "reference_file": None
        }

    try:
        is_hr_like = is_hr_query(question.question)
        is_llm_hr = is_hr_question_via_llm(question.question)

        docs_and_scores = []
        if is_hr_like or is_llm_hr:
            docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=3)

##################################🔐 START - RBAC (Charmaine)##################################
            
            # Retrieve the user's role and country based on their user ID
            role, user_country = get_user_role_and_country(question.user_id)

            # Apply RBAC if the user is not an ADMIN
            if role != "ADMIN":
                filtered_docs = []
                rbac_filtered_out = False # Flag to track if any relevant docs were filtered out due to access restrictions

                for doc, score in docs_and_scores:
                    visibility_scope = doc.metadata.get("visibility_scope")
                    category = (doc.metadata.get("category") or "").strip().lower() # Normalize category metadata
                    user_country_norm = (user_country or "").strip().lower() # Normalize user's country
                    allowed = False

                    # Check if the document is universally accessible or restricted to the user’s country
                    if visibility_scope == "ALL":
                        allowed = True
                    elif visibility_scope == "COUNTRY" and category == user_country_norm:
                        allowed = True

                    # If access is allowed, keep the document; otherwise, mark as filtered
                    if allowed:
                        filtered_docs.append((doc, score))
                    else:
                        rbac_filtered_out = True
                    

                # 🚫 If any relevant documents were excluded due to RBAC, return a permission denial message
                if rbac_filtered_out:
                    return {
                        "answer": (
                            f"The system found relevant information, but you do not have permission to access the document(s) "
                            f"based on your role or country ({user_country}). Please contact HR or your administrator for access."
                        ),
                        "reference_file": None
                    }
                docs_and_scores = filtered_docs

##################################🔐 END - RBAC (Charmaine)##################################

            user_query = question.question.strip().lower()
            is_physical = any(term in user_query for term in ["physical meeting", "in person", "face to face", "onsite"])
            is_digital = any(term in user_query for term in ["digital meeting", "online meeting", "virtual meeting", "zoom", "teams"])
            if is_physical:
                docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "physical"]
            elif is_digital:
                docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "digital"]

        score_threshold = 0.38
        answer = ""
        source_file = None

        system_prefix = (
            "You are a professional HR assistant at Verztec.\n"
            "Answer only using the content provided in the document — do not add anything outside of it.\n"
            "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
            "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
            "Speak as if you're helping a colleague or employee in a business setting.\n"
            "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
            "Be clear, concise, and human — not robotic or overly formal."
        )

        reference_file = None
        if docs_and_scores:
            top_doc, top_score = docs_and_scores[0]
            content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
            source_file = top_doc.metadata.get("source", None)
            file_path = os.path.join("data/pdfs", source_file) if source_file else ""
            if top_score >= score_threshold and os.path.exists(file_path):
                full_prompt = f"{system_prefix}\n---\n{content}\n---\nBased only on the content above, how would you answer this question?\n{question.question}"
                result = llama_pipeline.invoke(full_prompt)
                answer = truncate_answer(result.content)
                reference_file = {
                    "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
                    "name": source_file
                }
            else:
                result = llama_pipeline.invoke(question.question)
                answer = truncate_answer(result.content)
                reference_file = None
        else:
            result = llama_pipeline.invoke(question.question)
            answer = truncate_answer(result.content)
            reference_file = None

        save_chat_to_db(question.user_id, question.question, answer)

        return {
            "answer": answer,
            "reference_file": reference_file
        }

    except Exception as e:
        traceback.print_exc()
        return {"answer": "Sorry, something went wrong.", "reference_file": None}

@app.get("/history/{user_id}")
def get_chat_history(user_id: int):
    try:
        connection = mysql.connector.connect(
            host="db",
            user="user",
            password="password",
            database="Verztec"
        )
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT question, answer, timestamp FROM chat_history WHERE user_id = %s ORDER BY timestamp DESC", (user_id,))
        history = cursor.fetchall()
        cursor.close()
        connection.close()
        return history
    except Exception as e:
        print("Error retrieving history:", e)
        return {"error": "Unable to fetch history"}

@app.get("/")
def index():
    return FileResponse("static/index.html")

@app.get("/favicon.ico")
def favicon():
    return FileResponse("static/favicon.ico")

# Added this to enable reload of model after change is made - charmaine
@app.post("/reload_vectorstore")
def reload_vectorstore():
    global qa_chain, vectorstore
    print("🔄 [RELOAD] /reload_vectorstore endpoint called")
    qa_chain, vectorstore = load_chain()
    try:
        doc_count = len(vectorstore.index_to_docstore_id)
    except Exception:
        doc_count = "unknown"
    print(f"✅ [RELOAD] Reloaded vectorstore. Document count: {doc_count}")
    return {"status": "reloaded", "doc_count": doc_count}

# import os
# import re
# import traceback
# from datetime import datetime
# from urllib.parse import quote
# from fastapi import FastAPI
# from fastapi.middleware.cors import CORSMiddleware
# from pydantic import BaseModel
# from fastapi.staticfiles import StaticFiles
# from fastapi.responses import FileResponse
# from chatbot.rag_chain import load_chain
# from chatbot.llm_loader import llama_pipeline
# from chatbot.config import MAX_ANSWER_WORDS, PDF_DIR
# from rapidfuzz import fuzz

# app = FastAPI()

# # Add CORS middleware to allow frontend connections
# app.add_middleware(
#     CORSMiddleware,
#     allow_origins=["*"],  # In production, replace with specific domains testing
#     allow_credentials=True,
#     allow_methods=["*"],
#     allow_headers=["*"],
# )

# qa_chain, vectorstore = load_chain()
# chat_history = []

# # Mount folders
# app.mount("/static", StaticFiles(directory="static"), name="static")
# app.mount("/pdfs", StaticFiles(directory=PDF_DIR), name="pdfs")

# class Question(BaseModel):
#     question: str

# def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
#     words = answer.split()
#     if len(words) <= max_words:
#         return answer
#     truncated = " ".join(words[:max_words])
#     truncated = re.sub(r'([.!?])[^.!?]*$', r'\1', truncated.strip())
#     return truncated + "..."

# def is_rejection_response(text: str) -> bool:
#     text = text.lower()
#     patterns = [
#         r"i'?m not (qualified|able|equipped) to provide (a )?response",
#         r"document (does not|doesn’t) (address|mention).*(personal|family)",
#         r"recommend (seeking|speaking|getting).*(help|support|advice)",
#         r"i can’t provide (guidance|support|advice)",
#         r"this is beyond (my|the document's) scope",
#         r"not able to help (with )?(that|this question)",
#     ]
#     return any(re.search(p, text) for p in patterns)

# def is_personal_question(question: str) -> bool:
#     personal_keywords = [
#         "father", "mother", "brother", "sister", "family", "boyfriend", "girlfriend",
#         "relationship", "love", "hate", "angry", "feel", "emotional", "personal", "sad",
#         "mental health", "feeling", "friend", "mean"
#     ]
#     return any(word in question.lower() for word in personal_keywords)

# def is_hr_query(question: str, use_fuzzy=True) -> bool:
#     keywords = [
#         "leave", "policy", "hr", "human resource", "benefits", "meeting", "procedure",
#         "onboarding", "offboarding", "sop", "salary", "promotion", "resignation",
#         "complaint", "roles", "pantry", "email etiquette", "company policy", "form",
#         "employee", "attendance", "audit", "feedback", "payroll", "document", "workflow",
#         "cover page", "quality manual", "quality procedure", "controlled copy", "uncontrolled copy"
#     ]
#     question = question.lower()
#     for kw in keywords:
#         if kw in question:
#             return True
#         if use_fuzzy and fuzz.partial_ratio(kw, question) >= 80:
#             return True
#     return False

# def is_hr_question_via_llm(query: str) -> bool:
#     prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

#     Question: "{query}"

#     Respond with only "Yes" or "No"."""
#     result = llama_pipeline.invoke(prompt)
#     return "yes" in result.content.lower()

# @app.post("/chat")
# def chat(question: Question):
#     print("❓ User Question:", question.question)
#     print("🕵️‍♀️ Chat history:", chat_history)

#     # Reject clearly personal questions only
#     if is_personal_question(question.question):
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"[❌ Rejected Personal] {datetime.now().isoformat()} - Q: {question.question}\n---\n")
#         return {
#             "answer": (
#                 "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
#                 "For personal matters, I would recommend speaking to someone you trust or seek professional help."
#             ),
#             "reference_file": None
#         }

#     try:
#         # Only retrieve docs if it's HR-related
#         is_hr_like = is_hr_query(question.question)
#         is_llm_hr = is_hr_question_via_llm(question.question)

#         docs_and_scores = []
#         if is_hr_like or is_llm_hr:
#             docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=3)
#             user_query = question.question.lower()

#             is_physical = any(term in user_query for term in ["physical meeting", "in person", "face to face", "onsite"])
#             is_digital = any(term in user_query for term in ["digital meeting", "online meeting", "virtual meeting", "zoom", "teams"])

#             if is_physical:
#                 docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "physical"]
#             elif is_digital:
#                 docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "digital"]

#         score_threshold = 0.38
#         answer = ""
#         source_file = None

#         system_prefix = (
#             "You are a professional HR assistant at Verztec.\n"
#             "Answer only using the content provided in the document — do not add anything outside of it.\n"
#             "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
#             "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
#             "Speak as if you're helping a colleague or employee in a business setting.\n"
#             "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
#             "Be clear, concise, and human — not robotic or overly formal."
#         )

#         if docs_and_scores:
#             for i, (doc, score) in enumerate(docs_and_scores):
#                 print(f"📄 Doc {i+1}:")
#                 print(f"   ↳ Title     : {doc.metadata.get('title')}")
#                 print(f"   ↳ File      : {doc.metadata.get('source')}")
#                 print(f"   ↳ Type      : {doc.metadata.get('doc_type')}")
#                 print(f"   ↳ Score     : {score:.4f}")

#             top_doc, top_score = docs_and_scores[0]
#             content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
#             source_file = top_doc.metadata.get("source", None)
#             file_path = os.path.join("data/pdfs", source_file) if source_file else ""

#             if top_score >= score_threshold and os.path.exists(file_path):
#                 if top_doc.metadata.get("doc_type") == "cover_page":
#                     title = top_doc.metadata.get("title", "this document").upper()
#                     answer = (
#                         f"Yes, I can retrieve the cover page for this document. "
#                         f"According to the document, the title is \"{title}\". "
#                         f"It includes version control sections for Controlled and Uncontrolled Copy Numbers."
#                     )
#                 else:
#                     full_prompt = (
#                         f"{system_prefix}\n"
#                         f"---\n{content}\n---\n"
#                         f"Based only on the content above, how would you answer this question?\n"
#                         f"{question.question}"
#                     )
#                     result = llama_pipeline.invoke(full_prompt)
#                     answer = truncate_answer(result.content)
#             else:
#                 print("⚠️ Score too low or file not found. Skipping source.")
#                 result = llama_pipeline.invoke(question.question)
#                 answer = truncate_answer(result.content)
#         else:
#             # For general questions like "1+1" or "what should I eat"
#             result = llama_pipeline.invoke(question.question)
#             answer = truncate_answer(result.content)

#         if is_rejection_response(answer):
#             with open("question_log.txt", "a", encoding="utf-8") as log_file:
#                 log_file.write(f"[⚠️ Rejection Tone] A: {answer}\n")

#         chat_history.append((question.question, answer))
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"{datetime.now().isoformat()} - Q: {question.question}\n")
#             log_file.write(f"A: {answer}\n")
#             if source_file:
#                 log_file.write(f"Source: {source_file}\n")
#             log_file.write("---\n")

#         return {
#             "answer": answer,
#             "reference_file": {
#                 "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
#                 "name": source_file
#             } if source_file else None
#         }

#     except Exception as e:
#         print("❌ Exception in /chat endpoint")
#         traceback.print_exc()
#         return {"answer": "Sorry, something went wrong.", "reference_file": None}

# @app.get("/")
# def index():
#     return FileResponse("static/index.html")

# @app.get("/favicon.ico")
# def favicon():
#     return FileResponse("static/favicon.ico")

# # Added this to enable reload of model after change is made - charmaine
# @app.post("/reload_vectorstore")
# def reload_vectorstore():
#     global qa_chain, vectorstore
#     print("🔄 [RELOAD] /reload_vectorstore endpoint called")
#     qa_chain, vectorstore = load_chain()
#     try:
#         doc_count = len(vectorstore.index_to_docstore_id)
#     except Exception:
#         doc_count = "unknown"
#     print(f"✅ [RELOAD] Reloaded vectorstore. Document count: {doc_count}")
#     return {"status": "reloaded", "doc_count": doc_count}





