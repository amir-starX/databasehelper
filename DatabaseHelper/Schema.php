<?php

namespace DatabaseHelper;

class Schema
{
    private string $table;
    private string $mode;
    private array $columns = [];
    private array $indexes = [];

    public function __construct(string $table, string $mode = 'create')
    {
        $this->table = $table;
        $this->mode  = $mode;
    }

    public function id(string $name = 'id'): self
    {
        $this->columns[] = "`$name` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function string(string $name, int $length = 255, bool $nullable = false): self
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` VARCHAR($length) $null";
        return $this;
    }

    public function text(string $name, bool $nullable = true): self
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` TEXT $null";
        return $this;
    }

    public function integer(string $name, bool $nullable = false): self
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` INT $null";
        return $this;
    }

    public function boolean(string $name, bool $default = false): self
    {
        $d = $default ? 1 : 0;
        $this->columns[] = "`$name` TINYINT(1) NOT NULL DEFAULT $d";
        return $this;
    }

    public function decimal(string $name, int $p = 10, int $s = 2): self
    {
        $this->columns[] = "`$name` DECIMAL($p,$s) NOT NULL";
        return $this;
    }

    public function timestamp(string $name, bool $nullable = true): self
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` TIMESTAMP $null";
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    public function unique(): self
    {
        // روی آخرین ستون
        $last = end($this->columns);
        if (preg_match('/`(.+?)`/', $last, $m)) {
            $this->indexes[] = "UNIQUE KEY `uniq_{$m[1]}` (`{$m[1]}`)";
        }
        return $this;
    }

    public function index(): self
    {
        $last = end($this->columns);
        if (preg_match('/`(.+?)`/', $last, $m)) {
            $this->indexes[] = "KEY `idx_{$m[1]}` (`{$m[1]}`)";
        }
        return $this;
    }

    public function toSql(): string
    {
        if ($this->mode === 'create') {
            $parts = array_merge($this->columns, $this->indexes);
            return "CREATE TABLE IF NOT EXISTS `{$this->table}` (\n  "
                . implode(",\n  ", $parts)
                . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        }

        // ALTER
        $actions = [];
        foreach ($this->columns as $col) {
            $actions[] = "ADD COLUMN $col";
        }
        foreach ($this->indexes as $idx) {
            $actions[] = "ADD $idx";
        }
        return "ALTER TABLE `{$this->table}` " . implode(', ', $actions) . ';';
    }
}