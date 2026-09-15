import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The catalogue is loaded fresh for each case because `detectLocale()` runs at module scope —
 * the instance's starting locale is decided the moment the module is first imported, so a
 * cached module would answer for whatever `navigator` looked like in the first test.
 */
const loadI18n = async () => {
  vi.resetModules()

  return import('src/i18n/index.js')
}

/**
 * `vi.stubGlobal` rather than an assignment: on Node 24 `globalThis.navigator` is a getter with
 * no setter, so writing to it throws rather than replacing it.
 */
const stubNavigator = (languages) => {
  vi.stubGlobal('navigator', { languages, language: languages[0] })
}

describe('locale detection', () => {
  beforeEach(() => {
    stubNavigator(['en-US'])
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('matches on the language subtag, so a region we do not ship still gets its language', async () => {
    stubNavigator(['ru-BY'])

    const { detectLocale } = await loadI18n()

    expect(detectLocale()).toBe('ru-RU')
  })

  it('falls back to English for a language the app does not have', async () => {
    stubNavigator(['th-TH'])

    const { detectLocale } = await loadI18n()

    expect(detectLocale()).toBe('en-US')
  })

  it('survives storage the browser has switched off', async () => {
    vi.stubGlobal('localStorage', {
      getItem() {
        throw new Error('storage disabled')
      },
    })
    stubNavigator(['ru-RU'])

    const { detectLocale } = await loadI18n()

    expect(detectLocale()).toBe('ru-RU')
  })
})

describe('Russian plurals', () => {
  /**
   * The sentence the whole app is built around is a counted one, and the two forms vue-i18n
   * gives by default cannot spell it. 11-14 are the trap: they decline as *many* however they
   * end, so a rule reading only the last digit says "11 день".
   */
  it.each([
    [1, '1 день'],
    [2, '2 дня'],
    [5, '5 дней'],
    [11, '11 дней'],
    [21, '21 день'],
    [22, '22 дня'],
    [25, '25 дней'],
    [111, '111 дней'],
  ])('declines %i correctly', async (n, expected) => {
    const { i18n } = await loadI18n()

    i18n.global.setLocaleMessage('ru-RU', {
      test: { days: '{n} день | {n} дня | {n} дней' },
    })
    i18n.global.locale.value = 'ru-RU'

    expect(i18n.global.t('test.days', n, { n })).toBe(expected)
  })
})

describe('setLocale', () => {
  beforeEach(() => {
    stubNavigator(['en-US'])
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('switches the catalogue, remembers the choice and marks the document', async () => {
    const stored = {}
    vi.stubGlobal('localStorage', {
      getItem: (k) => stored[k] ?? null,
      setItem: (k, v) => {
        stored[k] = v
      },
    })
    vi.stubGlobal('document', { documentElement: {} })

    const { i18n, setLocale } = await loadI18n()
    await setLocale('ru-RU')

    expect(i18n.global.locale.value).toBe('ru-RU')
    expect(stored['dates.locale']).toBe('ru-RU')
    expect(globalThis.document.documentElement.lang).toBe('ru-RU')
  })

  it('ignores a locale the app does not ship, rather than blanking the UI', async () => {
    const { i18n, setLocale } = await loadI18n()
    await setLocale('th-TH')

    expect(i18n.global.locale.value).toBe('en-US')
  })

  /**
   * The language has already switched by the time storage is written, so a browser with storage
   * switched off must lose the choice for next launch and nothing else. A throw here would take
   * the switcher down with it.
   */
  it('still switches when storage refuses the write', async () => {
    vi.stubGlobal('localStorage', {
      getItem: () => null,
      setItem: () => {
        throw new Error('storage disabled')
      },
    })

    const { i18n, setLocale } = await loadI18n()
    await setLocale('ru-RU')

    expect(i18n.global.locale.value).toBe('ru-RU')
  })
})
