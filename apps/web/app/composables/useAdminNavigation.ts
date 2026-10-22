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

  function normalizePath(p?: string): string {
    if (!p) return "";
    const clean = p.split("?")[0];
    return clean === "/" ? "/" : clean.replace(/\/$/, "");
  }

  // Dynamic navigation tree sourced directly from the authenticated user
  const navTree = computed<AdminNavItemDef[]>(() => auth.user?.navigation ?? []);

  // Map of path to header metadata built dynamically from the navigation tree
  const flatHeaderMap = computed<Record<string, RouteHeaderMeta>>(() => {
    const map: Record<string, RouteHeaderMeta> = {};

    function walk(items: AdminNavItemDef[]) {
      for (const item of items) {
        if (item.to) {
          const key = normalizePath(item.to);
          if (key) {
            map[key] = {
              title: item.label,
              icon: item.icon,
            };
          }
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
    const path = normalizePath(route.path);
    const headers = flatHeaderMap.value;

    if (headers[path]) return headers[path];

    for (const [key, val] of Object.entries(headers)) {
      if (key !== "/admin" && key !== "/" && (path === key || path.startsWith(`${key}/`))) {
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
    const path = normalizePath(route.path) || "/admin";
    const tree = navTree.value;

    // 1. Check nested children in navigation tree
    for (const group of tree) {
      if (group.children && group.children.length > 0) {
        const matchedChild = group.children.find((c) => {
          if (!c.to) return false;
          const target = normalizePath(c.to);
          if (c.exact || target === "/admin" || target === "/") {
            return path === target;
          }
          return path === target || path.startsWith(`${target}/`);
        });

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
              to: normalizePath(matchedChild.to) === path ? undefined : matchedChild.to,
            },
          ];

          // Additional sub-path segment (e.g. /admin/dataset/jalan-poros-desa/create)
          const childTarget = normalizePath(matchedChild.to);
          if (
            childTarget &&
            childTarget !== "/" &&
            childTarget !== "/admin" &&
            path.length > childTarget.length &&
            path.startsWith(`${childTarget}/`)
          ) {
            const sub = path.slice(childTarget.length).replace(/^\//, "");
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
        const itemTarget = normalizePath(item.to);
        const isMatched =
          item.exact || itemTarget === "/admin" || itemTarget === "/"
            ? path === itemTarget
            : path === itemTarget || (itemTarget ? path.startsWith(`${itemTarget}/`) : false);

        if (isMatched) {
          const items: AdminBreadcrumbItem[] = [
            {
              label: item.label,
              icon: item.icon,
              to: itemTarget === path ? undefined : item.to,
            },
          ];

          if (
            itemTarget &&
            itemTarget !== "/" &&
            itemTarget !== "/admin" &&
            path.length > itemTarget.length &&
            path.startsWith(`${itemTarget}/`)
          ) {
            const sub = path.slice(itemTarget.length).replace(/^\//, "");
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

  function isPathActive(targetPath?: string, exact?: boolean): boolean {
    if (!targetPath) return false;
    const current = normalizePath(route.path);
    const target = normalizePath(targetPath);

    if (exact || target === "/admin" || target === "/") {
      return current === target;
    }

    return current === target || current.startsWith(`${target}/`);
  }

  function getNavItems(state: "collapsed" | "expanded"): NavigationMenuItem[] {
    const isCollapsed = state === "collapsed";

    return navTree.value.map((item) => {
      const visibleChildren = item.children ?? [];
      const hasActiveChild = visibleChildren.some((child) => isPathActive(child.to, child.exact));
      const isSelfActive = isPathActive(item.to, item.exact);
      const isItemActive = isCollapsed
        ? (hasActiveChild || isSelfActive)
        : (visibleChildren.length ? false : isSelfActive);

      return {
        label: item.label,
        icon: item.icon,
        to: isCollapsed
          ? item.to
          : (visibleChildren.length ? undefined : item.to),
        exact: item.exact,
        active: isItemActive,
        defaultOpen: true,
        tooltip: isCollapsed ? { text: item.label, content: { side: "right" } } : undefined,
        children: !isCollapsed
          ? visibleChildren.map((child) => ({
              label: child.label,
              icon: child.icon,
              to: child.to,
              target: child.target,
              exact: child.exact ?? (child.to === "/" || child.to === "/admin"),
              active: isPathActive(child.to, child.exact),
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
