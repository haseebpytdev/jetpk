<?php

namespace Tests\Unit\Services\Suppliers\PiaNdc;

use App\Services\Suppliers\PiaNdc\PiaNdcResponseNormalizer;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use Tests\TestCase;

class PiaNdcHititCancelSampleExactTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config = [
        'agency_id' => '187570',
        'agency_name' => 'Jet Pakistan Pvt Ltd OTA',
        'agency_contact_email' => 'ADMIN@JETPAKISTAN.COM',
        'currency' => 'PKR',
        'language_code' => 'EN',
        'owner_code' => 'PK',
    ];

    public function test_preview_exact_sample_shape_structure(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildCancelDiagnosticRequest($this->config, '9FCPZ3', 'PK', 'hitit_cancel_preview_sample_exact');

        $this->assertStringContainsString('IATA_OrderReshopRQ', $xml);
        $this->assertStringContainsString('<AgencyID>187570</AgencyID>', $xml);
        $this->assertStringContainsString('<Party>', $xml);
        $this->assertStringContainsString('<RequestedCurCode>PKR</RequestedCurCode>', $xml);
        $this->assertStringContainsString('<DeviceOwnerTypeCode>SL</DeviceOwnerTypeCode>', $xml);
        $this->assertStringContainsString('<LangCode>EN</LangCode>', $xml);
        $this->assertStringContainsString('<UpdateOrder>', $xml);
        $this->assertStringContainsString('<CancelOrder>', $xml);
        $this->assertStringContainsString('<OrderRefID>9FCPZ3</OrderRefID>', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('Password', $xml);
    }

    public function test_commit_exact_sample_shape_structure(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildCancelDiagnosticRequest($this->config, '9FCPZ3', 'PK', 'hitit_cancel_commit_sample_exact');

        $this->assertStringContainsString('IATA_OrderChangeRQ', $xml);
        $this->assertStringContainsString('<AgencyID>187570</AgencyID>', $xml);
        $this->assertStringContainsString('<Party>', $xml);
        $this->assertStringContainsString('<PrimaryLangID>EN</PrimaryLangID>', $xml);
        $this->assertStringContainsString('<PayloadAttributes>', $xml);
        $this->assertStringContainsString('<ChangeOrder>', $xml);
        $this->assertStringContainsString('<CancelOrder>', $xml);
        $this->assertStringContainsString('<OrderRefID>9FCPZ3</OrderRefID>', $xml);
        $this->assertStringContainsString('<OrderID>9FCPZ3</OrderID>', $xml);
        $this->assertStringContainsString('<OwnerCode>PK</OwnerCode>', $xml);
        $this->assertStringContainsString('<CurCode>PKR</CurCode>', $xml);
        $this->assertStringContainsString('<OrderChangeParameters>', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('AccountableDoc', $xml);
        $this->assertStringNotContainsString('MCO', $xml);
    }

    public function test_new_sample_exact_shapes_execute_policy(): void
    {
        foreach ([
            'hitit_cancel_preview_sample_exact',
            'hitit_cancel_preview_sample_exact_with_contact_info',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
        ] as $shape) {
            $this->assertContains($shape, PiaNdcXmlBuilder::CANCEL_EXECUTE_ALLOWED_SHAPES);
            $this->assertNotContains($shape, PiaNdcXmlBuilder::CANCEL_EXECUTE_BLOCKED_SHAPES);
        }
        $this->assertContains('hitit_cancel_commit_sample_exact', PiaNdcXmlBuilder::CANCEL_EXECUTE_BLOCKED_SHAPES);
    }

    public function test_contact_info_preview_shape_includes_uppercase_email(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildCancelDiagnosticRequest(
            $this->config,
            '9FCPZ3',
            'PK',
            'hitit_cancel_preview_sample_exact_with_contact_info',
        );

        $this->assertStringContainsString('<ContactInfo>', $xml);
        $this->assertStringContainsString('<EmailAddress>', $xml);
        $this->assertStringContainsString('<EmailAddressText>ADMIN@JETPAKISTAN.COM</EmailAddressText>', $xml);
        $this->assertStringContainsString('<Name>Jet Pakistan Pvt Ltd OTA</Name>', $xml);
        $this->assertStringContainsString('<AgencyID>187570</AgencyID>', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('MCO', $xml);
        $this->assertStringNotContainsString('AccountableDoc', $xml);
    }

    public function test_contact_info_preview_uses_configured_agency_email_uppercase(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $config = array_merge($this->config, ['agency_contact_email' => 'OPS@EXAMPLE.COM']);
        $xml = $builder->buildCancelDiagnosticRequest(
            $config,
            '9FCPZ3',
            'PK',
            'hitit_cancel_preview_sample_exact_with_contact_info',
        );

        $this->assertStringContainsString('<EmailAddressText>OPS@EXAMPLE.COM</EmailAddressText>', $xml);
    }

    public function test_owner_attr_preview_shape_includes_owner_code_on_order_ref_id(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildCancelDiagnosticRequest(
            $this->config,
            '9FCPZ3',
            'PK',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
        );

        $this->assertStringContainsString('<ContactInfo>', $xml);
        $this->assertSame(2, substr_count($xml, 'OwnerCode="PK"'));
        $this->assertStringContainsString('<OrderRefID OwnerCode="PK">9FCPZ3</OrderRefID>', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('ChangeOrder', $xml);
    }

    public function test_preview_normalizer_never_sets_cancellation_status_cancelled(): void
    {
        $normalizer = app(PiaNdcResponseNormalizer::class);
        $faultXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml')) ?: '';
        $parser = app(PiaNdcXmlParser::class);
        $parsed = $parser->parse($faultXml);
        $normalized = $normalizer->normalizeCancelPreviewDiagnosticResponse(
            parsedResponse: $parsed,
            httpStatus: 500,
            providerErrorCode: 'soap_fault',
            providerErrorMessage: 'java.lang.NullPointerException',
            soapFault: ['code' => 'soap:Server', 'message' => 'java.lang.NullPointerException'],
        );

        $this->assertSame('failed', $normalized['cancel_preview_status'] ?? null);
        $this->assertArrayNotHasKey('cancellation_status', $normalized);
    }

    public function test_order_cancel_rq_shapes_are_legacy(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $this->assertTrue($builder->isLegacyCancelShape('order_cancel_rq_order_id'));
        $this->assertTrue($builder->isLegacyCancelShape('order_cancel_rq_order_ref'));
        $this->assertFalse($builder->isLegacyCancelShape('hitit_cancel_preview_sample_exact'));
    }

    public function test_official_cancel_sample_files_constant_lists_four_samples(): void
    {
        $this->assertCount(4, PiaNdcXmlBuilder::HITIT_OFFICIAL_CANCEL_SAMPLE_FILES);
        $this->assertContains('doOrderCancelPreview_OW_req.xml', PiaNdcXmlBuilder::HITIT_OFFICIAL_CANCEL_SAMPLE_FILES);
        $this->assertContains('doOrderCancelCommit_OW_res.xml', PiaNdcXmlBuilder::HITIT_OFFICIAL_CANCEL_SAMPLE_FILES);
    }
}
