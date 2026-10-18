/** Contracts mirrored from the Laravel API Resources (camelCase). */

export type Plan = "free" | "business" | "advanced" | "enterprise";

export interface User {
  id: number;
  name: string;
  email: string;
  createdAt: string;
}

export interface PlanLimits {
  links_per_month: number | null;
  events_per_month: number | null;
  retention_days: number | null;
  folders: number | null;
  domains: number | null;
  users: number | null;
  rate_limit: number;
}

export interface Workspace {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
  plan: Plan;
  limits: PlanLimits;
  usage: { links: number; events: number };
  usageResetAt: string | null;
  /** Short domains available to every workspace without verification. */
  systemDomains: string[];
  role?: "owner" | "member";
  createdAt: string;
}

export interface Member {
  id: number;
  name: string;
  email: string;
  role: "owner" | "member";
  joinedAt: string;
}

export interface Invite {
  id: string;
  email: string;
  role: "owner" | "member";
  expiresAt: string;
  createdAt: string;
}

export interface ApiKey {
  id: string;
  name: string;
  preview: string;
  /** Only present in the creation response. */
  key?: string;
  lastUsedAt: string | null;
  expiresAt: string | null;
  createdAt: string;
}

export interface TagRef {
  id: string;
  name: string;
  color: TagColor;
}

export type TagColor = "red" | "yellow" | "green" | "blue" | "purple" | "pink" | "brown";

export interface Tag extends TagRef {
  createdAt: string;
}

export interface Folder {
  id: string;
  name: string;
  description: string | null;
  accessLevel: "write" | "read" | "none";
  linkCount?: number;
  createdAt: string;
}

export interface UtmTemplate {
  id: string;
  name: string;
  utm_source: string | null;
  utm_medium: string | null;
  utm_campaign: string | null;
  utm_term: string | null;
  utm_content: string | null;
  ref: string | null;
}

export interface Domain {
  id: string;
  slug: string;
  verified: boolean;
  verifiedAt: string | null;
  primary: boolean;
  archived: boolean;
  placeholder: string | null;
  notFoundUrl: string | null;
  expiredUrl: string | null;
  dns: { type: string; name: string; value: string };
  createdAt: string;
}

export interface TestVariant {
  url: string;
  percentage: number;
}

export interface Link {
  id: string;
  domain: string;
  key: string;
  url: string;
  shortLink: string;
  qrCode: string;
  externalId: string | null;
  tenantId: string | null;
  trackConversion: boolean;
  archived: boolean;
  expiresAt: string | null;
  expiredUrl: string | null;
  disabledAt: string | null;
  hasPassword: boolean;
  proxy: boolean;
  rewrite: boolean;
  doIndex: boolean;
  publicStats: boolean;
  title: string | null;
  description: string | null;
  image: string | null;
  video: string | null;
  ios: string | null;
  android: string | null;
  geo: Record<string, string> | null;
  comments: string | null;
  folderId: string | null;
  tags: TagRef[];
  utm_source: string | null;
  utm_medium: string | null;
  utm_campaign: string | null;
  utm_term: string | null;
  utm_content: string | null;
  ref: string | null;
  testVariants: TestVariant[] | null;
  clicks: number;
  leads: number;
  sales: number;
  saleAmount: number;
  lastClicked: string | null;
  userId: number | null;
  workspaceId: string;
  createdAt: string;
  updatedAt: string;
}

/** Writable link fields (create + update). Keys follow the API, so utm_* stay snake_case. */
export interface LinkInput {
  url: string;
  domain?: string;
  key?: string;
  externalId?: string | null;
  trackConversion?: boolean;
  archived?: boolean;
  tagIds?: string[];
  folderId?: string | null;
  comments?: string | null;
  expiresAt?: string | null;
  expiredUrl?: string | null;
  password?: string | null;
  proxy?: boolean;
  title?: string | null;
  description?: string | null;
  image?: string | null;
  rewrite?: boolean;
  ios?: string | null;
  android?: string | null;
  geo?: Record<string, string> | null;
  doIndex?: boolean;
  publicStats?: boolean;
  utm_source?: string | null;
  utm_medium?: string | null;
  utm_campaign?: string | null;
  utm_term?: string | null;
  utm_content?: string | null;
  ref?: string | null;
  testVariants?: TestVariant[] | null;
}

export interface Paginated<T> {
  data: T[];
  meta: { page: number; pageSize: number; hasMore: boolean };
}

export type LinkSort = "createdAt" | "clicks" | "saleAmount" | "lastClicked";

export interface LinkFilters {
  search?: string;
  domain?: string;
  tagIds?: string[];
  folderId?: string;
  userId?: number;
  showArchived?: boolean;
  sortBy?: LinkSort;
  sortOrder?: "asc" | "desc";
  page?: number;
  pageSize?: number;
}

// ---- analytics -----------------------------------------------------------------------------------

export type AnalyticsEvent = "clicks" | "leads" | "sales";

export type Interval = "24h" | "7d" | "30d" | "90d" | "1y" | "mtd" | "qtd" | "ytd" | "all";

export type Dimension =
  | "country" | "region" | "city" | "continent" | "device" | "browser" | "os" | "referer" | "refererUrl"
  | "utm_source" | "utm_medium" | "utm_campaign" | "utm_term" | "utm_content";

export type GroupBy = "count" | "timeseries" | "top_links" | "top_urls" | "trigger" | Dimension;

export interface AnalyticsParams {
  event?: AnalyticsEvent;
  interval?: Interval;
  start?: string;
  end?: string;
  timezone?: string;
  linkId?: string;
  domain?: string;
  key?: string;
  tagIds?: string[];
  folderId?: string;
  trigger?: "qr" | "link";
  unique?: boolean;
  /** Dimension filters: `"US,DE"` includes, `"-US"` excludes. */
  [dimension: string]: unknown;
}

export interface Totals {
  clicks: number;
  uniqueClicks: number;
  leads: number;
  sales: number;
  saleAmount: number;
}

export interface SeriesPoint {
  start: string;
  clicks?: number;
  uniqueClicks?: number;
  leads?: number;
  sales?: number;
  saleAmount?: number;
}

export interface TopRow {
  [key: string]: unknown;
  clicks?: number;
  uniqueClicks?: number;
  leads?: number;
  sales?: number;
  saleAmount?: number;
}

export interface TopLinkRow extends TopRow {
  link: { id: string; domain?: string; key?: string; shortLink?: string; url?: string; title?: string | null; deleted?: boolean };
}

export interface EventRow {
  event: "click" | "lead" | "sale";
  timestamp: string;
  eventName?: string;
  clickId: string;
  link: { id: string; domain: string; key: string; shortLink: string } | null;
  customer?: { id: string; externalId: string; name: string | null; email: string | null; avatar: string | null };
  url?: string;
  country?: string;
  region?: string;
  city?: string;
  continent?: string;
  device?: string;
  browser?: string;
  os?: string;
  referer?: string;
  refererUrl?: string;
  qr?: boolean;
  isUnique?: boolean;
  amount?: number;
  currency?: string;
  invoiceId?: string;
}

export interface EventsPage {
  data: EventRow[];
  meta: { nextCursor: string | null };
}

export interface Customer {
  id: string;
  externalId: string;
  name: string | null;
  email: string | null;
  avatar: string | null;
  country: string | null;
  link: { id: string; domain: string; key: string; shortLink: string } | null;
  createdAt: string;
}

export interface CustomerDetail {
  data: Customer;
  lifetimeValue: number;
  activity: Array<{ type: "lead" | "sale"; timestamp: string; eventName: string; amount?: number; currency?: string; invoiceId?: string | null }>;
}

export interface SharedDashboard {
  id: string;
  linkId: string;
  passwordProtected: boolean;
  doIndex: boolean;
}

export interface PublicDashboard {
  id: string;
  passwordProtected: boolean;
  locked: boolean;
  doIndex: boolean;
  link: { domain: string; key: string; shortLink: string; url: string; title: string | null } | null;
}

export type WebhookTrigger = "link.created" | "link.updated" | "link.deleted" | "link.clicked" | "lead.created" | "sale.created";

export interface Webhook {
  id: string;
  name: string;
  url: string;
  triggers: WebhookTrigger[];
  disabled: boolean;
  disabledAt: string | null;
  consecutiveFailures: number;
  lastFailedAt: string | null;
  /** Only present in the creation response. */
  secret?: string;
  createdAt: string;
}

export interface WebhookDelivery {
  id: string;
  event: string;
  status: "pending" | "success" | "failed";
  response_code: number | null;
  attempts: number;
  created_at: string;
}
