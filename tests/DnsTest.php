<?php

namespace Spatie\Dns\Test;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Spatie\Dns\Dns;
use Spatie\Dns\Exceptions\CouldNotFetchDns;
use Spatie\Dns\Exceptions\InvalidArgument;
use Spatie\Dns\Records\A;
use Spatie\Dns\Records\MX;
use Spatie\Dns\Records\NS;
use Spatie\Dns\Records\PTR;
use Spatie\Dns\Records\Record;
use Spatie\Dns\Records\SOA;
use Spatie\Dns\Support\Collection;
use Spatie\Dns\Test\TestClasses\CustomHandler;

class DnsTest extends TestCase
{
    protected Dns $dns;

    protected function setUp(): void
    {
        parent::setUp();

        ray()->newScreen($this->getName());

        $this->dns = new Dns();
    }

    /** @test */
    public function it_throws_an_exception_if_an_empty_string_is_passed()
    {
        $this->expectException(InvalidArgument::class);

        $this->dns->getRecords('');
    }

    /** @test */
    public function it_fetches_all_records_by_default()
    {
        $records = $this->dns->getRecords('spatie.be');

        $this->assertSeeRecordTypes(
            $records,
            [A::class, NS::class, SOA::class, MX::class]
        );
    }

    /** @test */
    public function it_has_a_static_constructor()
    {
        $records = DNS::query()->getRecords('spatie.be');

        $this->assertSeeRecordTypes(
            $records,
            [A::class, NS::class, SOA::class, MX::class]
        );
    }

    /** @test */
    public function it_fetches_all_records_with_asterisk()
    {
        $records = $this->dns->getRecords('spatie.be', '*');

        $this->assertSeeRecordTypes(
            $records,
            [A::class, NS::class, SOA::class, MX::class]
        );
    }

    /** @test */
    public function it_fetches_records_for_a_single_type_via_flag()
    {
        $records = $this->dns->getRecords('spatie.be', DNS_NS);

        $this->assertOnlySeeRecordTypes($records, [NS::class]);
    }

    /** @test */
    public function it_fetches_records_for_a_single_type_via_name()
    {
        $records = $this->dns->getRecords('spatie.be', 'NS');

        $this->assertOnlySeeRecordTypes($records, [NS::class]);
    }

    /** @test */
    public function it_fetches_records_for_multiple_types_via_flags()
    {
        $records = $this->dns->getRecords('spatie.be', DNS_NS | DNS_SOA);

        $this->assertOnlySeeRecordTypes($records, [NS::class, SOA::class]);
    }

    /** @test */
    public function it_fetches_records_for_multiple_types_via_names()
    {
        $records = $this->dns->getRecords('spatie.be', ['NS', 'SOA']);

        $this->assertOnlySeeRecordTypes($records, [NS::class, SOA::class]);
    }

    /** @test */
    public function it_fetches_records_via_name_and_ignores_casing()
    {
        $records = $this->dns->getRecords('spatie.be', 'ns');

        $this->assertOnlySeeRecordTypes($records, [NS::class]);
    }

    /** @test */
    public function it_fetches_records_for_given_type_and_ignores_record_chain()
    {
        $records = $this->dns->getRecords('www.opendor.me', DNS_A);

        $this->assertOnlySeeRecordTypes($records, [A::class]);
    }

    /** @test */
    public function it_can_fetch_ptr_record()
    {
        $records = $this->dns->getRecords('1.73.1.5.in-addr.arpa', DNS_PTR);
        $record = array_pop($records);

        $ptrRecord = PTR::make([
            'host' => '1.73.1.5.in-addr.arpa.',
            'class' => 'IN',
            'ttl' => 3600,
            'type' => 'PTR',
            'target' => 'ae0.452.fra1.de.creoline.net.',
        ]);

        $this->assertSame(
            [$record->host(), $record->class(), $record->type(), $record->target()],
            [$ptrRecord->host(), $ptrRecord->class(), $ptrRecord->type(), $ptrRecord->target()]
        );
    }

    /** @test */
    public function it_throws_an_exception_if_an_invalid_record_type_is_passed()
    {
        $this->expectException(InvalidArgument::class);

        $this->dns->getRecords('spatie.be', 'xyz');
    }

    /** @test */
    public function it_uses_provided_nameserver_if_set()
    {
        $this->dns->useNameserver('ns1.openminds.be');

        $this->assertEquals('ns1.openminds.be', $this->dns->getNameserver());
    }

    /** @test */
    public function it_uses_default_nameserver_if_not_set()
    {
        $this->assertNull($this->dns->getNameserver());
    }

    /** @test */
    public function it_uses_provided_timeout_if_set()
    {
        $this->dns->setTimeout(5);

        $this->assertEquals(5, $this->dns->getTimeout());
    }

    /** @test */
    public function it_uses_default_timout_if_not_set()
    {
        $this->assertEquals(2, $this->dns->getTimeout());
    }

    /** @test */
    public function it_uses_provided_retries_if_set()
    {
        $this->dns->setRetries(5);

        $this->assertEquals(5, $this->dns->getRetries());
    }

    /** @test */
    public function it_uses_default_retries_if_not_set()
    {
        $this->assertEquals(2, $this->dns->getRetries());
    }

    /** @test */
    public function it_throws_exception_on_failed_to_fetch_dns_record()
    {
        $this->expectException(CouldNotFetchDns::class);

        $this->dns
            ->useNameserver('dns.spatie.be')
            ->getRecords('spatie.be', DNS_A);
    }

    /** @test */
    public function it_can_use_custom_handlers()
    {
        $result = $this->dns
            ->useHandlers([new CustomHandler()])
            ->getRecords('spatie.be');

        $handlers = [
            'custom-handler-results-A',
            'custom-handler-results-AAAA',
            'custom-handler-results-CNAME',
            'custom-handler-results-NS',
            'custom-handler-results-PTR',
            'custom-handler-results-SOA',
            'custom-handler-results-MX',
            'custom-handler-results-SRV',
            'custom-handler-results-TXT',
        ];

        if (defined('DNS_CAA')) {
            $handlers[] = 'custom-handler-results-CAA';
        }

        $this->assertEquals($handlers, $result);
    }

    protected function assertSeeRecordTypes(array $records, array $types)
    {
        foreach ($types as $type) {
            $foundRecords = array_filter(
                $records,
                fn (Record $record): bool => is_a($record, $type)
            );

            $this->assertNotEmpty($foundRecords);
        }
    }

    protected function assertDontSeeRecordTypes(Collection $records, array $types)
    {
        foreach ($types as $type) {
            $foundRecords = array_filter(
                $records->all(),
                fn (Record $record): bool => is_a($record, $type)
            );

            $this->assertEmpty($foundRecords);
        }
    }

    protected function assertOnlySeeRecordTypes(array $records, array $types)
    {
        $expectedCount = count($records);

        $foundRecords = Collection::make($records)
            ->filter(fn (Record $record) => $this->recordIsOfType($record, $types));

        $this->assertCount($expectedCount, $foundRecords);
    }

    protected function recordIsOfType(Record $record, array $types): bool
    {
        foreach ($types as $type) {
            if (is_a($record, $type) && $record->type() === (new ReflectionClass($type))->getShortName()) {
                return true;
            }
        }

        return false;
    }
}
