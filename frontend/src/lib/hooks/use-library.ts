"use client";

import { useQuery } from "@tanstack/react-query";
import type { UtmTemplate } from "@/types/api";
import { useApiMutation } from "./use-api-mutation";
import { useWorkspaceApi } from "./use-workspace-api";

/** Tags, folders, domains and UTM templates — small per-workspace lists shared by many screens. */
export function useWorkspaceInfo() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "workspace"], queryFn: () => api.workspace.get() });
}

export function useTags() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "tags"], queryFn: () => api.tags.list(), staleTime: 60_000 });
}

export function useFolders() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "folders"], queryFn: () => api.folders.list(), staleTime: 60_000 });
}

export function useDomains() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "domains"], queryFn: () => api.domains.list(), staleTime: 60_000 });
}

export function useUtmTemplates() {
  const { slug, api } = useWorkspaceApi();
  return useQuery({ queryKey: ["ws", slug, "utm"], queryFn: () => api.utmTemplates.list(), staleTime: 60_000 });
}

export function useTagMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "tags"], ["ws", slug, "links"]];
  return {
    create: useApiMutation({ mutationFn: (b: { name: string; color?: string }) => api.tags.create(b), invalidate, success: "Tag created", silent: true }),
    update: useApiMutation({ mutationFn: ({ id, ...b }: { id: string; name?: string; color?: string }) => api.tags.update(id, b), invalidate, success: "Tag updated", silent: true }),
    remove: useApiMutation({ mutationFn: (id: string) => api.tags.remove(id), invalidate, success: "Tag deleted" }),
  };
}

export function useFolderMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "folders"], ["ws", slug, "links"]];
  return {
    create: useApiMutation({ mutationFn: (b: { name: string; description?: string | null; accessLevel?: string }) => api.folders.create(b), invalidate, success: "Folder created", silent: true }),
    update: useApiMutation({ mutationFn: ({ id, ...b }: { id: string; name?: string; description?: string | null; accessLevel?: string }) => api.folders.update(id, b), invalidate, success: "Folder updated", silent: true }),
    remove: useApiMutation({ mutationFn: (id: string) => api.folders.remove(id), invalidate, success: "Folder deleted" }),
  };
}

export function useDomainMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "domains"]];
  return {
    create: useApiMutation({ mutationFn: (domain: string) => api.domains.create(domain), invalidate, success: "Domain added", silent: true }),
    update: useApiMutation({ mutationFn: ({ id, ...b }: { id: string } & Parameters<typeof api.domains.update>[1]) => api.domains.update(id, b), invalidate, success: "Domain updated" }),
    verify: useApiMutation({ mutationFn: (id: string) => api.domains.verify(id), invalidate, success: (d) => (d.verified ? "Domain verified" : undefined) }),
    remove: useApiMutation({ mutationFn: (id: string) => api.domains.remove(id), invalidate, success: "Domain removed" }),
  };
}

export function useUtmTemplateMutations() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [["ws", slug, "utm"]];
  return {
    create: useApiMutation({ mutationFn: (b: Partial<UtmTemplate> & { name: string }) => api.utmTemplates.create(b), invalidate, success: "Template created", silent: true }),
    remove: useApiMutation({ mutationFn: (id: string) => api.utmTemplates.remove(id), invalidate, success: "Template deleted" }),
  };
}
