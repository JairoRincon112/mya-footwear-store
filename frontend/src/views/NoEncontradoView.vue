<template>
  <main class="no-encontrado">
    <h2 class="titulo_descuento">PÁGINA NO ENCONTRADA</h2>
    <p>La página que buscas no existe.</p>
    <RouterLink to="/" class="boton">Volver al inicio</RouterLink>
  </main>
</template>

<style scoped>
.no-encontrado {
  display: grid;
  justify-items: center;
  gap: 16px;
  min-height: 60vh;
  padding: calc(var(--alto-header) + 40px) 16px 40px;
  text-align: center;
}
</style>
