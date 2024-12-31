<?php

namespace plugin\smsbao\api;

use plugin\admin\api\Menu;
use plugin\smsbao\app\admin\controller\SettingController;

class Install
{
    /**
     * 安装
     *
     * @param $version
     * @return void
     */
    public static function install($version)
    {
        // 导入菜单
        if($menus = static::getMenus()) {
            Menu::import($menus);
        }

        if (Menu::get(SettingController::class)) {
            return;
        }
        // 找到通用菜单
        $commonMenu = Menu::get('common');
        if (!$commonMenu) {
            echo "未找到通用设置菜单" . PHP_EOL;
            return;
        }
        // 以通用菜单为上级菜单插入菜单
        $pid = $commonMenu['id'];
        Menu::add([
            'title' => '短信宝设置',
            'href' => '/app/smsbao/admin/setting',
            'pid' => $pid,
            'key' => SettingController::class,
            'weight' => 0,
            'type' => 1,
        ]);
    }

    /**
     * 卸载
     *
     * @param $version
     * @return void
     */
    public static function uninstall($version)
    {
        // 删除菜单
        foreach (static::getMenus() as $menu) {
            Menu::delete($menu['key']);
        }
    }

    /**
     * 更新
     *
     * @param $from_version
     * @param $to_version
     * @param $context
     * @return void
     */
    public static function update($from_version, $to_version, $context = null)
    {
        // 删除不用的菜单
        if (isset($context['previous_menus'])) {
            static::removeUnnecessaryMenus($context['previous_menus']);
        }
        // 导入新菜单
        if ($menus = static::getMenus()) {
            Menu::import($menus);
        }
    }

    /**
     * 更新前数据收集等
     *
     * @param $from_version
     * @param $to_version
     * @return array|array[]
     */
    public static function beforeUpdate($from_version, $to_version)
    {
        // 在更新之前获得老菜单，通过context传递给 update
        return ['previous_menus' => static::getMenus()];
    }

    /**
     * 获取菜单
     *
     * @return array|mixed
     */
    public static function getMenus()
    {
        clearstatcache();
        if (is_file($menu_file = __DIR__ . '/../config/menu.php')) {
            $menus = include $menu_file;
            return $menus ?: [];
        }
        return [];
    }

    /**
     * 删除不需要的菜单
     *
     * @param $previous_menus
     * @return void
     */
    public static function removeUnnecessaryMenus($previous_menus)
    {
        $menus_to_remove = array_diff(Menu::column($previous_menus, 'name'), Menu::column(static::getMenus(), 'name'));
        foreach ($menus_to_remove as $name) {
            Menu::delete($name);
        }
    }

}