"use client";

import { Dialog } from "@/components/ui/Dialog";
import { LoginForm } from "@/features/auth/components/LoginForm";

type GroupCheckoutAuthModalProps = {
  open: boolean;
  onClose: () => void;
  /** Authoritative checkout resume path, e.g. /groups/ALH-1/passengers */
  returnPath: string;
  onAuthenticated: (resumePath: string) => void;
};

/**
 * Floating login for anonymous Book Now — preserves Group detail context.
 * Reuses LoginForm + Laravel auth; return path is allowlisted server-side.
 */
export function GroupCheckoutAuthModal({
  open,
  onClose,
  returnPath,
  onAuthenticated,
}: GroupCheckoutAuthModalProps) {
  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Login required"
      description="Sign in to continue to group checkout. Your selected group fare will be kept."
      className="max-w-md font-[Inter,system-ui,sans-serif]"
    >
      <div data-testid="group-checkout-auth-modal">
        <LoginForm
          returnPath={returnPath}
          compact
          onSuccessNavigate={(path) => {
            onAuthenticated(path);
          }}
        />
      </div>
    </Dialog>
  );
}
