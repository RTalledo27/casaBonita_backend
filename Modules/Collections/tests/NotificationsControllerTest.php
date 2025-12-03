<?php

namespace Modules\Collections\Tests;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\CRM\Models\Client;
use Modules\Collections\Models\PaymentSchedule;
use Modules\Collections\Models\CollectionMessageLog;

class NotificationsControllerTest extends TestCase
{
    public function test_send_custom_email_for_schedule_creates_log()
    {
        Mail::fake();

        $client = Client::create(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com']);
        $reservation = Reservation::create(['client_id' => $client->client_id]);
        $contract = Contract::create(['reservation_id' => $reservation->reservation_id, 'contract_number' => 'C-1']);
        $schedule = PaymentSchedule::create([
            'contract_id' => $contract->contract_id,
            'installment_number' => 1,
            'due_date' => now()->addDays(5),
            'amount' => 100,
            'status' => 'pendiente'
        ]);

        $response = $this->postJson('/v1/collections/notifications/schedules/'.$schedule->schedule_id.'/send-custom', [
            'subject' => 'Prueba',
            'html' => '<p>Hola</p>'
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('collection_message_logs', [
            'schedule_id' => $schedule->schedule_id,
            'recipient_email' => 'test@example.com',
            'status' => 'sent'
        ]);
    }
}

