// Stands in for the Quasar plugins the app reaches for outside a component. The tests assert on
// what would have been shown, so the messages are recorded rather than rendered.
export const notifications = []

export const Notify = {
  create: (opts) => notifications.push(opts),
}

export const Lang = {
  set: () => {},
}
