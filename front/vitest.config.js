import { defineConfig } from 'vitest/config'
import { fileURLToPath } from 'node:url'

const src = fileURLToPath(new URL('./src', import.meta.url))
const stub = (name) => fileURLToPath(new URL(`./test/stubs/${name}.js`, import.meta.url))

// The array form and the anchored patterns rather than the object one: object aliases match by
// prefix, so a bare `quasar` would turn `quasar/lang/ru` into a path inside whatever it points
// at, and a bare `src` would also answer an import of `'src'`.
//
// `environment: 'node'`, deliberately. The stores, the i18n module and the pure helpers are
// plain JS, so nothing here needs a DOM or the Quasar build, and `quasar` is aliased to a stub
// because those files import Notify from it. A jsdom `window` would let a test pass on a
// `localStorage` the real cold-launch path does not have. A second project with jsdom and a
// real SFC compiler belongs here the day there is a component worth mounting.
export default defineConfig({
  resolve: {
    alias: [
      { find: /^src\//, replacement: `${src}/` },
      { find: /^quasar$/, replacement: stub('quasar') },
      { find: /^#q-app\/wrappers$/, replacement: stub('q-app-wrappers') },
    ],
  },
  test: {
    name: 'node',
    environment: 'node',
    include: ['test/*.spec.js'],
  },
})
