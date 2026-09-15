// `#q-app/wrappers` only exists to give boot files, the router and the store their types; at
// runtime each wrapper hands the function straight back. Standing them in lets those modules be
// imported and called under plain vitest, with no Quasar build in the way.
export const defineBoot = (fn) => fn
export const defineRouter = (fn) => fn
export const defineStore = (fn) => fn
