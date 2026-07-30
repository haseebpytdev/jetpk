export type AuthBenefitItem = {
  title: string;
  description: string;
  icon: "ticket" | "tag" | "clock" | "shield" | "bolt" | "users" | "calendar";
};

export const LOGIN_BENEFITS: AuthBenefitItem[] = [
  {
    title: "Manage bookings easily",
    description: "View trips, documents, and payment status in one place.",
    icon: "ticket",
  },
  {
    title: "Unlock exclusive deals",
    description: "Access member offers when you are signed in.",
    icon: "tag",
  },
  {
    title: "Faster checkout",
    description: "Save traveler details for quicker repeat bookings.",
    icon: "clock",
  },
  {
    title: "Secure and trusted",
    description: "Your account is protected with industry-standard security.",
    icon: "shield",
  },
];

export const SIGNUP_BENEFITS: AuthBenefitItem[] = [
  {
    title: "Faster bookings",
    description: "Save your details and book flights in just a few clicks.",
    icon: "bolt",
  },
  {
    title: "Save travelers",
    description: "Store traveler information securely for quick bookings.",
    icon: "users",
  },
  {
    title: "Manage bookings",
    description: "View and manage your trips anytime, anywhere.",
    icon: "calendar",
  },
  {
    title: "Exclusive offers",
    description: "Get access to special deals when available.",
    icon: "tag",
  },
];

export const RECOVERY_BENEFITS: AuthBenefitItem[] = [
  {
    title: "Secure recovery",
    description: "Reset links are sent only when your details match our records.",
    icon: "shield",
  },
  {
    title: "Privacy first",
    description: "We use the same response whether or not an account exists.",
    icon: "shield",
  },
];
