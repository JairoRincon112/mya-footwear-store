<script setup>
// Estructura del panel de administración (admin y vendedor).
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Secciones del panel; "soloAdmin" se oculta a los vendedores.
const SECCIONES = [
  { ruta: 'panel-pedidos', texto: 'Pedidos', icono: 'fa-receipt' },
  { ruta: 'panel-productos', texto: 'Productos', icono: 'fa-shoe-prints' },
  { ruta: 'panel-inventario', texto: 'Inventario', icono: 'fa-boxes-stacked' },
  { ruta: 'panel-mensajes', texto: 'Mensajes', icono: 'fa-envelope' },
  { ruta: 'panel-reportes', texto: 'Reportes', icono: 'fa-chart-column' },
  { ruta: 'panel-cupones', texto: 'Cupones', icono: 'fa-ticket', soloAdmin: true },
  { ruta: 'panel-usuarios', texto: 'Usuarios', icono: 'fa-users', soloAdmin: true },
]
</script>

<template>
  <div class="panel">
    <aside class="panel-menu" aria-label="Menú del panel">
      <p class="panel-rol">{{ auth.esAdmin ? 'Administrador' : 'Vendedor' }}</p>
      <nav>
        <RouterLink :to="{ name: 'panel' }" exact-active-class="activo">
          <i class="fa-solid fa-gauge"></i> Resumen
        </RouterLink>
        <template v-for="seccion in SECCIONES" :key="seccion.ruta">
          <RouterLink v-if="!seccion.soloAdmin || auth.esAdmin" :to="{ name: seccion.ruta }" active-class="activo">
            <i class="fa-solid" :class="seccion.icono"></i> {{ seccion.texto }}
          </RouterLink>
        </template>
      </nav>
    </aside>

    <main class="panel-contenido">
      <RouterView />
    </main>
  </div>
</template>

<style>
/* Estilos compartidos por todas las pantallas del panel (sin scoped a propósito). */
.panel {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr);
  gap: 24px;
  max-width: 1300px;
  min-height: 100vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 24px) 16px 40px;
}

.panel-menu {
  align-self: start;
  position: sticky;
  top: calc(var(--alto-header) + 24px);
  padding: 18px 12px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.panel-rol {
  padding: 0 10px 12px;
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--naranja);
}

.panel-menu nav {
  display: grid;
  gap: 4px;
}

.panel-menu a,
.panel-menu .deshabilitado {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 8px;
  font-weight: 500;
}

.panel-menu a:hover {
  background: var(--gris-suave);
}

.panel-menu a.activo {
  background: #fff3eb;
  color: #c24e00;
}

.panel-menu .deshabilitado {
  color: #9a9a9a;
  cursor: not-allowed;
}

.panel-menu i {
  width: 18px;
  text-align: center;
}

.panel-tarjeta {
  margin-bottom: 20px;
  padding: 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.panel-encabezado {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.panel-encabezado h2 {
  font-size: 1.5rem;
}

.panel-tarjeta h3 {
  margin-bottom: 14px;
  font-size: 1.1rem;
}

.tabla-contenedor {
  /* relative: los textos solo para lectores de pantalla (position: absolute) quedan
     dentro de la tabla y no estiran la página en celulares */
  position: relative;
  overflow-x: auto;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: .92rem;
}

.tabla th,
.tabla td {
  padding: 10px 12px;
  text-align: left;
  vertical-align: middle;
  border-bottom: 1px solid #e6e6e6;
}

.tabla th {
  font-size: .82rem;
  font-weight: 600;
  white-space: nowrap;
  color: var(--texto-secundario);
}

.tabla .numero {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.miniatura {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 8px;
  background: var(--gris-suave);
}

.etiqueta {
  display: inline-block;
  padding: 2px 10px;
  font-size: .78rem;
  font-weight: 600;
  border-radius: 20px;
  white-space: nowrap;
}

.etiqueta-activa {
  color: var(--exito);
  background: #e7f4ea;
}

.etiqueta-inactiva {
  color: #6b6b6b;
  background: #ececec;
}

.etiqueta-agotada {
  color: var(--error);
  background: #fdecea;
}

.texto-secundario {
  font-size: .82rem;
  color: var(--texto-secundario);
}

@media (max-width: 860px) {
  .panel {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }

  .panel-menu {
    position: static;
  }

  .panel-menu nav {
    display: flex;
    flex-wrap: wrap;
  }
}
</style>
