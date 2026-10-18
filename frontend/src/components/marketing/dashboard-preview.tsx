/** Static, decorative product preview (pure SVG/CSS — no client JS). */
export function DashboardPreview() {
  const points = [12, 18, 15, 24, 22, 31, 28, 40, 36, 52, 47, 63, 58, 74];
  const max = Math.max(...points);
  const path = points.map((p, i) => `${i === 0 ? "M" : "L"}${(i / (points.length - 1)) * 600},${180 - (p / max) * 160}`).join(" ");

  return (
    <div className="mx-auto mt-4 max-w-5xl px-4 sm:px-6" aria-hidden>
      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-2xl shadow-black/5">
        <div className="flex items-center gap-1.5 border-b border-border px-4 py-3">
          <span className="size-2.5 rounded-full bg-red-400" /><span className="size-2.5 rounded-full bg-yellow-400" /><span className="size-2.5 rounded-full bg-green-400" />
          <span className="ml-3 text-xs text-muted-foreground">shortie.link/launch</span>
        </div>
        <div className="grid grid-cols-3 divide-x divide-border border-b border-border text-left">
          {[["Clicks", "124.8K"], ["Leads", "9,214"], ["Revenue", "$48.2K"]].map(([label, value], i) => (
            <div key={label} className={`px-5 py-4 ${i === 0 ? "bg-muted/60" : ""}`}>
              <p className="text-xs text-muted-foreground">{label}</p>
              <p className="text-2xl font-semibold tabular-nums">{value}</p>
            </div>
          ))}
        </div>
        <svg viewBox="0 0 600 200" className="h-56 w-full p-4" preserveAspectRatio="none">
          <defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor="var(--accent)" stopOpacity="0.35" /><stop offset="1" stopColor="var(--accent)" stopOpacity="0" /></linearGradient></defs>
          <path d={`${path} L600,200 L0,200 Z`} fill="url(#g)" />
          <path d={path} fill="none" stroke="var(--accent)" strokeWidth="2.5" vectorEffect="non-scaling-stroke" />
        </svg>
      </div>
    </div>
  );
}
