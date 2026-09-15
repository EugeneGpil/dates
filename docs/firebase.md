# Firebase — what Dates needs from it, and where the pieces go

Dates has no password of its own. A user signs in to Google through Firebase, the app hands the
resulting **id token** to `POST /api/auth/firebase`, and the server — which verifies that token
against Google's public keys — answers with a Sanctum token that every other request carries.
That is the whole of the trust chain: the values baked into the client identify the project, and
the credentials on the server are what can actually vouch for anybody.

The same project will serve Cloud Messaging in phase 5, so it is worth creating once, properly.

## 1. The project and the sign-in provider

In the [Firebase console](https://console.firebase.google.com):

1. Create a project (Google Analytics is not needed).
2. **Build → Authentication → Get started → Sign-in method → Google → Enable.** Set a support
   email. Google is the provider the app is *for*.
3. **Sign-in method → Email/Password → Enable** as well, and leave the passwordless email link
   off. This is the second way in, and it is not there for ordinary users — see §5.
4. **Authentication → Settings → Authorized domains**: add the domains the PWA is served from.
   `localhost` is there by default, which covers `localhost:9201` and `localhost:8084` — the
   port is not part of the entry. Production needs `dates.evgenii-efimov.ru` added by hand, and
   a sign-in popup from a domain that is not on this list fails with `auth/unauthorized-domain`.

## 2. The client half — `VITE_FIREBASE_*`

**Project settings → General → Your apps → Web app** (create one if there is none). Copy the
config object's six values into `front/.env.local`:

```
VITE_FIREBASE_API_KEY=...
VITE_FIREBASE_AUTH_DOMAIN=<project>.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=<project>
VITE_FIREBASE_STORAGE_BUCKET=<project>.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=...
VITE_FIREBASE_APP_ID=1:...:web:...
```

None of this is secret — it identifies the project to Google and ships readable inside every
client. It is still kept out of the repo, because the values differ between whoever's project a
given checkout is pointed at.

They are read at **build time** (`src/boot/firebase.js`), so a change needs `npm run build`, or a
restart of `npm run dev`. Built with them empty, the app is a blank page: there is no session to
read back and nothing to show somebody who cannot sign in, so the boot file stops with

```
Firebase is not configured: the VITE_FIREBASE_* values are empty. See docs/firebase.md.
```

in the console, rather than letting the SDK's own `auth/invalid-api-key` be the only clue.

In CI the same six values come from repository secrets of the same names and are written into
`front/.env` before the build (`.github/workflows/deploy.yml`).

## 3. The server half — the service account

**Project settings → Service accounts → Generate new private key.** This one *is* secret: it can
mint tokens for any user in the project and delete their accounts.

- Locally: save it as `back/storage/app/firebase-credentials.json` (gitignored).
- In production: the same path under the shared storage directory, so it survives releases —
  `<DEPLOY_PATH>/shared/back/storage/app/firebase-credentials.json`. Nothing in the deploy puts
  it there; it is placed by hand, once.

`back/.env` points at it:

```
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

Without the file, sign-in and account deletion fail with `Unable to determine the Firebase
Project ID` — every other endpoint keeps working, which is what makes the error easy to
misread. The test suite never touches it: `Kreait\Firebase\Contract\Auth` is swapped for a mock
(`tests/Feature/Auth/FirebaseLoginTest.php`).

## 4. What the server does with it

`FirebaseLoginController` verifies the id token, then `updateOrCreate`s a user on the `sub`
claim. There is no registration form: a uid Firebase vouches for and this database has not seen
is a new account. The profile (name, email, picture) is refreshed on every sign-in because it is
Google's to change; the timezone and the language are not, and are taken only from a user who
has neither yet — the controller says why.

`DELETE /api/account` deletes the row here first and the Firebase user second, and tolerates the
second failing. The order is deliberate and the controller explains it.

## 5. The email and password way in

The login page offers a form under the Google button. It exists for two audiences, and a normal
user is neither of them:

- **An automation browser cannot sign in with Google.** Google refuses OAuth from one, so without
  this form nothing — no `chrome-devtools` session, no manual-tester run — can reach a single
  screen behind the login. This is the path to use for that (`.claude/agents/manual-tester.md`
  says the same).
- **Play's reviewers** are given one of these rather than a Google account of ours to borrow,
  and an app whose every screen is behind a sign-in is rejected without working credentials
  (plan §11).

Create the account with the Admin SDK rather than by hand in the console — the service account
from §3 can do it, and it is one command:

```sh
docker compose exec php php artisan tinker --execute \
  "app(Kreait\Firebase\Contract\Auth::class)->createUser([
      'email' => 'local.test@dates.invalid', 'password' => '<pick one>',
  ]);"
```

Signing in with it creates the matching row in this app's own `users` table, exactly as a Google
sign-in does — `auth/firebase` does not care which provider minted the id token.

## 6. Still to come

- **Phase 4 (Android)** needs the native sign-in path: Google refuses OAuth from an embedded
  WebView, so the account picker comes from Play services and only the resulting id token
  crosses into the web layer. That also means registering the app's SHA-1 and SHA-256
  fingerprints in the console.
- **Phase 5 (notifications)** uses Cloud Messaging on this same project, with
  `google-services.json` from **Project settings → Your apps → Android app**.
