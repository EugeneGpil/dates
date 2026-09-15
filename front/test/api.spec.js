import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The fetch wrapper every request in the app goes through.
 *
 * Two things are worth a test here and neither is visible on screen. **The 401 recovery**: a
 * sanctum token dies in ordinary ways and the Firebase session behind it usually outlives it,
 * so one request must be able to trade for a new one and retry — exactly once, and never from
 * the exchange itself, or a refused session becomes an infinite loop of sign-in attempts. And
 * **`isNetworkError`**, which is how the rest of the app tells "the server said no" from "we
 * never reached it" — the difference between showing the session-expired banner and waiting
 * for a connection.
 */

const recoverSession = vi.fn()

vi.mock('src/stores/auth', () => ({
  useAuthStore: () => ({ recoverSession }),
}))

const jsonResponse = (status, body = {}) => ({
  ok: status >= 200 && status < 300,
  status,
  json: () => Promise.resolve(body),
})

// A token in storage, because that is the state every one of these cases starts from.
const storage = new Map([['sanctum_token', 'stored-token']])

let fetchMock

beforeEach(() => {
  vi.resetModules()
  recoverSession.mockReset()
  fetchMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('localStorage', {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  })
  vi.stubGlobal('navigator', { onLine: true, languages: ['en-US'], language: 'en-US' })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('request headers', () => {
  it('sends the stored token and the language the app is being read in', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: null }))
    const { api } = await import('src/api')

    await api.get('auth/me')

    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/auth/me')
    expect(options.headers.Authorization).toBe('Bearer stored-token')
    expect(options.headers['Accept-Language']).toBe('en-US')
  })
})

describe('the 401 recovery', () => {
  it('trades the firebase session for a new token and retries once', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(401))
      .mockResolvedValueOnce(jsonResponse(200, { data: 'the answer' }))
    recoverSession.mockResolvedValue(true)
    const { api } = await import('src/api')

    await expect(api.get('dates')).resolves.toEqual({ data: 'the answer' })
    expect(recoverSession).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('gives up rather than looping when the retry is refused as well', async () => {
    fetchMock.mockResolvedValue(jsonResponse(401))
    recoverSession.mockResolvedValue(true)
    const { api } = await import('src/api')

    await expect(api.get('dates')).rejects.toMatchObject({ status: 401 })
    // One recovery, one retry: the retry's own 401 must not start the whole thing again.
    expect(recoverSession).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('does not try to recover the exchange that is itself the recovery', async () => {
    fetchMock.mockResolvedValue(jsonResponse(401))
    const { api } = await import('src/api')

    await expect(api.post('auth/firebase', { id_token: 'x' })).rejects.toMatchObject({
      status: 401,
    })
    expect(recoverSession).not.toHaveBeenCalled()
  })

  it('carries the failed response body, so a caller can act on the answer', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, { message: 'nope' }))
    const { api } = await import('src/api')

    await expect(api.post('date', {})).rejects.toMatchObject({
      status: 422,
      body: { message: 'nope' },
    })
  })
})

describe('isNetworkError', () => {
  it('is false for anything the server answered, however badly', async () => {
    const { isNetworkError } = await import('src/api')

    expect(isNetworkError(Object.assign(new Error('HTTP 500'), { status: 500 }))).toBe(false)
  })

  it('is true for a transport failure and for firebase saying the same thing', async () => {
    const { isNetworkError } = await import('src/api')

    expect(isNetworkError(new TypeError('Failed to fetch'))).toBe(true)
    expect(isNetworkError({ code: 'auth/network-request-failed' })).toBe(true)
  })

  it('is true for an unclassifiable failure raised while the device is offline', async () => {
    vi.stubGlobal('navigator', { onLine: false, languages: ['en-US'], language: 'en-US' })
    const { isNetworkError } = await import('src/api')

    expect(isNetworkError(new Error('something else'))).toBe(true)
  })
})
