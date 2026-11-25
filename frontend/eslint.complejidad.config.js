// Solo para medir: reporta la complejidad ciclomática de TODAS las funciones del frontend
// (umbral 0). La revisión normal del código usa eslint.config.js.
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
  ...pluginVue.configs['flat/base'],
  {
    languageOptions: { ecmaVersion: 'latest', sourceType: 'module', globals: { ...globals.browser } },
    rules: { complexity: ['warn', 0] },
  },
]
