<?php

namespace Tests\Feature\Pushsale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationConnectionMetadataSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_and_shipping_metadata_columns_exist_after_migrations(): void
    {
        $this->assertTrue(Schema::hasColumn('integration_connections', 'metadata'));
        $this->assertTrue(Schema::hasColumn('shipping_partner_connections', 'metadata'));
    }
}
