import type { NavigationMenuItem } from "@nuxt/ui";
import type { NavItem } from "~/stores/auth";

export interface RouteHeaderMeta {
  title: string;
  icon: string;
}

export interface AdminBreadcrumbItem {
  label: string;
  icon?: string;
  to?: string;
}

export type AdminNavItemDef = NavItem;

export function useAdminNavigation() {
  const route = useRoute();
  const auth = useAuthStore();

  // Dynamic navigation tree sourced directly from the authenticated user
  const navTree = computed<AdminNavItemDef[]>(() => auth.user?.navigation ?? []);

  // Map of path to header metadata built dynamically from the navigation tree
  const flatHeaderMap = computed<Record<string, RouteHeaderMeta>>(() => {
    const map: Record<string, RouteHeaderMeta> = {};

    function walk(items: AdminNavItemDef[]) {
      for (const item of items) {
        if (item.to) {
          map[item.to.replace(/\/$/, "")] = {
            title: item.label,
            icon: item.icon,
          };
        }
        if (item.children) {
          walk(item.children);
        }
      }
    }

    walk(navTree.value);
    return map;
  });

  const currentHeader = computed<RouteHeaderMeta>(() => {
    const path = route.path.replace(/\/$/, "");
    const headers = flatHeaderMap.value;

    if (headers[path]) return headers[path];

    for (const [key, val] of Object.entries(headers)) {
      if (key !== "/admin" && key !== "/" && path.startsWith(key)) {
        return val;
      }
    }

    if (route.meta?.title) {
      return { title: String(route.meta.title), icon: "i-lucide-layout" };
    }

    const segment = path.split("/").filter(Boolean).pop() || "Admin";
    const formatted = segment
      .split("-")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
    return { title: formatted, icon: "i-lucide-layout" };
  });

  const breadcrumbs = computed<AdminBreadcrumbItem[]>(() => {
    const path = route.path.replace(/\/$/, "") || "/admin";
    const tree = navTree.value;

    // 1. Check nested children in navigation tree
    for (const group of tree) {
      if (group.children && group.children.length > 0) {
        const matchedChild = group.children.find(
          (c) => c.to === path || (c.to && c.to !== "/admin" && c.to !== "/" && path.startsWith(c.to))
        );

        if (matchedChild) {
          const items: AdminBreadcrumbItem[] = [
            {
              label: group.label,
              icon: group.icon,
              to: group.to || group.children[0]?.to,
            },
            {
              label: matchedChild.label,
              icon: matchedChild.icon,
              to: matchedChild.to === path ? undefined : matchedChild.to,
            },
          ];

          // Additional sub-path segment (e.g. /admin/dataset/jalan-poros-desa/create)
          if (matchedChild.to && path.length > matchedChild.to.length) {
            const sub = path.slice(matchedChild.to.length).replace(/^\//, "");
            if (sub) {
              const subLabel = sub
                .split("/")
                .map((seg) =>
                  seg
                    .split("-")
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join(" ")
                )
                .join(" / ");
              items.push({
                label: subLabel,
              });
            }
          }

          return items;
        }
      }
    }

    // 2. Check top-level items without children
    for (const item of tree) {
      if (!item.children || item.children.length === 0) {
        if (
          item.to === path ||
          (!item.exact && item.to && item.to !== "/admin" && item.to !== "/" && path.startsWith(item.to))
        ) {
          const items: AdminBreadcrumbItem[] = [
            {
              label: item.label,
              icon: item.icon,
              to: item.to === path ? undefined : item.to,
            },
          ];

          if (item.to && path.length > item.to.length) {
            const sub = path.slice(item.to.length).replace(/^\//, "");
            if (sub) {
              const subLabel = sub
                .split("/")
                .map((seg) =>
                  seg
                    .split("-")
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join(" ")
                )
                .join(" / ");
              items.push({
                label: subLabel,
              });
            }
          }

          return items;
        }
      }
    }

    // 3. Fallback: single item from currentHeader
    return [
      {
        label: currentHeader.value.title,
        icon: currentHeader.value.icon,
      },
    ];
  });

  function getNavItems(state: "collapsed" | "expanded"): NavigationMenuItem[] {
    const isCollapsed = state === "collapsed";

    return navTree.value.map((item) => {
      const visibleChildren = item.children ?? [];

      return {
        label: item.label,
        icon: item.icon,
        to: isCollapsed
          ? item.to
          : (visibleChildren.length ? undefined : item.to),
        exact: item.exact,
        defaultOpen: true,
        tooltip: isCollapsed ? { text: item.label, content: { side: "right" } } : undefined,
        children: !isCollapsed
          ? visibleChildren.map((child) => ({
              label: child.label,
              icon: child.icon,
              to: child.to,
            }))
          : [],
      };
    });
  }

  return {
    navTree,
    adminNavTree: navTree,
    routeHeaders: flatHeaderMap,
    currentHeader,
    breadcrumbs,
    getNavItems,
  };
}
