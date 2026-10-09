<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\JpqaWriteSupplierConnectionClassifier;
use Tests\TestCase;

class JpqaWriteSupplierConnectionClassifierTest extends TestCase
{
    public function test_matches_jpqa_write_cert_name_pattern(): void
    {
        $this->assertTrue(JpqaWriteSupplierConnectionClassifier::isJpqaWriteCertName('JPQA-WRITE-1791479357017-api'));
        $this->assertFalse(JpqaWriteSupplierConnectionClassifier::isJpqaWriteCertName('AirBlue Zapways TEST v2'));
        $this->assertFalse(JpqaWriteSupplierConnectionClassifier::isJpqaWriteCertName('JPQA-WRITE-api'));
    }

    public function test_protected_connection_id_22_is_never_delete_candidate(): void
    {
        $connection = new SupplierConnection([
            'name' => 'JPQA-WRITE-9999999999999-api',
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        $connection->id = 22;

        $this->assertFalse(JpqaWriteSupplierConnectionClassifier::isDeleteCandidate($connection));
        $this->assertSame('KEEP_REAL', JpqaWriteSupplierConnectionClassifier::classification($connection));
    }

    public function test_jpqa_write_row_is_delete_candidate_when_not_protected(): void
    {
        $connection = new SupplierConnection([
            'id' => 11,
            'name' => 'JPQA-WRITE-1791479357017-api',
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $this->assertTrue(JpqaWriteSupplierConnectionClassifier::isDeleteCandidate($connection));
        $this->assertSame('DELETE_QA_CERT', JpqaWriteSupplierConnectionClassifier::classification($connection));
    }
}
