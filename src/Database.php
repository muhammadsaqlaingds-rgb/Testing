<?php
declare(strict_types=1);

use MongoDB\Driver\{Manager, Query, BulkWrite};

/** Thin wrapper over the native mongodb PHP extension (no Composer needed). */
final class Database
{
    private static ?Manager $manager = null;
    private static array $cfg = [];

    public static function init(array $cfg): void { self::$cfg = $cfg; }
    public static function cfg(string $k) { return self::$cfg[$k]; }

    private static function manager(): Manager
    {
        return self::$manager ??= new Manager(self::$cfg['mongo_uri']);
    }

    private static function ns(string $coll): string
    {
        return self::$cfg['mongo_db'] . '.' . $coll;
    }

    public static function find(string $coll, array $filter = [], array $options = []): array
    {
        $cursor = self::manager()->executeQuery(self::ns($coll), new Query($filter, $options));
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        return $cursor->toArray();
    }

    public static function findOne(string $coll, array $filter): ?array
    {
        return self::find($coll, $filter, ['limit' => 1])[0] ?? null;
    }

    public static function update(string $coll, array $filter, array $update): int
    {
        $bulk = new BulkWrite();
        $bulk->update($filter, $update, ['multi' => false, 'upsert' => false]);
        return self::manager()->executeBulkWrite(self::ns($coll), $bulk)->getModifiedCount() ?? 0;
    }

    public static function insert(string $coll, array $doc): void
    {
        $bulk = new BulkWrite();
        $bulk->insert($doc);
        self::manager()->executeBulkWrite(self::ns($coll), $bulk);
    }

    public static function delete(string $coll, array $filter): int
    {
        $bulk = new BulkWrite();
        $bulk->delete($filter, ['limit' => 1]);
        return self::manager()->executeBulkWrite(self::ns($coll), $bulk)->getDeletedCount() ?? 0;
    }
}
