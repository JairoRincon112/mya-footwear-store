import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// En desarrollo la app corre en http://localhost:5173/
// Al compilar (npm run build) el resultado queda en ../public/mya, la carpeta que
// publica Apache (XAMPP en local y el hosting en internet): http://localhost/mya/
export default defineConfig(({ command }) => ({
  plugins: [vue()],
  base: command === 'build' ? '/mya/' : '/',
  build: {
    outDir: '../public/mya',
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
}))
