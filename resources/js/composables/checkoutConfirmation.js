export function confirmedOrder(data) {
  const order = data?.order;
  if (data?.success !== true || !order?.id || typeof order.order_number !== 'string'
    || !order.order_number.trim() || order.total == null || !Number.isFinite(Number(order.total))
    || Number(order.total) < 0 || order.points_earned == null || !Number.isInteger(Number(order.points_earned))
    || Number(order.points_earned) < 0 || (!order.pickup_slot && !order.delivery_address) || !Array.isArray(order.items) || order.items.length === 0) {
    throw new Error('The server did not return a valid order confirmation. Your cart has been kept. Check your orders before trying again.');
  }
  return order;
}
