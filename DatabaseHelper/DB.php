<?php

namespace DatabaseHelper;

use PDO;
use PDOException;
use Exception;

class DB
{
    private static ?PDO $pdo = null;
    private static string $table = '';
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private string $orderBy = '';
    private array $selects = ['*'];

    public function __construct($existingConnection = null)
    {
        if ($existingConnection instanceof PDO) {
            self::$pdo = $existingConnection;
            return;
        }

        // اتصال پیش‌فرض از config
        if (self::$pdo === null) {
            $config = $this->loadConfig();
            try {
                self::$pdo = new PDO(
                    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                throw new Exception('DB Connection failed: ' . $e->getMessage());
            }
        }
    }

    private function loadConfig(): array
    {
        $paths = [
            __DIR__ . '/../../config/database.php',
            __DIR__ . '/../../config/config.php',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $cfg = require $p;
                if (isset($cfg['database'])) return $cfg['database'];
                if (isset($cfg['db'])) return $cfg['db'];
            }
        }
        // پیش‌فرض
        return [
            'host' => '127.0.0.1',
            'database' => 'app',
            'username' => 'root',
            'password' => '',
        ];
    }

    /* ============================================================
     |  Schema Builder  —  ساخت و تغییر جدول
     * ============================================================ */

    /**
     * CREATE TABLE
     * DB::create('users', function (Schema $t) {
     *     $t->id();
     *     $t->string('name');
     *     $t->string('email')->unique();
     *     $t->timestamps();
     * });
     */
    public static function create(string $table, callable $callback): bool
    {
        $schema = new Schema($table, 'create');
        $callback($schema);
        $sql = $schema->toSql();
        return self::raw($sql) !== false;
    }

    /**
     * ALTER TABLE
     */
    public static function alter(string $table, callable $callback): bool
    {
        $schema = new Schema($table, 'alter');
        $callback($schema);
        $sql = $schema->toSql();
        return self::raw($sql) !== false;
    }

    /**
     * DROP TABLE
     */
    public static function drop(string $table): bool
    {
        return self::raw("DROP TABLE IF EXISTS `$table`") !== false;
    }

    /**
     * DROP TABLE IF EXISTS
     */
    public static function dropIfExists(string $table): bool
    {
        return self::drop($table);
    }

    /**
     * بررسی وجود جدول
     */
    public static function hasTable(string $table): bool
    {
        try {
            $stmt = self::pdo()->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /* ============================================================
     |  Query Builder
     * ============================================================ */

    public static function table(string $table): self
    {
        $instance = new self();
        self::$table = $table;
        $instance->wheres = [];
        $instance->bindings = [];
        return $instance;
    }

    public function select(...$columns): self
    {
        $this->selects = $columns ?: ['*'];
        return $this;
    }

    public function where(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = "`$column` $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "`$column` IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): self
    {
        $this->orderBy = "ORDER BY `$column` " . strtoupper($dir);
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    private function buildSelect(): string
    {
        $cols = implode(', ', array_map(fn($c) => $c === '*' ? '*' : "`$c`", $this->selects));
        $sql  = "SELECT $cols FROM `" . self::$table . "`";
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->orderBy) $sql .= ' ' . $this->orderBy;
        if ($this->limit !== null) $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;
        return $sql;
    }

    public function get(): array
    {
        $stmt = self::pdo()->prepare($this->buildSelect());
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as c FROM `" . self::$table . "`";
        if ($this->wheres) $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return (int) $stmt->fetch()['c'];
    }

    public function insert(array $data): int
    {
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $sql  = "INSERT INTO `" . self::$table . "` ($cols) VALUES ($ph)";
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public function update(array $data): int
    {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `" . self::$table . "` SET $set";
        $bindings = array_values($data);
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
            $bindings = array_merge($bindings, $this->bindings);
        }
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM `" . self::$table . "`";
        if ($this->wheres) $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    /* ============================================================
     |  Raw Query
     * ============================================================ */

    public static function raw(string $sql, array $bindings = [])
    {
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($bindings);
            // اگر SELECT بود، نتیجه برگردان
            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE)/i', $sql)) {
                return $stmt->fetchAll();
            }
            return true;
        } catch (PDOException $e) {
            error_log('[DatabaseHelper] ' . $e->getMessage());
            return false;
        }
    }

    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            new self();
        }
        return self::$pdo;
    }
}