<?php

namespace Tests\Feature;

use Tests\TestCase;

class CourierConfigTest extends TestCase
{
    public function test_courier_configuration_contains_all_quantitative_sections(): void
    {
        $config = config('courier');

        foreach (['simulation', 'scoring', 'optimization', 'routing', 'demand', 'explanation', 'benchmark'] as $section) {
            $this->assertArrayHasKey($section, $config);
        }

        $weights = $config['scoring']['weights'];
        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.000000001);
        $this->assertSame(2, $config['simulation']['max_concurrent_orders']);
        $this->assertSame(2, $config['optimization']['batch_size']);
        $this->assertSame('none', $config['explanation']['provider']);
        $this->assertNotEmpty($config['routing']['base_url']);
        $this->assertNotEmpty($config['benchmark']['default_seeds']);
    }
}
