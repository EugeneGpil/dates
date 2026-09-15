import { defineBoot } from '#q-app/wrappers'
import { initializeApp } from 'firebase/app'
import { getAuth } from 'firebase/auth'
import { useAuthStore } from 'src/stores/auth'

// Baked into the bundle at build time by Vite. None of it is secret — a Firebase web config
// identifies the project to Google and is readable in any client that ships it; what protects
// the data is the id token exchange on the server, not these values.
const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
}

// A build with no Firebase settings cannot sign anybody in, and this app has nothing to show a
// stranger — so it says so here rather than failing deeper, where `initializeApp` reports an
// invalid API key and the app is a blank page with no hint of which file is empty.
if (!firebaseConfig.apiKey) {
  throw new Error(
    'Firebase is not configured: the VITE_FIREBASE_* values are empty. See docs/firebase.md.',
  )
}

const app = initializeApp(firebaseConfig)
export const auth = getAuth(app)

/**
 * The app does not mount until the stored Firebase session has been read back, so the router
 * guard can ask "is anybody signed in" and get the true answer rather than "not yet".
 */
export default defineBoot(async () => {
  const authStore = useAuthStore()
  await authStore.init()
  // If the token exchange was skipped because we booted offline, pick it up on reconnect.
  window.addEventListener('online', () => authStore.retrySync())
})
