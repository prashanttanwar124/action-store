import test from 'node:test';
import assert from 'node:assert/strict';
import { pickupOptions } from '../resources/js/composables/useCheckoutSchedule.js';
import { confirmedOrder } from '../resources/js/composables/checkoutConfirmation.js';
import { createPinia, setActivePinia } from 'pinia';
import { useStore } from '../resources/js/stores/cart.js';

const info = {
  pickup_slot_start_time: '09:00', pickup_slot_end_time: '21:00', effective_prep_time_minutes: 15,
  available_pickup_slots: [{ id: '10-11', label: '10:30 AM – 11:00 AM', active: true }],
};

test('half-hour slots honor minutes, preparation time and the store timezone', () => {
  const options = pickupOptions(info, 'today', new Date('2026-10-03T14:00:00Z'), 'America/Toronto');
  assert.equal(options.date, '2026-10-03');
  assert.equal(options.slots[0].disabled, false);
  assert.equal(options.times[0], '10:15 AM');
  const later = pickupOptions(info, 'today', new Date('2026-10-03T14:16:00Z'), 'America/Toronto');
  assert.equal(later.slots[0].disabled, true);
});

test('empty configured slots do not fall back to hardcoded windows', () => {
  assert.deepEqual(pickupOptions({ ...info, available_pickup_slots: [] }, 'today', new Date('2026-10-03T10:00Z'), 'UTC').slots, []);
});

test('tomorrow crosses month boundary in the store timezone', () => {
  const options = pickupOptions(info, 'tomorrow', new Date('2026-11-01T02:00Z'), 'America/Toronto');
  assert.equal(options.date, '2026-11-01');
  assert.equal(options.times[0], '09:00 AM');
});

test('midnight is valid and narrow windows never offer out-of-range times', () => {
  const midnight = pickupOptions({ ...info, pickup_slot_start_time: '00:00' }, 'tomorrow', new Date('2026-10-03T10:00Z'), 'UTC');
  assert.equal(midnight.times[0], '12:00 AM');
  const narrow = pickupOptions({ ...info, pickup_slot_start_time: '09:01', pickup_slot_end_time: '09:10' }, 'tomorrow', new Date('2026-10-03T10:00Z'), 'UTC');
  assert.deepEqual(narrow.times, []);
});

test('closed or paused pickup produces no custom times', () => {
  assert.deepEqual(pickupOptions(info, 'today', new Date('2026-10-03T22:00Z'), 'UTC').times, []);
  assert.deepEqual(pickupOptions({ ...info, is_pickup_active: false }, 'tomorrow', new Date('2026-10-03T10:00Z'), 'UTC').times, []);
});

test('confirmation rejects missing and malformed orders', () => {
  const order = { id: 1, order_number: '#MM-12345', total: '12.99', points_earned: 12, pickup_slot: 'ASAP', items: [{ name: 'Rice' }] };
  assert.equal(confirmedOrder({ success: true, order }), order);
  for (const data of [{}, '<html>Login</html>', { success: false, order }, { success: true, order: { ...order, total: null } }, { success: true, order: { ...order, items: [] } }]) {
    assert.throws(() => confirmedOrder(data), /valid order confirmation/);
  }
});

test('cart subtotal uses the same per-unit subscription rounding as checkout', () => {
  setActivePinia(createPinia());
  const store = useStore();
  store.addToCart({ id: 1, name: 'Paneer', price: 4.99, quantity: 3, isSubscribed: true });
  assert.equal(store.subtotal, 14.22);
  assert.equal(store.subscribeSavings, 0.75);
});
