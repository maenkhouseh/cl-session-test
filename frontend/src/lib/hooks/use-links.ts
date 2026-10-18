"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import type { LinkFilters, LinkInput } from "@/types/api";
import { useApiMutation } from "./use-api-mutation";
import { useWorkspaceApi } from "./use-workspace-api";

export const linkKeys = (slug: string) => ({
  all: ["ws", slug, "links"] as const,
  list: (filters: LinkFilters) => ["ws", slug, "links", "list", filters] as const,
});

export function useLinks(filters: LinkFilters) {
  const { slug, api } = useWorkspaceApi();
  return useQuery({
    queryKey: linkKeys(slug).list(filters),
    queryFn: ({ signal }) => api.links.list(filters, signal),
    placeholderData: keepPreviousData,
  });
}

export function useCreateLink() {
  const { slug, api } = useWorkspaceApi();
  return useApiMutation({
    mutationFn: (input: LinkInput) => api.links.create(input),
    invalidate: [linkKeys(slug).all, ["ws", slug, "workspace"], ["ws", slug, "folders"]],
    success: (link) => `Created ${link.shortLink.replace("https://", "")}`,
    silent: true,
  });
}

export function useUpdateLink() {
  const { slug, api } = useWorkspaceApi();
  return useApiMutation({
    mutationFn: ({ id, ...input }: { id: string } & Partial<LinkInput>) => api.links.update(id, input),
    invalidate: [linkKeys(slug).all, ["ws", slug, "folders"]],
    success: "Link updated",
    silent: true,
  });
}

export function useDeleteLink() {
  const { slug, api } = useWorkspaceApi();
  return useApiMutation({
    mutationFn: (id: string) => api.links.remove(id),
    invalidate: [linkKeys(slug).all, ["ws", slug, "folders"]],
    success: "Link deleted",
  });
}

export function useBulkLinks() {
  const { slug, api } = useWorkspaceApi();
  const invalidate = [linkKeys(slug).all, ["ws", slug, "folders"]];
  return {
    archive: useApiMutation({ mutationFn: ({ ids, archived }: { ids: string[]; archived: boolean }) => api.links.bulkUpdate(ids, { archived }), invalidate, success: (_, v) => (v.archived ? "Links archived" : "Links restored") }),
    move: useApiMutation({ mutationFn: ({ ids, folderId }: { ids: string[]; folderId: string | null }) => api.links.bulkUpdate(ids, { folderId }), invalidate, success: "Links moved" }),
    remove: useApiMutation({ mutationFn: (ids: string[]) => api.links.bulkDelete(ids), invalidate, success: (r) => `Deleted ${r.deletedCount} link${r.deletedCount === 1 ? "" : "s"}` }),
  };
}
