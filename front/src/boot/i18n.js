import { defineBoot } from '#q-app/wrappers'
import { i18n, setLocale } from 'src/i18n'

/**
 * Install the catalogue, and bring Quasar's own strings along with it.
 *
 * The instance is built in `src/i18n/index.js` rather than here, so the stores and the helpers
 * under `utils/` can translate through the same object — see there. What is left for boot is
 * the two things that need a running app: handing it to Vue, and loading the Quasar language
 * pack for whatever locale was detected, which is an async import and so cannot happen while
 * the module graph is still being built.
 */
export default defineBoot(({ app }) => {
  app.use(i18n)

  // Not awaited: the pack only affects Quasar's own components — a date picker's month names,
  // the buttons a `$q.dialog` writes for itself — and none of that is on screen at boot. Making
  // the whole app wait on it would delay the first frame for strings nothing is showing yet.
  setLocale(i18n.global.locale.value)
})
