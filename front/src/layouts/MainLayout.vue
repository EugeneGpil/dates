<template>
  <q-layout view="lHh Lpr lFf">
    <!-- The header exists for the account menu, and only for it: everything the app *does*
         belongs to the page below. It lives in the layout rather than in `DatesPage` so that
         "log out" and "delete account" stay in one place as pages are added. -->
    <q-header elevated>
      <q-toolbar>
        <q-toolbar-title>{{ $t('app.title') }}</q-toolbar-title>

        <q-btn v-if="authStore.isLoggedIn" flat round dense :aria-label="$t('account.menu')">
          <q-avatar size="32px">
            <img v-if="avatar" :src="avatar" alt="" />
            <q-icon v-else name="account_circle" size="32px" />
          </q-avatar>

          <q-menu>
            <q-list style="min-width: 180px">
              <q-item v-if="email" class="text-caption text-grey">
                <q-item-section>{{ email }}</q-item-section>
              </q-item>
              <q-separator v-if="email" />
              <q-item clickable @click="onLogout">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>{{ $t('account.logout') }}</q-item-section>
              </q-item>
              <!-- Last, separated, and the only red thing in the menu: it is one tap from the
                   same place as "Log out" and does something nothing can undo. -->
              <q-separator />
              <q-item clickable @click="onDeleteAccount">
                <q-item-section avatar>
                  <q-icon name="delete_forever" color="negative" />
                </q-item-section>
                <q-item-section class="text-negative">
                  {{ $t('account.delete') }}
                </q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-page-container>
      <SessionExpiredBanner />
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import SessionExpiredBanner from 'src/components/SessionExpiredBanner.vue'
import { isNetworkError } from 'src/api'
import { useAuthStore } from 'src/stores/auth'

export default {
  name: 'MainLayout',

  components: { SessionExpiredBanner },

  computed: {
    authStore() {
      return useAuthStore()
    },

    // Google's own photograph, straight from the Firebase session rather than from our copy of
    // it: both say the same thing, and this one is there on the first frame after a cold launch.
    avatar() {
      return this.authStore.user?.photoURL || null
    },

    email() {
      return this.authStore.user?.email || null
    },
  },

  methods: {
    async onLogout() {
      await this.authStore.logout()
      this.$router.push('/login')
    },

    /**
     * The one action in the app with no undo and no server-side copy to recover from — Play's
     * User Data policy requires it to be reachable from inside the app, and the privacy policy
     * describes exactly what it removes.
     *
     * `persistent` because a tap outside the dialog is not consent, and the button says what it
     * does rather than "OK" — this menu entry sits one row below "Log out", and the two must not
     * be confusable at the moment of confirming.
     */
    onDeleteAccount() {
      this.$q
        .dialog({
          title: this.$t('account.deleteTitle'),
          message: this.$t('account.deleteMessage'),
          cancel: true,
          persistent: true,
          ok: { label: this.$t('account.deleteConfirm'), color: 'negative' },
        })
        .onOk(async () => {
          try {
            await this.authStore.deleteAccount()
          } catch (err) {
            // Nothing has been touched, so the session is still usable and the only thing to do
            // is say so. Unlike an edit this cannot be queued for later: a deletion that happens
            // whenever the connection returns is not something to leave armed.
            this.$q.notify({
              type: 'negative',
              message: isNetworkError(err)
                ? this.$t('account.deleteOffline')
                : this.$t('account.deleteFailed'),
            })
            return
          }
          // `endSession`, never `logout` — a request now would resurrect the account through
          // the 401 recovery path. See `deleteAccount` in the auth store.
          await this.authStore.endSession()
          this.$router.push('/login')
        })
    },
  },
}
</script>
