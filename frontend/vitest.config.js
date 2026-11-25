// Pruebas automatizadas del frontend (Vitest + Vue Test Utils en un DOM simulado con jsdom).
import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config.js'

export default mergeConfig(viteConfig({ command: 'serve', mode: 'test' }), defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/**/*.test.js'],
  },
}))
