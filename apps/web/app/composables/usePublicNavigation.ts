import type { NavigationMenuItem } from '@nuxt/ui';
import type { PublicNavItem, PublicBreadcrumbItem, PublicHeaderMeta } from '~/types/public-navigation';

// Shared state across the public app lifecycle
const publicNavTreeState = ref<PublicNavItem[]>([
  {
    label: 'Beranda',
    to: '/',
    exact: true,
    description: 'Portal spasial dan data geospasial Kabupaten Bojonegoro',
  },
  {
    label: 'Peta',
    to: '/maps',
    description: 'Peta digital batas wilayah desa, batas kecamatan, dan jalan poros',
  },
  {
    label: 'GeoStory',
    to: '/geostory',
    description: 'Peta digital batas wilayah desa, batas kecamatan, dan jalan poros',
  },
  {
    label: 'Katalog Data',
    to: '/katalog-data',
    description: 'Pusat informasi dan direktori data geospasial Kabupaten Bojonegoro',
  },
]);

export function usePublicNavigation() {
  const route = useRoute();

  const navTree = computed<PublicNavItem[]>(() => publicNavTreeState.value);

  function setNavTree(items: PublicNavItem[]) {
    publicNavTreeState.value = items;
  }

  function addNavItem(item: PublicNavItem) {
    const existingIndex = publicNavTreeState.value.findIndex(existing => existing.to === item.to);
    if (existingIndex >= 0) {
      publicNavTreeState.value[existingIndex] = item;
    } else {
      publicNavTreeState.value.push(item);
    }
  }

  function removeNavItem(to: string) {
    publicNavTreeState.value = publicNavTreeState.value.filter(item => item.to !== to);
  }

  const flatHeaderMap = computed<Record<string, PublicHeaderMeta>>(() => {
    const map: Record<string, PublicHeaderMeta> = {};

    function walk(items: PublicNavItem[]) {
      for (const item of items) {
        if (item.to) {
          const normalizedPath = item.to.replace(/\/$/, '') || '/';
          map[normalizedPath] = {
            title: item.label,
            description: item.description,
            icon: item.icon,
          };
        }
        if (item.children && item.children.length > 0) {
          walk(item.children);
        }
      }
    }

    walk(navTree.value);
    return map;
  });

  const currentHeader = computed<PublicHeaderMeta>(() => {
    const path = route.path.replace(/\/$/, '') || '/';
    const headers = flatHeaderMap.value;

    if (headers[path]) {
      return headers[path];
    }

    for (const [key, val] of Object.entries(headers)) {
      if (key !== '/' && path.startsWith(key)) {
        return val;
      }
    }

    if (route.meta?.title) {
      return {
        title: String(route.meta.title),
        description: route.meta.description ? String(route.meta.description) : undefined,
      };
    }

    const segment = path.split('/').filter(Boolean).pop() || 'Halaman';
    const formatted = segment
      .split('-')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');

    return {
      title: formatted,
    };
  });

  const breadcrumbs = computed<PublicBreadcrumbItem[]>(() => {
    const path = route.path.replace(/\/$/, '') || '/';
    const trail: PublicBreadcrumbItem[] = [];

    const homeItem = navTree.value.find(item => item.to === '/' || item.exact);
    const homeLabel = homeItem ? homeItem.label : 'Beranda';

    if (path === '/') {
      return [
        {
          label: homeLabel,
        },
      ];
    }

    trail.push({
      label: homeLabel,
      to: '/',
    });

    for (const topItem of navTree.value) {
      if (topItem.children && topItem.children.length > 0) {
        const matchedChild = topItem.children.find(child =>
          child.to === path || (child.to && child.to !== '/' && path.startsWith(child.to))
        );

        if (matchedChild) {
          trail.push({
            label: topItem.label,
            to: topItem.to,
          });

          trail.push({
            label: matchedChild.label,
            to: matchedChild.to === path ? undefined : matchedChild.to,
          });

          if (matchedChild.to && path.length > matchedChild.to.length) {
            const sub = path.slice(matchedChild.to.length).replace(/^\//, '');
            if (sub) {
              const subLabel = sub
                .split('/')
                .map(segment =>
                  segment
                    .split('-')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ')
                )
                .join(' / ');

              trail.push({
                label: subLabel,
              });
            }
          }

          return trail;
        }
      }
    }

    for (const item of navTree.value) {
      if (!item.children || item.children.length === 0) {
        if (item.to === path || (!item.exact && item.to && item.to !== '/' && path.startsWith(item.to))) {
          trail.push({
            label: item.label,
            to: item.to === path ? undefined : item.to,
          });

          if (item.to && path.length > item.to.length) {
            const sub = path.slice(item.to.length).replace(/^\//, '');
            if (sub) {
              const subLabel = sub
                .split('/')
                .map(segment =>
                  segment
                    .split('-')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ')
                )
                .join(' / ');

              trail.push({
                label: subLabel,
              });
            }
          }

          return trail;
        }
      }
    }

    trail.push({
      label: currentHeader.value.title,
    });

    return trail;
  });

  function isPathActive(targetPath?: string, exact?: boolean): boolean {
    if (!targetPath) return false;
    const current = route.path.split('?')[0].replace(/\/$/, '') || '/';
    const target = targetPath.split('?')[0].replace(/\/$/, '') || '/';

    if (exact || target === '/') {
      return current === target;
    }

    return current === target || current.startsWith(`${target}/`);
  }

  const navMenuItems = computed<NavigationMenuItem[]>(() => {
    return navTree.value.map(item => {
      const active = isPathActive(item.to, item.exact);
      return {
        label: item.label,
        to: item.to,
        icon: item.icon,
        target: item.target,
        badge: item.badge,
        active,
        children: item.children?.map(child => ({
          label: child.label,
          to: child.to,
          icon: child.icon,
          target: child.target,
          badge: child.badge,
          active: isPathActive(child.to, child.exact),
        })),
      };
    });
  });

  return {
    navTree,
    setNavTree,
    addNavItem,
    removeNavItem,
    flatHeaderMap,
    currentHeader,
    breadcrumbs,
    isPathActive,
    navMenuItems,
  };
}
