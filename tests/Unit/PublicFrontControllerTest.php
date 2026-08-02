<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicFrontControllerTest extends TestCase
{
    #[Test]
    public function public_index_bootstraps_laravel(): void
    {
        $index = file_get_contents(base_path('public/index.php'));

        $this->assertNotFalse($index);
        $this->assertStringContainsString('handleRequest', $index);
        $this->assertStringContainsString("__DIR__.'/../vendor/autoload.php'", $index);
        $this->assertStringNotContainsString('package public index with wrong paths', $index);
    }
}
