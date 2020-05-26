<?php

$webhook = config('cc_paystack.test.webhook');;

// return live credentials
if(config('cc_paystack.live_mode')) {
	$webhook = config('cc_paystack.live.webhook');
}

Route::post($webhook, 'Webhook@handleWebhook');
