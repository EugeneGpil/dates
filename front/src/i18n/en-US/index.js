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

  notFound: {
    title: 'Nothing here',
    home: 'Go home',
  },
}
