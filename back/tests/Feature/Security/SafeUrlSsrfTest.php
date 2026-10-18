<?php

namespace Tests\Feature\Security;

use App\Rules\SafeUrl;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class SafeUrlSsrfTest extends TestCase
{
    use FakesDns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'internal.example.com' => ['10.0.0.5'],
        ]);
    }

    private function validate(mixed $url): ValidatorInstance
    {
        return Validator::make(['url' => $url], ['url' => [new SafeUrl]]);
    }

    public function test_public_url_passes(): void
    {
        $this->assertTrue($this->validate('https://example.com/status')->passes());
    }

    public function test_url_resolving_to_a_private_address_fails_with_a_clear_message(): void
    {
        $validator = $this->validate('https://internal.example.com');

        $this->assertTrue($validator->fails());
        $this->assertSame('The url points to an address that is not allowed.', $validator->errors()->first('url'));
    }

    public function test_unsupported_scheme_fails_with_a_clear_message(): void
    {
        $this->assertSame('The url must use http or https.', $this->validate('ftp://example.com')->errors()->first('url'));
    }

    public function test_unresolvable_host_fails(): void
    {
        $this->assertSame('The url host could not be resolved.', $this->validate('https://nope.example.com')->errors()->first('url'));
    }

    public function test_non_string_values_fail(): void
    {
        $this->assertTrue($this->validate(['https://example.com'])->fails());
    }
}
