from __future__ import annotations
from livekit.agents import(
    AutoSubscribe,
    JobContext,
    WorkerOptions,
    cli,
    llm
)
from livekit.plugins import openai, silero
from dotenv import load_dotenv
from api import AssistantFnc
from prompts import WELCOME_MESSAGE, INSTRUCTIONS, LOOKUP_VIN_MESSAGE, SERVICE_TYPES, DEPARTMENTS
import os

load_dotenv()

async def process_user_input(text: str, assistant: AssistantFnc, session) -> str:
    # VIN arama veya profil oluşturma
    if 'profil oluştur' in text.lower() or 'yeni profil' in text.lower():
        return await assistant.create_profile(text)
    
    # Servis geçmişi sorgulama
    if 'servis geçmişi' in text.lower() or 'geçmiş servisler' in text.lower():
        return await assistant.get_service_history()
    
    # Servis randevusu oluşturma
    for service_type in SERVICE_TYPES:
        if service_type.lower() in text.lower():
            return await assistant.schedule_service(service_type, text)
    
    # Departman yönlendirmesi
    for dept, desc in DEPARTMENTS.items():
        if dept.lower() in text.lower():
            return f"{dept} departmanına yönlendiriliyorsunuz. {desc}"
    
    # VIN arama
    return await assistant.lookup_customer_vehicle(text)

async def entrypoint(ctx: JobContext):
    await ctx.connect(auto_subscribe=AutoSubscribe.SUBSCRIBE_ALL)
    await ctx.wait_for_participants()
    
    # Ses modeli ayarları
    voice_model = silero.SileroTTS(
        language="tr",
        speaker="gokce",  # Türkçe kadın sesi
        sample_rate=24000
    )
    
    # OpenAI modeli ayarları
    model = openai.realtime.RealtimeOpenAI(
        instructions=INSTRUCTIONS,
        voice=voice_model,
        temperature=0.8,
        modalities=["text", "audio"],
    )
    
    # Asistan başlatma
    assistant = AssistantFnc()
    assistant.start(ctx.room)
    
    # Oturum başlatma ve karşılama mesajı
    session = model.session[0]
    session.conversation.item.create(
        llm.ChatMessage(
            role="assistant",
            content=WELCOME_MESSAGE
        )
    )
    
    # Kullanıcı mesajlarını dinleme ve yanıtlama
    async for message in session.conversation.stream():
        if message.role == "user":
            response = await process_user_input(message.content, assistant, session)
            session.conversation.item.create(
                llm.ChatMessage(
                    role="assistant",
                    content=response
                )
            )
            session.response.create()

if __name__ == "__main__":
    cli.run(entrypoint, WorkerOptions(name="auto-service-assistant"))
