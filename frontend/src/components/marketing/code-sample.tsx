export function CodeSample({ title, code }: { title: string; code: string }) {
  return (
    <figure className="overflow-hidden rounded-xl border border-border bg-[#0b0b0e] text-left text-[#e4e4e7]">
      <figcaption className="border-b border-white/10 px-4 py-2 text-xs text-white/50">{title}</figcaption>
      <pre className="overflow-x-auto p-4 text-sm leading-relaxed"><code>{code}</code></pre>
    </figure>
  );
}
