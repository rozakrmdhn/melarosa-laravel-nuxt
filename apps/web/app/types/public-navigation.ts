export interface PublicNavItem {
  label: string;
  to: string;
  exact?: boolean;
  description?: string;
  icon?: string;
  badge?: string;
  target?: '_blank' | '_self';
  children?: PublicNavItem[];
}

export interface PublicBreadcrumbItem {
  label: string;
  to?: string;
  icon?: string;
}

export interface PublicHeaderMeta {
  title: string;
  description?: string;
  icon?: string;
}
