// Public source revision only; release automation waits for this exact commit.
export const dynamic = "force-dynamic";

export function GET() {
  return Response.json(
    { service: "web", commit: process.env.VERCEL_GIT_COMMIT_SHA || null },
    { headers: { "Cache-Control": "no-store" } },
  );
}
