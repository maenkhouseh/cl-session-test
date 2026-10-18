import { createClient, request, type Client } from "./client";
import { toQuery } from "@/lib/utils/url";
import type {
  AnalyticsParams, ApiKey, Customer, CustomerDetail, Domain, EventsPage, Folder, GroupBy, Invite, Link, LinkFilters, LinkInput, Member,
  Paginated, PublicDashboard, SeriesPoint, SharedDashboard, Tag, TopRow, Totals, User, UtmTemplate, Webhook, WebhookDelivery, Workspace,
} from "@/types/api";

type Wrapped<T> = { data: T };

// ---- session (no workspace context) --------------------------------------------------------------

export const authApi = {
  me: () => request<Wrapped<User>>("GET", "/auth/me").then((r) => r.data),
  login: (body: { email: string; password: string }) => request<Wrapped<User>>("POST", "/auth/login", { body }).then((r) => r.data),
  register: (body: { name: string; email: string; password: string; password_confirmation: string; workspace_name?: string }) =>
    request<Wrapped<User>>("POST", "/auth/register", { body }).then((r) => r.data),
  logout: () => request<void>("POST", "/auth/logout", { body: {} }),
};

export const workspacesApi = {
  list: () => request<Wrapped<Workspace[]>>("GET", "/workspaces").then((r) => r.data),
  create: (body: { name: string; slug?: string }) => request<Wrapped<Workspace>>("POST", "/workspaces", { body }).then((r) => r.data),
  acceptInvite: (token: string) => request<Wrapped<Workspace>>("POST", "/invites/accept", { body: { token } }).then((r) => r.data),
};

export const publicApi = {
  dashboard: (token: string, password?: string) =>
    request<Wrapped<PublicDashboard>>("GET", `/public/dashboards/${token}`, { headers: password ? { "X-Dashboard-Password": password } : {} }).then((r) => r.data),
  analytics: <T>(token: string, params: AnalyticsParams & { groupBy?: GroupBy }, password?: string) =>
    request<T>("GET", `/public/dashboards/${token}/analytics${toQuery(params)}`, { headers: password ? { "X-Dashboard-Password": password } : {} }),
};

// ---- workspace-scoped ----------------------------------------------------------------------------

/** All endpoints that act inside one workspace, bound to its slug. */
export function createApi(workspace: string) {
  const c: Client = createClient(workspace);

  return {
    workspace: {
      get: () => c.get<Wrapped<Workspace>>("/workspace").then((r) => r.data),
      update: (body: Partial<Pick<Workspace, "name" | "slug" | "logo">>) => c.patch<Wrapped<Workspace>>("/workspace", body).then((r) => r.data),
      remove: () => c.delete("/workspace"),
    },
    members: {
      list: () => c.get<Wrapped<Member[]>>("/members").then((r) => r.data),
      remove: (id: number) => c.delete(`/members/${id}`),
      invites: () => c.get<Wrapped<Invite[]>>("/invites").then((r) => r.data),
      invite: (body: { email: string; role?: "owner" | "member" }) => c.post<Wrapped<Invite>>("/invites", body).then((r) => r.data),
      revokeInvite: (id: string) => c.delete(`/invites/${id}`),
    },
    apiKeys: {
      list: () => c.get<Wrapped<ApiKey[]>>("/api-keys").then((r) => r.data),
      create: (body: { name: string; expires_at?: string | null }) => c.post<Wrapped<ApiKey>>("/api-keys", body).then((r) => r.data),
      remove: (id: string) => c.delete(`/api-keys/${id}`),
    },
    links: {
      list: (filters: LinkFilters, signal?: AbortSignal) => c.get<Paginated<Link>>(`/links${toQuery({ ...filters })}`, signal),
      count: (filters: LinkFilters) => c.get<{ count: number }>(`/links/count${toQuery({ ...filters, page: undefined, pageSize: undefined, sortBy: undefined, sortOrder: undefined })}`),
      get: (id: string) => c.get<Wrapped<Link>>(`/links/${id}`).then((r) => r.data),
      create: (body: LinkInput) => c.post<Wrapped<Link>>("/links", body).then((r) => r.data),
      update: (id: string, body: Partial<LinkInput>) => c.patch<Wrapped<Link>>(`/links/${id}`, body).then((r) => r.data),
      remove: (id: string) => c.delete(`/links/${id}`),
      bulkUpdate: (linkIds: string[], data: Partial<LinkInput>) => c.patch<Wrapped<Link[]>>("/links/bulk", { linkIds, data }).then((r) => r.data),
      bulkDelete: (linkIds: string[]) => c.delete<{ deletedCount: number }>(`/links/bulk?linkIds=${linkIds.join(",")}`),
      share: {
        get: (id: string) => c.get<Wrapped<SharedDashboard | null>>(`/links/${id}/share`).then((r) => r.data),
        save: (id: string, body: { password?: string | null; doIndex?: boolean }) => c.put<Wrapped<SharedDashboard>>(`/links/${id}/share`, body).then((r) => r.data),
        remove: (id: string) => c.delete(`/links/${id}/share`),
      },
    },
    tags: {
      list: () => c.get<Wrapped<Tag[]>>("/tags").then((r) => r.data),
      create: (body: { name: string; color?: string }) => c.post<Wrapped<Tag>>("/tags", body).then((r) => r.data),
      update: (id: string, body: { name?: string; color?: string }) => c.patch<Wrapped<Tag>>(`/tags/${id}`, body).then((r) => r.data),
      remove: (id: string) => c.delete(`/tags/${id}`),
    },
    folders: {
      list: () => c.get<Wrapped<Folder[]>>("/folders").then((r) => r.data),
      create: (body: { name: string; description?: string | null; accessLevel?: string }) => c.post<Wrapped<Folder>>("/folders", body).then((r) => r.data),
      update: (id: string, body: { name?: string; description?: string | null; accessLevel?: string }) => c.patch<Wrapped<Folder>>(`/folders/${id}`, body).then((r) => r.data),
      remove: (id: string) => c.delete(`/folders/${id}`),
    },
    utmTemplates: {
      list: () => c.get<Wrapped<UtmTemplate[]>>("/utm-templates").then((r) => r.data),
      create: (body: Partial<UtmTemplate> & { name: string }) => c.post<Wrapped<UtmTemplate>>("/utm-templates", body).then((r) => r.data),
      update: (id: string, body: Partial<UtmTemplate>) => c.patch<Wrapped<UtmTemplate>>(`/utm-templates/${id}`, body).then((r) => r.data),
      remove: (id: string) => c.delete(`/utm-templates/${id}`),
    },
    domains: {
      list: () => c.get<Wrapped<Domain[]>>("/domains").then((r) => r.data),
      create: (slug: string) => c.post<Wrapped<Domain>>("/domains", { slug }).then((r) => r.data),
      update: (id: string, body: Partial<Pick<Domain, "primary" | "archived" | "placeholder" | "notFoundUrl" | "expiredUrl">>) =>
        c.patch<Wrapped<Domain>>(`/domains/${id}`, body).then((r) => r.data),
      verify: (id: string) => c.post<Wrapped<Domain>>(`/domains/${id}/verify`).then((r) => r.data),
      remove: (id: string) => c.delete(`/domains/${id}`),
    },
    analytics: {
      query: <T = Totals | SeriesPoint[] | TopRow[]>(params: AnalyticsParams & { groupBy?: GroupBy }, signal?: AbortSignal) =>
        c.get<T>(`/analytics${toQuery(params)}`, signal),
      events: (params: AnalyticsParams & { cursor?: string; limit?: number }, signal?: AbortSignal) => c.get<EventsPage>(`/events${toQuery(params)}`, signal),
      exportUrl: (params: AnalyticsParams) => `/api/v1/analytics/export${toQuery({ ...params, workspace: workspace })}`,
    },
    customers: {
      list: (params: { search?: string; page?: number; pageSize?: number }) => c.get<Paginated<Customer>>(`/customers${toQuery(params)}`),
      get: (id: string) => c.get<CustomerDetail>(`/customers/${id}`),
    },
    webhooks: {
      list: () => c.get<Wrapped<Webhook[]>>("/webhooks").then((r) => r.data),
      create: (body: { name: string; url: string; triggers: string[] }) => c.post<Wrapped<Webhook>>("/webhooks", body).then((r) => r.data),
      update: (id: string, body: { name?: string; url?: string; triggers?: string[] }) => c.patch<Wrapped<Webhook>>(`/webhooks/${id}`, body).then((r) => r.data),
      remove: (id: string) => c.delete(`/webhooks/${id}`),
      deliveries: (id: string) => c.get<Wrapped<WebhookDelivery[]>>(`/webhooks/${id}/deliveries`).then((r) => r.data),
      test: (id: string) => c.post<{ queued: boolean }>(`/webhooks/${id}/test`),
    },
  };
}

export type WorkspaceApi = ReturnType<typeof createApi>;
export { ApiError } from "./client";
