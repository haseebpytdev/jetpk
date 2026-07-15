<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Default CMS policy and information pages for public footer and /pages/{slug}.
 *
 * Run: php artisan db:seed --class=DefaultCmsPagesSeeder
 *
 * Safe to re-run: uses updateOrCreate by slug (no duplicates).
 * Not registered in DatabaseSeeder — run manually on dev/staging/production when needed.
 */
class DefaultCmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->pages() as $page) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'status' => CmsPage::STATUS_ACTIVE,
                    'show_in_footer' => true,
                    'open_in_new_tab' => false,
                    'canonical_url' => null,
                    'featured_image_path' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'published_at' => $now,
                ]),
            );
        }

        $this->command?->info('DefaultCmsPagesSeeder: seeded '.count($this->pages()).' CMS pages.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        return [
            $this->refundPolicy(),
            $this->cancellationPolicy(),
            $this->privacyPolicy(),
            $this->termsAndConditions(),
            $this->paymentPolicy(),
            $this->baggagePolicy(),
            $this->travelAdvisory(),
            $this->agentTerms(),
            $this->walletCreditPolicy(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refundPolicy(): array
    {
        return [
            'title' => 'Refund Policy',
            'slug' => 'refund-policy',
            'footer_group' => 'policies',
            'footer_label' => 'Refund Policy',
            'footer_sort_order' => 10,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Refund Policy',
            'seo_description' => 'How airline refunds, penalties, OTA service charges, and processing times work. Refunds depend on airline and fare rules.',
            'excerpt' => 'Learn how refunds are handled after cancellation or schedule changes, including airline penalties, service charges, and processing timelines.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> This page provides general information. Refund eligibility, amounts, and timing depend on your specific booking, fare rules, and the airline or supplier. Always refer to your ticket conditions and booking confirmation.</p>

<h2>Overview</h2>
<p>Refunds are subject to the fare rules of the airline or travel supplier, applicable laws, and any service charges applied by the platform. A refund is not guaranteed until the airline or supplier approves it.</p>

<h2>Airline and supplier rules</h2>
<p>Most tickets are governed by the operating carrier&rsquo;s conditions of carriage. These rules may classify fares as non-refundable, partially refundable, or refundable with penalties. The platform displays estimates where available, but the airline or GDS record is final.</p>

<h2>Penalties and deductions</h2>
<p>When a refund is permitted, the amount returned may be reduced by:</p>
<ul>
<li>Airline or supplier cancellation or refund penalties</li>
<li>Taxes or surcharges that are non-refundable under fare rules</li>
<li>Platform service or processing fees, where disclosed at booking</li>
<li>Payment gateway or bank charges, if applicable</li>
</ul>

<h2>Processing time</h2>
<p>After approval, refunds are typically processed within 7&ndash;21 business days, depending on the airline, payment method, and your bank or card issuer. Some cases may take longer during peak periods or when manual airline review is required.</p>

<h2>Refund method</h2>
<p>Refunds are usually returned to the original form of payment. For agent wallet or credit bookings, refunds may be credited to the agency wallet per platform policy.</p>

<h2>Non-refundable items</h2>
<p>The following are often non-refundable unless required by law or explicit fare terms:</p>
<ul>
<li>Certain promotional or special-fare tickets</li>
<li>No-show segments after departure</li>
<li>Optional add-ons already consumed or ticketed as non-refundable</li>
<li>Service fees marked non-refundable at checkout</li>
</ul>

<h2>How to request a refund</h2>
<p>Contact our team through your booking reference with passenger names and travel dates. We will verify eligibility with the airline or supplier and advise next steps. You may be asked to provide supporting documents.</p>

<h2>No guarantee until approval</h2>
<p>Submitting a refund request does not guarantee approval. The platform will communicate the outcome once the supplier responds.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cancellationPolicy(): array
    {
        return [
            'title' => 'Cancellation Policy',
            'slug' => 'cancellation-policy',
            'footer_group' => 'policies',
            'footer_label' => 'Cancellation Policy',
            'footer_sort_order' => 20,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Cancellation Policy',
            'seo_description' => 'Cancellation rules before and after ticketing, airline penalties, no-shows, partial cancellations, and how to submit a request.',
            'excerpt' => 'Understand cancellation timelines, airline penalties, no-show rules, and how to request changes or cancellations for your booking.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> This page is general guidance. Your booking is governed by airline fare rules, ticket conditions, and supplier policies that may differ from the information below.</p>

<h2>Before ticketing</h2>
<p>If your reservation is held but not yet ticketed, you may be able to cancel without airline penalties, subject to fare validity and payment status. Unpaid holds may expire automatically per airline rules.</p>

<h2>After ticketing</h2>
<p>Once a ticket is issued, cancellation is controlled by the airline&rsquo;s fare rules. Refundable fares may allow cancellation with a penalty; non-refundable fares may not permit cancellation or may only allow future credit, if at all.</p>

<h2>Airline penalties</h2>
<p>Penalties vary by route, fare brand, class of service, and time of cancellation. The platform will quote applicable charges when processing your request, but the airline&rsquo;s final assessment prevails.</p>

<h2>No-show</h2>
<p>If you do not check in or board as scheduled without prior cancellation, the airline may treat the ticket as flown or forfeited. No-show segments are often non-refundable and may affect return or onward segments.</p>

<h2>Partial cancellations</h2>
<p>For multi-passenger or multi-segment bookings, partial cancellation may not be permitted or may require re-pricing of remaining segments. Group and family bookings should be reviewed as a whole before changes.</p>

<h2>Group and family bookings</h2>
<p>Special group fares or bundled itineraries may have stricter change and cancellation terms. Contact our team early if you need to adjust passenger counts or dates.</p>

<h2>How to request cancellation</h2>
<p>Provide your booking reference, passenger names, and the segments you wish to cancel. Our team will confirm eligibility, penalties, and whether a refund, credit, or rebooking is available.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privacyPolicy(): array
    {
        return [
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'footer_group' => 'policies',
            'footer_label' => 'Privacy Policy',
            'footer_sort_order' => 30,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Privacy Policy',
            'seo_description' => 'How we collect, use, and protect personal data for bookings, payments, support, and agent accounts on our OTA platform.',
            'excerpt' => 'Describes what personal data we collect for travel bookings, how it is used, shared with suppliers, and how you can contact us about privacy.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> This policy describes general practices. Specific processing may vary by booking type, jurisdiction, and supplier requirements.</p>

<h2>Information we collect</h2>
<p>We collect information needed to search, book, ticket, and support travel, including:</p>
<ul>
<li>Contact details (name, email, phone)</li>
<li>Passenger identity and travel document data required by airlines</li>
<li>Payment and billing information processed through secure payment providers</li>
<li>Booking history, support correspondence, and account preferences</li>
<li>Agent and agency account details for B2B users</li>
<li>Technical logs for security, fraud prevention, and service improvement</li>
</ul>

<h2>How we use your data</h2>
<p>Personal data is used to complete reservations, communicate itinerary changes, process payments, provide customer support, comply with legal obligations, and maintain platform security.</p>

<h2>Sharing with third parties</h2>
<p>We share necessary data with:</p>
<ul>
<li>Airlines, GDS providers, and other travel suppliers to fulfill bookings</li>
<li>Payment processors to authorize and settle transactions</li>
<li>Service providers that assist with hosting, email, and analytics under contractual safeguards</li>
<li>Authorities when required by law or to protect rights and safety</li>
</ul>
<p>We do not sell personal data.</p>

<h2>Data protection</h2>
<p>We apply administrative, technical, and organizational measures appropriate to the sensitivity of travel and payment data. No method of transmission or storage is completely secure; please use strong credentials for your account.</p>

<h2>Retention</h2>
<p>We retain data as needed for bookings, accounting, dispute resolution, and legal compliance, then delete or anonymize it when no longer required.</p>

<h2>Your rights</h2>
<p>Depending on your location, you may have rights to access, correct, or request deletion of certain personal data. Contact our team with your request and booking reference.</p>

<h2>Contact</h2>
<p>For privacy-related questions, contact our support team through the channels listed on the platform. Include sufficient detail for us to verify your identity and booking relationship.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function termsAndConditions(): array
    {
        return [
            'title' => 'Terms & Conditions',
            'slug' => 'terms-and-conditions',
            'footer_group' => 'policies',
            'footer_label' => 'Terms & Conditions',
            'footer_sort_order' => 40,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Terms & Conditions',
            'seo_description' => 'Platform terms covering bookings, fare availability, passenger data, ticketing deadlines, user responsibilities, and limitation of liability.',
            'excerpt' => 'Terms governing use of the OTA platform, including bookings, fares, passenger accuracy, changes, and liability limits.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> These terms are general. Airline conditions of carriage, fare rules, and your booking confirmation may impose additional requirements.</p>

<h2>Use of the platform</h2>
<p>By using this OTA platform, you agree to these terms and applicable laws. You must provide accurate information and use the service only for lawful travel arrangements.</p>

<h2>Fare availability</h2>
<p>Fares and seat availability displayed during search are subject to change until a booking is confirmed and, where applicable, ticketed. Displayed prices may exclude optional fees, baggage, or taxes until shown at checkout.</p>

<h2>Booking confirmation</h2>
<p>A booking is subject to confirmation by the airline or supplier. We are not responsible for declines due to inventory, fare expiration, regulatory restrictions, or payment failure.</p>

<h2>Passenger data accuracy</h2>
<p>You are responsible for names, dates of birth, document numbers, and contact details matching travel documents. Errors may result in denied boarding, additional charges, or cancellation without refund.</p>

<h2>Fare changes and errors</h2>
<p>Obvious pricing errors may be corrected or cancelled. After ticketing, changes follow airline fare rules and may incur penalties or fare differences.</p>

<h2>Ticketing deadlines</h2>
<p>Some fares require payment and ticketing by a stated deadline. Failure to meet the deadline may result in automatic cancellation of the reservation.</p>

<h2>User responsibilities</h2>
<p>You must review itinerary details, visa and passport requirements, and health documentation before travel. You are responsible for arriving at the airport within airline recommended times.</p>

<h2>Limitation of liability</h2>
<p>To the extent permitted by law, the platform is not liable for indirect, incidental, or consequential losses arising from airline schedule changes, cancellations, or acts beyond our reasonable control. Our liability for a booking is limited to fees paid to us for that transaction, except where law requires otherwise.</p>

<h2>Governing terms</h2>
<p>These terms apply together with supplier conditions, payment policies, and privacy practices published on the platform. If a conflict arises, supplier fare rules and ticket conditions govern the transport service itself.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPolicy(): array
    {
        return [
            'title' => 'Payment Policy',
            'slug' => 'payment-policy',
            'footer_group' => 'policies',
            'footer_label' => 'Payment Policy',
            'footer_sort_order' => 50,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Payment Policy',
            'seo_description' => 'Accepted payment methods, verification, proof of payment, service charges, currency handling, and when a fare is guaranteed.',
            'excerpt' => 'How payments are accepted and verified, when fares are guaranteed, and how failed or partial payments are handled.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> Payment options and charges may vary by route, agency, and booking channel. Your checkout summary is authoritative for your transaction.</p>

<h2>Accepted payment methods</h2>
<p>Available methods may include bank transfer, card payments through approved gateways, and agency wallet or credit where enabled. Options shown at checkout apply to your booking.</p>

<h2>Proof of payment</h2>
<p>For manual or bank-transfer payments, you may need to upload proof of payment. Ticketing may be delayed until our team verifies funds.</p>

<h2>Payment verification</h2>
<p>We reserve the right to verify payer identity and transaction details for fraud prevention. Bookings may be held or cancelled if verification fails.</p>

<h2>Fare guarantee</h2>
<p>A fare is not guaranteed until payment is received in full and the ticket is issued, or the airline confirms a paid reservation per fare rules. Exchange rates and taxes may change before ticketing.</p>

<h2>Failed or partial payments</h2>
<p>Partial payments do not secure a fare unless explicitly agreed. Failed card authorizations or insufficient wallet balance may result in cancellation of unpaid segments.</p>

<h2>Service charges</h2>
<p>Platform service fees, payment surcharges, or agency fees are disclosed during booking where applicable. These may be non-refundable if stated at checkout.</p>

<h2>Currency differences</h2>
<p>Charges may be processed in a billing currency that differs from the displayed fare currency. Your bank may apply conversion rates or international transaction fees outside our control.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baggagePolicy(): array
    {
        return [
            'title' => 'Baggage Policy',
            'slug' => 'baggage-policy',
            'footer_group' => 'travel_info',
            'footer_label' => 'Baggage Policy',
            'footer_sort_order' => 10,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Baggage Policy',
            'seo_description' => 'Baggage allowance depends on airline, route, and fare brand. Verify hand carry, checked bags, and excess fees on your ticket.',
            'excerpt' => 'Baggage allowances vary by airline and fare. Check your ticket and operating carrier rules for hand carry, checked baggage, and special items.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> Baggage rules are set by the operating airline and may differ from summaries shown during search. Your ticket and airline website are the final reference.</p>

<h2>Allowance depends on airline and fare</h2>
<p>Checked and cabin baggage limits vary by airline, route, aircraft type, fare brand, and class of service. Basic economy fares may not include checked baggage.</p>

<h2>Hand carry</h2>
<p>Most airlines permit one cabin bag and a small personal item, subject to size and weight limits. Liquids and sharp items must comply with security regulations at each airport.</p>

<h2>Checked baggage</h2>
<p>Weight and piece allowances are defined in your ticket conditions. Interline or codeshare flights may apply the most restrictive carrier&rsquo;s rules.</p>

<h2>Excess baggage</h2>
<p>Additional bags or overweight items may be charged at the airport or prepaid through the airline. Fees are determined by the operating carrier and are not controlled by the platform.</p>

<h2>Special baggage</h2>
<p>Sports equipment, musical instruments, medical devices, and pets often require advance approval and may incur extra fees. Contact the airline before travel.</p>

<h2>Your responsibility</h2>
<p>Review your e-ticket, booking confirmation, and the airline&rsquo;s baggage page before departure. The platform displays estimates only and cannot guarantee allowance at check-in.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function travelAdvisory(): array
    {
        return [
            'title' => 'Travel Advisory',
            'slug' => 'travel-advisory',
            'footer_group' => 'travel_info',
            'footer_label' => 'Travel Advisory',
            'footer_sort_order' => 20,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'Travel Advisory',
            'seo_description' => 'Passport and visa requirements, health documents, airport arrival times, schedule changes, and passenger travel responsibilities.',
            'excerpt' => 'Important travel reminders on documents, visas, health requirements, airport timing, and schedule change awareness.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> Entry rules and advisories change frequently. Confirm requirements with embassies, airlines, and official government sources before travel.</p>

<h2>Passport validity</h2>
<p>Many destinations require passports valid for at least six months beyond your return date. Ensure names match your booking exactly.</p>

<h2>Visa and transit requirements</h2>
<p>Visas, transit visas, and onward-ticket proof may be required even for short connections. The platform does not provide immigration advice.</p>

<h2>Health and vaccination documents</h2>
<p>Some countries require vaccination certificates, health declarations, or insurance. Carry printed and digital copies where accepted.</p>

<h2>Airport arrival times</h2>
<p>Arrive at the airport according to airline guidance—typically two to three hours before international departures and earlier during peak periods.</p>

<h2>Schedule changes</h2>
<p>Airlines may change flight times or equipment. Monitor email and SMS notifications and reconfirm check-in times before departure.</p>

<h2>Travel restrictions</h2>
<p>Government travel bans, sanctions, or local regulations may affect eligibility to travel or enter a country. Passengers are responsible for compliance.</p>

<h2>Passenger responsibility</h2>
<p>You are responsible for meeting all documentary and timing requirements. Denied boarding or refusal of entry due to incomplete documents is not grounds for automatic compensation from the platform.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agentTerms(): array
    {
        return [
            'title' => 'Agent Terms',
            'slug' => 'agent-terms',
            'footer_group' => 'agent_b2b',
            'footer_label' => 'Agent Terms',
            'footer_sort_order' => 10,
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'seo_title' => 'Agent Terms',
            'seo_description' => 'B2B agent account terms: booking responsibility, payments, misuse, cancellations, suspension, and platform audit rights.',
            'excerpt' => 'Terms for travel agents using the B2B platform, including account use, payment obligations, and compliance expectations.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> These agent terms supplement agency agreements and airline fare rules. Specific credit and wallet terms are described in the Wallet &amp; Credit Policy.</p>

<h2>B2B account use</h2>
<p>Agent accounts are for licensed or authorized travel sellers only. Credentials must not be shared outside your agency. You are responsible for all activity under your login.</p>

<h2>Booking responsibility</h2>
<p>You must issue accurate passenger data, comply with airline rules, and disclose applicable fees to your customers. The agency remains liable to the platform for unpaid or disputed bookings.</p>

<h2>Payment and credit obligations</h2>
<p>Bookings must be paid per agreed terms—wallet balance, approved credit, or immediate settlement. Failure to pay by ticketing deadlines may result in cancellation and penalties.</p>

<h2>Misuse</h2>
<p>Fraudulent bookings, fare scraping abuse, or misrepresentation of agency status may lead to immediate suspension and legal action.</p>

<h2>Cancellations and refunds</h2>
<p>Agent-initiated changes follow the same supplier rules as consumer bookings. Refunds to wallet or original payment are processed per platform policy after supplier approval.</p>

<h2>Account suspension</h2>
<p>We may suspend or terminate access for non-payment, policy violations, chargebacks, or regulatory concerns, with or without notice where required for security.</p>

<h2>Platform audit rights</h2>
<p>We may review booking logs, payment records, and account activity to ensure compliance, prevent fraud, and reconcile ledgers.</p>
HTML,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function walletCreditPolicy(): array
    {
        return [
            'title' => 'Wallet & Credit Policy',
            'slug' => 'wallet-credit-policy',
            'footer_group' => 'agent_b2b',
            'footer_label' => 'Wallet & Credit Policy',
            'footer_sort_order' => 20,
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'seo_title' => 'Wallet & Credit Policy',
            'seo_description' => 'Agent wallet deposits, ledger entries, credit limits, verification, reversals, refunds to wallet, and reconciliation rules.',
            'excerpt' => 'How agency wallet balances, deposits, credit limits, and ledger adjustments work on the B2B platform.',
            'content' => <<<'HTML'
<p><em>Last updated: June 2026</em></p>
<p><strong>Disclaimer:</strong> Wallet and credit limits are assigned per agency agreement and may differ from the general description below.</p>

<h2>Agent wallet</h2>
<p>The wallet holds prepaid funds used to ticket bookings. Available balance is reduced immediately when a qualifying debit is posted.</p>

<h2>Deposits</h2>
<p>Deposits may require proof of bank transfer or approved payment channel. Funds are credited after verification by our finance team.</p>

<h2>Ledger</h2>
<p>All wallet movements are recorded in the agency ledger with references to bookings, deposits, adjustments, and refunds. Agents should reconcile statements regularly.</p>

<h2>Credit limits</h2>
<p>Approved agencies may receive a credit line for ticketing subject to periodic review. Exceeding the limit blocks new debits until balance is restored.</p>

<h2>Pending verification</h2>
<p>Deposits or adjustments marked pending are not available for ticketing until confirmed. Do not assume credit until the ledger shows cleared funds.</p>

<h2>Reversals</h2>
<p>Erroneous credits or duplicate deposits may be reversed after investigation. You will be notified of material adjustments.</p>

<h2>Refunds to wallet</h2>
<p>Supplier-approved refunds for agent bookings may be credited to the wallet rather than the end customer&rsquo;s payment method, per agency settings.</p>

<h2>Negative balances</h2>
<p>Negative balances must be cleared before further ticketing. Persistent arrears may trigger suspension.</p>

<h2>Reconciliation and admin approval</h2>
<p>Manual ledger corrections and credit-limit changes require platform admin approval. Disputes must be raised within the timeframe stated in your agency agreement.</p>
HTML,
        ];
    }
}
