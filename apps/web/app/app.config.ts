export default defineAppConfig({
  ui: {
    container: {
      base: 'max-w-7xl w-full'
    },
    colors: {
      primary: 'blue',
      neutral: 'slate'
    },
    theme: {
      colors: ['primary', 'secondary', 'tertiary', 'info', 'success', 'warning', 'error']
    },
    button: {
      slots: {
        base: 'cursor-pointer',
      }
    },
    dropdownMenu: {
      slots: {
        item: 'cursor-pointer',
      }
    },
    modal: {
      slots: {
        overlay: 'z-[100] bg-slate-950/60 backdrop-blur-xs',
        content: 'z-[110]',
      }
    }
  }
})
