import { defineRouter } from '#q-app/wrappers'
import {
  createRouter,
  createMemoryHistory,
  createWebHistory,
  createWebHashHistory,
} from 'vue-router'
import routes from './routes'
import { useAuthStore } from 'src/stores/auth'

export default defineRouter((/* { store, ssrContext } */) => {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : process.env.VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    // Leave this as is and make changes in quasar.config.js instead!
    // quasar.config.js -> build -> vueRouterMode
    // quasar.config.js -> build -> publicPath
    history: createHistory(process.env.VUE_ROUTER_BASE),
  })

  // Every date in this app belongs to somebody, so there is nothing to show a visitor who is
  // not signed in. The answer is reliable by the time this runs: `boot/firebase.js` awaits the
  // store's `init()`, which settles only once Firebase has read back whatever session the
  // device had — so a cold launch with a valid session does not flash the login page on its way
  // to the list.
  Router.beforeEach((to) => {
    const authStore = useAuthStore()
    if (to.path !== '/login' && !authStore.isLoggedIn) return '/login'
    if (to.path === '/login' && authStore.isLoggedIn) return '/'
  })

  return Router
})
