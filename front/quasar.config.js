// Configuration for your app
// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file

import { defineConfig } from '#q-app/wrappers'
import { fileURLToPath } from 'node:url'

export default defineConfig((ctx) => {
  return {
    // app boot file (/src/boot)
    // https://v2.quasar.dev/quasar-cli-vite/boot-files
    boot: ['i18n'],

    // https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#css
    css: ['app.scss'],

    // https://github.com/quasarframework/quasar/tree/dev/extras
    extras: ['roboto-font', 'material-icons'],

    // Full list of options: https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#build
    build: {
      target: {
        browser: 'baseline-widely-available',
        node: 'node22',
      },

      vueRouterMode: 'hash',

      vitePlugins: [
        [
          '@intlify/unplugin-vue-i18n/vite',
          {
            // The catalogues are `.js` modules, not `.json` resources, so the plugin does not
            // pre-compile them, and their messages carry named tokens (`{n}`, `{title}`).
            // Without the message compiler in the bundle those render as an error rather than a
            // sentence, so this is load-bearing, not a preference.
            runtimeOnly: false,

            ssr: ctx.modeName === 'ssr',

            include: [fileURLToPath(new URL('./src/i18n', import.meta.url))],
          },
        ],
        [
          'vite-plugin-checker',
          {
            eslint: {
              lintCommand: 'eslint -c ./eslint.config.js "./src*/**/*.{js,mjs,cjs,vue}"',
              useFlatConfig: true,
            },
          },
          { server: false },
        ],
      ],
    },

    // Full list of options: https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#devserver
    devServer: {
      // No explicit port: Quasar picks a per-mode default (spa 9000, pwa 9200) so each mode
      // gets its own origin and cannot inherit another mode's service worker. The container
      // publishes 9200 on the host as 9201 (docker-compose.yml).
      open: false,
    },

    // https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#framework
    framework: {
      config: {
        // Follow the OS, and keep following it: 'auto' has Quasar watch `prefers-color-scheme`
        // for the life of the session, so a phone switching to dark at sunset switches the app
        // with it. There is deliberately no in-app toggle — the setting the user already made
        // is the answer.
        dark: 'auto',
      },

      plugins: ['Notify', 'Dialog'],
    },

    animations: [],

    // https://v2.quasar.dev/quasar-cli-vite/developing-pwa/configuring-pwa
    pwa: {
      workboxMode: 'GenerateSW',

      extendGenerateSWOptions(cfg) {
        // In production the API shares the origin with the front (host nginx routes /api and
        // /sanctum to Laravel), so the SPA navigation fallback must not answer for those paths.
        cfg.navigateFallbackDenylist = [
          ...(cfg.navigateFallbackDenylist || []),
          /^\/api\//,
          /^\/sanctum\//,
        ]
      },
    },

    // Full list of options: https://v2.quasar.dev/quasar-cli-vite/developing-capacitor-apps/configuring-capacitor
    capacitor: {
      hideSplashscreen: true,
    },
  }
})
