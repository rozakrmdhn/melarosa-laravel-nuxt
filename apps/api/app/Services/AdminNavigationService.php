<?php

namespace App\Services;

use App\Models\User;

class AdminNavigationService
{
    /**
     * Master configuration for admin navigation items
     */
    public static function getMasterNavTree(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'icon' => 'i-lucide-inbox',
                'to' => '/admin',
                'exact' => true,
                'permission' => ['dashboard-view'],
            ],
            [
                'label' => 'User Management',
                'icon' => 'i-lucide-users',
                'to' => '/admin/permissions',
                'permission' => ['permissions-view', 'roles-view', 'users-view'],
                'children' => [
                    [
                        'label' => 'Hak Akses',
                        'icon' => 'i-lucide-key',
                        'to' => '/admin/permissions',
                        'permission' => ['permissions-view', 'permissions-manage'],
                    ],
                    [
                        'label' => 'Akses Group',
                        'icon' => 'i-lucide-shield',
                        'to' => '/admin/roles',
                        'permission' => ['roles-view', 'roles-manage'],
                    ],
                    [
                        'label' => 'Pengguna',
                        'icon' => 'i-lucide-user',
                        'to' => '/admin/users',
                        'permission' => ['users-view', 'users-manage'],
                    ],
                ],
            ],
            [
                'label' => 'Dataset',
                'icon' => 'i-lucide-map',
                'to' => '/admin/dataset/wilayah-kecamatan',
                'permission' => [
                    'batas-kecamatan-view',
                    'batas-kecamatan-manage',
                    'batas-desa-view',
                    'batas-desa-manage',
                    'jalan-poros-desa-view',
                    'jalan-poros-desa-manage',
                ],
                'children' => [
                    [
                        'label' => 'Wilayah Kecamatan',
                        'icon' => 'i-lucide-map',
                        'to' => '/admin/dataset/wilayah-kecamatan',
                        'permission' => ['batas-kecamatan-view', 'batas-kecamatan-manage'],
                    ],
                    [
                        'label' => 'Wilayah Desa',
                        'icon' => 'i-lucide-map-pin',
                        'to' => '/admin/dataset/wilayah-desa',
                        'permission' => ['batas-desa-view', 'batas-desa-manage'],
                    ],
                    [
                        'label' => 'Jalan Poros Desa',
                        'icon' => 'i-lucide-route',
                        'to' => '/admin/dataset/jalan-poros-desa',
                        'permission' => ['jalan-poros-desa-view', 'jalan-poros-desa-manage'],
                    ],
                ],
            ],
            [
                'label' => 'Monitoring Infrastruktur',
                'icon' => 'i-lucide-activity',
                'to' => '/admin/plotting-anggaran',
                'permission' => [
                    'plotting-anggaran-view',
                    'plotting-anggaran-manage',
                    'infrastruktur-segmen-view',
                    'infrastruktur-segmen-create',
                    'infrastruktur-segmen-verify-kecamatan',
                    'infrastruktur-segmen-verify-bappeda',
                    'monitoring-realisasi-view',
                    'monitoring-realisasi-manage',
                ],
                'children' => [
                    [
                        'label' => 'Plotting Anggaran',
                        'icon' => 'i-lucide-wallet',
                        'to' => '/admin/plotting-anggaran',
                        'permission' => ['plotting-anggaran-view', 'plotting-anggaran-manage'],
                    ],
                    [
                        'label' => 'Segmen Fisik',
                        'icon' => 'i-lucide-git-commit',
                        'to' => '/admin/infrastruktur-segmen',
                        'permission' => ['infrastruktur-segmen-view', 'infrastruktur-segmen-create'],
                    ],
                    [
                        'label' => 'Rekapitulasi Jalan',
                        'icon' => 'i-lucide-table-properties',
                        'to' => '/admin/rekap-jalan',
                        'permission' => ['infrastruktur-segmen-view', 'jalan-poros-desa-view'],
                    ],
                    [
                        'label' => 'Verifikasi Lapangan',
                        'icon' => 'i-lucide-check-check',
                        'to' => '/admin/verifikasi-segmen',
                        'permission' => ['infrastruktur-segmen-verify-kecamatan', 'infrastruktur-segmen-verify-bappeda'],
                    ],
                    [
                        'label' => 'Berita Acara Realisasi',
                        'icon' => 'i-lucide-clipboard-check',
                        'to' => '/admin/monitoring-realisasi',
                        'permission' => ['monitoring-realisasi-view', 'monitoring-realisasi-manage'],
                    ],
                    [
                        'label' => 'Tipe Infrastruktur',
                        'icon' => 'i-lucide-layers-2',
                        'to' => '/admin/infrastruktur-tipe',
                        'permission' => ['infrastruktur-tipe-view', 'infrastruktur-tipe-manage'],
                    ],
                    [
                        'label' => 'Sumber Dana',
                        'icon' => 'i-lucide-coins',
                        'to' => '/admin/ref-sumber-dana',
                        'permission' => ['ref-sumber-dana-view', 'ref-sumber-dana-manage'],
                    ],
                    [
                        'label' => 'Jadwal Tahun Anggaran',
                        'icon' => 'i-lucide-calendar-clock',
                        'to' => '/admin/jadwal-anggaran',
                        'permission' => ['jadwal-anggaran-view', 'jadwal-anggaran-manage'],
                    ],
                ],
            ],
            [
                'label' => 'Proposal BKKD',
                'icon' => 'i-lucide-file-text',
                'to' => '/admin/bkkd/proposal',
                'permission' => [
                    'bkkd-proposal-view',
                    'bkkd-proposal-create',
                    'bkkd-riwayat-view',
                ],
                'children' => [
                    [
                        'label' => 'Analisis Proposal',
                        'icon' => 'i-lucide-layers',
                        'to' => '/admin/bkkd/proposal',
                        'permission' => ['bkkd-proposal-view'],
                    ],
                    [
                        'label' => 'Workspace Peta Baru',
                        'icon' => 'i-lucide-map-pin-plus',
                        'to' => '/admin/bkkd/proposal/baru',
                        'permission' => ['bkkd-proposal-create'],
                    ],
                    [
                        'label' => 'Agregasi Usulan Desa',
                        'icon' => 'i-lucide-table-properties',
                        'to' => '/admin/bkkd/agregasi-desa',
                        'permission' => ['bkkd-proposal-view'],
                    ],
                    [
                        'label' => 'Riwayat Perubahan',
                        'icon' => 'i-lucide-history',
                        'to' => '/admin/bkkd/riwayat',
                        'permission' => ['bkkd-riwayat-view'],
                    ],
                ],
            ],
            [
                'label' => 'Jaringan Jalan',
                'icon' => 'i-lucide-network',
                'to' => '/admin/jaringan-jalan',
                'permission' => [
                    'bkkd-proposal-view',
                    'jalan-poros-desa-view',
                    'jalan-poros-desa-manage',
                ],
            ],
            [
                'label' => 'Layers',
                'icon' => 'i-lucide-layers',
                'to' => '/admin/layers',
                'permission' => ['layers-view'],
            ],
            [
                'label' => 'Basemap',
                'icon' => 'i-lucide-globe',
                'to' => '/admin/basemaps',
                'permission' => ['layers-view', 'layers-manage', 'settings-view', 'settings-manage'],
            ],
            [
                'label' => 'Publikasi',
                'icon' => 'i-lucide-file-down',
                'to' => '/admin/publikasi-peta',
                'permission' => ['layers-view', 'layers-manage', 'batas-desa-view', 'batas-desa-manage', 'settings-view', 'settings-manage'],
            ],
            [
                'label' => 'Audit Log',
                'icon' => 'i-lucide-history',
                'to' => '/admin/audit-logs',
                'permission' => ['audit-logs-view'],
            ],
            [
                'label' => 'Backup & Restore',
                'icon' => 'i-lucide-database-backup',
                'to' => '/admin/backups',
                'roles' => ['admin'],
            ],
            [
                'label' => 'Settings',
                'icon' => 'i-lucide-settings',
                'to' => '/admin/account/general',
                'children' => [
                    [
                        'label' => 'Account Profile',
                        'icon' => 'i-lucide-user',
                        'to' => '/admin/account/general',
                    ],
                    [
                        'label' => 'Connected Devices',
                        'icon' => 'i-lucide-smartphone',
                        'to' => '/admin/account/devices',
                    ],
                    [
                        'label' => 'Public Site',
                        'icon' => 'i-lucide-external-link',
                        'to' => '/',
                        'target' => '_blank',
                        'exact' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Filter master navigation tree according to user's permissions and roles
     */
    public function getFilteredMenu(User $user): array
    {
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');
        $tree = self::getMasterNavTree();

        return $this->filterNodes($tree, $user, $isAdmin);
    }

    /**
     * Recursively filter navigation nodes
     */
    protected function filterNodes(array $nodes, User $user, bool $isAdmin): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (! empty($node['roles'])) {
                $allowedRoles = (array) $node['roles'];
                $hasRole = false;
                foreach ($allowedRoles as $role) {
                    if ($user->hasRole($role)) {
                        $hasRole = true;
                        break;
                    }
                }
                if (! $hasRole) {
                    continue;
                }
            }

            $hasPermission = true;

            if (!$isAdmin && !empty($node['permission'])) {
                $permissions = (array) $node['permission'];
                $hasPermission = false;

                foreach ($permissions as $perm) {
                    if ($user->can($perm)) {
                        $hasPermission = true;
                        break;
                    }
                }
            }

            if (!$hasPermission) {
                continue;
            }

            // Filter children if present
            if (!empty($node['children'])) {
                $filteredChildren = $this->filterNodes($node['children'], $user, $isAdmin);

                // If node has children defined but none are accessible, skip parent node
                if (empty($filteredChildren)) {
                    continue;
                }

                $node['children'] = array_values($filteredChildren);
            }

            // Remove internal permission metadata from frontend payload
            unset($node['permission'], $node['roles']);
            $result[] = $node;
        }

        return $result;
    }
}
