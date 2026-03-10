<?php

use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use Illuminate\Support\Facades\DB;

it('round-trips encrypted arrays safely on json-backed integration tables', function (): void {
    $ingestion = WebLeadIngestion::factory()->create([
        'payload' => [
            'source' => 'website',
            'full_name' => 'Lead JSON Cast',
        ],
        'response' => [
            'created' => true,
        ],
    ]);

    $delivery = WebLeadEmailDelivery::factory()->create([
        'web_lead_ingestion_id' => $ingestion->id,
        'payload' => [
            'subject' => '[CRM Lead] JSON safe',
            'request_id' => $ingestion->request_id,
        ],
        'mailer_snapshot' => [
            'host' => 'smtp.example.test',
            'queue' => 'web-lead-mail',
        ],
    ]);

    $rawIngestionPayload = DB::table('web_lead_ingestions')
        ->where('id', $ingestion->id)
        ->value('payload');
    $rawDeliveryPayload = DB::table('web_lead_email_deliveries')
        ->where('id', $delivery->id)
        ->value('payload');

    expect($rawIngestionPayload)->toBeString()->not->toBe('')
        ->and($rawDeliveryPayload)->toBeString()->not->toBe('')
        ->and($ingestion->fresh()->payload)->toMatchArray([
            'source' => 'website',
            'full_name' => 'Lead JSON Cast',
        ])
        ->and($delivery->fresh()->payload)->toMatchArray([
            'subject' => '[CRM Lead] JSON safe',
            'request_id' => $ingestion->request_id,
        ])
        ->and($delivery->fresh()->mailer_snapshot)->toMatchArray([
            'host' => 'smtp.example.test',
            'queue' => 'web-lead-mail',
        ]);
});
