"use client";

import { useCallback, useId, useState } from "react";
import type { CabinClass, PassengerSelection } from "../types";

const DEFAULT_PASSENGERS: PassengerSelection = {
  adults: 1,
  children: 0,
  infants: 0,
  cabin: "economy",
};

export function usePassengerSelection(initial: PassengerSelection = DEFAULT_PASSENGERS) {
  const [passengers, setPassengers] = useState<PassengerSelection>(initial);

  const setAdults = useCallback((value: number) => {
    setPassengers((current) => {
      const adults = Math.max(1, value);
      const infants = Math.min(current.infants, adults);
      return { ...current, adults, infants };
    });
  }, []);

  const setChildren = useCallback((value: number) => {
    setPassengers((current) => ({ ...current, children: Math.max(0, value) }));
  }, []);

  const setInfants = useCallback((value: number) => {
    setPassengers((current) => ({
      ...current,
      infants: Math.min(Math.max(0, value), current.adults),
    }));
  }, []);

  const setCabin = useCallback((cabin: CabinClass) => {
    setPassengers((current) => ({ ...current, cabin }));
  }, []);

  return { passengers, setAdults, setChildren, setInfants, setCabin, setPassengers };
}

export function passengerSummary(passengers: PassengerSelection, cabinLabel: string): string {
  const parts: string[] = [];
  parts.push(`${passengers.adults} Adult${passengers.adults === 1 ? "" : "s"}`);
  if (passengers.children > 0) parts.push(`${passengers.children} Child${passengers.children === 1 ? "" : "ren"}`);
  if (passengers.infants > 0) parts.push(`${passengers.infants} Infant${passengers.infants === 1 ? "" : "s"}`);
  return `${parts.join(", ")} · ${cabinLabel}`;
}

export function useFieldIds(prefix: string) {
  const id = useId();
  const base = `${prefix}-${id}`;
  return {
    labelId: `${base}-label`,
    inputId: `${base}-input`,
    listId: `${base}-list`,
    errorId: `${base}-error`,
  };
}
