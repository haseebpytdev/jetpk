import type { CustomersPageResult, CustomersQuery, CustomerRecord } from "@/types/customer";
import { buildCustomersPage } from "@/lib/customers-filter";
import { getCustomerById, mockCustomers } from "@/mocks/customer-fixtures";
import { useMockData } from "@/lib/preview";

export class CustomersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "CustomersServiceError";
    this.referenceId = referenceId;
  }
}

export async function getCustomersPage(query: CustomersQuery): Promise<CustomersPageResult> {
  if (!useMockData()) {
    throw new CustomersServiceError(
      "Live customer data is disabled in preview.",
      "CU-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new CustomersServiceError(
      "Mock customer service returned a recoverable error (preview simulation).",
      "CU-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildCustomersPage(query, mockCustomers);
}

export async function getCustomerDetail(id: string): Promise<CustomerRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getCustomerById(id) ?? null;
}

export function listAllMockCustomers(): CustomerRecord[] {
  return mockCustomers;
}
