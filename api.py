from livekit.agents import llm
from db_driver import DatabaseDriver
from typing import Optional, Dict
import re

class AssistantFnc(llm.FunctionContext):
    def __init__(self):
        super().__init__()
        self.db = DatabaseDriver()
        self.current_customer: Optional[Dict] = None
        self.current_vehicle: Optional[Dict] = None

    def extract_vin(self, text: str) -> Optional[str]:
        # VIN genellikle 17 karakter uzunluğunda alfanumerik bir koddur
        vin_pattern = r'[A-HJ-NPR-Z0-9]{17}'
        match = re.search(vin_pattern, text.upper())
        return match.group(0) if match else None

    async def lookup_customer_vehicle(self, text: str) -> str:
        vin = self.extract_vin(text)
        if not vin:
            return "VIN numarası bulunamadı. Lütfen 17 karakterli VIN numaranızı paylaşır mısınız?"

        vehicle_info = self.db.lookup_vin(vin)
        if vehicle_info:
            self.current_vehicle = vehicle_info['vehicle']
            self.current_customer = vehicle_info['customer']
            return f"Hoş geldiniz {vehicle_info['customer']['name']}! {vehicle_info['vehicle']['year']} {vehicle_info['vehicle']['make']} {vehicle_info['vehicle']['model']} aracınız için nasıl yardımcı olabilirim?"
        else:
            return "Bu VIN numarasına ait kayıt bulamadım. Yeni profil oluşturmak ister misiniz?"

    async def create_profile(self, text: str) -> str:
        # Basit bir profil oluşturma diyaloğu
        if not hasattr(self, 'profile_state'):
            self.profile_state = 'name'
            return "Yeni profil oluşturmak için adınızı ve soyadınızı alabilir miyim?"

        if self.profile_state == 'name':
            self.temp_name = text
            self.profile_state = 'contact'
            return "Teşekkürler! Telefon numaranızı veya e-posta adresinizi alabilir miyim?"

        if self.profile_state == 'contact':
            # E-posta veya telefon numarası kontrolü
            email_pattern = r'[^@]+@[^@]+\.[^@]+'
            phone_pattern = r'\d{10,11}'
            
            email = re.search(email_pattern, text)
            phone = re.search(phone_pattern, text)
            
            customer_id = self.db.create_customer(
                name=self.temp_name,
                phone=phone.group(0) if phone else None,
                email=email.group(0) if email else None
            )
            
            self.profile_state = 'vehicle'
            return "Harika! Şimdi aracınızın bilgilerini alalım. Lütfen aracınızın markasını söyler misiniz?"

        if self.profile_state == 'vehicle':
            self.temp_make = text
            self.profile_state = 'model'
            return "Aracınızın modelini alabilir miyim?"

        if self.profile_state == 'model':
            self.temp_model = text
            self.profile_state = 'year'
            return "Son olarak aracınızın üretim yılını söyler misiniz?"

        if self.profile_state == 'year':
            try:
                year = int(re.search(r'\d{4}', text).group(0))
                vin = f"TEMP{str(year).zfill(13)}"
                vehicle_id = self.db.create_vehicle(
                    vin=vin,
                    make=self.temp_make,
                    model=self.temp_model,
                    year=year,
                    customer_id=customer_id
                )
                
                delattr(self, 'profile_state')
                delattr(self, 'temp_name')
                delattr(self, 'temp_make')
                delattr(self, 'temp_model')
                
                return f"Profil başarıyla oluşturuldu! Size nasıl yardımcı olabilirim?"
            except:
                return "Üzgünüm, yılı anlayamadım. Lütfen yyyy formatında tekrar söyler misiniz?"

    async def get_service_history(self) -> str:
        if not self.current_vehicle:
            return "Önce araç bilgilerinizi kontrol etmeliyim. VIN numaranızı paylaşır mısınız?"

        history = self.db.get_service_history(self.current_vehicle['id'])
        if not history:
            return "Aracınız için henüz servis kaydı bulunmuyor."

        response = "İşte aracınızın servis geçmişi:\n"
        for service in history:
            response += f"- {service['date']}: {service['type']} - {service['description']}\n"
        return response

    async def schedule_service(self, service_type: str, description: str) -> str:
        if not self.current_vehicle:
            return "Önce araç bilgilerinizi kontrol etmeliyim. VIN numaranızı paylaşır mısınız?"

        self.db.add_service_history(
            vehicle_id=self.current_vehicle['id'],
            service_type=service_type,
            description=description
        )
        return f"Servis kaydınız oluşturuldu! {service_type} için randevunuz alındı."