<?php

namespace Tests\Support\OneApi;

/**
 * Immutable mandatory requirement IDs (Phases 5–8). Do not remove or rename without explicit review.
 *
 * @return list<array{id: string, source_phase: string, mandatory: bool}>
 */
final class OneApiAcceptanceRequiredIdRegistry
{
    public static function entries(): array
    {
        $ids = [
            // Workflow ownership (Phase 5)
            ['OWN-001', '5'], ['OWN-002', '5'], ['OWN-003', '5'], ['OWN-004', '5'], ['OWN-005', '5'],
            // Transport (Phase 5)
            ['TRN-001', '5'], ['TRN-002', '5'], ['TRN-003', '5'],
            // Communication (Phase 6–7)
            ['COMM-001', '7'], ['COMM-002', '7'], ['COMM-003', '7'], ['COMM-004', '7'], ['COMM-005', '8'],
            ['COMM-006', '8'], ['COMM-007', '8'], ['COMM-008', '8'],
            // Hold/read/pay (Phase 6–7)
            ['HOLD-001', '7'], ['HOLD-002', '8'], ['READ-001', '8'], ['PAY-001', '7'], ['PAY-002', '8'],
            // Matrix
            ['MAT-001', '6'], ['MAT-002', '6'], ['MAT-003', '8'],
            // Corruption (Phase 6–8) — one ID per scenario
            ['COR-001', '6'], ['COR-002', '6'], ['COR-003', '8'], ['COR-004', '8'], ['COR-005', '8'],
            ['COR-006', '8'], ['COR-007', '8'], ['COR-008', '8'], ['COR-009', '8'], ['COR-010', '8'],
            ['COR-011', '8'], ['COR-012', '8'], ['COR-013', '8'], ['COR-014', '8'], ['COR-015', '8'],
            ['COR-016', '8'], ['COR-017', '8'], ['COR-018', '8'], ['COR-019', '8'], ['COR-020', '8'],
            ['COR-021', '8'], ['COR-022', '8'], ['COR-023', '8'], ['COR-024', '8'], ['COR-025', '8'],
            ['COR-026', '8'], ['COR-027', '8'],
            // Authentication (Phase 6–8)
            ['AUTH-001', '6'], ['AUTH-002', '8'], ['AUTH-003', '8'], ['AUTH-004', '8'], ['AUTH-005', '8'],
            ['AUTH-006', '8'], ['AUTH-007', '8'], ['AUTH-008', '8'], ['AUTH-009', '8'], ['AUTH-010', '8'],
            // Search/signature
            ['SRCH-001', '6'], ['SRCH-002', '8'], ['SIG-001', '6'], ['SIG-002', '8'],
            // Pricing
            ['PRICE-001', '6'], ['PRICE-002', '8'],
            // Admin HTTP
            ['ADM-001', '6'], ['ADM-002', '8'], ['ADM-003', '8'], ['ADM-004', '8'],
            // Vendor-blocked (mandatory but not missing — blocked status)
            ['FLY-001', '6'],
        ];

        return array_map(static fn (array $row): array => [
            'id' => $row[0],
            'source_phase' => $row[1],
            'mandatory' => true,
        ], $ids);
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::entries(), 'id');
    }

    /**
     * Phase 9 gate closure set — must remain mandatory until covered in the requirement map.
     *
     * @return list<string>
     */
    public static function phase9OpenUntilCoveredIds(): array
    {
        return [
            'COMM-005', 'COMM-006', 'COMM-008',
            'HOLD-002', 'READ-001', 'PAY-002',
            'COR-003', 'COR-004', 'COR-008', 'COR-010', 'COR-011', 'COR-014',
            'AUTH-004', 'AUTH-005', 'AUTH-006', 'AUTH-007', 'AUTH-008', 'AUTH-009', 'AUTH-010',
            'ADM-002', 'ADM-003', 'ADM-004',
        ];
    }
}
