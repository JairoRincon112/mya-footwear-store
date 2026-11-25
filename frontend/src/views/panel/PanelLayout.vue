<script setup>
// Estructura del panel de administración (admin y vendedor).
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Secciones del panel; "soloAdmin" se oculta a los vendedores.
const SECCIONES = [
  { ruta: 'panel-pedidos', texto: 'Pedidos', icono: 'fa-receipt' },
  { ruta: 'panel-productos', texto: 'Productos', icono: 'fa-shoe-prints' },
  { ruta: 'panel-inventario', texto: 'Inventario', icono: 'fa-boxes-stacked' },
  { ruta: 'panel-devoluciones', texto: 'Devoluciones', icono: 'fa-rotate-left' },
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
/* El panel ocupa toda la pantalla debajo de la barra naranja: sin franjas del
   fondo de la tienda entre la barra, el menú y el contenido. */
.panel {
  display: grid;
  grid-template-columns: 210px minmax(0, 1fr);
  min-height: 100vh;
  padding-top: var(--alto-header);
  background: #fff;
}

.panel-menu {
  align-self: start;
  position: sticky;
  top: var(--alto-header);
  height: calc(100vh - var(--alto-header));
  overflow-y: auto;
  padding: 16px 10px;
  background: #fff;
  border-right: 1px solid #e4e6ea;
}

.panel-contenido {
  min-width: 0;
  padding: 18px 22px 28px;
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
  color: #a8521f;
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
  margin-bottom: 16px;
  padding: 18px;
  background: #fff;
  border: 1px solid #e4e6ea;
  border-radius: 10px;
}

.panel-encabezado {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
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
  padding: 9px 10px;
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

/* Columna de acciones: un solo botón «⋮» por fila. */
.tabla td.acciones {
  width: 1%;
  text-align: right;
  white-space: nowrap;
}

/* Filas que abren el detalle al hacer clic. */
.tabla .fila-clic {
  cursor: pointer;
}

.tabla .fila-clic:hover td {
  background: #fafbfc;
}

.miniatura {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 8px;
  background: var(--gris-suave);
}

/* ---------- Semáforo del panel ----------
   Los mismos colores en todas las secciones:
   verde = activo / vigente / entregado / leído
   amarillo = pendiente / bajo stock / sin leer
   azul = pagado / programado
   naranja = en tránsito
   rojo = cancelado / agotado / desactivado / vencido */
.sem-neutro { --color: #1d2430; --fondo: #fff; }
.sem-verde { --color: #1e8e3e; --fondo: #e6f4ea; }
.sem-amarillo { --color: #b58100; --fondo: #fff6d6; }
.sem-azul { --color: #1565c0; --fondo: #e3effb; }
.sem-naranja { --color: #d35400; --fondo: #fdeedd; }
.sem-rojo { --color: #c62828; --fondo: #fdecea; }

/* Botones de filtro: punto de color + borde; el seleccionado se rellena. */
.semaforo {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.semaforo button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  font-size: .88rem;
  font-weight: 600;
  color: var(--color, #1d2430);
  background: var(--fondo, #fff);
  border: 2px solid var(--color, #1d2430);
  border-radius: 20px;
  cursor: pointer;
  transition: background-color .15s, color .15s;
}

.semaforo .luz {
  width: 11px;
  height: 11px;
  background: var(--color);
  border-radius: 50%;
  box-shadow: 0 0 0 2px rgba(255, 255, 255, .7);
}

.semaforo button.activo {
  color: #fff;
  background: var(--color, #1d2430);
}

.semaforo button:focus-visible {
  outline: 3px solid #1d2430;
  outline-offset: 2px;
}

/* Etiquetas de estado dentro de las tablas: mismos colores del semáforo. */
.etiqueta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 10px;
  font-size: .78rem;
  font-weight: 600;
  border: 1.5px solid currentColor;
  border-radius: 20px;
  white-space: nowrap;
}

.etiqueta-activa {
  color: #17692e;
  background: #e6f4ea;
}

.etiqueta-inactiva,
.etiqueta-agotada {
  color: #b71c1c;
  background: #fdecea;
}

.etiqueta-bajo {
  color: #7a5600;
  background: #fff6d6;
}

.etiqueta-programado {
  color: #0d47a1;
  background: #e3effb;
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
    height: auto;
    border-right: 0;
    border-bottom: 1px solid #e4e6ea;
  }

  .panel-contenido {
    padding: 16px 14px 24px;
  }

  .panel-menu nav {
    display: flex;
    flex-wrap: wrap;
  }
}
</style>
