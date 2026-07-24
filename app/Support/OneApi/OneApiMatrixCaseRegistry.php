<?php

namespace App\Support\OneApi;

/**
 * Vendor ISA 24-case workbook keys (unchanged rows).
 */
final class OneApiMatrixCaseRegistry
{
    /**
     * @return list<array{flow: string, id: string, test_case: string, key: string}>
     */
    public static function cases(): array
    {
        return [
            ['flow' => 'ONEWAY', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'oneway_basic_1'],
            ['flow' => 'ONEWAY', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_basic_2'],
            ['flow' => 'ONEWAY', 'id' => '1', 'test_case' => 'Booking with 2 Adult', 'key' => 'oneway_ancillary_1'],
            ['flow' => 'ONEWAY', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_ancillary_2'],
            ['flow' => 'ONEWAY', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'oneway_bundle_1'],
            ['flow' => 'ONEWAY', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_bundle_2'],
            ['flow' => 'RETURN', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'return_basic_1'],
            ['flow' => 'RETURN', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_basic_2'],
            ['flow' => 'RETURN', 'id' => '1', 'test_case' => 'Booking with 2 Adult', 'key' => 'return_ancillary_1'],
            ['flow' => 'RETURN', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_ancillary_2'],
            ['flow' => 'RETURN', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'return_bundle_1'],
            ['flow' => 'RETURN', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_bundle_2'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'oneway_connection_basic_1'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_connection_basic_2'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 2 Adult', 'key' => 'oneway_connection_ancillary_1'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_connection_ancillary_2'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'oneway_connection_bundle_1'],
            ['flow' => 'ONEWAY- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'oneway_connection_bundle_2'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'return_connection_basic_1'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_connection_basic_2'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 2 Adult', 'key' => 'return_connection_ancillary_1'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_connection_ancillary_2'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '1', 'test_case' => 'Booking with 1 Adult', 'key' => 'return_connection_bundle_1'],
            ['flow' => 'RETURN- CONNECTION', 'id' => '2', 'test_case' => 'Booking with 1 Adult 1 Child 1 Infant', 'key' => 'return_connection_bundle_2'],
        ];
    }
}
