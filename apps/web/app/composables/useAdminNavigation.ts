import type { NavigationMenuItem } from "@nuxt/ui";

export interface RouteHeaderMeta {
  title: string;
  icon: string;
}

export interface AdminBreadcrumbItem {
  label: string;
  icon?: string;
  to?: string;
}

export interface AdminNavItemDef {
  label: string;
  icon: string;
  to?: string;
  exact?: boolean;
  children?: AdminNavItemDef[];
}

export const routeHeaders: Record<string, RouteHeaderMeta> = {
  "/admin": { title: "Dashboard", icon: "i-lucide-inbox" },
  "/admin/roles": { title: "Roles Management", icon: "i-lucide-square-dot" },
  "/admin/permissions": { title: "Permissions Management", icon: "i-lucide-square-activity" },
  "/admin/users": { title: "User Access", icon: "i-lucide-users" },
  "/admin/dataset/wilayah-kecamatan": { title: "Wilayah Kecamatan", icon: "i-lucide-map" },
  "/admin/dataset/wilayah-desa": { title: "Wilayah Desa", icon: "i-lucide-map-pin" },
  "/admin/dataset/jalan-poros-desa": { title: "Jalan Poros Desa", icon: "i-lucide-route" },
  "/admin/account": { title: "Account Settings", icon: "i-lucide-settings" },
  "/admin/account/general": { title: "Account Profile", icon: "i-lucide-user" },
  "/admin/account/devices": { title: "Connected Devices", icon: "i-lucide-smartphone" },
};

export const adminNavTree: AdminNavItemDef[] = [
  {
    label: "Dashboard",
    icon: "i-lucide-inbox",
    to: "/admin",
    exact: true,
  },
  {
    label: "Roles",
    icon: "i-lucide-square-dot",
    to: "/admin/roles",
  },
  {
    label: "Permissions",
    icon: "i-lucide-square-activity",
    to: "/admin/permissions",
  },
  {
    label: "User Access",
    icon: "i-lucide-users",
    to: "/admin/users",
  },
  {
    label: "Dataset",
    icon: "i-lucide-map",
    to: "/admin/dataset/wilayah-kecamatan",
    children: [
      {
        label: "Wilayah Kecamatan",
        icon: "i-lucide-map",
        to: "/admin/dataset/wilayah-kecamatan",
      },
      {
        label: "Wilayah Desa",
        icon: "i-lucide-map-pin",
        to: "/admin/dataset/wilayah-desa",
      },
      {
        label: "Jalan Poros Desa",
        icon: "i-lucide-route",
        to: "/admin/dataset/jalan-poros-desa",
      },
    ],
  },
  {
    label: "Settings",
    icon: "i-lucide-settings",
    to: "/admin/account/general",
    children: [
      {
        label: "Account Profile",
        icon: "i-lucide-user",
        to: "/admin/account/general",
      },
      {
        label: "Connected Devices",
        icon: "i-lucide-smartphone",
        to: "/admin/account/devices",
      },
      {
        label: "Public Site",
        icon: "i-lucide-external-link",
        to: "/",
      },
    ],
  },
];

export function useAdminNavigation() {
  const route = useRoute();

  const currentHeader = computed<RouteHeaderMeta>(() => {
    const path = route.path.replace(/\/$/, "");
    if (routeHeaders[path]) return routeHeaders[path];

    for (const [key, val] of Object.entries(routeHeaders)) {
      if (key !== "/admin" && path.startsWith(key)) {
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

    // 1. Check nested children in sidebar navigation
    for (const group of adminNavTree) {
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
    for (const item of adminNavTree) {
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

    return adminNavTree.map((item) => ({
      label: item.label,
      icon: item.icon,
      to: isCollapsed ? item.to : (item.children?.length ? undefined : item.to),
      exact: item.exact,
      defaultOpen: true,
      tooltip: isCollapsed ? { text: item.label, content: { side: "right" } } : undefined,
      children: !isCollapsed && item.children
        ? item.children.map((child) => ({
            label: child.label,
            icon: child.icon,
            to: child.to,
          }))
        : [],
    }));
  }

  return {
    routeHeaders,
    adminNavTree,
    currentHeader,
    breadcrumbs,
    getNavItems,
  };
}

