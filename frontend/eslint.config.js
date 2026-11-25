// Análisis estático del frontend (ESLint): reglas recomendadas de JavaScript y de Vue 3,
// más límites de complejidad para detectar funciones difíciles de mantener.
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
  { ignores: ['dist/**', 'node_modules/**', 'coverage/**'] },
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser, ...globals.node },
    },
    rules: {
      complexity: ['warn', 10],
      'max-depth': ['warn', 4],
      'max-params': ['warn', 4],
      'max-lines': ['warn', { max: 500, skipBlankLines: true, skipComments: true }],
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      // Reglas de formato de plantillas: el proyecto usa su propio estilo
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/html-indent': 'off',
    },
  },
  {
    files: ['src/**/*.test.js', 'tests/**/*.js'],
    languageOptions: { globals: { ...globals.vitest } },
  },
]
