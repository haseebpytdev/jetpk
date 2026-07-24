<?php

namespace App\Support\Client;

use App\Services\Client\ClientPageContentResolver;

/**
 * First-install bootstrap templates for JetPK managed pages.
 *
 * Used only by explicit import commands — never as public runtime fallback.
 */
final class ClientPageBootstrapTemplate
{
    /**
     * @return array<string, mixed>
     */
    public static function contentFor(string $pageKey): array
    {
        return match ($pageKey) {
            ClientPageKeys::HOME => self::homeContent(),
            ClientPageKeys::FOOTER => self::footerContent(),
            ClientPageKeys::GLOBAL => self::globalContent(),
            ClientPageKeys::ABOUT => self::aboutContent(),
            ClientPageKeys::SUPPORT => self::supportContent(),
            ClientPageKeys::GROUP_SEARCH => self::groupSearchContent(),
            ClientPageKeys::BOOKING_LOOKUP => self::bookingLookupContent(),
            ClientPageKeys::AGENT_REGISTRATION => self::agentRegistrationContent(),
            ClientPageKeys::LOGIN => self::loginContent(),
            ClientPageKeys::REGISTER => self::registerContent(),
            ClientPageKeys::TERMS => self::termsContent(),
            ClientPageKeys::PRIVACY => self::privacyContent(),
            ClientPageKeys::FAQ => self::faqContent(),
            default => ClientPageKeys::isCustom($pageKey) ? self::emptyContentPage() : [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function homeContent(): array
    {
        return require __DIR__.'/Bootstrap/homepage.bootstrap.php';
    }

    /**
     * @return array<string, mixed>
     */
    public static function footerContent(): array
    {
        return [
            'description' => [
                'text' => "Pakistan's premium flight booking platform. Honest fares, instant tickets, human support.",
            ],
            'columns' => [
                ['id' => 'foot-company', 'title' => 'Company', 'enabled' => '1', 'sort_order' => 0, 'links' => [
                    ['id' => 'foot-about', 'label' => 'About us', 'destination' => 'route:about', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'foot-contact', 'label' => 'Contact', 'destination' => 'route:support', 'enabled' => '1', 'sort_order' => 1],
                ]],
                ['id' => 'foot-policies', 'title' => 'Policies', 'enabled' => '1', 'sort_order' => 1, 'links' => [
                    ['id' => 'foot-terms', 'label' => 'Terms', 'destination' => 'route:terms', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'foot-privacy', 'label' => 'Privacy', 'destination' => 'route:privacy', 'enabled' => '1', 'sort_order' => 1],
                ]],
                ['id' => 'foot-support', 'title' => 'Support', 'enabled' => '1', 'sort_order' => 2, 'links' => [
                    ['id' => 'foot-faq', 'label' => 'Help centre', 'destination' => 'route:faq', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'foot-lookup', 'label' => 'Manage booking', 'destination' => 'route:booking.lookup', 'enabled' => '1', 'sort_order' => 1],
                ]],
                ['id' => 'foot-b2b', 'title' => 'B2B & agents', 'enabled' => '1', 'sort_order' => 3, 'links' => [
                    ['id' => 'foot-agent', 'label' => 'Become an agent', 'destination' => 'route:agent.register', 'enabled' => '1', 'sort_order' => 0],
                ]],
            ],
            'social' => [
                ['platform' => 'Facebook', 'url' => 'https://www.facebook.com/jetpakistancom/'],
                ['platform' => 'Instagram', 'url' => 'https://www.instagram.com/jetpakistanofficial'],
            ],
            'legal' => [
                'copyright' => '© {year} JetPakistan. All rights reserved.',
                'company_line' => 'JetPakistan — IATA accredited travel services.',
            ],
            'contact' => self::globalContent()['contact'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function globalContent(): array
    {
        $defaults = app(ClientPageContentResolver::class)->defaultGlobalContent();
        $defaults['header'] = [
            'logo_asset' => 'header_logo',
            'logo_dark_asset' => 'header_logo_dark',
            'support_pill_label' => '24/7 Support',
            'support_pill_url' => 'tel:+923111222427',
            'sign_in_label' => 'Sign in',
            'register_label' => 'Register',
            'theme_toggle_visible' => '1',
            'sticky_enabled' => '1',
            'nav_items' => [
                ['id' => 'nav-home', 'label' => 'Home', 'destination' => 'route:home', 'enabled' => '1', 'guest_visible' => '1', 'auth_visible' => '1', 'sort_order' => 0],
                ['id' => 'nav-booking', 'label' => 'Booking', 'destination' => 'route:booking.lookup', 'enabled' => '1', 'guest_visible' => '1', 'auth_visible' => '1', 'sort_order' => 1],
                ['id' => 'nav-support', 'label' => 'Support', 'destination' => 'route:support', 'enabled' => '1', 'guest_visible' => '1', 'auth_visible' => '1', 'sort_order' => 2],
                ['id' => 'nav-about', 'label' => 'About', 'destination' => 'route:about', 'enabled' => '1', 'guest_visible' => '1', 'auth_visible' => '1', 'sort_order' => 3],
            ],
        ];
        $defaults['contact'] = [
            'phone' => '0311 1222427',
            'phone_e164' => '+923111222427',
            'email' => 'ota@jetpakistan.pk',
            'whatsapp' => '923111222427',
            'website' => 'https://www.jetpakistan.com',
            'office' => 'Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore',
            'hours' => '24/7',
            'company_legal_name' => 'JetPakistan',
        ];

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public static function aboutContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'About JetPakistan',
                'title' => 'Cheap flights and secure online booking for Pakistan',
                'description' => 'JetPakistan helps travellers discover low fares, compare airlines, and complete domestic and international flight bookings online with confidence.',
            ],
            'feature_cards' => [
                'enabled' => '1',
                'items' => [
                    ['id' => 'about-fc-1', 'title' => 'Lowest fare discovery', 'body' => 'Search hundreds of routes from Pakistan and compare airline options side by side before you book.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'about-fc-2', 'title' => 'Secure online booking', 'body' => 'Book with clear pricing in PKR, protected checkout, and support if your plans change.', 'enabled' => '1', 'sort_order' => 1],
                    ['id' => 'about-fc-3', 'title' => 'Mobile travel app', 'body' => 'Search, book, and manage trips on the go with the JetPakistan mobile experience.', 'enabled' => '1', 'sort_order' => 2],
                ],
            ],
            'content_grid' => [
                'enabled' => '1',
                'items' => [
                    ['id' => 'about-cg-1', 'title' => 'Why JetPakistan', 'body' => "Cheap air tickets for domestic and international travel from Pakistan\nTransparent fares with no surprise charges at checkout\nOnline check-in guidance and e-ticket delivery\nHuman support for booking changes, invoices, and travel questions\nPopular routes: Lahore, Karachi, Islamabad, Dubai, Jeddah, and beyond", 'enabled' => '1', 'sort_order' => 0, 'format' => 'list'],
                    ['id' => 'about-cg-2', 'title' => 'Domestic & international travel', 'body' => "Whether you are flying within Pakistan or heading abroad for work, Umrah, or leisure, JetPakistan brings airline options together in one place.\n\nCompare departure times, cabin classes, and total price — then book the itinerary that fits your schedule and budget.", 'enabled' => '1', 'sort_order' => 1, 'format' => 'paragraphs'],
                    ['id' => 'about-cg-3', 'title' => 'Booking confidence', 'body' => 'Every search is designed to be simple: pick your route, choose dates, select travellers, and confirm. Our team is available when you need help before or after purchase.', 'enabled' => '1', 'sort_order' => 2, 'format' => 'paragraphs'],
                ],
            ],
            'contact' => self::globalContent()['contact'],
            'cta' => [
                'primary_label' => 'Search flights',
                'primary_url' => 'route:home#jp-flight-search',
                'secondary_label' => 'Contact support',
                'secondary_url' => 'route:support',
            ],
            'seo' => [
                'title' => 'About us — JetPakistan',
                'description' => 'Learn about JetPakistan, Pakistan-focused online flight booking with transparent PKR fares and licensed operations.',
                'robots' => 'index,follow',
            ],
            'sections_order' => ['hero', 'feature_cards', 'content_grid', 'contact', 'cta'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function supportContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'Support & contact',
                'title' => 'Flight booking help, 24/7',
                'description' => 'Get assistance with online ticket booking, fare questions, payments, e-tickets, changes, and online check-in.',
            ],
            'department_cards' => [
                'enabled' => '1',
                'items' => [
                    ['id' => 'sup-dept-1', 'title' => 'Booking assistance', 'body' => 'New bookings, itinerary changes, and passenger detail updates.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'sup-dept-2', 'title' => 'Payments & confirmation', 'body' => 'Payment proof, booking confirmation, and invoice questions.', 'enabled' => '1', 'sort_order' => 1],
                    ['id' => 'sup-dept-3', 'title' => 'Online check-in', 'body' => 'Guidance for airline check-in and boarding pass access.', 'enabled' => '1', 'sort_order' => 2],
                ],
            ],
            'contact' => self::globalContent()['contact'],
            'form' => [
                'helper_text' => 'Tell us what you need and our team will respond shortly.',
                'success_copy' => 'Thank you — our team will respond shortly.',
            ],
            'faq_teaser' => [
                'enabled' => '1',
                'title' => 'FAQ',
                'link_label' => 'View full help centre',
                'link_url' => 'route:faq',
            ],
            'seo' => [
                'title' => 'Support & contact — JetPakistan',
                'description' => 'Contact JetPakistan support for booking help, payments, e-tickets, and travel questions.',
                'robots' => 'index,follow',
            ],
            'sections_order' => ['hero', 'department_cards', 'contact', 'form', 'faq_teaser'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function faqContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'Help centre',
                'title' => 'Frequently asked questions',
                'description' => 'Answers to common questions about booking, payments, and managing your trip.',
            ],
            'categories' => [
                'enabled' => '1',
                'items' => [
                    [
                        'id' => 'faq-cat-booking',
                        'title' => 'Booking',
                        'enabled' => '1',
                        'sort_order' => 0,
                        'questions' => [
                            ['id' => 'faq-q-1', 'question' => 'How do I book a flight on JetPakistan?', 'answer' => 'Search your route on the homepage, select dates and travellers, then complete checkout with your passenger details.', 'enabled' => '1', 'sort_order' => 0],
                            ['id' => 'faq-q-2', 'question' => 'Can I book domestic and international flights?', 'answer' => 'Yes. JetPakistan supports both domestic Pakistan routes and international destinations from major Pakistani cities.', 'enabled' => '1', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'id' => 'faq-cat-after',
                        'title' => 'After booking',
                        'enabled' => '1',
                        'sort_order' => 1,
                        'questions' => [
                            ['id' => 'faq-q-3', 'question' => 'How do I get help after booking?', 'answer' => 'Contact us by phone, WhatsApp, or email with your booking reference. You can also use Manage booking on the website.', 'enabled' => '1', 'sort_order' => 0],
                        ],
                    ],
                ],
            ],
            'cta' => [
                'label' => 'Contact support',
                'url' => 'route:support',
            ],
            'seo' => [
                'title' => 'FAQ — JetPakistan',
                'description' => 'JetPakistan help centre — booking, payments, and trip management FAQs.',
                'robots' => 'index,follow',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function termsContent(): array
    {
        return [
            'legal' => [
                'title' => 'Terms of service',
                'effective_date' => '2026-01-01',
                'last_updated' => date('Y-m-d'),
                'intro' => 'These terms govern your use of the JetPakistan online travel platform.',
                'sections' => [
                    ['id' => 'terms-1', 'heading' => 'Use of service', 'body' => 'You agree to use JetPakistan only for lawful travel booking purposes and to provide accurate passenger information.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'terms-2', 'heading' => 'Bookings and payments', 'body' => 'Fares are subject to airline rules and availability. Payment must be completed before ticketing where required.', 'enabled' => '1', 'sort_order' => 1],
                ],
            ],
            'seo' => [
                'title' => 'Terms of service — JetPakistan',
                'description' => 'JetPakistan terms of service for online flight booking.',
                'robots' => 'index,follow',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function privacyContent(): array
    {
        return [
            'legal' => [
                'title' => 'Privacy policy',
                'effective_date' => '2026-01-01',
                'last_updated' => date('Y-m-d'),
                'intro' => 'This policy explains how JetPakistan collects, uses, and protects your personal information.',
                'sections' => [
                    ['id' => 'privacy-1', 'heading' => 'Information we collect', 'body' => 'We collect contact details, booking information, and payment references needed to complete your travel requests.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'privacy-2', 'heading' => 'How we use data', 'body' => 'Data is used to process bookings, provide support, and meet legal obligations. We do not sell personal data.', 'enabled' => '1', 'sort_order' => 1],
                ],
            ],
            'seo' => [
                'title' => 'Privacy policy — JetPakistan',
                'description' => 'JetPakistan privacy policy for online flight booking.',
                'robots' => 'index,follow',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function loginContent(): array
    {
        return [
            'hero' => [
                'title' => 'Log in',
                'subtitle' => 'Sign in to manage bookings, e-tickets, and travel updates.',
            ],
            'side_panel' => [
                'eyebrow' => 'Secure portal',
                'title' => 'Book, manage, and track travel with JetPakistan.',
                'body' => 'Use the same trusted OTA account flow with JetPakistan branding, PKR fares, booking updates, and human support when your plans change.',
            ],
            'footer_text' => 'Need help? Contact JetPakistan support.',
            'seo' => ['title' => 'Log in — JetPakistan', 'description' => 'Sign in to your JetPakistan account.', 'robots' => 'noindex,nofollow'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function registerContent(): array
    {
        return [
            'hero' => [
                'title' => 'Create account',
                'subtitle' => 'Register to book faster, save traveller details, and manage trips online.',
            ],
            'side_panel' => [
                'eyebrow' => 'Join JetPakistan',
                'title' => 'Your travel account in one place.',
                'body' => 'Create a secure customer account to search fares, complete bookings, and receive e-tickets and updates.',
            ],
            'benefits' => [
                'items' => [
                    ['id' => 'reg-ben-1', 'text' => 'Faster repeat bookings with saved traveller profiles', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'reg-ben-2', 'text' => 'Booking history and e-ticket access', 'enabled' => '1', 'sort_order' => 1],
                ],
            ],
            'footer_text' => 'By registering you agree to our terms and privacy policy.',
            'seo' => ['title' => 'Register — JetPakistan', 'description' => 'Create your JetPakistan customer account.', 'robots' => 'noindex,nofollow'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bookingLookupContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'Manage booking',
                'title' => 'Lookup your booking',
                'description' => 'Access your booking request, documents, payment status, and travel updates securely.',
            ],
            'instructions' => [
                'how_it_works' => 'Enter the booking reference from your confirmation together with the email address used when you booked.',
                'hint' => 'Your reference usually looks like a short code from your confirmation email or receipt.',
                'requirements' => "Booking reference — from your confirmation\nEmail address — must match what we have on file",
            ],
            'help_text' => 'For privacy, access links are only sent when your details match the booking.',
            'cta' => ['label' => 'Contact support', 'url' => 'route:support'],
            'seo' => ['title' => 'Lookup your booking — JetPakistan', 'description' => 'Securely look up your JetPakistan booking.', 'robots' => 'noindex,nofollow'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function agentRegistrationContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'Agent partnership',
                'title' => 'Join the JetPakistan agent network',
                'description' => 'Partner with our OTA platform to manage client bookings, track performance, and grow your agency sales.',
                'cta_text' => 'Apply as agent',
                'cta_url' => 'route:agent.register.form',
            ],
            'steps' => [
                'items' => [
                    ['id' => 'agent-step-1', 'title' => '1. Submit application', 'body' => 'Share your agency and verification details through our secure application form.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'agent-step-2', 'title' => '2. Admin review', 'body' => 'Our team validates your business profile and may request supporting documents.', 'enabled' => '1', 'sort_order' => 1],
                    ['id' => 'agent-step-3', 'title' => '3. Start booking', 'body' => 'Approved partners receive onboarding instructions and agent dashboard access.', 'enabled' => '1', 'sort_order' => 2],
                ],
            ],
            'benefits' => [
                'title' => 'Benefits for agents',
                'items' => [
                    ['id' => 'agent-ben-1', 'text' => 'Agent dashboard to manage booking requests in one place', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'agent-ben-2', 'text' => 'Fast flight search and fare tools for client itineraries', 'enabled' => '1', 'sort_order' => 1],
                    ['id' => 'agent-ben-3', 'text' => 'Commission tracking and performance visibility', 'enabled' => '1', 'sort_order' => 2],
                    ['id' => 'agent-ben-4', 'text' => 'Priority partner support for urgent travel issues', 'enabled' => '1', 'sort_order' => 3],
                ],
            ],
            'faq' => [
                'items' => [
                    ['id' => 'agent-faq-1', 'question' => 'Who can apply?', 'answer' => 'Licensed agencies, consultants, and travel businesses handling customer bookings.', 'enabled' => '1', 'sort_order' => 0],
                    ['id' => 'agent-faq-2', 'question' => 'Is approval instant?', 'answer' => 'No — every application is reviewed before access is granted.', 'enabled' => '1', 'sort_order' => 1],
                ],
            ],
            'cta' => [
                'title' => 'Ready to partner?',
                'body' => 'Complete the agency application and our team will contact you after verification.',
                'primary_label' => 'Start application',
                'primary_url' => 'route:agent.register.form',
                'secondary_label' => 'Agent log in',
                'secondary_url' => 'route:login',
            ],
            'seo' => ['title' => 'Agent partnership — JetPakistan', 'description' => 'Apply to join the JetPakistan agent network.', 'robots' => 'index,follow'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function groupSearchContent(): array
    {
        return [
            'hero' => [
                'kicker' => 'Group travel',
                'title' => 'Search group departures',
                'description' => 'Find block-seat group inventory with transparent per-seat pricing.',
            ],
            'seo' => ['title' => 'Group travel search — JetPakistan', 'description' => 'Search JetPakistan group and series inventory.', 'robots' => 'index,follow'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyContentPage(): array
    {
        return [
            'identity' => ['title' => '', 'slug' => ''],
            'sections' => [],
            'seo' => ['title' => '', 'description' => '', 'robots' => 'index,follow'],
        ];
    }
}
