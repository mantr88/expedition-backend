<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Pulse (фаза B5): дашборд моніторингу доступний лише в local —
 * у testing/production без явного viewPulse-гейту віддає 403.
 */

it('denies the pulse dashboard outside the local environment', function () {
    actingAs(User::factory()->create());

    get('/pulse')->assertForbidden();
});
