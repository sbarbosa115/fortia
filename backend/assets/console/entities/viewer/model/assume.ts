import {readStored, removeStored, writeStored} from '@shared/lib';

/**
 * The account a super-admin is viewing as (PRD §10.20). Stored in the browser; while set, every request except
 * /admin/* sends X-Assume-Customer-Id.
 */
export type AssumedCustomer = {id: string; name: string; email: string};

const KEY = 'mappi.console.assume';
let assumed: AssumedCustomer | null = readStored<AssumedCustomer>(KEY);
const listeners = new Set<() => void>();

function emit(): void {
  listeners.forEach((listener) => listener());
}

export function subscribeAssumed(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function getAssumed(): AssumedCustomer | null {
  return assumed;
}

export function assumeCustomer(customer: AssumedCustomer): void {
  assumed = customer;
  writeStored(KEY, customer);
  emit();
}

export function stopAssuming(): void {
  assumed = null;
  removeStored(KEY);
  emit();
}
