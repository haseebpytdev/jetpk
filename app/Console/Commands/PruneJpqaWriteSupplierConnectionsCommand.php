<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Support\Suppliers\JpqaWriteSupplierConnectionClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneJpqaWriteSupplierConnectionsCommand extends Command
{
    protected $signature = 'supplier:prune-jpqa-write-connections
        {--execute : Permanently delete DELETE_QA_CERT rows (default is dry-run)}
        {--connection-id= : Limit to one connection id (still respects protection rules)}';

    protected $description = 'Remove stale JPQA-WRITE-*-api dashboard certification SupplierConnection rows (DB-only, no supplier calls)';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $onlyId = $this->option('connection-id');
        $onlyId = $onlyId !== null && $onlyId !== '' ? (int) $onlyId : null;

        $query = SupplierConnection::query()->orderBy('id');
        if ($onlyId !== null) {
            $query->where('id', $onlyId);
        }

        $connections = $query->get();
        $candidates = [];
        $review = [];
        $protected = [];

        foreach ($connections as $connection) {
            $class = JpqaWriteSupplierConnectionClassifier::classification($connection);
            $row = [
                'id' => $connection->id,
                'name' => $connection->name,
                'provider' => $connection->provider?->value ?? (string) $connection->provider,
                'environment' => $connection->environment?->value ?? (string) $connection->environment,
                'is_active' => $connection->is_active,
                'created_at' => $connection->created_at?->toIso8601String(),
                'updated_at' => $connection->updated_at?->toIso8601String(),
                'classification' => $class,
            ];

            if ($class === 'DELETE_QA_CERT') {
                $candidates[] = $row;
            } elseif ($class === 'REVIEW') {
                $review[] = $row;
            } else {
                $protected[] = $row;
            }
        }

        $this->line('TOTAL_CONNECTIONS='.$connections->count());
        $this->line('QA_DELETE_CANDIDATES='.count($candidates));
        $this->line('REVIEW_COUNT='.count($review));
        $this->line('PROTECTED_CONNECTIONS_FOUND='.count($protected));
        $this->line('MODE='.($execute ? 'execute' : 'dry-run'));

        foreach ($candidates as $row) {
            $this->line(sprintf(
                'DELETE_CANDIDATE id=%d name=%s provider=%s env=%s active=%s',
                $row['id'],
                $row['name'],
                $row['provider'],
                $row['environment'],
                $row['is_active'] ? '1' : '0',
            ));
        }

        if (! $execute) {
            $this->info('Dry-run complete. Pass --execute to delete DELETE_QA_CERT rows only.');

            return self::SUCCESS;
        }

        if ($candidates === []) {
            $this->info('No DELETE_QA_CERT rows to remove.');

            return self::SUCCESS;
        }

        $deleted = [];
        DB::transaction(function () use ($candidates, &$deleted): void {
            foreach ($candidates as $row) {
                $connection = SupplierConnection::query()->lockForUpdate()->find($row['id']);
                if ($connection === null) {
                    continue;
                }
                if (! JpqaWriteSupplierConnectionClassifier::isDeleteCandidate($connection)) {
                    continue;
                }
                $deleted[] = ['id' => $connection->id, 'name' => $connection->name];
                $connection->delete();
            }
        });

        $this->line('QA_RECORDS_DELETED='.count($deleted));
        foreach ($deleted as $row) {
            $this->line('DELETED id='.$row['id'].' name='.$row['name']);
        }

        return self::SUCCESS;
    }
}
