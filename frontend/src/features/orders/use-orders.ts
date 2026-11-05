"use client";

import { useCallback, useEffect, useState } from "react";
import {
  ordersApi,
  type Order,
  type OrderList,
  type ProofItem,
  type RefundRequest,
  type TimelineEvent,
} from "./orders-api";

export interface AsyncQueryState<T> {
  data: T | null;
  error: unknown;
  isLoading: boolean;
  mutate: () => Promise<void>;
}

export function useOrders(page = 1): AsyncQueryState<OrderList> {
  const [data, setData] = useState<OrderList | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    ordersApi
      .list(page)
      .then((result) => {
        if (!active) return;
        setData(result);
        setError(null);
        setIsLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err);
        setIsLoading(false);
      });
    return () => {
      active = false;
    };
  }, [page, tick]);

  const mutate = useCallback(async () => {
    setIsLoading(true);
    setTick((t) => t + 1);
  }, []);

  return { data, error, isLoading, mutate };
}

export function useOrder(id: string | number): AsyncQueryState<Order> {
  const [data, setData] = useState<Order | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    ordersApi
      .show(id)
      .then((result) => {
        if (!active) return;
        setData(result);
        setError(null);
        setIsLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err);
        setIsLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id, tick]);

  const mutate = useCallback(async () => {
    setIsLoading(true);
    setTick((t) => t + 1);
  }, []);

  return { data, error, isLoading, mutate };
}

export function useOrderTimeline(id: string | number): AsyncQueryState<{ events: TimelineEvent[] }> {
  const [data, setData] = useState<{ events: TimelineEvent[] } | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    ordersApi
      .timeline(id)
      .then((result) => {
        if (!active) return;
        setData(result);
        setError(null);
        setIsLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err);
        setIsLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id, tick]);

  const mutate = useCallback(async () => {
    setIsLoading(true);
    setTick((t) => t + 1);
  }, []);

  return { data, error, isLoading, mutate };
}

export function useOrderProofs(id: string | number): AsyncQueryState<{ items: ProofItem[] }> {
  const [data, setData] = useState<{ items: ProofItem[] } | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    ordersApi
      .proofs(id)
      .then((result) => {
        if (!active) return;
        setData(result);
        setError(null);
        setIsLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err);
        setIsLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id, tick]);

  const mutate = useCallback(async () => {
    setIsLoading(true);
    setTick((t) => t + 1);
  }, []);

  return { data, error, isLoading, mutate };
}

export function useOrderRefunds(id: string | number): AsyncQueryState<{ items: RefundRequest[] }> {
  const [data, setData] = useState<{ items: RefundRequest[] } | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    ordersApi
      .refundRequests(id)
      .then((result) => {
        if (!active) return;
        setData(result);
        setError(null);
        setIsLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err);
        setIsLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id, tick]);

  const mutate = useCallback(async () => {
    setIsLoading(true);
    setTick((t) => t + 1);
  }, []);

  return { data, error, isLoading, mutate };
}
