<?php

it('passes when critical foreign keys are present', function (): void {
    $this->artisan('schema:assert-critical-foreign-keys')
        ->expectsOutputToContain('Critical foreign key gate: OK')
        ->assertSuccessful();
});
