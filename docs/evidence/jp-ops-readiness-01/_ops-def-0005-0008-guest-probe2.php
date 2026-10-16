<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Services\Customer\GuestBookingAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$before = Schema::hasTable('guest_booking_access_tokens')
    ? DB::table('guest_booking_access_tokens')->count()
    : 0;

$qa = Booking::query()
    ->whereNotNull('booking_reference')
    ->where('booking_reference', '!=', '')
    ->whereHas('contact', function ($q): void {
        $q->where(function ($inner): void {
            $inner->where('email', 'like', '%@example.com')
                ->orWhere('email', 'like', '%@example.org')
                ->orWhere('email', 'like', 'qa.%@%')
                ->orWhere('email', 'like', '%+qa@%')
                ->orWhere('email', 'like', 'test%@jetpakistan.pk');
        })->whereNotNull('phone')->where('phone', '!=', '');
    })
    ->with('contact')
    ->orderByDesc('id')
    ->first();

if ($qa === null) {
    try {
        $qa = Booking::query()
            ->whereNotNull('booking_reference')
            ->where('booking_reference', '!=', '')
            ->whereRaw('CAST(metadata AS CHAR) LIKE ?', ['%qa_fixture%'])
            ->with('contact')
            ->orderByDesc('id')
            ->first();
        if ($qa === null
            || $qa->contact === null
            || trim((string) $qa->contact->email) === ''
            || trim((string) $qa->contact->phone) === '') {
            $qa = null;
        }
    } catch (Throwable $e) {
        $qa = null;
    }
}

$svc = app(GuestBookingAccessService::class);

if ($qa === null) {
    echo "PROD_GUEST_DYNAMIC_PROBE=SKIPPED_NO_SAFE_QA_FIXTURE\n";
    echo "PROD_GUEST_BOTH_CORRECT=SKIPPED_NO_SAFE_QA_FIXTURE\n";
    echo "PROD_GUEST_EMAIL_CORRECT_PHONE_WRONG=SKIPPED_NO_SAFE_QA_FIXTURE\n";
    echo "PROD_GUEST_EMAIL_WRONG_PHONE_CORRECT=SKIPPED_NO_SAFE_QA_FIXTURE\n";
    echo "PROD_GUEST_EMAIL_ONLY=SKIPPED_NO_SAFE_QA_FIXTURE\n";
    echo "GUEST_ACCESS_TOKENS_CREATED=0\n";
    exit(0);
}

$ref = (string) $qa->booking_reference;
$email = (string) $qa->contact->email;
$phone = (string) $qa->contact->phone;
echo "QA_BOOKING_FOUND=YES\n";
echo "QA_HAS_REF=YES\n";

$a = $svc->findBookingForLookup($ref, $email, $phone);
echo 'PROD_GUEST_BOTH_CORRECT='.($a !== null ? 'PASS' : 'FAIL')."\n";

$b = $svc->findBookingForLookup($ref, $email, '0000000000');
echo 'PROD_GUEST_EMAIL_CORRECT_PHONE_WRONG='.($b === null ? 'DENY' : 'FAIL_MATCHED')."\n";

$c = $svc->findBookingForLookup($ref, 'wrong-qa-probe@example.invalid', $phone);
echo 'PROD_GUEST_EMAIL_WRONG_PHONE_CORRECT='.($c === null ? 'DENY' : 'FAIL_MATCHED')."\n";

$d = $svc->findBookingForLookup($ref, $email, null);
echo 'PROD_GUEST_EMAIL_ONLY='.($d !== null ? 'PASS' : 'FAIL')."\n";

$after = Schema::hasTable('guest_booking_access_tokens')
    ? DB::table('guest_booking_access_tokens')->count()
    : 0;
echo 'GUEST_ACCESS_TOKENS_CREATED='.($after - $before)."\n";
