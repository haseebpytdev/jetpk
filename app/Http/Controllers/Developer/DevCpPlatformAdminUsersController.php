<?php

namespace App\Http\Controllers\Developer;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\StoreDevCpPlatformAdminRequest;
use App\Http\Requests\Developer\UpdateDevCpPlatformAdminStatusRequest;
use App\Models\DeveloperUser;
use App\Models\User;
use App\Services\Developer\DevCpPlatformAdminUserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Dev CP platform_admin account management for this deployment.
 */
class DevCpPlatformAdminUsersController extends Controller
{
    public function __construct(
        protected DevCpPlatformAdminUserService $platformAdmins,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('developer.users.index', [
            'users' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status']),
            'activePlatformAdminCount' => $this->platformAdmins->activePlatformAdminCount(),
        ]);
    }

    public function store(StoreDevCpPlatformAdminRequest $request): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $result = $this->platformAdmins->createPlatformAdmin(
            email: $request->string('email')->toString(),
            name: $request->string('name')->toString(),
            developer: $developer,
            request: $request,
        );

        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', 'Platform Admin account created for '.$result['user']->email.'.')
            ->with('dev_cp_temp_password', $result['tempPassword'])
            ->with('dev_cp_temp_password_email', $result['user']->email);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPlatformAdmin(), 404);

        $developer = $this->resolveDeveloper($request);

        try {
            $result = $this->platformAdmins->resetPassword($user, $developer, $request);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('dev.cp.users.index')
                ->withErrors(['user' => $e->getMessage()]);
        }

        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', 'Password reset for '.$result['user']->email.'.')
            ->with('dev_cp_temp_password', $result['tempPassword'])
            ->with('dev_cp_temp_password_email', $result['user']->email);
    }

    public function updateStatus(UpdateDevCpPlatformAdminStatusRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isPlatformAdmin(), 404);

        $developer = $this->resolveDeveloper($request);
        $active = $request->string('status')->toString() === UserAccountStatus::Active->value;

        try {
            $updated = $this->platformAdmins->setActiveStatus($user, $active, $developer, $request);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('dev.cp.users.index')
                ->withErrors(['user' => $e->getMessage()]);
        }

        $label = $updated->status === UserAccountStatus::Active ? 'reactivated' : 'deactivated';

        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', 'Platform Admin '.$updated->email.' '.$label.'.');
    }

    private function resolveDeveloper(Request $request): DeveloperUser
    {
        $userId = $request->session()->get('dev_cp_user_id');
        abort_if($userId === null, 403);

        $developer = DeveloperUser::query()->find($userId);
        abort_if($developer === null || ! $developer->is_active, 403);

        return $developer;
    }
}
