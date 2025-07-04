# rag_chain.py

from langchain.chains import ConversationalRetrievalChain
from langchain_community.vectorstores import FAISS
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain.memory import ConversationBufferMemory
from chatbot.llm_loader import llama_pipeline
from chatbot.config import EMBEDDING_MODEL_NAME, VECTORSTORE_DIR

def load_chain():
    vectorstore = FAISS.load_local(
        folder_path=VECTORSTORE_DIR,
        embeddings=HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME),
        index_name="index",
        allow_dangerous_deserialization=True
    )

    retriever = vectorstore.as_retriever(
        search_type="similarity",  # change from score_threshold to pure similarity
        search_kwargs={"k": 3}
    )

    memory = ConversationBufferMemory(
        memory_key="chat_history",
        return_messages=True,
        output_key="answer"
    )

    qa_chain = ConversationalRetrievalChain.from_llm(
        llm=llama_pipeline,
        retriever=retriever,
        memory=memory,
        return_source_documents=True,
        output_key="answer"
    )

    return qa_chain, vectorstore  # Return both


# from langchain.chains import ConversationalRetrievalChain
# from langchain_community.vectorstores import FAISS
# from langchain_community.embeddings import HuggingFaceEmbeddings
# from langchain.memory import ConversationBufferMemory
# from chatbot.llm_loader import llama_pipeline
# # from config import EMBEDDING_MODEL_NAME, VECTORSTORE_DIR
# from chatbot.config import EMBEDDING_MODEL_NAME, VECTORSTORE_DIR

# def load_chain():
#     vectorstore = FAISS.load_local(
#         folder_path=VECTORSTORE_DIR,
#         embeddings=HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL_NAME),
#         index_name="index",
#         allow_dangerous_deserialization=True
#     )

#     memory = ConversationBufferMemory(
#         memory_key="chat_history",
#         return_messages=True,
#         output_key="answer"
#     )

#     qa_chain = ConversationalRetrievalChain.from_llm(
#         llm=llama_pipeline,
#         retriever=vectorstore.as_retriever(
#             search_type="similarity_score_threshold",
#             search_kwargs={"score_threshold": 0.6, "k": 3}
#         ),
#         memory=memory,
#         return_source_documents=True,
#         output_key="answer"
#     )

#     return qa_chain



