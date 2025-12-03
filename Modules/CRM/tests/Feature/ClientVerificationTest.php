<?php

namespace Modules\CRM\Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\ClientVerification;

class ClientVerificationTest extends TestCase
{
    public function test_request_and_confirm_email_change()
    {
        Mail::fake();
        $client = Client::create([
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'old@example.com'
        ]);

        $resp = $this->postJson('/v1/crm/clients/'.$client->client_id.'/verifications/request', [
            'type' => 'email',
            'value' => 'new@example.com'
        ]);
        $resp->assertStatus(200)->assertJson(['success' => true]);

        $vid = $resp->json('data.verification_id');
        $ver = ClientVerification::find($vid);
        $this->assertNotNull($ver);

        $confirm = $this->postJson('/v1/crm/clients/'.$client->client_id.'/verifications/confirm', [
            'verification_id' => $vid,
            'code' => $ver->code
        ]);
        $confirm->assertStatus(200)->assertJson(['success' => true]);

        $client->refresh();
        $this->assertEquals('new@example.com', $client->email);
    }
}

