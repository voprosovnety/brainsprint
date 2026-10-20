import { NextRequest, NextResponse } from "next/server";
export const dynamic = "force-dynamic";
export const runtime = "nodejs";
const run = "[0-9a-f-]{36}";
const routes = {
  GET: new RegExp(`^v1/(bootstrap|demo-analytics|runs/${run})$`),
  POST: new RegExp(`^v1/(privacy/delete|events|runs(/${run}/steps/[a-z][a-z0-9_-]{0,39}/complete)?)$`),
};
function attributionQuery(params: URLSearchParams) {
  const bounded = new URLSearchParams();
  for (const key of ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"]) {
    const value = params.get(key);
    if (value !== null) bounded.set(key, value);
  }
  return bounded.size ? `?${bounded}` : "";
}
async function proxy(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  const path = (await context.params).path.join("/");
  const method = request.method as keyof typeof routes;
  if (!routes[method]?.test(path))
    return NextResponse.json(
      { error: { code: "not_found", message: "This page is not available." } },
      { status: 404 },
    );
  if (["v1/demo-analytics", "v1/privacy/delete"].includes(path) && request.nextUrl.searchParams.size) {
    return NextResponse.json({ error: { code: "invalid_query", message: "This action does not accept query parameters." } }, { status: 422, headers: { "Cache-Control": "private, no-store" } });
  }
  if (
    method === "POST" &&
    request.headers.get("origin") &&
    request.headers.get("origin") !==
      (process.env.WEB_ORIGIN || `${request.nextUrl.protocol}//${request.headers.get("host")}`)
  ) {
    return NextResponse.json(
      {
        error: {
          code: "forbidden_origin",
          message: "Reload this page to continue.",
        },
      },
      { status: 403 },
    );
  }
  if (Number(request.headers.get("content-length") || 0) > 4096)
    return new NextResponse(null, { status: 413 });
  const headers = new Headers({
    Accept: "application/json",
    "Content-Type": "application/json",
  });
  if (process.env.CONSUMER_PROXY_SECRET) headers.set("X-Consumer-Proxy", process.env.CONSUMER_PROXY_SECRET);
  for (const name of ["cookie", "x-xsrf-token"]) {
    const value = request.headers.get(name);
    if (value) headers.set(name, value);
  }
  try {
    const body = method === "POST" ? await request.text() : undefined;
    if (body && new TextEncoder().encode(body).length > 4096)
      return new NextResponse(null, { status: 413 });
    const upstream = await fetch(
      `${process.env.API_ORIGIN || "http://127.0.0.1:8008"}/api/${path}${path === "v1/bootstrap" ? attributionQuery(request.nextUrl.searchParams) : ""}`,
      {
        method,
        headers,
        body,
        cache: "no-store",
        redirect: "error",
        signal: AbortSignal.timeout(12000),
      },
    );
    const response = new NextResponse(upstream.body, {
      status: upstream.status,
      headers: {
        "Content-Type": "application/json",
        "Cache-Control": "private, no-store",
      },
    });
    for (const cookie of upstream.headers.getSetCookie())
      response.headers.append("Set-Cookie", cookie);
    const retry = upstream.headers.get("retry-after");
    if (retry) response.headers.set("Retry-After", retry);
    return response;
  } catch {
    return NextResponse.json(
      {
        error: {
          code: "unavailable",
          message:
            "The challenge is taking a break. Try again in a moment; your saved progress is safe.",
        },
      },
      { status: 503, headers: { "Cache-Control": "private, no-store" } },
    );
  }
}
export const GET = proxy;
export const POST = proxy;
