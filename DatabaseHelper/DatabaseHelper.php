<?php

namespace DatabaseHelper;

use Core\Plugin;
use Core\TemplateEngine;

class DatabaseHelper extends Plugin
{
    private static ?DB $db = null;

    public function register(): void
    {
        // مقداردهی اولیه اتصال دیتابیس
        self::$db = new DB($this->app()->db ?? null);

        // ثبت سینتکس‌های اختصاصی View
        $this->registerSyntax();

        // ثبت Routeهای کمکی (اختیاری برای تست)
        $this->hook('register_routes', [$this, 'registerRoutes']);

        // اضافه کردن یک متغیر global به Viewها
        $this->hook('before_render', [$this, 'injectHelpers']);
    }

    /**
     * ثبت سینتکس‌های دیتابیسی در Template Engine
     */
    private function registerSyntax(): void
    {
        // @db_table('users') => SELECT * FROM users
        TemplateEngine::extend('/@db_table\(\s*[\'"](.+?)[\'"]\s*\)/', function (array $m) {
            return '<?= json_encode(DB::table("' . $m[1] . '")->get()) ?>';
        });

        // @db_query('SELECT * FROM users WHERE id = ?', [$id])
        TemplateEngine::extend('/@db_query\(\s*[\'"](.+?)[\'"]\s*,\s*(\[.*?\])\s*\)/', function (array $m) {
            return '<?= json_encode(DB::raw("' . addslashes($m[1]) . '", ' . $m[2] . ')) ?>';
        });

        // @db_count('users')
        TemplateEngine::extend('/@db_count\(\s*[\'"](.+?)[\'"]\s*\)/', function (array $m) {
            return '<?= DB::table("' . $m[1] . '")->count() ?>';
        });

        // @db_insert('users', ['name' => $name, 'email' => $email])
        TemplateEngine::extend('/@db_insert\(\s*[\'"](.+?)[\'"]\s*,\s*(\[.*?\])\s*\)/', function (array $m) {
            return '<?= DB::table("' . $m[1] . '")->insert(' . $m[2] . ') ?>';
        });
    }

    /**
     * تزریق کلاس DB به Viewها
     */
    public function injectHelpers($request, $app): void
    {
        if (method_exists($app, 'share')) {
            $app->share('DB', self::$db);
        }
    }

    /**
     * Routeهای تست/کمکی
     */
    public function registerRoutes($router): void
    {
        // ساخت جدول از طریق URL (فقط برای توسعه)
        // مثال: /db-helper/create-table?name=products&columns=...
        $router->get('/db-helper/ping', function () {
            return 'DatabaseHelper is active ✅';
        });
    }

    /**
     * دسترسی سراسری به DB
     */
    public static function db(): DB
    {
        return self::$db;
    }

    public function boot(): void
    {
        // می‌توان اینجا Migrationهای پلاگین را اجرا کرد
    }
}