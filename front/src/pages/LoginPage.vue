<template>
  <!-- A plain element, not a `q-page`: this route is deliberately outside `MainLayout` — its
       header carries the account menu, which a visitor with no account has nothing to do with —
       and a `q-page` outside a `q-layout` renders nothing at all. -->
  <div class="column items-center justify-center q-pa-lg window-height">
    <div class="text-h4 text-weight-bold q-mb-sm">{{ $t('app.title') }}</div>
    <div class="text-grey text-center q-mb-xl">{{ $t('login.subtitle') }}</div>

    <!-- Capped rather than full-width: two inputs stretched across a desktop window read as a
         form nobody finished, and the button above them has to keep their width to stay one
         choice between two ways in rather than two unrelated screens. -->
    <div class="full-width" style="max-width: 360px">
      <q-btn
        class="full-width"
        color="white"
        text-color="dark"
        size="lg"
        unelevated
        rounded
        no-caps
        :disable="busy"
        @click="signInWithGoogle"
      >
        <q-icon name="img:icons/google.svg" size="20px" class="q-mr-sm" />
        {{ $t('login.google') }}
      </q-btn>

      <div class="row items-center q-my-lg text-grey">
        <q-separator class="col" />
        <div class="q-px-md text-caption">{{ $t('login.or') }}</div>
        <q-separator class="col" />
      </div>

      <q-form @submit="signInWithEmail">
        <q-input
          v-model="email"
          type="email"
          outlined
          class="q-mb-sm"
          autocomplete="email"
          :label="$t('login.email')"
          :disable="busy"
        />

        <q-input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          outlined
          autocomplete="current-password"
          :label="$t('login.password')"
          :disable="busy"
        >
          <template #append>
            <q-icon
              class="cursor-pointer"
              :name="showPassword ? 'visibility_off' : 'visibility'"
              @click="showPassword = !showPassword"
            />
          </template>
        </q-input>

        <div v-if="error" class="text-negative text-body2 q-mt-sm">{{ error }}</div>

        <q-btn
          type="submit"
          class="full-width q-mt-md"
          color="primary"
          size="lg"
          unelevated
          rounded
          no-caps
          :label="$t('login.signIn')"
          :disable="!email || !password"
          :loading="busy"
        />
      </q-form>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from 'src/stores/auth'

export default {
  name: 'LoginPage',

  data() {
    return { email: '', password: '', showPassword: false, busy: false, error: '' }
  },

  computed: {
    authStore() {
      return useAuthStore()
    },
  },

  watch: {
    // Also fires `immediate`, which is what sends an already-signed-in visitor who typed
    // /#/login straight back out again.
    'authStore.isLoggedIn': {
      handler(loggedIn) {
        if (loggedIn) this.$router.push('/')
      },
      immediate: true,
    },
  },

  methods: {
    /**
     * Both ways in live in the store, because the expired-session banner signs in the same way
     * and there must not be two versions of what "sign in" means.
     *
     * `busy` is lowered whatever happens, including on the way to the list: what redirects is
     * the watcher above, on a session the store's `onAuthStateChanged` sets a tick later, and a
     * spinner left running until then would be one this page could not turn off if the backend
     * exchange failed and we stayed here.
     */
    async signInWithGoogle() {
      this.busy = true
      try {
        await this.authStore.loginWithGoogle()
      } finally {
        this.busy = false
      }
    },

    // Unlike the popup, this one has somewhere to put a reason, so a refusal is shown rather
    // than swallowed.
    async signInWithEmail() {
      this.busy = true
      this.error = ''
      try {
        await this.authStore.loginWithEmail(this.email, this.password)
        this.password = ''
      } catch (err) {
        this.error = err.message
      } finally {
        this.busy = false
      }
    },
  },
}
</script>
