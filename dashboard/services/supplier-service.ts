import type { SuppliersPageResult, SuppliersQuery, SupplierRecord } from "@/types/supplier";
import { buildSuppliersPage } from "@/lib/suppliers-filter";
import { getSupplierById, mockSuppliers } from "@/mocks/supplier-fixtures";
import { useMockData } from "@/lib/preview";

export class SuppliersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "SuppliersServiceError";
    this.referenceId = referenceId;
  }
}

export async function getSuppliersPage(query: SuppliersQuery): Promise<SuppliersPageResult> {
  if (!useMockData()) {
    throw new SuppliersServiceError(
      "Live supplier data is disabled in preview.",
      "SU-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new SuppliersServiceError(
      "Mock supplier service returned a recoverable error (preview simulation).",
      "SU-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildSuppliersPage(query, mockSuppliers);
}

export async function getSupplierDetail(id: string): Promise<SupplierRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getSupplierById(id) ?? null;
}

export function listAllMockSuppliers(): SupplierRecord[] {
  return mockSuppliers;
}
