<?php

namespace Tests\Unit;

use App\Models\ProviderAccount;
use App\Social\IngestionProviderAdapter;
use App\Social\InstagramIngestionAdapter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstagramIngestionAdapterTest extends TestCase
{
    #[Test]
    public function adapter_implements_provider_agnostic_contract(): void
    {
        $adapter = new InstagramIngestionAdapter();

        $this->assertInstanceOf(IngestionProviderAdapter::class, $adapter);
        $this->assertSame('instagram', $adapter->providerName());
    }

    #[Test]
    public function it_supports_cursor_pagination_in_a_testable_way(): void
    {
        $adapter = new InstagramIngestionAdapter();
        $account = new ProviderAccount([
            'external_account_id' => 'acct_1',
        ]);

        $first = $adapter->fetchMediaPage($account, null, 2);
        $this->assertCount(2, $first['items']);
        $this->assertSame('2', $first['next_cursor']);

        $second = $adapter->fetchMediaPage($account, $first['next_cursor'], 2);
        $this->assertCount(2, $second['items']);
        $this->assertSame('4', $second['next_cursor']);

        $third = $adapter->fetchMediaPage($account, $second['next_cursor'], 2);
        $this->assertCount(1, $third['items']);
        $this->assertNull($third['next_cursor']);
    }
}
