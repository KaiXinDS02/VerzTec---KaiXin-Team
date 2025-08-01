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
import enchant
from textblob import Word


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

# def truncate_answer(answer, max_words=MAX_ANSWER_WORDS): # changed
#     words = answer.split()
#     if len(words) <= max_words:
#         return answer
#     truncated = " ".join(words[:max_words])
#     # Cut at the last complete sentence
#     sentences = re.split(r'(?<=[.!?]) +', truncated)
#     return " ".join(sentences[:-1]) + "..."

def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
    words = answer.split()
    if len(words) <= max_words:
        return answer
    truncated = " ".join(words[:max_words])
    sentences = re.split(r'(?<=[.!?]) +', truncated)
    clean = " ".join(sentences[:-1])
    return clean if clean.strip() else truncated + "..."

def format_answer_if_needed(answer: str) -> str:
    lines = answer.strip().splitlines()
    formatted_lines = []
    bullet_started = False

    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue

        # ✅ Step 1: If line ends with ":", treat as bold paragraph heading (not a bullet)
        if re.match(r".*:$", stripped):
            if bullet_started:
                formatted_lines.append("</ul>")
                bullet_started = False
            formatted_lines.append(f"<p><strong>{stripped}</strong></p>")
            continue

        # # ✅ Step 2: Bullet detection
        # if re.match(r"^(\s*[\u2022\-•*]+\s+)", stripped):
        #     if not bullet_started:
        #         formatted_lines.append("<ul>")
        #         bullet_started = True
        #     formatted_lines.append(f"<li>{stripped.lstrip('•-*').strip()}</li>")

        # ✅ Step 2: Bullet detection (handles •, -, *, +)
        if re.match(r"^(\s*[\u2022\-\*\+•]\s+)", stripped):  # Accepts + and * too
            if not bullet_started:
                formatted_lines.append("<ul>")
                bullet_started = True
            # Remove any leading bullet symbols or whitespace
            cleaned = re.sub(r"^[\u2022\-\*\+•]\s*", "", stripped)
            formatted_lines.append(f"<li>{cleaned}</li>")

        else:
            if bullet_started:
                formatted_lines.append("</ul>")
                bullet_started = False
            formatted_lines.append(f"<p>{stripped}</p>")

    if bullet_started:
        formatted_lines.append("</ul>")

    return "\n".join(formatted_lines)

# English dictionary
english_dict = enchant.Dict("en_US")

def correct_spelling(text: str) -> str:
    corrected_words = []
    for word in text.split():
        original = word
        # Check if word is not in dictionary AND longer than 3 characters
        if not english_dict.check(original) and len(word) > 3:
            corrected = str(Word(original).correct())
            # Only apply if the correction is actually different
            if original.lower() != corrected.lower():
                corrected_words.append(corrected)
                continue
        corrected_words.append(original)
    corrected_text = " ".join(corrected_words)
    print(f"📄 Original: {text} → Corrected: {corrected_text}")
    return corrected_text

# def correct_spelling(text: str) -> str:
#     from textblob import TextBlob
#     corrected_words = [str(TextBlob(word).correct()) for word in text.split()]
#     corrected = " ".join(corrected_words)
#     print(f"📄 Original: {text} → Corrected: {corrected}")
#     return corrected

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

def is_hr_query(question: str, threshold: int = 60) -> bool:
    keywords = [
        "leave", "pantry", "benefit", "policy", "claim", "resign",
        "probation", "bonus", "medical", "organisation", "birthday",
        "working hours", "appraisal", "termination", "staff"
    ]
    question = question.lower().strip()
    
    # Use both partial_ratio and token_sort_ratio
    best_score = max(
        max(
            fuzz.partial_ratio(kw, question),
            fuzz.token_sort_ratio(kw, question)
        )
        for kw in keywords
    )
    
    print(f"🔍 Best HR keyword fuzzy match score: {best_score}")
    return best_score >= threshold

def is_non_hr_topic(question: str) -> bool:
    non_hr_keywords = [
        "visa", "tourist", "immigration", "passport", "flight", "travel", "hotel",
        "booking", "application for country", "apply for visa", "overseas trip",
        "embassy", "moe", "moh", "housing", "hdb", "singpass", "government", "bank"
    ]
    return any(kw in question.lower() for kw in non_hr_keywords)


def is_org_chart_question(question: str) -> bool:
    keywords = [
        "org chart", "organization chart", "organisation chart", "company structure", "organisational chart",
        "organizational structure", "organisational structure", "reporting structure", "organizational chart"
        "hierarchy", "reporting line", "team structure", "how is the company structured"
    ]
    question = question.lower()
    return any(kw in question for kw in keywords) or fuzz.partial_ratio("org chart", question) > 80

def staff_new_leave(question: str) -> bool:
    keywords = [
        "new staff", "new employee", "new employee leave",
        "new staff leave", "leave for new staff", "probation leave"
    ]
    question = question.lower()
    return any(kw in question for kw in keywords) or fuzz.partial_ratio("how many days of leave", question) > 80


def is_hr_question_via_llm(query: str) -> bool:
    prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

    Question: "{query}"

    Respond with only "Yes" or "No"."""
    result = llama_pipeline.invoke(prompt)
    return "yes" in result.content.lower()

def is_retrieval_question(question: str) -> bool:
    retrieval_phrases = ["retrieve", "get me", "where is", "download", "fetch", "access", "view", "give me"]
    return any(p in question.lower() for p in retrieval_phrases)


def is_generic_or_restricted_response(answer, threshold=85):
    normalized = answer.strip().lower()
    generic_phrases = [
        "i'm sorry",
        "i can't provide advice",
        "i don't have the information",
        "based on the documents i have access to, i don't have the information",
        "you may want to contact the hr department at hr@verztec.com for further assistance."
    ]
    
    for phrase in generic_phrases:
        if phrase in normalized:
            return True
        if fuzz.partial_ratio(phrase, normalized) >= threshold:
            return True
    return False

def is_greeting(question: str) -> bool:
    greetings = ["hi", "hello", "hey", "good morning", "good afternoon", "good evening"]
    question = question.lower().strip()
    return any(question == g or question.startswith(g + " ") for g in greetings)

# Formatting
def bold_intro_to_bullets(text: str) -> str:
    lines = text.strip().split('\n')
    updated_lines = []

    for i in range(len(lines)):
        current = lines[i].strip()
        next_line = lines[i + 1].strip() if i + 1 < len(lines) else ""

        # Check if the next line starts with a bullet and current line is not already bolded
        if re.match(r"^\s*[\u2022\-\*\+]\s+", next_line) and "<strong>" not in current:
            if current.endswith(":"):
                # Bold full intro line that ends with colon
                # current = f"<strong>{current}</strong>"
                # Only bold the portion before the colon (e.g., "Key responsibilities")
                bold_part = current.split(":")[0]
                remaining = current[len(bold_part) + 1:].strip()

                if remaining:
                    current = f"<strong>{bold_part}</strong>: {remaining}"
                else:
                    current = f"<strong>{bold_part}</strong>"


            elif ";" in current:
                # Bold up to semicolon if exists
                bold_part = current.split(";")[0]
                remaining = current[len(bold_part) + 1:].strip()
                current = f"<strong>{bold_part};</strong> {remaining}"

            else:
                words = current.split()
                if len(words) <= 5:
                    current = f"<strong>{current}</strong>"
                else:
                    bold_part = " ".join(words[:5])
                    remaining = " ".join(words[5:])
                    current = f"<strong>{bold_part}</strong> {remaining}"

        updated_lines.append(current)

    return "\n".join(updated_lines)

def convert_markdown_to_html(text: str) -> str: #new
    # Convert **bold**
    text = re.sub(r"\*\*(.*?)\*\*", r"<strong>\1</strong>", text)
    # Convert *italic*
    text = re.sub(r"\*(.*?)\*", r"<em>\1</em>", text)
    text = text.replace("$", "&#36;")  # ← This escapes all "$"
    return text

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
    original_question = question.question
    question.question = correct_spelling(original_question)

    if is_greeting(question.question):
        return {
            "answer": (
                "<br>Hello! I'm Verztec's AI Assistant. 👋<br>"
                "I'm here to help you with HR-related queries such as company policies, employee benefits, retrieval of doucuments, and more."
                " Just ask your question and I’ll do my best to assist!"
            ),
            "reference_file": None
        }
    
    # Special case for organization chart question
    if is_org_chart_question(question.question):
        file_name = "2_QUALITY MANUAL-revised Jan 2016-rev NOV 2016-020924.docx"

        return {
            "answer": (
                "<br>The company’s organizational structure is outlined in "
                "<strong>Section QM-06: Organization Structure</strong>. "
                "It provides a visual overview of Verztec Consulting Pte Ltd's key departments and reporting lines across the organization."
            ),

             "reference_file": {
                "url": f"http://localhost:8000/pdfs/{quote(file_name)}",
                "name": file_name
            }
        }
           
    if is_non_hr_topic(question.question):
        return {
            "answer": (
                "I'm sorry, I can only assist with Verztec's HR-related questions.  "
                "Please ensure your queries are related to HR matters. "
                "You may want to contact the HR department at <strong>HR@verztec.com</strong> for further assistance."
            ),
            "reference_file": None
    }

    if is_personal_question(question.question):
        return {
            "answer": (
                "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
                "For personal matters, I would recommend speaking to someone you trust or seek professional help."
            ),
            "reference_file": None
        }

    # Special case for leave entitlement
    if staff_new_leave(question.question):
        file_name = "leave_policy.docx"

        raw_answer = (
            "As a new employee, you are entitled to 14 days of leave for full-time employees who have been with the company for less than 3 years."
            " However, please note that during your probationary period (usually the first 3 months), you will not be eligible for paid leave or sick leave.\n\n"
            "**Here's a breakdown of the leave entitlements for new employees:**\n"
            "• For employees under 3 years: 14 days\n"
            "• During probationary period (first 3 months): No paid leave or sick leave\n"
            "• Birthday leave: You may apply for it after 2 months from your join date, during your birthday month or on your actual birthday\n\n"
            "Please check with your department Manager/Supervisor for specific details and ensure that your leave doesn't clash with team members."
        )

        #  Apply formatting pipeline (duplicate here even if repeated below)
        formatted = format_answer_if_needed(raw_answer)
        intro_bolded = bold_intro_to_bullets(formatted)
        truncated = truncate_answer(intro_bolded)
        answer = convert_markdown_to_html(truncated)

        return {
            "answer": answer,
            "reference_file": {
                "url": f"http://localhost:8000/pdfs/{quote(file_name)}",
                "name": file_name
            }
        }

    # ✅ If it's a retrieval request, proceed without HR check
    if is_retrieval_question(question.question):
        pass  # Let it proceed to vectorstore and fallback handling

    # ❌ Block unrelated non-HR queries
    # HR intent check (outside try)
    # ❌ Block unrelated non-HR queries
    print("📌 Calling is_hr_query() and is_hr_question_via_llm()...")
    is_hr_like = is_hr_query(question.question)
    is_llm_hr = is_hr_question_via_llm(question.question)

    # Reject unrelated queries early
    if not is_hr_like and not is_llm_hr:
        return {
            "answer": (
                "I'm sorry, I can only assist with Verztec's HR-related questions. "
                "Please ensure your queries are related to HR matters. "
                "You may want to contact the HR department at <strong>HR@verztec.com</strong> for further assistance."
            ),
            "reference_file": None
        }

    
    # Continue with document retrieval and answering
    try:
        docs_and_scores = []
        if is_hr_like or is_llm_hr:
            # Get top 5 candidates to increase chance of matching cover page
            docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=5)

            # ✅ Filter to prefer cover pages if asking about "cover page"
            if "cover page" in question.question.lower():
                cover_docs = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "cover_page"]
                if cover_docs:
                    docs_and_scores = cover_docs



##################################🔐 START - RBAC (Charmaine)##################################
            
            # Retrieve the user's role and country based on their user ID
            role, user_country = get_user_role_and_country(question.user_id)

            # Apply RBAC if the user is not an ADMIN
            if role != "ADMIN":
                filtered_docs = []
                rbac_filtered_out = False  # Flag to track if any relevant docs were filtered out due to access restrictions

                user_country_norm = (user_country or "").strip().upper()  # Normalize user's country code (e.g., 'SG')

                for doc, score in docs_and_scores:
                    countries_metadata = (doc.metadata.get("countries") or "").strip().upper()

                    allowed = False

                    if countries_metadata == "ALL":
                        allowed = True
                    elif user_country_norm in countries_metadata.split():
                        allowed = True

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
            "If the question is about policy (e.g., leave, claims), always give a concise summary followed by a short bullet list if the document contains specific breakdowns or exceptions."
            "Answer only using the content provided in the document — do not add anything outside of it.\n"
            "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
            "Where the document lists multiple steps or actions, present them as bullet points using clear formatting (e.g., • or <li>)."
            "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
            "Speak as if you're helping a colleague or employee in a business setting.\n"
            "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
            "Be clear, concise, and human — not robotic or overly formal not robotic, overly formal, or speculative.\n."
            "Avoid saying 'I would retrieve' or speculating about documents. Instead, use confident phrasing like 'Yes, I can retrieve' or simply start summarizing directly.\n"
            "Do not repeat that the document is relevant or restate what was asked. Focus only on answering the question based on the content.\n"
            # "Do not repeat visual diagrams or organization charts by listing roles or positions.\n"
            # "If referring to a chart, summarize its purpose (e.g., department structure or reporting lines) rather than copying its content."

        )

        reference_file = None
        if docs_and_scores:
            top_doc, top_score = docs_and_scores[0]
            content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
            source_file = top_doc.metadata.get("source", None)
            file_path = os.path.join("data/pdfs", source_file) if source_file else ""

            # 🚫 Reject if query terms do not meaningfully overlap with top document
            query_terms = set(re.findall(r'\w+', question.question.lower()))
            doc_terms = set(re.findall(r'\w+', top_doc.page_content.lower()))
            overlap = query_terms & doc_terms

            if len(overlap) < 2:
                return {
                    "answer": (
                        "Sorry, based on the documents I have access to, I don't have the information to answer your question. "
                        "You may want to contact HR at <strong>HR@verztec.com</strong>."
                    ),
                    "reference_file": None
                }

            # 💡 Perform score and file existence check
            if top_score < score_threshold or not os.path.exists(file_path):
                return {
                    "answer": (
                        "Sorry, based on the documents I have access to, I don't have the information to answer your question "
                        "You may want to contact HR at <strong>HR@verztec.com</strong>."
                    ),
                    "reference_file": None
                }

            # ✅ Passed checks — continue
            full_prompt = f"{system_prefix}\n---\n{content}\n---\nBased only on the content above, how would you answer this question?\n{question.question}"

            # 🔍 Special handling for cover pages
            # if top_doc.metadata.get("doc_type") == "cover_page":
            #     content_snippet = top_doc.page_content.lower()
            doc_type = top_doc.metadata.get("doc_type", "")
            content_snippet = top_doc.page_content.lower()
            question_lower = question.question.lower()

            if (
                doc_type == "cover_page" or
                ("cover page" in question_lower and any(kw in content_snippet for kw in ["quality manual", "quality procedure"]))
            ):


                if "quality manual" in content_snippet:
                    answer = (
                        "Yes, I can retrieve the cover page.\n"
                        "It is titled \"QUALITY MANUAL\" and includes Verztec’s corporate address and a confidentiality notice. "
                        "The document also has placeholders for both Controlled Copy Number and Uncontrolled Copy Number to track versioning."
                    )
                elif "quality procedure" in content_snippet:
                    answer = (
                        "Yes, I can retrieve the cover page.\n"
                        "The title of the document is \"QUALITY PROCEDURE\", and it contains Verztec’s business address, "
                        "a proprietary use disclaimer, and version control sections for Controlled and Uncontrolled copies."
                    )
                else:
                    answer = (
                        "Yes, I can retrieve the cover page. "
                        "It includes version control sections for both Controlled and Uncontrolled Copy Numbers."
                    )
            else:
                result = llama_pipeline.invoke(full_prompt) # changed
                
                raw_answer = result.content
                formatted = format_answer_if_needed(raw_answer)     # wrap bullets first
                intro_bolded = bold_intro_to_bullets(formatted)     # bold intros after bullets
                truncated  = truncate_answer(intro_bolded)              # trim at the end
                answer = convert_markdown_to_html(truncated)


                # intro_bolded = bold_intro_to_bullets(raw_answer)        # bold main intro lines
                # formatted = format_answer_if_needed(intro_bolded)       # wrap bullets & paragraphs
                # answer = truncate_answer(formatted)                     # trim long responses last

            reference_file = None
            if not is_generic_or_restricted_response(answer.strip()):
                reference_file = {
                    "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
                    "name": source_file
                }

        
        # Fallback when vectorstore returns no documents at all
        if not docs_and_scores:
            if is_retrieval_question(question.question):
                return {
                    "answer": (
                        "Sorry, I couldn’t retrieve the document you’re referring to. "
                        "It may not exist or I don’t have access to it. "
                        "You may want to contact HR at <strong>HR@verztec.com</strong> for assistance."
                    ),
                    "reference_file": None
                }
            else:
                return {
                    "answer": (
                        "I'm sorry, I couldn’t find any relevant document to answer your question. "
                        "You may want to contact HR at <strong>HR@verztec.com</strong>."
                    ),
                    "reference_file": None
                }


        # ✅ Save and return response
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

