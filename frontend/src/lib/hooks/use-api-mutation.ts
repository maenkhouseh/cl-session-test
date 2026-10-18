"use client";

import { useMutation, useQueryClient, type QueryKey } from "@tanstack/react-query";
import { toast } from "sonner";
import { ApiError } from "@/lib/api/client";

interface Options<TData, TVars> {
  mutationFn: (vars: TVars) => Promise<TData>;
  /** Query keys (prefixes) to invalidate after success. */
  invalidate?: QueryKey[];
  success?: string | ((data: TData, vars: TVars) => string | undefined);
  onSuccess?: (data: TData, vars: TVars) => void;
  /** Suppress the default error toast (e.g. when the form shows field errors itself). */
  silent?: boolean;
}

/** useMutation with consistent cache invalidation and toast feedback. */
export function useApiMutation<TData = unknown, TVars = void>({ mutationFn, invalidate = [], success, silent, onSuccess }: Options<TData, TVars>) {
  const queryClient = useQueryClient();

  return useMutation<TData, Error, TVars>({
    mutationFn,
    onSuccess: async (data, vars) => {
      await Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
      const message = typeof success === "function" ? success(data, vars) : success;
      if (message) toast.success(message);
      onSuccess?.(data, vars);
    },
    onError: (error) => {
      if (silent) return;
      toast.error(error instanceof ApiError ? error.message : "Something went wrong. Please try again.");
    },
  });
}
