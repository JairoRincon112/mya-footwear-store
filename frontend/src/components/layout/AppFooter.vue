<script setup>
// Pie de página del diseño original (footer.html).
</script>

<template>
  <footer class="footer">
    <div class="container">
      <div class="footer-row">
        <div class="footer-links">
          <h4>Compañía</h4>
          <ul>
            <li><RouterLink :to="{ path: '/', hash: '#mision' }">Nosotros</RouterLink></li>
            <li><RouterLink to="/contacto">Contacto</RouterLink></li>
          </ul>
        </div>
        <div class="footer-links">
          <h4>Ayuda</h4>
          <ul>
            <li><RouterLink to="/login">Mi cuenta</RouterLink></li>
            <li><RouterLink to="/contacto">Puntos de venta</RouterLink></li>
          </ul>
        </div>
        <div class="footer-links">
          <h4>Tienda</h4>
          <ul>
            <li><RouterLink to="/catalogo/adidas">ADIDAS</RouterLink></li>
            <li><RouterLink to="/catalogo/nike">NIKE</RouterLink></li>
            <li><RouterLink to="/catalogo/puma">PUMA</RouterLink></li>
          </ul>
        </div>
        <div class="footer-links">
          <h4>Síguenos</h4>
          <div class="social-link">
            <a href="https://www.facebook.com/share/1AiU6GN67E/" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://www.instagram.com/mya112102/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://x.com/footwear36903/likes" target="_blank" rel="noopener" aria-label="X"><i class="fab fa-x"></i></a>
            <a href="https://www.youtube.com/channel/UCMIkzmM4B8WYh67yyo8gVog" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="https://www.linkedin.com" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
          </div>
          <div class="copyright"><p>&copy; MyA FOOTWEAR STORE</p></div>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
/* Estilos originales del footer (home.css) */
.container {
  margin: 0 auto;
}

.footer {
  position: relative;
  /* sin margen superior: dejaba una franja clara entre la última sección y el pie */
  background-color: rgb(0 0 0 / 87%);
  padding: 30px 0 20px;
  width: 100%;
}

.footer-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
}

.footer-links {
  width: 300px;
  padding: 0 15px;
}

.footer-links h4 {
  font-size: 16px;
  color: #fff;
  margin-bottom: 20px;
  font-weight: 500;
}

.footer-links ul li a {
  font-size: 16px;
  color: #7e7e7e;
  display: block;
  margin: 15px;
  transition: all .3s ease;
}

.footer-links ul li a:hover {
  color: #ff6600;
  padding-left: 6px;
}

.social-link a {
  display: inline-block;
  min-height: 40px;
  width: 40px;
  background-color: rgba(255, 255, 255, 0.2);
  margin: 0 10px 10px 0;
  text-align: center;
  line-height: 40px;
  border-radius: 50%;
  color: #fff;
  transition: all .5s ease;
}

.social-link a:hover {
  background-color: #ff6600;
}

.copyright {
  color: #ff6600;
  text-align: right;
  margin-top: 70px;
}

@media (max-width: 768px) {
  .footer {
    padding: 40px 0;
  }

  .footer-links {
    width: 50%;
    margin: 0 auto;
    padding: 10px;
    text-align: center;
  }

  .footer-links ul li a {
    margin: 10px 0;
  }

  .social-link a {
    min-height: 30px;
    width: 30px;
    line-height: 30px;
  }

  .copyright {
    margin-top: 40px;
    text-align: center;
  }
}
</style>
