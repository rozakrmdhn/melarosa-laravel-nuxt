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
                'permission' => ['dashboard.view'],
            ],
            [
                'label' => 'User Management',
                'icon' => 'i-lucide-users',
                'to' => '/admin/permissions',
                'permission' => ['permissions.view', 'roles.view', 'users.view'],
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
                        'permission' => ['roles.view', 'roles.manage'],
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
                    'batas-kecamatan.view',
                    'batas-kecamatan.manage',
                    'batas-desa.view',
                    'batas-desa.manage',
                    'jalan-poros-desa.view',
                    'jalan-poros-desa.manage',
                ],
                'children' => [
                    [
                        'label' => 'Wilayah Kecamatan',
                        'icon' => 'i-lucide-map',
                        'to' => '/admin/dataset/wilayah-kecamatan',
                        'permission' => ['batas-kecamatan.view', 'batas-kecamatan.manage'],
                    ],
                    [
                        'label' => 'Wilayah Desa',
                        'icon' => 'i-lucide-map-pin',
                        'to' => '/admin/dataset/wilayah-desa',
                        'permission' => ['batas-desa.view', 'batas-desa.manage'],
                    ],
                    [
                        'label' => 'Jalan Poros Desa',
                        'icon' => 'i-lucide-route',
                        'to' => '/admin/dataset/jalan-poros-desa',
                        'permission' => ['jalan-poros-desa.view', 'jalan-poros-desa.manage'],
                    ],
                ],
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
            unset($node['permission']);
            $result[] = $node;
        }

        return $result;
    }
}
