"use client";

import useSWR from "swr";
import { ordersApi } from "./orders-api";

export function useOrders() {
  return useSWR(["orders"], () => ordersApi.list());
}

export function useOrder(id: string) {
  return useSWR(["order", id], () => ordersApi.show(id));
}

export function useOrderTimeline(id: string) {
  return useSWR(["order-timeline", id], () => ordersApi.timeline(id));
}
