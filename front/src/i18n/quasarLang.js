import { Lang } from 'quasar'

/**
 * Quasar's own strings — the month names in a date picker, the Cancel/OK a `$q.dialog` writes
 * for itself — kept in step with ours.
 *
 * **A module of its own, imported statically, and that is the whole point of it.** Doing this
 * with `await import('quasar')` from inside `setLocale` loads a *second* copy of the Lang
 * plugin: `set()` runs on it, dutifully rewrites `<html lang>`, and leaves `$q.lang` — which is
 * the app's installed copy — still in English. A static import resolves through the same alias
 * the rest of `src/` gets, so this is the instance the app is actually using.
 *
 * `src/i18n/index.js` reaches this by dynamically importing *this file*, which costs nothing at
 * the seam and keeps `quasar` out of the i18n module's own static graph — the plain-node half
 * of the test suite imports that module and has only a stub where Quasar should be.
 *
 * The pack names are Quasar's, not ours: our `ru-RU` is their `ru`.
 */
const PACKS = {
  'en-US': () => import('quasar/lang/en-US'),
  'ru-RU': () => import('quasar/lang/ru'),
}

export async function setQuasarLang(code) {
  const load = PACKS[code]
  if (!load) return

  Lang.set((await load()).default)
}
