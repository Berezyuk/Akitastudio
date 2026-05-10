<script setup>
// Данные для ссылок на услуги (пока ведут на страницу услуг, в будущем можно добавить якоря)
const serviceLinks = {
  'Детейлинг-уход': '/services',
  'Оклейка пленкой': '/services',
  'Полировка автомобиля': '/services',
  'Керамические покрытия': '/services',
  'Ремонт салона': '/services',
  'Малярные работы': '/services',
  'Дооснащение': '/services',
  'Комплексы Akita': '/services'
}
</script>

<template>
  <footer>
    <div class="container">
      <hr class="footer-divider" />
      
      <div class="footer-content">
        <div class="footer-brand">
          <img src="../assets/Images/Logo.svg" alt="logo" class="footer-logo" />
          <p class="footer-copyright">
            © 2025 Все права защищены. Использование материалов разрешено только с
            согласия правообладателей.
          </p>
        </div>
        
        <nav class="footer-nav">
          <div class="footer-nav-column">
            <h3 class="footer-column-title">Услуги</h3>
            <ul class="footer-menu">
              <li v-for="(url, service) in serviceLinks" :key="service" class="footer-menu-item">
                <a :href="url">{{ service }}</a>
              </li>
            </ul>
          </div>
          
          <div class="footer-nav-column">
            <h3 class="footer-column-title">Компания</h3>
            <ul class="footer-menu">
              <li class="footer-menu-item"><router-link to="/about">О нас</router-link></li>
              <li class="footer-menu-item"><a href="#">Преимущества</a></li>
              <li class="footer-menu-item"><a href="#">Отзывы</a></li>
              <li class="footer-menu-item"><a href="#">Сертификаты</a></li>
            </ul>
          </div>
          
          <div class="footer-nav-column">
            <h3 class="footer-column-title">Контакты</h3>
            <ul class="footer-menu">
              <li class="footer-menu-item"><a href="tel:+79098029868">+7 (909) 802-98-68</a></li>
              <li class="footer-menu-item">
                <address>г. Хабаровск, ул. Кавказская 35/5</address>
              </li>
              <li class="footer-menu-item">ПН-СБ 10:00 - 20:00</li>
            </ul>
          </div>
          
          <div class="footer-nav-column">
            <h3 class="footer-column-title">Соцсети</h3>
            <ul class="footer-menu">
              <li class="footer-menu-item">
                <a href="https://t.me/akita_auto" target="_blank" rel="noopener noreferrer">
                  Telegram
                </a>
                <span class="restricted-badge">*</span>
              </li>
              <li class="footer-menu-item">
                <a href="https://www.instagram.com/auto.akita/" target="_blank" rel="noopener noreferrer">
                  Instagram
                </a>
                <span class="restricted-badge">*</span>
              </li>
              <li class="footer-menu-item">
                <a href="https://2gis.ru/khabarovsk/firm/70000001028746438" target="_blank" rel="noopener noreferrer">
                  2GIS
                </a>
              </li>
            </ul>
            <p class="restricted-note">* Социальные сети, запрещённые на территории РФ</p>
          </div>
        </nav>
      </div>
    </div>
  </footer>
</template>

<style scoped>
footer {
  background-color: #1a1a1a;
  color: #f5f5f5;
  padding: 48px 0 32px;
  font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
}

.footer-divider {
  height: 1px;
  background: linear-gradient(90deg, #fc9303, transparent);
  border: none;
  margin: 0 auto 40px;
  width: 100%;
  max-width: 1200px;
}

.footer-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 60px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.footer-brand {
  flex: 0 0 280px;
}

.footer-logo {
  width: 120px;
  height: auto;
  margin-bottom: 20px;
  filter: brightness(0) invert(1);
}

.footer-copyright {
  font-size: 14px;
  line-height: 1.6;
  color: #999;
  margin: 0;
}

.footer-nav {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 40px;
  flex: 1;
}

.footer-nav-column {
  min-width: 180px;
}

.footer-column-title {
  font-size: 16px;
  font-weight: 600;
  color: #fff;
  margin: 0 0 20px 0;
  position: relative;
  padding-bottom: 8px;
}

.footer-column-title::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 0;
  width: 40px;
  height: 2px;
  background-color: #fc9303;
  transition: width 0.3s ease;
}

.footer-column-title:hover::after {
  width: 60px;
}

.footer-menu {
  list-style: none;
  margin: 0;
  padding: 0;
}

.footer-menu-item {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
}

.footer-menu-item:last-child {
  margin-bottom: 0;
}

.footer-menu-item a,
.footer-menu-item address {
  font-size: 15px;
  line-height: 1.5;
  color: #ccc;
  text-decoration: none;
  transition: all 0.3s ease;
  font-style: normal;
}

.footer-menu-item a:hover {
  color: #fc9303;
  transform: translateX(3px);
}

.footer-menu-item address {
  line-height: 1.6;
}

.restricted-badge {
  color: #fc9303;
  font-size: 14px;
  font-weight: bold;
  cursor: help;
}

.restricted-note {
  font-size: 11px;
  color: #888;
  margin-top: 12px;
  border-top: 1px solid #333;
  padding-top: 10px;
}

/* Адаптивность */
@media (max-width: 1024px) {
  .footer-content {
    flex-direction: column;
    gap: 40px;
  }
  
  .footer-brand {
    flex: 0 0 auto;
    width: 100%;
    text-align: center;
  }
  
  .footer-nav {
    grid-template-columns: repeat(2, 1fr);
    width: 100%;
  }
}

@media (max-width: 768px) {
  footer {
    padding: 32px 0 24px;
  }
  
  .footer-nav {
    grid-template-columns: 1fr;
    gap: 30px;
  }
  
  .footer-column-title {
    margin-bottom: 16px;
  }
  
  .footer-menu-item {
    margin-bottom: 10px;
  }
  
  .footer-divider {
    margin-bottom: 32px;
  }
}

/* Фокус для доступности */
.footer-menu-item a:focus {
  outline: 2px solid #fc9303;
  outline-offset: 2px;
}
</style>