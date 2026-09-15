import { defineStore, acceptHMRUpdate } from 'pinia'
import { auth } from 'src/boot/firebase'
import {
  GoogleAuthProvider,
  onAuthStateChanged,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
} from 'firebase/auth'
import { api, TOKEN_KEY, isNetworkError } from 'src/api'
import { i18n, t } from 'src/i18n'

/**
 * What to say about a sign-in Firebase refused.
 *
 * The conflation in the first case is Firebase's and is kept on purpose: a wrong password and an
 * address with no account both answer `auth/invalid-credential`, so that a login form cannot be
 * used to find out who has an account here. The message must not be more specific than the code
 * it is translating.
 */
const signInErrorMessage = (err) => {
  switch (err?.code) {
    case 'auth/invalid-credential':
    case 'auth/wrong-password':
    case 'auth/user-not-found':
      return t('login.errors.invalidCredential')
    case 'auth/invalid-email':
      return t('login.errors.invalidEmail')
    case 'auth/user-disabled':
      return t('login.errors.disabled')
    case 'auth/too-many-requests':
      return t('login.errors.tooManyAttempts')
    case 'auth/network-request-failed':
      return t('login.errors.offline')
    default:
      return t('login.errors.generic')
  }
}

/**
 * What this device can tell the server about its owner that the server cannot work out for
 * itself: the zone the user's dates are counted in, and the language a notification should be
 * written in. Both are read at sign-in and only adopted for a user who has neither yet — see
 * `FirebaseLoginController::adoptDevicePreferences`.
 */
const devicePreferences = () => ({
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  locale: i18n.global.locale.value,
})

// The in-flight recovery, shared by every caller. A dead token makes every request in flight
// fail at once, and they must produce one exchange between them, not one each.
let recovering = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    ready: false,
    // Signed in to Firebase but with no sanctum token yet, because the exchange failed offline.
    syncPending: false,
    // The backend refused our token and would not mint a new one from the Firebase session
    // either. Only the user can fix it, so this is what puts the "sign in again" banner up.
    sessionExpired: false,
  }),

  getters: {
    isLoggedIn: (state) => !!state.user,

    /**
     * Signed in with an email and a password rather than with Google.
     *
     * One thing branches on it — `signInAgain`, which has no popup to reopen for these accounts.
     */
    isPasswordUser: (state) =>
      !!state.user?.providerData?.some(({ providerId }) => providerId === 'password'),
  },

  actions: {
    init() {
      return new Promise((resolve) => {
        onAuthStateChanged(auth, async (firebaseUser) => {
          try {
            if (firebaseUser) {
              // Set before the await: if the exchange below fails we still know who is signed in.
              this.user = firebaseUser
              if (!localStorage.getItem(TOKEN_KEY)) {
                await this._syncWithBackend(firebaseUser)
              }
            } else {
              this.user = null
              localStorage.removeItem(TOKEN_KEY)
            }
          } catch (err) {
            if (isNetworkError(err)) {
              // Offline with a persisted Firebase session: stay signed in locally and get the
              // sanctum token once we are back online.
              this.syncPending = true
            } else {
              // The backend answered and refused this identity — don't pretend we are signed in.
              this.user = null
              localStorage.removeItem(TOKEN_KEY)
            }
          } finally {
            // Must always run. `boot/firebase.js` awaits this promise, so leaving it unsettled
            // means the app never mounts at all — a blank page with no error.
            this.ready = true
            resolve(this.user)
          }
        })
      })
    },

    /**
     * Retry the exchange that was skipped offline. Safe and cheap to call at any time — it
     * returns at once unless a Firebase session is sitting there without a sanctum token.
     */
    async retrySync() {
      if (!this.syncPending || !this.user) return
      if (localStorage.getItem(TOKEN_KEY)) {
        this.syncPending = false
        return
      }
      try {
        await this._syncWithBackend(this.user)
        this.syncPending = false
      } catch (err) {
        // Still offline: leave the flag up so the next attempt retries. Anything else is a
        // definitive refusal, so stop retrying.
        //
        // Deliberately unlike `init()`, a refusal here does NOT clear `user`: this runs
        // mid-session, and dropping the session would take whatever is on screen with it. The
        // banner is how re-authentication is asked for instead.
        if (!isNetworkError(err)) this.syncPending = false
      }
    },

    /**
     * Mint a fresh API token from the Firebase session, after the backend refused the one we
     * were using. Returns whether we hold a working token now.
     *
     * Called from `api.js` on any 401, so it has to be cheap to call and safe to call from
     * several places at once: already-known-dead returns without a request, and concurrent
     * callers share one exchange.
     */
    async recoverSession() {
      // No point asking again — the banner is up, and only the user can act on it now.
      if (this.sessionExpired || !this.user) return false
      if (!recovering) {
        recovering = this._recoverOnce().finally(() => {
          recovering = null
        })
      }
      return recovering
    },

    async _recoverOnce() {
      try {
        await this._syncWithBackend(this.user, true)
        return true
      } catch (err) {
        // Offline says nothing about the session. Leave the flags alone and let the caller's
        // own offline handling deal with it, or a re-login would be demanded of someone whose
        // only problem is a tunnel.
        if (isNetworkError(err)) return false
        // A definitive refusal. Drop the dead token as well as raising the flag: `init()` only
        // exchanges when there is none, so keeping it would stop the next sign-in from ever
        // getting a working one.
        localStorage.removeItem(TOKEN_KEY)
        this.sessionExpired = true
        return false
      }
    },

    /**
     * Sign in with Google. Resolves to the user, or null if it did not happen.
     *
     * A popup, which is the browser answer and not the phone one: Google refuses OAuth from an
     * embedded WebView, so the packaged Android build needs the account picker from Play
     * services instead. That branch lands with Capacitor in phase 4; until then this app runs
     * in a browser, where the popup is what works.
     */
    async loginWithGoogle() {
      const provider = new GoogleAuthProvider()
      return signInWithPopup(auth, provider)
        .then(({ user }) => user)
        .catch(() => null)
    },

    /**
     * Sign in with an email and a password — the second way in.
     *
     * It exists for two audiences and neither of them is a normal user. Play's reviewers are
     * handed one of these rather than a Google account of ours to borrow (plan §11), and it is
     * the only path that works in an **automation browser**: Google refuses OAuth from one, so
     * without this nothing can drive a signed-in screen locally.
     *
     * The one path with no branch in it. There is no popup and no account picker, only an HTTPS
     * call to Firebase — and what follows is the same for every provider: `init()`'s listener
     * picks the session up and trades it for the API token.
     *
     * Unlike `loginWithGoogle`, a failure here rejects rather than answering `null`. A form has
     * somewhere to put a reason and a popup does not, and "wrong password" is worth saying.
     */
    async loginWithEmail(email, password) {
      try {
        const { user } = await signInWithEmailAndPassword(auth, email.trim(), password)
        return user
      } catch (err) {
        throw new Error(signInErrorMessage(err))
      }
    },

    /**
     * What the expired-session banner does: re-authenticate, then immediately trade that for an
     * API token, because `onAuthStateChanged` does not fire when the same user signs in again —
     * so nothing else would.
     */
    async signInAgain() {
      // A password session has nothing to reopen — those credentials would have to be typed
      // again, and this banner is not a login form. It is also rarely what is wrong: the
      // Firebase session is still good, or `init()` would have signed us out, and it is the
      // backend that refused the token. So retry that exchange with a freshly minted one, and
      // let the banner come back if it is refused again.
      if (this.isPasswordUser) {
        this.sessionExpired = false
        return this.recoverSession()
      }

      const user = await this.loginWithGoogle()
      if (!user) return false
      this.user = user
      // Lowered first, or `recoverSession` would decline to try.
      this.sessionExpired = false
      return this.recoverSession()
    },

    async _syncWithBackend(firebaseUser, forceFresh = false) {
      // `true` re-reads the token from Firebase instead of using its cached copy, which is the
      // point when we are here because the backend just rejected what we had.
      const idToken = await firebaseUser.getIdToken(forceFresh)
      const {
        data: { token },
      } = await api.post('auth/firebase', { id_token: idToken, ...devicePreferences() })
      localStorage.setItem(TOKEN_KEY, token)
      // The only place a token is minted, so the only place both flags can be cleared.
      this.syncPending = false
      this.sessionExpired = false
    },

    async logout() {
      // A failure here costs a token row on the server that nobody will ever present again;
      // refusing to sign out over it would cost the user the device in their hand.
      await api.post('auth/logout').catch(() => {})
      await this.endSession()
    },

    /**
     * Everything a sign-out does on this device, with nothing said to the server.
     *
     * Split out of `logout` for the one caller that must not talk to the server: after the
     * account is deleted there is no token left to revoke, and asking anyway is actively
     * harmful (see `deleteAccount`).
     */
    async endSession() {
      localStorage.removeItem(TOKEN_KEY)
      await signOut(auth)
      this.user = null
      this.sessionExpired = false
    },

    /**
     * Delete the account and everything on it. Throws if the server refuses, and touches
     * nothing locally either way, so a failure leaves the session exactly as it was.
     *
     * The caller does the teardown, and it must be `endSession()`, never `logout()`: `logout`
     * POSTs with a token the server has just deleted, the 401 handler in `api.js` reads that as
     * an expired session and trades the still-valid Firebase session for a fresh one — and
     * `auth/firebase` is an `updateOrCreate`, so it would put the account straight back, empty.
     */
    async deleteAccount() {
      await api.del('account')
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
