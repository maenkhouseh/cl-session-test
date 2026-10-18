"use client";

import { useQuery } from "@tanstack/react-query";
import { useApiMutation } from "./use-api-mutation";
import { useWorkspaceApi } from "./use-workspace-api";

export function useMembers() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "members"], queryFn: () => api.members.list() });
}

export function useInvites(enabled: boolean) {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "invites"], queryFn: () => api.members.invites(), enabled });
}

export function useTeamMutations() {
  const { slug, api } = useWorkspaceApi();
  return {
    invite: useApiMutation({ mutationFn: (b: { email: string; role?: "owner" | "member" }) => api.members.invite(b), invalidate: [["ws", slug, "invites"]], success: "Invitation sent", silent: true }),
    revokeInvite: useApiMutation({ mutationFn: (id: string) => api.members.revokeInvite(id), invalidate: [["ws", slug, "invites"]], success: "Invitation revoked" }),
    removeMember: useApiMutation({ mutationFn: (id: number) => api.members.remove(id), invalidate: [["ws", slug, "members"]], success: "Member removed" }),
  };
}

export function useApiKeys() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "api-keys"], queryFn: () => api.apiKeys.list() });
}

export function useApiKeyMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "api-keys"]];
  return {
    create: useApiMutation({ mutationFn: (b: { name: string; expires_at?: string | null }) => api.apiKeys.create(b), invalidate, silent: true }),
    remove: useApiMutation({ mutationFn: (id: string) => api.apiKeys.remove(id), invalidate, success: "API key revoked" }),
  };
}

export function useWebhooks() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "webhooks"], queryFn: () => api.webhooks.list() });
}

export function useWebhookDeliveries(id: string | null) {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "webhooks", id, "deliveries"], queryFn: () => api.webhooks.deliveries(id as string), enabled: !!id });
}

export function useWebhookMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "webhooks"]];
  return {
    create: useApiMutation({ mutationFn: (b: { name: string; url: string; triggers: string[] }) => api.webhooks.create(b), invalidate, silent: true }),
    update: useApiMutation({ mutationFn: ({ id, ...b }: { id: string; name?: string; url?: string; triggers?: string[] }) => api.webhooks.update(id, b), invalidate, success: "Webhook updated", silent: true }),
    remove: useApiMutation({ mutationFn: (id: string) => api.webhooks.remove(id), invalidate, success: "Webhook deleted" }),
    test: useApiMutation({ mutationFn: (id: string) => api.webhooks.test(id), success: "Test event queued" }),
  };
}

export function useCustomers(params: { search?: string; page?: number; pageSize?: number }) {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "customers", params], queryFn: () => api.customers.list(params) });
}

export function useCustomer(id: string) {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "customers", id], queryFn: () => api.customers.get(id) });
}
