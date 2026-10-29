import type { RouterConfig } from '@nuxt/schema';

export default <RouterConfig>{
  scrollBehavior(to, from) {
    if (to.hash) {
      return {
        el: to.hash,
        behavior: 'smooth',
      };
    }

    if (to.path !== from.path) {
      return {
        top: 0,
        left: 0,
        behavior: 'instant',
      };
    }

    return false;
  },
};

