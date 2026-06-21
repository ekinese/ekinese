/**
 * XGOUD i18n – übersetzt die UI (NL-Quelltext → DE/EN/FR/ES/IT/TR/PL) ohne die
 * Widgets umzubauen: eine DOM-Schicht ersetzt bekannte Strings und beobachtet
 * dynamisch nachgerenderte Knoten (Calculator, Chat, …).
 *
 * Wörterbuch nach NL-Quelltext. Fehlt ein Eintrag → bleibt Niederländisch.
 */
(function () {
	'use strict';

	// Reihenfolge der Sprachwerte: de, en, fr, es, it, tr, pl
	var L = ['de', 'en', 'fr', 'es', 'it', 'tr', 'pl'];
	function d() { var o = {}; for (var i = 0; i < L.length; i++) o[L[i]] = arguments[i]; return o; }

	var DICT = {
		'Berekenen': d('Berechnen', 'Calculate', 'Calculer', 'Calcular', 'Calcola', 'Hesapla', 'Oblicz'),
		'Selectie': d('Auswahl', 'Selection', 'Sélection', 'Selección', 'Selezione', 'Seçim', 'Wybór'),
		'Product': d('Produkt', 'Product', 'Produit', 'Producto', 'Prodotto', 'Ürün', 'Produkt'),
		'Details': d('Details', 'Details', 'Détails', 'Detalles', 'Dettagli', 'Ayrıntılar', 'Szczegóły'),
		'Resultaat': d('Ergebnis', 'Result', 'Résultat', 'Resultado', 'Risultato', 'Sonuç', 'Wynik'),
		'Gegevens': d('Daten', 'Details', 'Coordonnées', 'Datos', 'Dati', 'Bilgiler', 'Dane'),
		'Service': d('Service', 'Service', 'Service', 'Servicio', 'Servizio', 'Hizmet', 'Usługa'),
		'Uitbetaling': d('Auszahlung', 'Payout', 'Paiement', 'Pago', 'Pagamento', 'Ödeme', 'Wypłata'),
		'Goede doel': d('Guter Zweck', 'Charity', 'Bonne cause', 'Buena causa', 'Beneficenza', 'Hayır', 'Cel charytatywny'),
		'Klaar': d('Fertig', 'Done', 'Terminé', 'Listo', 'Fatto', 'Tamam', 'Gotowe'),
		'Live prijzen': d('Live-Preise', 'Live prices', 'Prix en direct', 'Precios en vivo', 'Prezzi live', 'Canlı fiyatlar', 'Ceny na żywo'),
		'Wat wilt u verkopen?': d('Was möchten Sie verkaufen?', 'What would you like to sell?', 'Que souhaitez-vous vendre ?', '¿Qué desea vender?', 'Cosa vuoi vendere?', 'Ne satmak istersiniz?', 'Co chcesz sprzedać?'),
		'Kies een categorie – wij leiden u naar de prijs.': d('Wählen Sie eine Kategorie – wir führen Sie zum Preis.', 'Choose a category – we guide you to the price.', 'Choisissez une catégorie – nous vous guidons vers le prix.', 'Elija una categoría: le guiamos hasta el precio.', 'Scegli una categoria – ti guidiamo al prezzo.', 'Bir kategori seçin – sizi fiyata yönlendirelim.', 'Wybierz kategorię – poprowadzimy Cię do ceny.'),
		'Edelmetaal': d('Edelmetall', 'Precious metal', 'Métal précieux', 'Metal precioso', 'Metallo prezioso', 'Değerli metal', 'Metal szlachetny'),
		'Diamant': d('Diamant', 'Diamond', 'Diamant', 'Diamante', 'Diamante', 'Pırlanta', 'Diament'),
		'Edelsteen': d('Edelstein', 'Gemstone', 'Pierre précieuse', 'Piedra preciosa', 'Pietra preziosa', 'Değerli taş', 'Kamień szlachetny'),
		'Horloge': d('Uhr', 'Watch', 'Montre', 'Reloj', 'Orologio', 'Saat', 'Zegarek'),
		'Prijs berekenen': d('Preis berechnen', 'Calculate price', 'Calculer le prix', 'Calcular precio', 'Calcola il prezzo', 'Fiyatı hesapla', 'Oblicz cenę'),
		'Gewicht': d('Gewicht', 'Weight', 'Poids', 'Peso', 'Peso', 'Ağırlık', 'Waga'),
		'Metaal': d('Metall', 'Metal', 'Métal', 'Metal', 'Metallo', 'Metal', 'Metal'),
		'Vorm': d('Form', 'Form', 'Forme', 'Forma', 'Forma', 'Biçim', 'Forma'),
		'Staat': d('Zustand', 'Condition', 'État', 'Estado', 'Stato', 'Durum', 'Stan'),
		'Kleur': d('Farbe', 'Color', 'Couleur', 'Color', 'Colore', 'Renk', 'Kolor'),
		'Zuiverheid': d('Reinheit', 'Clarity', 'Pureté', 'Pureza', 'Purezza', 'Berraklık', 'Czystość'),
		'Karaatgewicht': d('Karatgewicht', 'Carat weight', 'Poids en carats', 'Peso en quilates', 'Peso in carati', 'Karat ağırlığı', 'Masa w karatach'),
		'Merk': d('Marke', 'Brand', 'Marque', 'Marca', 'Marca', 'Marka', 'Marka'),
		'Model': d('Modell', 'Model', 'Modèle', 'Modelo', 'Modello', 'Model', 'Model'),
		'Marktprijs': d('Marktpreis', 'Market price', 'Prix du marché', 'Precio de mercado', 'Prezzo di mercato', 'Piyasa fiyatı', 'Cena rynkowa'),
		'Marge': d('Marge', 'Margin', 'Marge', 'Margen', 'Margine', 'Marj', 'Marża'),
		'Vaste prijs': d('Festpreis', 'Fixed price', 'Prix fixe', 'Precio fijo', 'Prezzo fisso', 'Sabit fiyat', 'Cena stała'),
		'Prijsindicatie': d('Preisindikation', 'Price indication', 'Estimation', 'Indicación de precio', 'Stima del prezzo', 'Fiyat göstergesi', 'Orientacyjna cena'),
		'Aan selectie toevoegen': d('Zur Auswahl hinzufügen', 'Add to selection', 'Ajouter à la sélection', 'Añadir a la selección', 'Aggiungi alla selezione', 'Seçime ekle', 'Dodaj do wyboru'),
		'Nog een product berekenen': d('Weiteres Produkt berechnen', 'Calculate another product', 'Calculer un autre produit', 'Calcular otro producto', 'Calcola un altro prodotto', 'Başka ürün hesapla', 'Oblicz kolejny produkt'),
		'Afspraak maken & verkopen': d('Termin vereinbaren & verkaufen', 'Book appointment & sell', 'Prendre rendez-vous et vendre', 'Reservar cita y vender', 'Prenota e vendi', 'Randevu al & sat', 'Umów się i sprzedaj'),
		'Nog een product toevoegen': d('Weiteres Produkt hinzufügen', 'Add another product', 'Ajouter un autre produit', 'Añadir otro producto', 'Aggiungi un altro prodotto', 'Başka ürün ekle', 'Dodaj kolejny produkt'),
		'Uw contactgegevens': d('Ihre Kontaktdaten', 'Your contact details', 'Vos coordonnées', 'Sus datos de contacto', 'I tuoi dati', 'İletişim bilgileriniz', 'Twoje dane kontaktowe'),
		'Voornaam': d('Vorname', 'First name', 'Prénom', 'Nombre', 'Nome', 'Ad', 'Imię'),
		'Achternaam': d('Nachname', 'Last name', 'Nom', 'Apellido', 'Cognome', 'Soyad', 'Nazwisko'),
		'E-mail': d('E-Mail', 'Email', 'E-mail', 'Correo', 'E-mail', 'E-posta', 'E-mail'),
		'Telefoon': d('Telefon', 'Phone', 'Téléphone', 'Teléfono', 'Telefono', 'Telefon', 'Telefon'),
		'Stad': d('Stadt', 'City', 'Ville', 'Ciudad', 'Città', 'Şehir', 'Miasto'),
		'Verder': d('Weiter', 'Next', 'Suivant', 'Siguiente', 'Avanti', 'İleri', 'Dalej'),
		'← Terug': d('← Zurück', '← Back', '← Retour', '← Atrás', '← Indietro', '← Geri', '← Wstecz'),
		'Hoe wilt u verkopen?': d('Wie möchten Sie verkaufen?', 'How would you like to sell?', 'Comment souhaitez-vous vendre ?', '¿Cómo desea vender?', 'Come vuoi vendere?', 'Nasıl satmak istersiniz?', 'Jak chcesz sprzedać?'),
		'Bezoek aan huis': d('Hausbesuch', 'Home visit', 'Visite à domicile', 'Visita a domicilio', 'Visita a domicilio', 'Eve ziyaret', 'Wizyta domowa'),
		'In een vestiging': d('In einer Filiale', 'At a branch', 'En agence', 'En una oficina', 'In filiale', 'Şubede', 'W oddziale'),
		'Ophaalservice': d('Abhol-Service', 'Pickup service', 'Service de retrait', 'Servicio de recogida', 'Servizio di ritiro', 'Alma servisi', 'Usługa odbioru'),
		'Afspraak aanvragen': d('Termin anfragen', 'Request appointment', 'Demander un rendez-vous', 'Solicitar cita', 'Richiedi appuntamento', 'Randevu iste', 'Poproś o termin'),
		'Hartelijk dank!': d('Vielen Dank!', 'Thank you!', 'Merci beaucoup !', '¡Muchas gracias!', 'Grazie!', 'Teşekkürler!', 'Dziękujemy!'),
		'Nieuwe berekening starten': d('Neue Berechnung starten', 'Start new calculation', 'Nouveau calcul', 'Nuevo cálculo', 'Nuovo calcolo', 'Yeni hesaplama', 'Nowe obliczenie'),
		'XGOUD assistent': d('XGOUD Assistent', 'XGOUD assistant', 'Assistant XGOUD', 'Asistente XGOUD', 'Assistente XGOUD', 'XGOUD asistanı', 'Asystent XGOUD'),
		'Typ uw bericht…': d('Ihre Nachricht…', 'Type your message…', 'Votre message…', 'Escriba su mensaje…', 'Scrivi il messaggio…', 'Mesajınızı yazın…', 'Wpisz wiadomość…'),
		'Online — we reageren direct': d('Online – wir antworten sofort', 'Online — we reply right away', 'En ligne — réponse immédiate', 'En línea — respondemos al instante', 'Online — rispondiamo subito', 'Çevrimiçi — hemen yanıtlıyoruz', 'Online — odpowiadamy od razu'),
		'Offline — laat een bericht achter': d('Offline – hinterlassen Sie eine Nachricht', 'Offline — leave a message', 'Hors ligne — laissez un message', 'Sin conexión — deje un mensaje', 'Offline — lascia un messaggio', 'Çevrimdışı — mesaj bırakın', 'Offline — zostaw wiadomość'),
		'Aanmelden': d('Anmelden', 'Subscribe', "S'inscrire", 'Suscribirse', 'Iscriviti', 'Abone ol', 'Zapisz się'),
		'Uw e-mailadres': d('Ihre E-Mail-Adresse', 'Your email address', 'Votre adresse e-mail', 'Su correo electrónico', 'La tua e-mail', 'E-posta adresiniz', 'Twój adres e-mail'),
		'Stad (optioneel)': d('Stadt (optional)', 'City (optional)', 'Ville (facultatif)', 'Ciudad (opcional)', 'Città (facoltativo)', 'Şehir (isteğe bağlı)', 'Miasto (opcjonalnie)'),
		'Blijf op de hoogte': d('Bleiben Sie informiert', 'Stay informed', 'Restez informé', 'Manténgase informado', 'Resta aggiornato', 'Haberdar olun', 'Bądź na bieżąco'),
		'Onze kantoren': d('Unsere Filialen', 'Our offices', 'Nos agences', 'Nuestras oficinas', 'Le nostre sedi', 'Şubelerimiz', 'Nasze biura'),
		'Hoofdkantoor': d('Hauptsitz', 'Head office', 'Siège', 'Sede central', 'Sede centrale', 'Genel merkez', 'Siedziba główna'),
		'Route plannen': d('Route planen', 'Plan route', 'Itinéraire', 'Planificar ruta', 'Pianifica percorso', 'Rota planla', 'Zaplanuj trasę')
	};

	var LANG = 'nl';

	function translateTextNode(node) {
		var raw = node.nodeValue;
		if (!raw || !raw.trim()) return;
		var key = raw.trim();
		if (!node.__xgSrc) node.__xgSrc = key;
		var src = node.__xgSrc;
		if (LANG === 'nl') { node.nodeValue = raw.replace(key, src); return; }
		var tr = DICT[src] && DICT[src][LANG];
		if (tr) node.nodeValue = raw.replace(key, tr);
	}
	function translatePlaceholders(root) {
		(root.querySelectorAll ? root.querySelectorAll('input[placeholder],textarea[placeholder]') : []).forEach(function (i) {
			if (!i.__xgPh) i.__xgPh = i.getAttribute('placeholder');
			var tr = DICT[i.__xgPh] && DICT[i.__xgPh][LANG];
			i.setAttribute('placeholder', (LANG === 'nl' || !tr) ? i.__xgPh : tr);
		});
	}
	function walk(root) {
		if (!root || root.nodeType !== 1) { if (root && root.nodeType === 3) translateTextNode(root); return; }
		var tw = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
		var n, list = [];
		while ((n = tw.nextNode())) list.push(n);
		list.forEach(translateTextNode);
		translatePlaceholders(root);
	}
	function translateAll() { walk(document.body); }
	function setLang(lang) { LANG = lang || 'nl'; translateAll(); }

	if (window.XGLocale && window.XGLocale.get) LANG = window.XGLocale.get().lang || 'nl';
	document.addEventListener('xg:locale-change', function (e) { setLang(e.detail && e.detail.lang); });

	var obs = new MutationObserver(function (muts) {
		if (LANG === 'nl') return;
		muts.forEach(function (m) {
			m.addedNodes.forEach(function (node) { if (node.nodeType === 1) walk(node); else if (node.nodeType === 3) translateTextNode(node); });
		});
	});

	window.XGI18N = { setLang: setLang, t: function (s) { return (LANG !== 'nl' && DICT[s] && DICT[s][LANG]) || s; } };

	function boot() {
		if (LANG !== 'nl') translateAll();
		obs.observe(document.body, { childList: true, subtree: true });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
