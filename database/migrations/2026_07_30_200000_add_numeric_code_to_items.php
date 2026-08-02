<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('numeric_code', 6)->nullable()->unique()->after('asset_tag');
        });

        $used = [];
        \App\Models\Item::query()->orderBy('id')->each(function ($item) use (&$used) {
            do {
                $code = (string) random_int(100000, 999999);
            } while (isset($used[$code]));

            $used[$code] = true;
            DB::table('items')->where('id', $item->id)->update(['numeric_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['numeric_code']);
            $table->dropColumn('numeric_code');
        });
    }
};
