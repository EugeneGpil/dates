/**
 * The source catalogue. Every other language is a translation of this file, key for key.
 *
 * Two rules hold it together. **Plurals are declared, never concatenated**: a count reaches a
 * message as `{n}` and the forms are separated by `|`, because "1 day" and "5 дней" are not the
 * same sentence with a different number in it. And **nothing here is assembled from fragments**
 * at the call site: a translator needs the whole sentence to put its parts in the right order.
 */
export default {
  app: {
    title: 'Dates',
  },

  dates: {
    empty: 'No dates yet.',
  },

  login: {
    subtitle: 'The dates you must not forget, and a reminder before each one.',
    google: 'Continue with Google',
    or: 'or',
    email: 'Email',
    password: 'Password',
    signIn: 'Sign in',
    errors: {
      invalidCredential: 'Wrong email or password.',
      invalidEmail: 'That is not an email address.',
      disabled: 'This account has been disabled.',
      tooManyAttempts: 'Too many attempts. Try again in a few minutes.',
      offline: 'No connection — signing in needs one.',
      generic: 'Could not sign in. Try again.',
    },
  },

  account: {
    menu: 'Account',
    logout: 'Log out',
    delete: 'Delete account',
    deleteTitle: 'Delete this account?',
    deleteMessage:
      'Your dates and your reminders are deleted for good. This cannot be undone.',
    deleteConfirm: 'Delete for good',
    deleteFailed: 'The account could not be deleted. Nothing has been removed.',
    deleteOffline: 'No connection, so nothing was deleted. Try again when you are online.',
  },

  notices: {
    sessionExpired: 'Your session has expired.',
    signInAgain: 'Sign in again',
  },

  notFound: {
    title: 'Nothing here',
    home: 'Go home',
  },
}
