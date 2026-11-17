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
        // Portaled dropdown/select menus must stack above UModal/USlideover/UDrawer content (z-[110])
        content: 'z-[130] pointer-events-auto',
        item: 'cursor-pointer',
      }
    },
    selectMenu: {
      slots: {
        content: 'z-[130] pointer-events-auto',
        item: 'cursor-pointer',
      }
    },
    select: {
      slots: {
        content: 'z-[130] pointer-events-auto',
        item: 'cursor-pointer',
      }
    },
    popover: {
      slots: {
        content: 'z-[130] pointer-events-auto',
      }
    },
    tooltip: {
      slots: {
        content: 'z-[140]',
      }
    },
    toaster: {
      slots: {
        viewport: 'z-[150]',
      }
    },
    modal: {
      slots: {
        overlay: 'z-[100] bg-slate-950/60 backdrop-blur-xs',
        content: 'z-[110]',
      }
    },
    slideover: {
      slots: {
        overlay: 'z-[100] bg-slate-950/60 backdrop-blur-xs',
        content: 'z-[110]',
      }
    },
    drawer: {
      slots: {
        overlay: 'z-[100] bg-slate-950/60 backdrop-blur-xs',
        content: 'z-[110]',
      }
    }
  }
})
