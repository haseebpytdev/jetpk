import type { CustomerRecord, CustomersPageResult } from "@/types/customer";
import type { LaravelCustomersListPayload } from "@/lib/read-only/laravel/types";

export function transformCustomersPage(
  payload: LaravelCustomersListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): CustomersPageResult {
  const customers = payload.customers as CustomerRecord[];
  const cities = [...new Set(customers.map((c) => c.city).filter(Boolean))];
  const countries = [...new Set(customers.map((c) => c.country).filter(Boolean))];
  const customerTypes = [...new Set(customers.map((c) => c.customerType))];

  let totalTravellers = 0;
  let customersWithOutstanding = 0;
  let totalLifetimeValue = 0;

  for (const customer of customers) {
    totalTravellers += customer.travellerCount;
    if (customer.outstandingBalance > 0) customersWithOutstanding += 1;
    totalLifetimeValue += customer.totalBookedValue;
  }

  return {
    customers,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: {
      totalCustomers: customers.length,
      activeCustomers: payload.summary.active,
      totalTravellers,
      customersWithOutstanding,
      totalLifetimeValue,
      recentCustomers: 0,
      currency: "PKR",
    },
    facets: {
      cities,
      countries,
      customerTypes,
    },
  };
}

export function transformCustomerDetail(payload: CustomerRecord): CustomerRecord {
  return payload;
}
