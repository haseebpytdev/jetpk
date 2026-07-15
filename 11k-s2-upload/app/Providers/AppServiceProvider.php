<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMedia;
use App\Models\AgencyMessageTemplate;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingDocument;
use App\Models\BookingRefund;
use App\Models\CmsPage;
use App\Models\CommunicationLog;
use App\Models\HomepageFeaturedFare;
use App\Models\LedgerTransaction;
use App\Models\MarkupRule;
use App\Models\PromoCode;
use App\Models\SavedTraveler;
use App\Models\StaffProfile;
use App\Models\SupplierConnection;
use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\AccountingLedgerPolicy;
use App\Policies\AgencyBrandingPolicy;
use App\Policies\AgencyCommunicationSettingPolicy;
use App\Policies\AgencyMediaPolicy;
use App\Policies\AgencyMessageTemplatePolicy;
use App\Policies\AgentCommissionPolicy;
use App\Policies\AgentDepositRequestPolicy;
use App\Policies\AgentPolicy;
use App\Policies\BookingCancellationPolicy;
use App\Policies\BookingDocumentPolicy;
use App\Policies\BookingPolicy;
use App\Policies\BookingRefundPolicy;
use App\Policies\CmsPagePolicy;
use App\Policies\CommunicationLogPolicy;
use App\Policies\HomepageFeaturedFarePolicy;
use App\Policies\MarkupRulePolicy;
use App\Policies\MasterLedgerPolicy;
use App\Policies\PlatformAdminPolicy;
use App\Policies\PromoCodePolicy;
use App\Policies\SavedTravelerPolicy;
use App\Policies\StaffProfilePolicy;
use App\Policies\SupplierConnectionPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\UserManagementPolicy;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Branding\PlatformBrandingResolver;
use App\Support\Branding\PublicAgencyContactResolver;
use App\Support\Branding\SafeBrandingResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PlatformBrandingResolver::applyRuntimeConfig();

        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        RateLimiter::for('lookup-booking', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('guest-token', fn (Request $request): Limit => Limit::perMinute(15)->by($request->ip()));
        RateLimiter::for('public-booking-submit', fn (Request $request): Limit => Limit::perMinute(12)->by($request->ip()));
        RateLimiter::for('payment-proof-submit', fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('communication-test-email', fn (Request $request): Limit => Limit::perMinute(6)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('communication-resend', fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('register-validate-field', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('public-flight-results-data', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('public-flight-results-search', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        VerifyEmail::createUrlUsing(static function (object $notifiable): string {
            $verificationPath = URL::temporarySignedRoute(
                'verification.verify',
                now()->addHours(24),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                false
            );

            return url($verificationPath);
        });

        Gate::policy(Agency::class, AgencyBrandingPolicy::class);
        Gate::policy(AgencyCommunicationSetting::class, AgencyCommunicationSettingPolicy::class);
        Gate::policy(AgencyMedia::class, AgencyMediaPolicy::class);
        Gate::policy(AgencyMessageTemplate::class, AgencyMessageTemplatePolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(BookingCancellationRequest::class, BookingCancellationPolicy::class);
        Gate::policy(BookingRefund::class, BookingRefundPolicy::class);
        Gate::policy(BookingDocument::class, BookingDocumentPolicy::class);
        Gate::policy(CmsPage::class, CmsPagePolicy::class);
        Gate::policy(CommunicationLog::class, CommunicationLogPolicy::class);
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(AgentWalletTransaction::class, MasterLedgerPolicy::class);
        Gate::policy(LedgerTransaction::class, AccountingLedgerPolicy::class);
        Gate::policy(AgentCommissionEntry::class, AgentCommissionPolicy::class);
        Gate::policy(AgentCommissionStatement::class, AgentCommissionPolicy::class);
        Gate::policy(AgentDepositRequest::class, AgentDepositRequestPolicy::class);
        Gate::policy(StaffProfile::class, StaffProfilePolicy::class);
        Gate::policy(SavedTraveler::class, SavedTravelerPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(MarkupRule::class, MarkupRulePolicy::class);
        Gate::policy(HomepageFeaturedFare::class, HomepageFeaturedFarePolicy::class);
        Gate::policy(PromoCode::class, PromoCodePolicy::class);
        Gate::policy(SupplierConnection::class, SupplierConnectionPolicy::class);
        Gate::policy(User::class, UserManagementPolicy::class);

        Gate::define('commission.adjust', [AgentCommissionPolicy::class, 'adjust']);
        Gate::define('commission.payout', [AgentCommissionPolicy::class, 'payout']);
        Gate::define('commission.statement', [AgentCommissionPolicy::class, 'statement']);
        Gate::define('platform.admin', [PlatformAdminPolicy::class, 'accessAdminTools']);

        View::composer(
            [
                'layouts.frontend',
                'layouts.mobile-app',
                'layouts.auth',
                'layouts.dashboard',
                'errors.layout',
                'frontend.*',
                'auth.*',
                'mobile.*',
                'dashboard.customer.*',
            ],
            function ($view): void {
                $payload = SafeBrandingResolver::resolveForPublic();
                $settings = $payload['settings'] ?? null;
                $user = auth()->user();
                $brandName = BrandDisplayResolver::displayName($settings, $user);

                $view->with([
                    'brandName' => $brandName,
                    'brandTheme' => BrandDisplayResolver::themeColors($settings),
                    'brandCssVariables' => BrandDisplayResolver::cssVariables($settings),
                    'agencySettings' => $settings,
                    'publicBranding' => $payload,
                    'publicAgencyContact' => PublicAgencyContactResolver::resolve($settings),
                ]);
            }
        );

        View::composer('emails.*', function ($view): void {
            $view->with('companyEmailProfile', CompanyEmailProfileResolver::resolveForPlatform());
        });
    }
}
