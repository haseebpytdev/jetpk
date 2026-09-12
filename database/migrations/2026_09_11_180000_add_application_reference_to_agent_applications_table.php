<?php

use App\Support\References\CompactReferenceGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_applications', function (Blueprint $table): void {
            $table->string('application_reference', 32)->nullable()->unique()->after('id');
        });

        $generator = app(CompactReferenceGenerator::class);

        DB::table('agent_applications')
            ->orderBy('id')
            ->select(['id', 'company_name'])
            ->lazy()
            ->each(function (object $row) use ($generator): void {
                $prefix = self::referencePrefix((string) ($row->company_name ?? ''));
                $reference = $generator->generateUnique('agent_applications', 'application_reference', 12, $prefix);

                DB::table('agent_applications')
                    ->where('id', $row->id)
                    ->update(['application_reference' => $reference]);
            });
    }

    public function down(): void
    {
        Schema::table('agent_applications', function (Blueprint $table): void {
            $table->dropUnique(['application_reference']);
            $table->dropColumn('application_reference');
        });
    }

    private static function referencePrefix(string $companyName): string
    {
        return CompactReferenceGenerator::sanitizePrefix($companyName);
    }
};
