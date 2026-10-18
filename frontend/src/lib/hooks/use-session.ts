"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { authApi, workspacesApi } from "@/lib/api";
import type { User } from "@/types/api";
import { useApiMutation } from "./use-api-mutation";

export const sessionKeys = {
  me: ["session", "me"] as const,
  workspaces: ["session", "workspaces"] as const,
};

export function useMe() {
  return useQuery({ queryKey: sessionKeys.me, queryFn: authApi.me, retry: false, staleTime: 5 * 60_000 });
}

export function useWorkspaces(enabled = true) {
  return useQuery({ queryKey: sessionKeys.workspaces, queryFn: workspacesApi.list, enabled });
}

/** Auth mutations keep errors inline (field errors on the form), so the default toast is silenced. */
export function useLogin(onDone?: (user: User) => void) {
  const queryClient = useQueryClient();
  return useApiMutation({
    mutationFn: authApi.login,
    silent: true,
    onSuccess: (user) => {
      queryClient.setQueryData(sessionKeys.me, user);
      onDone?.(user);
    },
  });
}

export function useRegister(onDone?: (user: User) => void) {
  const queryClient = useQueryClient();
  return useApiMutation({
    mutationFn: authApi.register,
    silent: true,
    onSuccess: (user) => {
      queryClient.setQueryData(sessionKeys.me, user);
      onDone?.(user);
    },
  });
}

export function useLogout(onDone?: () => void) {
  const queryClient = useQueryClient();
  return useApiMutation({
    mutationFn: () => authApi.logout(),
    onSuccess: () => {
      queryClient.clear();
      onDone?.();
    },
  });
}
