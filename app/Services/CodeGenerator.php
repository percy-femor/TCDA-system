<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    public static function next(string $prefix, string $table, string $column = 'public_id'): string
    {
        $last = DB::table($table)->orderByDesc('id')->value($column);
        $n = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
