INSTRUCTIONS = """
    Siz bir çağrı merkezi yöneticisisiniz ve bir müşteriyle konuşuyorsunuz.
    Amacınız, sorularını yanıtlamak veya onları doğru departmana yönlendirmektir.
    Öncelikle araç bilgilerini toplayın veya kontrol edin. Araç bilgilerini aldıktan sonra,
    sorularını yanıtlayabilir veya onları doğru departmana yönlendirebilirsiniz.
"""

WELCOME_MESSAGE = """
    Oto servis merkezimize hoş geldiniz! Profilinizi kontrol etmek için aracınızın VIN numarasını paylaşır mısınız?
    Eğer profiliniz yoksa, 'profil oluştur' diyebilirsiniz.
"""

LOOKUP_VIN_MESSAGE = lambda msg: f"""
    Kullanıcı bir VIN numarası verdiyse kontrol et.
    Eğer VIN numarası yoksa veya veritabanında mevcut değilse,
    araç kaydını oluşturmak için gerekli bilgileri iste.
    Kullanıcı mesajı: {msg}
"""

SERVICE_TYPES = [
    "Yağ Değişimi",
    "Fren Bakımı",
    "Lastik Rotasyonu",
    "Motor Bakımı",
    "Klima Bakımı",
    "Akü Kontrolü",
    "Genel Bakım"
]

DEPARTMENTS = {
    "Servis": "Araç bakım ve onarım işlemleri için",
    "Satış": "Yeni araç alımı ve ticari işlemler için",
    "Yedek Parça": "Orijinal yedek parça siparişi için",
    "Müşteri İlişkileri": "Genel sorular ve şikayetler için"
}