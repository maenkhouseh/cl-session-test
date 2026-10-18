export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fieldErrors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** First validation message for a field, if the API returned one. */
  fieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0];
  }
}

type Method = "GET" | "POST" | "PATCH" | "PUT" | "DELETE";

export interface RequestOptions {
  body?: unknown;
  /** Workspace slug or id, sent as `X-Workspace`. */
  workspace?: string;
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : undefined;
}

let csrfPending: Promise<unknown> | null = null;

/** Sanctum SPA flow: fetch the XSRF cookie once before the first state-changing request. */
async function ensureCsrfCookie(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  csrfPending ??= fetch("/sanctum/csrf-cookie", { credentials: "include" }).finally(() => {
    csrfPending = null;
  });
  await csrfPending;
}

export async function request<T>(method: Method, path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json", ...options.headers };

  if (options.workspace) headers["X-Workspace"] = options.workspace;
  if (options.body !== undefined) headers["Content-Type"] = "application/json";

  if (method !== "GET") {
    await ensureCsrfCookie();
    const token = readCookie("XSRF-TOKEN");
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  const response = await fetch(`/api/v1${path}`, {
    method,
    headers,
    credentials: "include",
    signal: options.signal,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  });

  if (response.status === 204) return undefined as T;

  const text = await response.text();
  const data = text ? safeJson(text) : null;

  if (!response.ok) {
    const error = (data as { error?: { code?: string; message?: string; errors?: Record<string, string[]> } } | null)?.error;
    throw new ApiError(response.status, error?.code ?? "unknown_error", error?.message ?? `Request failed (${response.status})`, error?.errors);
  }

  return data as T;
}

function safeJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

/** HTTP client bound to one workspace. */
export interface Client {
  get<T>(path: string, signal?: AbortSignal): Promise<T>;
  post<T>(path: string, body?: unknown): Promise<T>;
  patch<T>(path: string, body?: unknown): Promise<T>;
  put<T>(path: string, body?: unknown): Promise<T>;
  delete<T = void>(path: string): Promise<T>;
}

export function createClient(workspace?: string): Client {
  return {
    get: (path, signal) => request("GET", path, { workspace, signal }),
    post: (path, body) => request("POST", path, { workspace, body: body ?? {} }),
    patch: (path, body) => request("PATCH", path, { workspace, body: body ?? {} }),
    put: (path, body) => request("PUT", path, { workspace, body: body ?? {} }),
    delete: (path) => request("DELETE", path, { workspace }),
  };
}
