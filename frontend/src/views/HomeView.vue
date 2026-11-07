<script setup>
// Página de inicio: migrada de home.html conservando textos, imágenes y estilos.
import HeroCarrusel from '@/components/HeroCarrusel.vue'
import imagenAdidas from '@/assets/img/home-adidas.jpg'
import imagenPuma from '@/assets/img/home-puma.png'
</script>

<template>
  <main class="home">
    <!-- Carrusel principal (reemplaza la franja "HOME" y la portada naranja) -->
    <HeroCarrusel />

    <section class="services container" id="informacion">
      <div class="service">
        <h3>Servicio Adidas</h3>
        <p>
          En nuestro servicio de adidas, ofrecemos una amplia variedad de diseños y estilos para todas las ocasiones.
          Desde aspectos formales para un look profesional hasta opciones casuales para un día tranquilo.
        </p>
        <RouterLink :to="{ hash: '#adidas' }" class="btn-1">Leer mas</RouterLink>
      </div>

      <div class="service">
        <h3>Servicio Nike</h3>
        <p>
          Nuestro servicio de Nike está diseñado para ofrecerte el máximo confort sin sacrificar el estilo. Ya sea
          para un día de playa, una tarde de descanso o una actividad al aire libre para ofrecerte comodidad.
        </p>
        <RouterLink :to="{ hash: '#nike' }" class="btn-1">Leer mas</RouterLink>
      </div>

      <div class="service">
        <h3>Servicio Puma</h3>
        <p>
          Descubre nuestro servicio de Puma, donde la calidad, el estilo y la comodidad se combinan en cada par. Ya
          sea que busques un par de zapatos formales para una ocasión especial o un modelo más casual para tu día a día.
        </p>
        <RouterLink :to="{ hash: '#puma' }" class="btn-1">Leer mas</RouterLink>
      </div>
    </section>

    <section class="camisas" id="adidas">
      <div class="camisas-content">
        <img :src="imagenAdidas" alt="Adidas Adi2000">
      </div>
      <div class="camisa-texto">
        <h2>ADIDAS</h2>
        <p>
          La marca, reconocida por sus tres franjas, se ha destacado por <br>
          ofrecer productos innovadores que combinan moda <br>
          y alto rendimiento deportivo. Adidas abarca desde calzado especializado para atletas hasta ropa casual <br>
          y accesorios como bolsas, relojes y gafas. Además, la marca tiene una fuerte presencia en eventos <br>
          y patrocinios deportivos globales, siendo sinónimo de rendimiento y estilo.
        </p>
        <RouterLink to="/catalogo/adidas" class="btn1">Ir</RouterLink>
      </div>
    </section>

    <section class="shorts" id="nike">
      <div class="short-texto">
        <h2>NIKE</h2>
        <p>
          La empresa se caracteriza por su enfoque en la innovación tecnológica y el diseño, creando productos que
          responden a las necesidades de atletas profesionales y usuarios cotidianos. Su famosa frase "Just Do It" y
          su logotipo "Swoosh" son reconocidos a nivel mundial. Nike es conocida por su amplia gama de productos,
          desde zapatillas deportivas de alto rendimiento hasta ropa urbana de estilo casual.
        </p>
        <RouterLink to="/catalogo/nike" class="btn2">Ir</RouterLink>
      </div>
    </section>

    <section class="zapatos" id="puma">
      <div class="zapato-content">
        <img :src="imagenPuma" alt="Puma x Rocket League">
      </div>
      <div class="zapato-texto">
        <h2>PUMA</h2>
        <p>
          Puma combina tecnología y moda en sus productos, <br>
          siendo una marca preferida por atletas y consumidores jóvenes. <br>
          La compañía ha establecido colaboraciones con celebridades y diseñadores, <br>
          expandiendo su influencia más allá del ámbito deportivo.
        </p>
        <RouterLink to="/catalogo/puma" class="btn3">Ir</RouterLink>
      </div>
    </section>

    <section class="nosotros">
      <div class="nosotros-content">
        <div class="text-1" id="mision">
          <h2>MISION</h2>
          <p>
            En MyA FOOTWEAR STORE, nuestra misión es ofrecer a nuestros clientes las últimas tendencias en moda,
            proporcionando calzado de alta calidad y diseño único. Nos comprometemos a crear una experiencia de compra
            accesible y personalizada, donde cada persona pueda encontrar su estilo propio y sentirse segura y cómoda
            con lo que lleva. Buscamos ser un referente en la moda, brindando productos que inspiren confianza y estilo
            en cada ocasión.
          </p>
        </div>
        <div class="text-1" id="vision">
          <h2>VISION</h2>
          <p>
            Nuestra visión es convertirnos en la tienda de moda líder, reconocida por nuestra capacidad para anticipar
            y adaptarnos a las tendencias globales, manteniendo siempre un enfoque en la calidad y el estilo accesible.
            Queremos que MyA FOOTWEAR STORE sea el destino preferido para quienes buscan expresar su identidad a través
            de la moda, creando un impacto positivo en la forma en que las personas se sienten y se ven cada día.
          </p>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
/* Estilos originales de home.css (valores finales que aplicaba la página) */
.home {
  padding-top: var(--alto-header);
}

.services.container {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
  padding: 40px 20px;
}

.service {
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 8px #000000;
  padding: 20px;
  width: 100%;
  max-width: 320px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  margin: 0 auto;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.service h3 {
  font-size: 1.5rem;
  margin-bottom: 10px;
  color: #000000;
}

.service p {
  font-size: 1rem;
  color: #666;
  margin-bottom: 20px;
}

.service a.btn-1 {
  display: inline-block;
  padding: 10px 20px;
  background-color: #ff6600;
  color: white;
  border-radius: 25px;
  font-size: 1rem;
  text-align: center;
  transition: background-color 0.3s ease, transform 0.3s ease;
}

.service a.btn-1:hover {
  background-color: #757575;
  transform: scale(1.1);
}

.service:hover {
  transform: translateY(-10px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.camisas,
.zapatos {
  display: flex;
  margin: 0;
  padding: 60px 50px;
  background-color: rgb(235, 222, 222);
  align-items: center;
  justify-content: center;
  gap: 30px;
  flex-wrap: wrap;
}

.camisas-content img,
.zapato-content img {
  width: 100%;
  max-width: 250px;
  height: auto;
  padding: 10px;
  border: 10px solid black;
  border-radius: 10px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.camisas-content img:hover,
.zapato-content img:hover {
  transform: scale(1.1);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.camisa-texto h2,
.short-texto h2,
.zapato-texto h2 {
  text-align: center;
  color: #111;
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.03em;
  margin-bottom: 15px;
}

/* Punto naranja al final del nombre de la marca, como en el carrusel */
.camisa-texto h2::after,
.short-texto h2::after,
.zapato-texto h2::after,
.text-1 h2::after {
  content: ".";
  color: var(--naranja);
}

.camisa-texto p,
.short-texto p,
.zapato-texto p {
  text-align: center;
  padding: 10px 30px;
  color: #666;
  font-size: 1rem;
}

.btn1,
.btn2,
.btn3 {
  border-radius: 15px;
  display: block;
  background-color: #ff6600;
  width: 150px;
  font-weight: bold;
  text-align: center;
  padding: 15px 0;
  margin: 20px auto;
  color: white;
  font-size: 16px;
  transition: background-color 0.3s ease, transform 0.3s ease;
}

.btn1:hover,
.btn2:hover,
.btn3:hover {
  background-color: #757575;
  color: white;
  transform: scale(1.1);
}

.shorts {
  background: url('@/assets/img/home-nike.png');
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  padding: 100px 50px;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

/* Tarjeta de vidrio esmerilado: el texto se lee aunque el tenis quede detrás (sobre todo en celular) */
.short-texto {
  max-width: 760px;
  padding: 36px 28px 28px;
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(255, 255, 255, 0.6);
  border-radius: 24px;
  box-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(10px) saturate(1.2);
  -webkit-backdrop-filter: blur(10px) saturate(1.2);
}

.short-texto h2,
.short-texto p {
  color: #000000;
}

.short-texto p {
  color: #333;
  line-height: 1.6;
}

.nosotros {
  display: flex;
  margin: 0;
  padding: 60px 50px;
  background-color: rgb(255, 255, 255);
  align-items: center;
  justify-content: space-around;
}

.nosotros-content {
  display: flex;
}

.text-1 {
  margin: 0 100px;
  text-align: justify;
}

.text-1 h2 {
  text-align: center;
  padding: 10px;
  color: #000000;
  font-size: clamp(2rem, 4vw, 2.6rem);
  letter-spacing: -0.03em;
}

/* Ajustes para celulares (el diseño original se desbordaba en pantallas pequeñas) */
@media (max-width: 800px) {
  .nosotros-content {
    flex-direction: column;
  }

  .text-1 {
    margin: 0 0 30px;
    /* justificado deja huecos grandes entre palabras en pantallas angostas */
    text-align: left;
    line-height: 1.6;
  }

  /* Los saltos de línea del diseño original cortan las frases en lugares raros en celular */
  .camisa-texto br,
  .zapato-texto br {
    display: none;
  }

  .camisa-texto p,
  .zapato-texto p {
    padding: 10px 4px;
    line-height: 1.6;
  }

  .camisas,
  .zapatos,
  .shorts,
  .nosotros {
    padding: 40px 16px;
  }

  .shorts {
    background-attachment: scroll;
    padding: 56px 16px;
  }

  .short-texto {
    padding: 28px 18px 20px;
    background: rgba(255, 255, 255, 0.86);
  }

  .short-texto p {
    padding: 10px 4px;
    font-size: 0.98rem;
  }
}
</style>
