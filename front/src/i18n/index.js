import { createI18n } from 'vue-i18n'

import enUS from './en-US'
import ruRU from './ru-RU'

/**
 * The app's languages, and the one instance of vue-i18n that serves all of them.
 *
 * **Created here rather than in the boot file**, which is what lets the stores and the pure
 * helpers under `utils/` translate: `t` below is an ordinary function import with no component
 * and no `this` behind it. `boot/i18n.js` installs this same object into the app, so a
 * template's `$t` and a store's `t` are the same catalogue and the same current locale.
 *
 * Both catalogues are bundled rather than fetched per locale. They are a few KB each, and a
 * language that needs the network to render is one that breaks on the cold launch this app is
 * built around.
 */

export const DEFAULT_LOCALE = 'en-US'

/**
 * What the switcher offers, in the order it offers it. `quasar` is the matching pack under
 * `quasar/lang/` — its own naming, which is why it is written out rather than derived: our
 * `ru-RU` is their `ru`, and guessing at that mapping breaks on the first locale where it
 * does not hold.
 */
export const SUPPORTED_LOCALES = [
  { code: 'en-US', label: 'English', quasar: 'en-US' },
  { code: 'ru-RU', label: 'Русский', quasar: 'ru' },
]

const messages = {
  'en-US': enUS,
  'ru-RU': ruRU,
}

const STORAGE_KEY = 'dates.locale'

export const isSupported = (code) => SUPPORTED_LOCALES.some((l) => l.code === code)

/**
 * Russian has three cardinal forms, and the two vue-i18n gives by default cannot spell any of
 * them: "1 день", "2 дня", "5 дней" — which is the app's most common sentence. Index 0 is
 * *one*, 1 is *few*, 2 is *many*. The teens are the trap: 11–14 decline as *many* however they
 * end.
 *
 * The clamp is not decoration. A key missing from this catalogue falls back to `en-US`, whose
 * messages have two forms — so a rule answering 2 for a two-form message would index past the
 * end of it.
 */
const eastSlavicPlural = (choice, choicesLength) => {
  const n = Math.abs(Number(choice)) % 100
  const last = n % 10
  let index = 2
  if (n < 11 || n > 14) {
    if (last === 1) index = 0
    else if (last >= 2 && last <= 4) index = 1
  }

  return Math.min(index, choicesLength - 1)
}

/**
 * The locale to open in: what was chosen here before, else the best match for what the browser
 * asks for, else English.
 *
 * Matched on the language subtag rather than the whole tag, because a browser saying `ru`,
 * `ru-BY` or `ru-KZ` is asking for a language this app has — refusing it over a region we do
 * not ship a separate catalogue for would answer a Russian request in English.
 */
export function detectLocale() {
  let stored = null
  try {
    stored = localStorage.getItem(STORAGE_KEY)
  } catch {
    // Private mode, or storage the browser has switched off. The browser's own preference is
    // still readable, so this is a lost choice rather than a lost language.
  }
  if (isSupported(stored)) return stored

  const asked = globalThis.navigator?.languages?.length
    ? globalThis.navigator.languages
    : [globalThis.navigator?.language].filter(Boolean)

  for (const tag of asked) {
    const exact = SUPPORTED_LOCALES.find((l) => l.code.toLowerCase() === String(tag).toLowerCase())
    if (exact) return exact.code
    const subtag = String(tag).split('-')[0].toLowerCase()
    const byLanguage = SUPPORTED_LOCALES.find((l) => l.code.split('-')[0].toLowerCase() === subtag)
    if (byLanguage) return byLanguage.code
  }

  return DEFAULT_LOCALE
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: detectLocale(),
  fallbackLocale: DEFAULT_LOCALE,
  // The fallback is a deliberate silence: a key not yet translated renders in English rather
  // than as its own dotted path, and the console stays readable while a catalogue is being
  // filled in.
  fallbackWarn: false,
  missingWarn: false,
  messages,
  pluralRules: {
    'ru-RU': eastSlavicPlural,
  },
})

/**
 * Translate from outside a component — the stores, and the helpers under `utils/`.
 *
 * A wrapper rather than `i18n.global.t` exported directly, so the call always reaches the
 * composer that is current *now*: the locale is swapped in place, and a bound reference taken
 * at import time would keep answering in the language the app started in.
 */
export const t = (...args) => i18n.global.t(...args)

/**
 * Switch language: the catalogue, Quasar's own strings, the `lang` attribute, and the choice
 * remembered for next launch.
 *
 * Quasar's pack is what puts the framework's own words in the same language as ours — the date
 * picker's month names, the "Cancel"/"OK" a `$q.dialog` writes for itself. It lives behind
 * `./quasarLang`, reached by a dynamic import so that `quasar` stays out of this module's
 * static graph: the plain-node half of the test suite imports this file and has only a stub
 * where the framework should be. See that file for why the *inner* import has to be a static
 * one.
 *
 * A pack that fails to load is not allowed to take the language down with it: our own strings
 * have already switched, and Quasar's staying English is a far smaller wrong than a switcher
 * that throws.
 */
export async function setLocale(code) {
  if (!isSupported(code)) return

  i18n.global.locale.value = code
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    // Same as reading it: the app is usable, the choice just will not outlive the session.
  }
  if (globalThis.document) {
    globalThis.document.documentElement.lang = code
  }

  try {
    const { setQuasarLang } = await import('./quasarLang')
    await setQuasarLang(code)
  } catch {
    // See above.
  }
}
